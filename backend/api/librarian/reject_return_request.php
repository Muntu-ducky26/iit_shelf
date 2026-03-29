<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/notification_helpers.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$transactionId = isset($input['transaction_id']) ? (int)$input['transaction_id'] : 0;

if ($transactionId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'transaction_id is required',
    ]);
    exit;
}

try {
    $db->beginTransaction();

    $infoStmt = $db->prepare('
        SELECT tr.requester_email, b.title, b.isbn, at.status
        FROM Approved_Transactions at
        JOIN Transaction_Requests tr ON at.request_id = tr.request_id
        JOIN Book_Copies bc ON at.copy_id = bc.copy_id
        JOIN Books b ON bc.isbn = b.isbn
        WHERE at.transaction_id = :tid
        LIMIT 1
    ');
    $infoStmt->execute([':tid' => $transactionId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    if (!$info) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction not found',
        ]);
        exit;
    }

    if (!in_array($info['status'], ['Borrowed', 'Overdue'], true)) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Transaction is not in an active borrow state',
        ]);
        exit;
    }

    // Return requests are tracked via Notifications (ReturnRequestPending).
    // Remove all pending return-request notifications for this transaction
    // so it disappears from librarian pending queue.
    $pattern = '%Transaction #' . $transactionId . ')%';
    $cleanupStmt = $db->prepare("
        DELETE FROM Notifications
        WHERE type = 'ReturnRequestPending'
          AND message LIKE :pattern
    ");
    $cleanupStmt->execute([':pattern' => $pattern]);

    $bookTitle = $info['title'] ?: 'Book';
    $isbn = $info['isbn'] ?: '';
    $message = "Your return request for '$bookTitle' (ISBN: $isbn) was rejected by librarian. Please contact library desk for details.";
    createNotification($db, $info['requester_email'], $message, 'ReturnRequestRejected');

    $db->commit();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Return request rejected',
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error rejecting return request: ' . $e->getMessage(),
    ]);
}

