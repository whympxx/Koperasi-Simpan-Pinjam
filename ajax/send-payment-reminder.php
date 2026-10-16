<?php
/**
 * Send Payment Reminder AJAX Handler
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once '../includes/init.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if user is authenticated and has admin role
    $auth->requireAuth();
    $currentUser = $auth->getCurrentUser();
    if (!in_array($currentUser['role'], ['admin', 'super_admin'])) {
        throw new Exception('Unauthorized access');
    }

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['schedule_id']) || empty($input['schedule_id'])) {
        throw new Exception('Schedule ID is required');
    }

    $scheduleId = (int)$input['schedule_id'];

    // Get schedule details with user information
    $scheduleQuery = "SELECT ls.*, u.full_name, u.phone, u.email, al.loan_number, al.remaining_balance
                      FROM loan_schedules ls
                      JOIN active_loans al ON ls.loan_id = al.id
                      JOIN users u ON al.user_id = u.id
                      WHERE ls.id = ?";
    
    $schedule = $db->selectOne($scheduleQuery, [$scheduleId]);

    if (!$schedule) {
        throw new Exception('Schedule not found');
    }

    // Prepare reminder message
    $message = "Halo " . $schedule['full_name'] . ",\n\n";
    $message .= "Kami ingin mengingatkan bahwa pembayaran angsuran pinjaman Anda dengan nomor " . $schedule['loan_number'] . " ";
    $message .= "jatuh tempo pada " . formatDateIndo($schedule['due_date']) . ".\n\n";
    $message .= "Detail pembayaran:\n";
    $message .= "- Angsuran ke: " . $schedule['installment_number'] . "\n";
    $message .= "- Jumlah: " . formatRupiah($schedule['total_amount']) . "\n";
    $message .= "- Jatuh tempo: " . formatDateIndo($schedule['due_date']) . "\n\n";
    
    if ($schedule['status'] == 'overdue') {
        $daysOverdue = abs(getDaysUntilDue($schedule['due_date']));
        $message .= "⚠️ PERHATIAN: Pembayaran Anda sudah terlambat " . $daysOverdue . " hari.\n";
        $message .= "Mohon segera melakukan pembayaran untuk menghindari denda keterlambatan.\n\n";
    }
    
    $message .= "Silakan lakukan pembayaran melalui:\n";
    $message .= "1. Transfer ke rekening perusahaan\n";
    $message .= "2. Potong gaji otomatis\n";
    $message .= "3. Bayar tunai di kantor\n\n";
    $message .= "Terima kasih atas perhatiannya.\n\n";
    $message .= "PT. Puninar Jaya Logistik\n";
    $message .= "Sistem Simpan Pinjam";

    // Create notification for user
    $notificationId = $dbHelper->createNotification(
        $schedule['user_id'],
        'Pengingat Pembayaran Angsuran',
        $message,
        'warning',
        'payment_reminder',
        'loan-payment.php?schedule_id=' . $scheduleId,
        date('Y-m-d H:i:s', strtotime('+7 days'))
    );

    // Log the reminder activity
    $dbHelper->logActivity(
        $currentUser['id'],
        'send_payment_reminder',
        'loan_schedule',
        $scheduleId,
        null,
        ['reminder_sent' => true, 'notification_id' => $notificationId]
    );

    // TODO: In a real implementation, you would send SMS/WhatsApp/Email here
    // For now, we'll just create a notification
    
    // Update schedule with reminder sent timestamp
    $db->update(
        "UPDATE loan_schedules SET reminder_sent_at = NOW() WHERE id = ?",
        [$scheduleId]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Pengingat pembayaran berhasil dikirim',
        'data' => [
            'schedule_id' => $scheduleId,
            'notification_id' => $notificationId,
            'user_name' => $schedule['full_name']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
