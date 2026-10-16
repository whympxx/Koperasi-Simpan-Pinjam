<?php
/**
 * Mark Notification as Read AJAX Handler
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once '../includes/init.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if user is authenticated
    $auth->requireAuth();

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['notification_id']) || empty($input['notification_id'])) {
        throw new Exception('Notification ID is required');
    }

    $notificationId = (int)$input['notification_id'];
    $userId = $auth->getCurrentUser()['id'];

    // Verify notification belongs to current user
    $notification = $db->selectOne(
        "SELECT * FROM notifications WHERE id = ? AND user_id = ?",
        [$notificationId, $userId]
    );

    if (!$notification) {
        throw new Exception('Notification not found');
    }

    // Mark notification as read
    $updated = $db->update(
        "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
        [$notificationId, $userId]
    );

    if ($updated > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        throw new Exception('Failed to update notification');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
