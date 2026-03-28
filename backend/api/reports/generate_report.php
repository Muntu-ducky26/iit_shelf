<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);

$reportType = $input['report_type'] ?? '';
$startDate = $input['start_date'] ?? null;
$endDate = $input['end_date'] ?? null;
$semester = $input['semester'] ?? null;
$session = $input['session'] ?? null;
$format = $input['format'] ?? 'json';

try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Failed to connect to database');
    }
    
    $data = [];
    
    switch ($reportType) {
        case 'most_borrowed':
            $data = generateMostBorrowedReport($pdo, $startDate, $endDate, $semester, $session);
            break;
        
        case 'most_requested':
            $data = generateMostRequestedReport($pdo, $startDate, $endDate, $semester, $session);
            break;
        
        case 'semester_wise':
            $data = generateSemesterWiseReport($pdo, $startDate, $endDate);
            break;
        
        case 'session_wise':
            $data = generateSessionWiseReport($pdo, $startDate, $endDate);
            break;
        
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid report type'
            ]);
            exit;
    }
    
    if ($format === 'csv') {
        outputCSV($data, $reportType);
    } elseif ($format === 'pdf') {
        outputPDF($data, $reportType, $startDate, $endDate);
    } else {
        echo json_encode([
            'success' => true,
            'data' => $data,
            'report_type' => $reportType,
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error generating report: ' . $e->getMessage()
    ]);
}

function generateMostBorrowedReport($pdo, $startDate, $endDate, $semester, $session) {
    $sql = "SELECT 
                b.isbn,
                b.title,
                b.author,
                b.category,
                COUNT(at.transaction_id) AS borrow_count,
                COUNT(DISTINCT tr.requester_email) AS unique_borrowers
            FROM Approved_Transactions at
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Book_Courses bcs ON b.isbn = bcs.isbn
            LEFT JOIN Courses c ON bcs.course_id = c.course_id
            LEFT JOIN Students s ON tr.requester_email = s.email
            WHERE 1=1";

    if ($startDate && $endDate) {
        $sql .= " AND at.issue_date BETWEEN :start_date AND :end_date";
    }

    if (!empty($semester)) {
        $sql .= " AND c.semester = :semester";
    }

    if (!empty($session)) {
        $sql .= " AND s.session = :session";
    }

    $sql .= " GROUP BY b.isbn, b.title, b.author, b.category
              ORDER BY borrow_count DESC
              LIMIT 50";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    if (!empty($semester)) {
        $stmt->bindParam(':semester', $semester);
    }

    if (!empty($session)) {
        $stmt->bindParam(':session', $session);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateMostRequestedReport($pdo, $startDate, $endDate, $semester, $session) {
    // Most requested books: based on Requests table (new book addition requests)
    // Count addition requests grouped by ISBN to show most requested books
    
    // First, check if the Requests table has the created_at column
    try {
        $checkSql = "SHOW COLUMNS FROM Requests LIKE 'created_at'";
        $checkStmt = $pdo->query($checkSql);
        $hasCreatedAt = $checkStmt->rowCount() > 0;
    } catch (Exception $e) {
        $hasCreatedAt = false;
    }
    
    $sql = "SELECT 
                r.isbn,
                MAX(COALESCE(r.title, b.title, '')) AS title,
                MAX(COALESCE(r.author, b.author, '')) AS author,
                MAX(COALESCE(r.category, b.category, '')) AS category,
                COUNT(r.request_id) AS request_count
            FROM Requests r
            LEFT JOIN Books b ON r.isbn = b.isbn
            WHERE r.isbn IS NOT NULL AND r.isbn != ''";

    if ($startDate && $endDate) {
        if ($hasCreatedAt) {
            $sql .= " AND DATE(COALESCE(r.created_at, r.approved_at)) BETWEEN :start_date AND :end_date";
        } else {
            $sql .= " AND (r.approved_at IS NULL OR DATE(r.approved_at) BETWEEN :start_date AND :end_date)";
        }
    }

    $sql .= " GROUP BY r.isbn
              ORDER BY request_count DESC
              LIMIT 50";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    try {
        $stmt->execute();
        $mostRequested = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback query without date filter if query fails
        error_log("Most requested report error: " . $e->getMessage());
        $sql = "SELECT 
                    r.isbn,
                    MAX(COALESCE(r.title, b.title, '')) AS title,
                    MAX(COALESCE(r.author, b.author, '')) AS author,
                    MAX(COALESCE(r.category, b.category, '')) AS category,
                    COUNT(r.request_id) AS request_count
                FROM Requests r
                LEFT JOIN Books b ON r.isbn = b.isbn
                WHERE r.isbn IS NOT NULL AND r.isbn != ''
                GROUP BY r.isbn
                ORDER BY request_count DESC
                LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $mostRequested = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $mostRequested;
}

function generateSemesterWiseReport($pdo, $startDate, $endDate) {
    $sql = "SELECT 
                COALESCE(c.semester, 'Unassigned') AS semester,
                COUNT(DISTINCT at.transaction_id) AS borrow_count,
                COUNT(DISTINCT tr.request_id) AS request_count,
                COUNT(DISTINCT tr.requester_email) AS unique_borrowers,
                COUNT(DISTINCT b.isbn) AS book_count
            FROM Approved_Transactions at
            JOIN Transaction_Requests tr ON at.request_id = tr.request_id
            JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Book_Courses bcs ON b.isbn = bcs.isbn
            LEFT JOIN Courses c ON bcs.course_id = c.course_id
            WHERE 1=1";

    if ($startDate && $endDate) {
        $sql .= " AND at.issue_date BETWEEN :start_date AND :end_date";
    }

    $sql .= " GROUP BY semester
              ORDER BY 
                CASE 
                    WHEN semester = 'Unassigned' THEN 999
                    ELSE CAST(semester AS UNSIGNED)
                END ASC";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateSessionWiseReport($pdo, $startDate, $endDate) {
    // Session-wise report based on actual student sessions from Students table
    // Get borrowing statistics grouped by student session (e.g., "21-22", "22-23")
    
    $sql = "SELECT 
                COALESCE(s.session, 'Unassigned') AS session,
                COUNT(DISTINCT at.transaction_id) AS borrow_count,
                COUNT(DISTINCT tr.request_id) AS request_count,
                COUNT(DISTINCT r.reservation_id) AS reservation_count,
                COUNT(DISTINCT tr.requester_email) AS unique_users,
                COUNT(DISTINCT b.isbn) AS book_count
            FROM Students s
            LEFT JOIN Transaction_Requests tr ON s.email = tr.requester_email
            LEFT JOIN Approved_Transactions at ON tr.request_id = at.request_id
            LEFT JOIN Book_Copies bc ON at.copy_id = bc.copy_id
            LEFT JOIN Books b ON bc.isbn = b.isbn
            LEFT JOIN Reservations r ON s.email = r.user_email
            WHERE 1=1";

    if ($startDate && $endDate) {
        $sql .= " AND (at.issue_date IS NULL OR at.issue_date BETWEEN :start_date AND :end_date)";
    }

    $sql .= " GROUP BY s.session
              ORDER BY s.session ASC";

    $stmt = $pdo->prepare($sql);

    if ($startDate && $endDate) {
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
    }

    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function outputCSV($data, $reportType) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data) && is_array($data)) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

function outputPDF($data, $reportType, $startDate, $endDate) {
    require_once __DIR__ . '/../../tcpdf/tcpdf.php';
    require_once __DIR__ . '/../includes/pdf_header.php';
    
    $rows = is_array($data) ? $data : [];
    $headers = !empty($rows) ? array_keys($rows[0]) : [];
    $colCount = count($headers);
    
    // Use landscape for more columns
    $orientation = ($colCount > 5) ? 'L' : 'P';
    
    $pdf = new TCPDF($orientation, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    $pdf->SetCreator('IIT Shelf Library System');
    $pdf->SetAuthor('IIT Shelf');
    $pdf->SetTitle(ucwords(str_replace('_', ' ', $reportType)) . ' Report');
    
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(8, 12, 8);
    $pdf->SetAutoPageBreak(TRUE, 10);
    $pdf->AddPage();
    
    // Add institution header with logos
    $title = ucwords(str_replace('_', ' ', $reportType)) . ' Report';
    $subtitle = ($startDate && $endDate) ? "Period: $startDate to $endDate" : '';
    addPdfHeader($pdf, $title, $subtitle);
    
    if (empty($rows)) {
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 10, 'No data available for this report.', 0, 1, 'C');
    } else {
        // Calculate intelligent column widths
        $pageWidth = $pdf->getPageWidth() - 16; // accounting for margins
        $colWidths = calculatePDFColumnWidths($headers, $rows, $pageWidth, $pdf);
        
        // Table header - use abbreviated names for long column headers
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(66, 139, 202);
        $pdf->SetTextColor(255, 255, 255);
        
        // Map long header names to shorter versions
        $headerAbbreviations = [
            'borrow_count' => 'Borrow Count',
            'unique_borrowers' => 'Unique Borrowers',
            'request_count' => 'Requests',
            'reservation_count' => 'Reservations',
            'unique_users' => 'Users',
            'book_count' => 'Books',
            'academic_year' => 'Year',
            'publication_year' => 'Year'
        ];
        
        // Calculate header row height based on longest header text
        $headerHeight = 10;
        foreach ($headers as $idx => $header) {
            $displayHeader = $headerAbbreviations[$header] ?? ucwords(str_replace('_', ' ', $header));
            $width = $colWidths[$idx];
            $numLines = $pdf->getNumLines($displayHeader, $width - 2);
            $headerHeight = max($headerHeight, $numLines * 5 + 4);
        }
        
        // Draw header cells with MultiCell for text wrapping
        $startX = $pdf->GetX();
        $startY = $pdf->GetY();
        
        foreach ($headers as $idx => $header) {
            $width = $colWidths[$idx];
            $displayHeader = $headerAbbreviations[$header] ?? ucwords(str_replace('_', ' ', $header));
            
            $pdf->SetXY($startX, $startY);
            $pdf->MultiCell($width, $headerHeight, $displayHeader, 1, 'C', true, 0);
            $startX += $width;
        }
        $pdf->Ln($headerHeight);
        
        // Table rows
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(240, 245, 250);
        
        $rowCount = 0;
        foreach ($rows as $row) {
            $cellData = [];
            
            // First pass: collect cell data and calculate max height needed
            foreach ($headers as $idx => $header) {
                $cellText = (string)($row[$header] ?? '');
                $cellData[] = $cellText;
            }
            
            // Calculate row height using TCPDF's getNumLines for accuracy
            $maxHeight = 6;
            foreach ($headers as $idx => $header) {
                $width = $colWidths[$idx];
                $numLines = $pdf->getNumLines($cellData[$idx], $width - 2);
                $maxHeight = max($maxHeight, $numLines * 4 + 2);
            }
            
            // Draw cells in a single row
            $startX = 8; // Left margin
            $startY = $pdf->GetY();
            $fill = ($rowCount % 2 == 0);
            
            foreach ($headers as $idx => $header) {
                $width = $colWidths[$idx];
                $text = $cellData[$idx];
                
                $pdf->SetXY($startX, $startY);
                $pdf->MultiCell($width, $maxHeight, $text, 1, 'L', $fill, 0);
                $startX += $width;
            }
            
            // Move to next row
            $pdf->SetY($startY + $maxHeight);
            $rowCount++;
            
            // Add new page if needed
            if ($pdf->GetY() > ($pdf->getPageHeight() - 20)) {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->SetFillColor(66, 139, 202);
                $pdf->SetTextColor(255, 255, 255);
                
                // Redraw header with MultiCell for text wrapping
                $newHeaderStartX = $pdf->GetX();
                $newHeaderStartY = $pdf->GetY();
                
                foreach ($headers as $idx => $header) {
                    $width = $colWidths[$idx];
                    $displayHeader = $headerAbbreviations[$header] ?? ucwords(str_replace('_', ' ', $header));
                    
                    $pdf->SetXY($newHeaderStartX, $newHeaderStartY);
                    $pdf->MultiCell($width, $headerHeight, $displayHeader, 1, 'C', true, 0);
                    $newHeaderStartX += $width;
                }
                $pdf->Ln($headerHeight);
                
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetTextColor(0, 0, 0);
            }
        }
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $reportType . '_' . date('Y-m-d') . '.pdf"');
    $pdf->Output($reportType . '_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

function calculatePDFColumnWidths($headers, $rows, $totalWidth, $pdf) {
    $colCount = count($headers);
    $widths = array_fill(0, $colCount, $totalWidth / $colCount);
    
    // Define minimum widths for common column types
    $minWidths = [
        'isbn' => 35,
        'title' => 70,
        'author' => 50,
        'category' => 40,
        'borrow_count' => 25,
        'unique_borrowers' => 25,
        'request_count' => 25,
        'reservation_count' => 25,
        'unique_users' => 25,
        'book_count' => 20,
        'session' => 25,
        'semester' => 25
    ];
    
    // Adjust widths based on content and header length
    $textWidths = [];
    
    foreach ($headers as $idx => $header) {
        $headerLabel = str_replace('_', ' ', $header);
        $maxLen = strlen($headerLabel);
        
        foreach ($rows as $row) {
            $cellText = (string)($row[$header] ?? '');
            $maxLen = max($maxLen, strlen($cellText));
        }
        
        $textWidths[$idx] = $maxLen;
    }
    
    // Calculate proportional widths
    $totalLen = array_sum($textWidths);
    if ($totalLen > 0) {
        foreach ($textWidths as $idx => $len) {
            $header = strtolower($headers[$idx]);
            $minWidth = $minWidths[$header] ?? 20;
            
            $widths[$idx] = ($len / $totalLen) * $totalWidth;
            // Enforce minimum column widths based on column type
            $widths[$idx] = max($minWidth, min(120, $widths[$idx]));
        }
    }
    
    // Normalize widths to fit page
    $totalCalc = array_sum($widths);
    foreach ($widths as &$w) {
        $w = ($w / $totalCalc) * $totalWidth;
    }
    
    return $widths;
}
?>
