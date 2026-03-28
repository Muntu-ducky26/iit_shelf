<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$courseId = isset($_GET['course_id']) ? $_GET['course_id'] : '';
$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$availability = isset($_GET['availability']) ? $_GET['availability'] : '';
$bookType = isset($_GET['book_type']) ? $_GET['book_type'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'title';
$sortOrder = isset($_GET['sortOrder']) && strtoupper($_GET['sortOrder']) === 'DESC' ? 'DESC' : 'ASC';

// Validate sortBy to prevent SQL injection
$allowedSortColumns = ['title', 'author', 'category', 'publication_year', 'availability'];
$sortByStock = false;
if ($sortBy === 'availability') {
    $sortByStock = true;
    $sortBy = 'title'; // Default SQL sort, we'll re-sort after
} elseif (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'title';
}

// Base query to fetch books
$query = "SELECT DISTINCT b.*
          FROM Books b 
          WHERE 1=1 AND b.title NOT LIKE '[DELETED]%'";

// Apply course filter if specified
if (!empty($courseId)) {
    $query .= " AND EXISTS (SELECT 1 FROM Book_Courses bc WHERE bc.isbn = b.isbn AND bc.course_id = :course_id)";
}

// Apply semester filter if specified
if (!empty($semester)) {
    $query .= " AND EXISTS (
        SELECT 1 FROM Book_Courses bc 
        JOIN Courses c ON bc.course_id = c.course_id 
        WHERE bc.isbn = b.isbn AND c.semester = :semester
    )";
}

if (!empty($search)) {
    $query .= " AND (b.title LIKE :search OR b.author LIKE :search OR b.isbn LIKE :search)";
}

if (!empty($category)) {
    $query .= " AND b.category = :category";
}

// Book type filter: Physical = has copies, Digital = has PDF
if (!empty($bookType)) {
    if ($bookType === 'Digital') {
        $query .= " AND EXISTS (SELECT 1 FROM Digital_Resources dr WHERE dr.isbn = b.isbn AND dr.resource_type = 'PDF')";
    } elseif ($bookType === 'Physical') {
        $query .= " AND EXISTS (SELECT 1 FROM Book_Copies WHERE Book_Copies.isbn = b.isbn)";
    }
}

// Apply sorting
$query .= " ORDER BY b.{$sortBy} {$sortOrder}";

$stmt = $db->prepare($query);

if (!empty($search)) {
    $search_param = "%{$search}%";
    $stmt->bindParam(":search", $search_param);
}

if (!empty($category)) {
    $stmt->bindParam(":category", $category);
}

if (!empty($courseId)) {
    $stmt->bindParam(":course_id", $courseId);
}

if (!empty($semester)) {
    $stmt->bindParam(":semester", $semester);
}

$stmt->execute();

$books = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Fetch all courses for this book
    $coursesStmt = $db->prepare('
        SELECT bc.course_id, c.course_name, c.semester
        FROM Book_Courses bc
        JOIN Courses c ON bc.course_id = c.course_id
        WHERE bc.isbn = :isbn
        ORDER BY c.course_id
    ');
    $coursesStmt->execute([':isbn' => $row['isbn']]);
    $courses = $coursesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract course_ids and semesters for backward compatibility
    $courseIds = array_map(function($c) { return $c['course_id']; }, $courses);
    $semesters = array_unique(array_map(function($c) { return $c['semester']; }, $courses));
    
    // Count available copies
    $copiesStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn AND status = "Available"');
    $copiesStmt->execute([':isbn' => $row['isbn']]);
    $copiesAvailable = (int)$copiesStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Count total copies
    $totalCopiesStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn');
    $totalCopiesStmt->execute([':isbn' => $row['isbn']]);
    $totalCopies = (int)$totalCopiesStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Count borrowed copies
    $borrowedStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn AND status = "Borrowed"');
    $borrowedStmt->execute([':isbn' => $row['isbn']]);
    $copiesBorrowed = (int)$borrowedStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Count unavailable copies (manually disabled by librarian)
    $unavailableStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn AND status = "Unavailable"');
    $unavailableStmt->execute([':isbn' => $row['isbn']]);
    $copiesUnavailable = (int)$unavailableStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    
    // Check if book is disabled (all non-borrowed copies are unavailable)
    $isBookDisabled = ($totalCopies > 0 && $copiesAvailable === 0 && $copiesUnavailable > 0 && $copiesBorrowed === 0) ||
                      ($totalCopies > 0 && $copiesUnavailable === ($totalCopies - $copiesBorrowed));
    
    // Apply availability filter
    // Frontend sends: 'available', 'low', 'out'
    if (!empty($availability)) {
        if ($availability === 'available' && $copiesAvailable === 0) {
            continue; // Skip books with no available copies (want in stock only)
        } elseif ($availability === 'out' && $copiesAvailable > 0) {
            continue; // Skip books with available copies (want out of stock only)
        } elseif ($availability === 'low') {
            // Low stock: has copies but few available (1-2 available, or less than 30% of total)
            if ($totalCopies === 0 || $copiesAvailable === 0 || $copiesAvailable > 2) {
                if ($totalCopies === 0 || $copiesAvailable === 0 || ($copiesAvailable / $totalCopies) > 0.3) {
                    continue;
                }
            }
        }
    }
    
    // Check for PDF
    $pdfStmt = $db->prepare('SELECT file_path FROM Digital_Resources WHERE isbn = :isbn AND resource_type = "PDF"');
    $pdfStmt->execute([':isbn' => $row['isbn']]);
    $pdfRow = $pdfStmt->fetch(PDO::FETCH_ASSOC);
    
    // Always serve PDFs through download endpoint so paths stay consistent
    $pdfUrl = null;
    if ($pdfRow && !empty($pdfRow['file_path'])) {
        $pdfUrl = 'http://localhost:8000/api/books/download_pdf.php?isbn=' . urlencode($row['isbn']);
    }
    
    $books[] = [
        'isbn' => $row['isbn'],
        'title' => $row['title'],
        'author' => $row['author'],
        'category' => $row['category'],
        'publisher' => $row['publisher'],
        'publication_year' => $row['publication_year'],
        'edition' => $row['edition'],
        'description' => $row['description'],
        'pic_path' => $row['pic_path'],
        'copies_available' => $copiesAvailable,
        'copies_total' => $totalCopies,
        'copies_borrowed' => $copiesBorrowed,
        'copies_unavailable' => $copiesUnavailable,
        'is_disabled' => $isBookDisabled,
        'course_id' => !empty($courseIds) ? $courseIds[0] : null, // First course for backward compatibility
        'course_ids' => $courseIds, // Array of all course IDs
        'courses' => $courses, // Full course details with names and semesters
        'semesters' => array_values($semesters), // Unique semesters
        'pdf_url' => $pdfUrl,
    ];
}

// Sort by availability if requested (needs post-processing since it's computed)
if ($sortByStock) {
    usort($books, function($a, $b) use ($sortOrder) {
        $diff = $a['copies_available'] - $b['copies_available'];
        return $sortOrder === 'DESC' ? -$diff : $diff;
    });
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'count' => count($books),
    'books' => $books,
]);
?>
