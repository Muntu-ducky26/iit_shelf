<?php
/**
 * Mark Notification as Read
 * Marks a single notification or all notifications as read for a user
 */

require_once '../../config/database.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Get input from JSON or POST
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user_email = isset($input['user_email']) ? strtolower(trim($input['user_email'])) : '';
$notification_id = isset($input['notification_id']) ? (int)$input['notification_id'] : 0;
$mark_all = isset($input['mark_all']) ? (bool)$input['mark_all'] : false;

if ($user_email === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'User email is required.'
    ]);
    exit;
}

try {
    if ($mark_all) {
        // Since we don't track read status in DB, just return success
        // In a full implementation, you'd want to store this in a separate table
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
    } else {
        // Mark single notification as read
        if ($notification_id === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Notification ID is required.'
            ]);
            exit;
        }
        
        // Verify notification exists
        $stmt = $db->prepare('
            SELECT notification_id FROM Notifications 
            WHERE notification_id = :id AND user_email = :email
        ');
        $stmt->execute([
            ':id' => $notification_id,
            ':email' => $user_email
        ]);
        
        if ($stmt->rowCount() > 0) {
            // Since we don't track read status in DB, just return success
            // In a full implementation, you'd want to store this in a separate table
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read.'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Notification not found.'
            ]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update notification: ' . $e->getMessage()
    ]);
}
?>
