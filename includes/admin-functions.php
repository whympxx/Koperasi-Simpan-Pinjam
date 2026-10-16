<?php
/**
 * Admin Helper Functions
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 * 
 * @author System Developer
 * @version 1.0
 * @since 2024
 */

/**
 * Check if user has admin privileges
 */
function isAdmin($user) {
    return in_array($user['role'], ['admin', 'super_admin']);
}

/**
 * Check if user has super admin privileges
 */
function isSuperAdmin($user) {
    return $user['role'] === 'super_admin';
}

/**
 * Get admin dashboard statistics
 */
function getAdminDashboardStats($db) {
    $stats = [];
    
    // Pending applications
    $stats['pending_applications'] = $db->getCount('loan_applications', 'status = ?', ['pending']);
    
    // Active loans
    $stats['active_loans'] = $db->getCount('active_loans', 'status = ?', ['active']);
    
    // Total outstanding balance
    $result = $db->selectOne("SELECT SUM(remaining_balance) as total FROM active_loans WHERE status = 'active'");
    $stats['total_outstanding'] = $result['total'] ?? 0;
    
    // Total savings
    $result = $db->selectOne("SELECT SUM(balance) as total FROM savings_accounts WHERE status = 'active'");
    $stats['total_savings'] = $result['total'] ?? 0;
    
    // Overdue payments
    $stats['overdue_payments'] = $db->getCount('loan_schedules', 'status = ? AND due_date < CURDATE()', ['pending']);
    
    // Total users
    $stats['total_users'] = $db->getCount('users', 'role = ? AND status = ?', ['nasabah', 'active']);
    
    // Monthly transaction count
    $result = $db->selectOne("SELECT COUNT(*) as count FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
    $stats['monthly_transactions'] = $result['count'] ?? 0;
    
    // Monthly transaction amount
    $result = $db->selectOne("SELECT SUM(amount) as total FROM transactions WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'completed'");
    $stats['monthly_transaction_amount'] = $result['total'] ?? 0;
    
    return $stats;
}

/**
 * Get pending loan applications for admin review
 */
function getPendingLoanApplications($db, $limit = 10, $offset = 0) {
    $query = "SELECT la.*, u.full_name, u.nik, u.phone, u.email, u.employment_date, u.monthly_salary, 
                     d.name as division_name, d.code as division_code
              FROM loan_applications la 
              JOIN users u ON la.user_id = u.id 
              LEFT JOIN divisions d ON u.division_id = d.id 
              WHERE la.status = 'pending' 
              ORDER BY la.created_at ASC 
              LIMIT ? OFFSET ?";
    
    return $db->select($query, [$limit, $offset]);
}

/**
 * Get overdue payments
 */
function getOverduePayments($db, $limit = 10, $offset = 0) {
    $query = "SELECT ls.*, u.full_name, u.nik, u.phone, u.email, al.loan_number, al.remaining_balance,
                     DATEDIFF(CURDATE(), ls.due_date) as days_overdue
              FROM loan_schedules ls 
              JOIN active_loans al ON ls.loan_id = al.id 
              JOIN users u ON al.user_id = u.id 
              WHERE ls.status = 'pending' AND ls.due_date < CURDATE()
              ORDER BY ls.due_date ASC 
              LIMIT ? OFFSET ?";
    
    return $db->select($query, [$limit, $offset]);
}

/**
 * Get recent transactions for admin dashboard
 */
function getRecentTransactions($db, $limit = 15, $offset = 0) {
    $query = "SELECT t.*, u.full_name, u.nik, u.email
              FROM transactions t 
              JOIN users u ON t.user_id = u.id 
              WHERE t.status = 'completed' 
              ORDER BY t.created_at DESC 
              LIMIT ? OFFSET ?";
    
    return $db->select($query, [$limit, $offset]);
}

/**
 * Get top borrowers by outstanding balance
 */
function getTopBorrowers($db, $limit = 10) {
    $query = "SELECT u.full_name, u.nik, u.phone, u.email, al.remaining_balance, al.monthly_installment, 
                     al.next_due_date, al.loan_number, al.start_date
              FROM active_loans al 
              JOIN users u ON al.user_id = u.id 
              WHERE al.status = 'active' 
              ORDER BY al.remaining_balance DESC 
              LIMIT ?";
    
    return $db->select($query, [$limit]);
}

/**
 * Get recent user registrations
 */
function getRecentUsers($db, $limit = 10, $offset = 0) {
    $query = "SELECT u.*, d.name as division_name, d.code as division_code
              FROM users u 
              LEFT JOIN divisions d ON u.division_id = d.id 
              WHERE u.role = 'nasabah' 
              ORDER BY u.created_at DESC 
              LIMIT ? OFFSET ?";
    
    return $db->select($query, [$limit, $offset]);
}

/**
 * Get monthly transaction statistics for charts
 */
function getMonthlyTransactionStats($db, $months = 12) {
    $query = "SELECT 
                DATE_FORMAT(transaction_date, '%Y-%m') as month,
                type,
                SUM(amount) as total_amount,
                COUNT(*) as transaction_count
              FROM transactions 
              WHERE status = 'completed' 
              AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
              GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), type
              ORDER BY month DESC";
    
    return $db->select($query, [$months]);
}

/**
 * Approve loan application
 */
function approveLoanApplication($db, $applicationId, $approvedBy, $notes = '') {
    $dbHelper = new DatabaseHelper();
    
    try {
        $db->beginTransaction();
        
        // Get application details
        $application = $db->selectOne(
            "SELECT * FROM loan_applications WHERE id = ? AND status = 'pending'",
            [$applicationId]
        );
        
        if (!$application) {
            throw new Exception('Application not found or not pending');
        }
        
        // Update application status
        $db->update(
            "UPDATE loan_applications SET status = 'approved', approved_by = ?, approved_at = NOW(), review_notes = ? WHERE id = ?",
            [$approvedBy, $notes, $applicationId]
        );
        
        // Create active loan record
        $loanNumber = $dbHelper->generateReferenceNumber('LN', 'active_loans', 'loan_number');
        
        $loanId = $db->insert(
            "INSERT INTO active_loans (user_id, application_id, loan_number, original_amount, remaining_balance, 
                                     monthly_installment, interest_rate, loan_term_months, remaining_months, 
                                     start_date, next_due_date, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'active')",
            [
                $application['user_id'],
                $applicationId,
                $loanNumber,
                $application['loan_amount'],
                $application['loan_amount'],
                $application['monthly_installment'],
                $application['interest_rate'],
                $application['loan_term_months'],
                $application['loan_term_months'],
            ]
        );
        
        // Create loan schedules
        createLoanSchedules($db, $loanId, $application);
        
        // Create disbursement transaction
        $refNumber = $dbHelper->generateReferenceNumber('DIS', 'transactions', 'reference_number');
        
        $db->insert(
            "INSERT INTO transactions (user_id, reference_number, type, amount, description, related_loan_id, 
                                     balance_before, balance_after, processed_by, status, transaction_date) 
             VALUES (?, ?, 'loan_disbursement', ?, ?, ?, 0, ?, ?, 'completed', CURDATE())",
            [
                $application['user_id'],
                $refNumber,
                $application['loan_amount'],
                'Pencairan pinjaman - ' . $loanNumber,
                $loanId,
                $application['loan_amount'],
                $approvedBy
            ]
        );
        
        // Create notification for user
        $dbHelper->createNotification(
            $application['user_id'],
            'Pinjaman Disetujui',
            'Pengajuan pinjaman Anda telah disetujui. Pinjaman akan segera dicairkan.',
            'success',
            'loan_approval',
            'loans.php',
            date('Y-m-d H:i:s', strtotime('+30 days'))
        );
        
        // Log activity
        $dbHelper->logActivity(
            $approvedBy,
            'approve_loan',
            'loan_application',
            $applicationId,
            ['status' => 'pending'],
            ['status' => 'approved', 'loan_id' => $loanId]
        );
        
        $db->commit();
        return ['success' => true, 'loan_id' => $loanId, 'loan_number' => $loanNumber];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Reject loan application
 */
function rejectLoanApplication($db, $applicationId, $rejectedBy, $reason) {
    $dbHelper = new DatabaseHelper();
    
    try {
        // Update application status
        $updated = $db->update(
            "UPDATE loan_applications SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejected_reason = ? WHERE id = ? AND status = 'pending'",
            [$rejectedBy, $reason, $applicationId]
        );
        
        if ($updated === 0) {
            throw new Exception('Application not found or not pending');
        }
        
        // Get application for notification
        $application = $db->selectOne(
            "SELECT user_id FROM loan_applications WHERE id = ?",
            [$applicationId]
        );
        
        // Create notification for user
        $dbHelper->createNotification(
            $application['user_id'],
            'Pinjaman Ditolak',
            'Maaf, pengajuan pinjaman Anda ditolak. Alasan: ' . $reason,
            'error',
            'loan_approval',
            'loans.php',
            date('Y-m-d H:i:s', strtotime('+7 days'))
        );
        
        // Log activity
        $dbHelper->logActivity(
            $rejectedBy,
            'reject_loan',
            'loan_application',
            $applicationId,
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $reason]
        );
        
        return ['success' => true];
        
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Create loan schedules for approved loan
 */
function createLoanSchedules($db, $loanId, $application) {
    $monthlyInstallment = $application['monthly_installment'];
    $loanTerm = $application['loan_term_months'];
    $interestRate = $application['interest_rate'];
    $principalAmount = $application['loan_amount'] / $loanTerm;
    
    $startDate = date('Y-m-d');
    
    for ($i = 1; $i <= $loanTerm; $i++) {
        $dueDate = date('Y-m-d', strtotime($startDate . " +$i months"));
        $interestAmount = ($application['loan_amount'] - (($i - 1) * $principalAmount)) * ($interestRate / 100);
        
        $db->insert(
            "INSERT INTO loan_schedules (loan_id, installment_number, due_date, principal_amount, 
                                       interest_amount, total_amount, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [
                $loanId,
                $i,
                $dueDate,
                $principalAmount,
                $interestAmount,
                $monthlyInstallment
            ]
        );
    }
}

/**
 * Get system health status
 */
function getSystemHealthStatus($db) {
    $health = [
        'database' => 'healthy',
        'overdue_payments' => 0,
        'pending_applications' => 0,
        'failed_transactions' => 0,
        'unread_notifications' => 0
    ];
    
    try {
        // Check database connection
        $db->query("SELECT 1");
        
        // Count overdue payments
        $result = $db->selectOne("SELECT COUNT(*) as count FROM loan_schedules WHERE status = 'pending' AND due_date < CURDATE()");
        $health['overdue_payments'] = $result['count'];
        
        // Count pending applications
        $result = $db->selectOne("SELECT COUNT(*) as count FROM loan_applications WHERE status = 'pending'");
        $health['pending_applications'] = $result['count'];
        
        // Count failed transactions
        $result = $db->selectOne("SELECT COUNT(*) as count FROM transactions WHERE status = 'failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $health['failed_transactions'] = $result['count'];
        
        // Count unread notifications
        $result = $db->selectOne("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
        $health['unread_notifications'] = $result['count'];
        
    } catch (Exception $e) {
        $health['database'] = 'error';
    }
    
    return $health;
}

/**
 * Generate admin dashboard summary
 */
function generateAdminDashboardSummary($db) {
    $stats = getAdminDashboardStats($db);
    $health = getSystemHealthStatus($db);
    
    $summary = [
        'total_users' => $stats['total_users'],
        'active_loans' => $stats['active_loans'],
        'pending_applications' => $stats['pending_applications'],
        'overdue_payments' => $stats['overdue_payments'],
        'total_outstanding' => $stats['total_outstanding'],
        'total_savings' => $stats['total_savings'],
        'monthly_transactions' => $stats['monthly_transactions'],
        'monthly_transaction_amount' => $stats['monthly_transaction_amount'],
        'system_health' => $health
    ];
    
    return $summary;
}

/**
 * Get admin activity log
 */
function getAdminActivityLog($db, $limit = 20, $offset = 0) {
    $query = "SELECT al.*, u.full_name as user_name, u.nik
              FROM activity_logs al
              JOIN users u ON al.user_id = u.id
              WHERE u.role IN ('admin', 'super_admin')
              ORDER BY al.created_at DESC
              LIMIT ? OFFSET ?";
    
    return $db->select($query, [$limit, $offset]);
}

/**
 * Check if admin can perform action
 */
function canAdminPerformAction($admin, $action) {
    $permissions = [
        'super_admin' => [
            'approve_loans',
            'reject_loans',
            'manage_users',
            'manage_settings',
            'view_reports',
            'manage_system'
        ],
        'admin' => [
            'approve_loans',
            'reject_loans',
            'view_reports',
            'manage_users'
        ]
    ];
    
    return isset($permissions[$admin['role']]) && in_array($action, $permissions[$admin['role']]);
}
?>
