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
include_once '../lib/reservation_helpers.php';

const MAX_RESERVATION_QUEUE = 5;

$database = new Database();
$db = $database->getConnection();

$payload = json_decode(file_get_contents('php://input'));

$userEmail = isset($payload->user_email) ? strtolower(trim($payload->user_email)) : null;
$isbn = isset($payload->isbn) ? trim($payload->isbn) : null;

if (empty($isbn) || empty($userEmail)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'isbn and user_email are required',
    ]);
    exit;
}

try {
    // Ensure requester exists and is allowed to reserve
    $userStmt = $db->prepare('SELECT role FROM Users WHERE LOWER(email) = :email LIMIT 1');
    $userStmt->execute([':email' => $userEmail]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
        ]);
        exit;
    }

    $role = strtolower(trim((string)$user['role']));
    if (!in_array($role, ['student', 'teacher', 'director'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Only students, teachers, and directors can reserve books',
        ]);
        exit;
    }

    // Ensure book exists
    $bookCheck = $db->prepare('SELECT isbn FROM Books WHERE isbn = :isbn');
    $bookCheck->execute([':isbn' => $isbn]);
    if (!$bookCheck->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Book not found',
        ]);
        exit;
    }

    // Reservation is only for books currently unavailable to borrow
    $availableStmt = $db->prepare('SELECT COUNT(*) as cnt FROM Book_Copies WHERE isbn = :isbn AND status = "Available"');
    $availableStmt->execute([':isbn' => $isbn]);
    $availableCopies = (int)$availableStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    if ($availableCopies > 0) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Book is currently available for borrowing, reservation is not needed',
        ]);
        exit;
    }

    // Keep queue clean before calculating position/cap
    cleanupExpiredReservationsForIsbn($db, $isbn);
    renumberReservationQueue($db, $isbn);

    // Prevent duplicate active reservation
    $dup = $db->prepare('SELECT reservation_id FROM Reservations WHERE isbn = :isbn AND user_email = :user_email AND status = "Active"');
    $dup->execute([
        ':isbn' => $isbn,
        ':user_email' => $userEmail,
    ]);
    if ($dup->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have an active reservation for this book',
        ]);
        exit;
    }

    // Enforce queue size limit (max 5 active reservations per ISBN)
    $countStmt = $db->prepare('SELECT COUNT(*) as total FROM Reservations WHERE isbn = :isbn AND status = "Active"');
    $countStmt->execute([':isbn' => $isbn]);
    $activeQueueCount = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    if ($activeQueueCount >= MAX_RESERVATION_QUEUE) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Reservation queue is full (maximum 5 people). Please try again later.',
        ]);
        exit;
    }

    // Determine next queue position
    $queueStmt = $db->prepare('SELECT IFNULL(MAX(queue_position),0) as pos FROM Reservations WHERE isbn = :isbn AND status = "Active"');
    $queueStmt->execute([':isbn' => $isbn]);
    $pos = (int)$queueStmt->fetch(PDO::FETCH_ASSOC)['pos'] + 1;

    $stmt = $db->prepare('INSERT INTO Reservations (
        isbn, user_email, queue_position, status, created_at
    ) VALUES (
        :isbn, :user_email, :queue_position, "Active", NOW()
    )');

    $stmt->execute([
        ':isbn' => $isbn,
        ':user_email' => $userEmail,
        ':queue_position' => $pos,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Book reserved successfully',
        'reservation_id' => $db->lastInsertId(),
        'queue_position' => $pos,
        'queue_limit' => MAX_RESERVATION_QUEUE,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to reserve book: ' . $e->getMessage(),
    ]);
}
?>
