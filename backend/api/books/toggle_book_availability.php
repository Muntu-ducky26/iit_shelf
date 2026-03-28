<?php
/**
 * Toggle Book Availability
 * Sets all copies of a book to either 'Unavailable' or 'Available'
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['isbn'])) {
    echo json_encode(['success' => false, 'message' => 'ISBN is required']);
    exit;
}

$isbn = $data['isbn'];
$makeAvailable = isset($data['available']) ? (bool)$data['available'] : null;

try {
    // If makeAvailable is not specified, toggle the current state
    if ($makeAvailable === null) {
        // Check current state - if any copies are available, make all unavailable
        $checkStmt = $db->prepare("SELECT COUNT(*) as available_count FROM Book_Copies WHERE isbn = :isbn AND status = 'Available'");
        $checkStmt->execute([':isbn' => $isbn]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        // If there are available copies, make all unavailable. Otherwise, make all available.
        $makeAvailable = ($result['available_count'] == 0);
    }
    
    $newStatus = $makeAvailable ? 'Available' : 'Unavailable';
    
    // Update all copies of this book (only those not currently borrowed)
    $stmt = $db->prepare("
        UPDATE Book_Copies 
        SET status = :status 
        WHERE isbn = :isbn 
        AND status != 'Borrowed'
    ");
    $stmt->execute([
        ':status' => $newStatus,
        ':isbn' => $isbn
    ]);
    
    $affectedRows = $stmt->rowCount();
    
    // Get current counts
    $countStmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN status = 'Borrowed' THEN 1 ELSE 0 END) as borrowed,
            SUM(CASE WHEN status = 'Unavailable' THEN 1 ELSE 0 END) as unavailable
        FROM Book_Copies 
        WHERE isbn = :isbn
    ");
    $countStmt->execute([':isbn' => $isbn]);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $makeAvailable 
            ? "Book copies are now available ({$affectedRows} copies updated)"
            : "Book copies are now unavailable ({$affectedRows} copies updated)",
        'available' => $makeAvailable,
        'counts' => [
            'total' => (int)$counts['total'],
            'available' => (int)$counts['available'],
            'borrowed' => (int)$counts['borrowed'],
            'unavailable' => (int)$counts['unavailable']
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
