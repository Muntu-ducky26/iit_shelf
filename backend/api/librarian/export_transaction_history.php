<?php
/**
 * Export Transaction History as PDF or CSV
 */

require_once '../../config/database.php';
require_once '../../tcpdf/tcpdf.php';
require_once '../includes/pdf_header.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Get parameters
$format = isset($_GET['format']) ? $_GET['format'] : 'pdf';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'All';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $transactions = [];
    
    // Fetch Borrow transactions
    if ($filter === 'All' || $filter === 'Borrow') {
        $sql = "SELECT 
                    'Borrow' as type,
                    b.title as book_title,
                    u.email as user_id,
                    u.name as user_name,
                    DATE(at.issue_date) as date,
                    TIME_FORMAT(at.issue_date, '%h:%i %p') as time,
                    '' as amount,
                    'Completed' as status
                FROM Approved_Transactions at
                JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                JOIN Users u ON u.email = tr.requester_email
                JOIN Books b ON b.isbn = tr.isbn
                WHERE at.status IN ('Borrowed', 'Returned')";
        
        if ($search !== '') {
            $sql .= " AND (u.email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $sql .= " AND DATE(at.issue_date) >= :start1";
        }
        if ($endDate !== '') {
            $sql .= " AND DATE(at.issue_date) <= :end1";
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Fetch Return transactions
    if ($filter === 'All' || $filter === 'Return') {
        $sql = "SELECT 
                    'Return' as type,
                    b.title as book_title,
                    u.email as user_id,
                    u.name as user_name,
                    DATE(at.return_date) as date,
                    TIME_FORMAT(at.return_date, '%h:%i %p') as time,
                    '' as amount,
                    'Completed' as status
                FROM Approved_Transactions at
                JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                JOIN Users u ON u.email = tr.requester_email
                JOIN Books b ON b.isbn = tr.isbn
                WHERE at.return_date IS NOT NULL";
        
        if ($search !== '') {
            $sql .= " AND (u.email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $sql .= " AND DATE(at.return_date) >= :start1";
        }
        if ($endDate !== '') {
            $sql .= " AND DATE(at.return_date) <= :end1";
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Fetch Fine Payment transactions
    if ($filter === 'All' || $filter === 'Fine Payment') {
        $sql = "SELECT 
                    'Fine Payment' as type,
                    COALESCE(b.title, f.description) as book_title,
                    f.user_email as user_id,
                    u.name as user_name,
                    DATE(f.payment_date) as date,
                    TIME_FORMAT(f.payment_date, '%h:%i %p') as time,
                    f.amount as amount,
                    'Paid' as status
                FROM Fines f
                JOIN Users u ON u.email = f.user_email
                LEFT JOIN Approved_Transactions at ON at.transaction_id = f.transaction_id
                LEFT JOIN Transaction_Requests tr ON tr.request_id = at.request_id
                LEFT JOIN Books b ON b.isbn = tr.isbn
                WHERE f.paid = 1";
        
        if ($search !== '') {
            $sql .= " AND (f.user_email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $sql .= " AND DATE(f.payment_date) >= :start1";
        }
        if ($endDate !== '') {
            $sql .= " AND DATE(f.payment_date) <= :end1";
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Fetch Reservation transactions
    if ($filter === 'All' || $filter === 'Reservation') {
        $sql = "SELECT 
                    'Reservation' as type,
                    b.title as book_title,
                    r.user_email as user_id,
                    u.name as user_name,
                    DATE(r.created_at) as date,
                    TIME_FORMAT(r.created_at, '%h:%i %p') as time,
                    '' as amount,
                    CASE 
                        WHEN r.status = 'Active' THEN 'Active'
                        WHEN r.status = 'Notified' THEN 'Notified'
                        WHEN r.status = 'Fulfilled' THEN 'Completed'
                        WHEN r.status = 'Expired' THEN 'Expired'
                        WHEN r.status = 'Cancelled' THEN 'Cancelled'
                        ELSE r.status
                    END as status
                FROM Reservations r
                JOIN Users u ON u.email = r.user_email
                JOIN Books b ON b.isbn = r.isbn";
        
        $whereClauses = [];
        if ($search !== '') {
            $whereClauses[] = "(r.user_email LIKE :search1 OR u.name LIKE :search2 OR b.title LIKE :search3)";
        }
        if ($startDate !== '') {
            $whereClauses[] = "DATE(r.created_at) >= :start1";
        }
        if ($endDate !== '') {
            $whereClauses[] = "DATE(r.created_at) <= :end1";
        }
        
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(" AND ", $whereClauses);
        }
        
        $stmt = $db->prepare($sql);
        if ($search !== '') {
            $like = "%$search%";
            $stmt->bindValue(':search1', $like);
            $stmt->bindValue(':search2', $like);
            $stmt->bindValue(':search3', $like);
        }
        if ($startDate !== '') {
            $stmt->bindValue(':start1', $startDate);
        }
        if ($endDate !== '') {
            $stmt->bindValue(':end1', $endDate);
        }
        $stmt->execute();
        $transactions = array_merge($transactions, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Sort by date descending
    usort($transactions, function($a, $b) {
        return strtotime($b['date'] . ' ' . $b['time']) - strtotime($a['date'] . ' ' . $a['time']);
    });
    
    if ($format === 'csv') {
        exportCSV($transactions, $filter, $startDate, $endDate);
    } else {
        exportPDF($transactions, $filter, $startDate, $endDate);
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function exportCSV($data, $filter, $startDate, $endDate) {
    $filename = 'Transaction_History_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Title row
    fputcsv($output, ['IITShelf - Transaction History Report']);
    fputcsv($output, ['Filter: ' . $filter]);
    if ($startDate && $endDate) {
        fputcsv($output, ['Date Range: ' . $startDate . ' to ' . $endDate]);
    }
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []); // Empty row
    
    // Header row
    fputcsv($output, ['Date', 'Time', 'Book Title', 'User Name', 'User ID', 'Type', 'Amount', 'Status']);
    
    // Data rows
    foreach ($data as $row) {
        $amount = !empty($row['amount']) ? 'BDT ' . number_format((float)$row['amount'], 2) : '-';
        fputcsv($output, [
            $row['date'],
            $row['time'],
            $row['book_title'],
            $row['user_name'],
            $row['user_id'],
            $row['type'],
            $amount,
            $row['status']
        ]);
    }
    
    fclose($output);
    exit;
}

function exportPDF($data, $filter, $startDate, $endDate) {
    // Create PDF
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('IITShelf');
    $pdf->SetAuthor('IITShelf Library System');
    $pdf->SetTitle('Transaction History Report');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins (increased top margin for header)
    $pdf->SetMargins(10, 10, 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Add institution header with logos
    $subtitle = 'Filter: ' . $filter;
    if ($startDate && $endDate) {
        $subtitle .= ' | Date Range: ' . $startDate . ' to ' . $endDate;
    }
    addPdfHeader($pdf, 'Transaction History Report', $subtitle);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255, 255, 255);
    
    // Column widths (total ~277mm for A4 landscape with margins)
    $w = [25, 70, 40, 55, 25, 25, 37];
    
    $pdf->Cell($w[0], 8, 'Date', 1, 0, 'C', true);
    $pdf->Cell($w[1], 8, 'Book Title', 1, 0, 'C', true);
    $pdf->Cell($w[2], 8, 'User Name', 1, 0, 'C', true);
    $pdf->Cell($w[3], 8, 'User ID', 1, 0, 'C', true);
    $pdf->Cell($w[4], 8, 'Type', 1, 0, 'C', true);
    $pdf->Cell($w[5], 8, 'Amount', 1, 0, 'C', true);
    $pdf->Cell($w[6], 8, 'Status', 1, 1, 'C', true);
    
    // Table body
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    
    $fill = false;
    foreach ($data as $row) {
        $pdf->SetFillColor(245, 247, 250);
        
        $dateTime = $row['date'] . "\n" . $row['time'];
        $amount = !empty($row['amount']) ? 'BDT ' . number_format((float)$row['amount'], 2) : '-';
        
        // Calculate row height based on content
        $bookTitle = strlen($row['book_title']) > 40 ? substr($row['book_title'], 0, 40) . '...' : $row['book_title'];
        
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        $pdf->MultiCell($w[0], 10, $dateTime, 1, 'C', $fill, 0);
        $pdf->MultiCell($w[1], 10, $bookTitle, 1, 'L', $fill, 0);
        $pdf->MultiCell($w[2], 10, $row['user_name'], 1, 'L', $fill, 0);
        $pdf->MultiCell($w[3], 10, $row['user_id'], 1, 'L', $fill, 0);
        $pdf->MultiCell($w[4], 10, $row['type'], 1, 'C', $fill, 0);
        $pdf->MultiCell($w[5], 10, $amount, 1, 'C', $fill, 0);
        $pdf->MultiCell($w[6], 10, $row['status'], 1, 'C', $fill, 1);
        
        $fill = !$fill;
    }
    
    if (empty($data)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(array_sum($w), 10, 'No transactions found for the selected filters.', 1, 1, 'C');
    }
    
    // Output PDF
    $filename = 'Transaction_History_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}
