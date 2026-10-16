<?php
/**
 * Savings Management Class
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

class SavingsManager {
    private $db;
    private $dbHelper;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->dbHelper = new DatabaseHelper();
    }
    
    /**
     * Create savings account for user
     */
    public function createAccount($userId) {
        try {
            // Check if account already exists
            $existingAccount = $this->dbHelper->getSavingsAccountByUserId($userId);
            if ($existingAccount) {
                return ['success' => false, 'message' => 'Akun tabungan sudah ada.'];
            }
            
            // Generate account number
            $accountNumber = $this->generateAccountNumber();
            
            // Get default interest rate
            $interestRate = $this->dbHelper->getSystemSetting('savings_interest_rate') ?: DEFAULT_SAVINGS_INTEREST_RATE;
            
            $query = "INSERT INTO savings_accounts (user_id, account_number, interest_rate, opened_date) 
                      VALUES (?, ?, ?, CURDATE())";
            
            $accountId = $this->db->insert($query, [$userId, $accountNumber, $interestRate]);
            
            // Log activity
            $this->dbHelper->logActivity(
                $userId,
                'savings_account_created',
                'savings_account',
                $accountId,
                null,
                ['account_number' => $accountNumber]
            );
            
            // Create notification
            $this->dbHelper->createNotification(
                $userId,
                'Akun Tabungan Dibuka',
                "Akun tabungan Anda telah berhasil dibuka dengan nomor rekening: $accountNumber",
                'success',
                'account_activity'
            );
            
            return [
                'success' => true,
                'message' => 'Akun tabungan berhasil dibuat.',
                'account_id' => $accountId,
                'account_number' => $accountNumber
            ];
            
        } catch (Exception $e) {
            error_log("Create savings account error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Process deposit
     */
    public function deposit($userId, $amount, $paymentMethod = 'transfer', $description = '') {
        try {
            // Get or create savings account
            $account = $this->dbHelper->getSavingsAccountByUserId($userId);
            if (!$account) {
                $createResult = $this->createAccount($userId);
                if (!$createResult['success']) {
                    return $createResult;
                }
                $account = $this->dbHelper->getSavingsAccountByUserId($userId);
            }
            
            // Validate amount
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Jumlah setoran harus lebih dari 0.'];
            }
            
            $this->db->beginTransaction();
            
            // Get current balance
            $currentBalance = $account['balance'];
            $newBalance = $currentBalance + $amount;
            
            // Generate transaction reference
            $transactionRef = $this->dbHelper->generateReferenceNumber('DEP', 'transactions', 'reference_number');
            
            // Record transaction
            $transactionQuery = "INSERT INTO transactions (
                user_id, reference_number, type, amount, description,
                related_account_id, balance_before, balance_after,
                payment_method, transaction_date
            ) VALUES (?, ?, 'savings_deposit', ?, ?, ?, ?, ?, ?, CURDATE())";
            
            $transactionParams = [
                $userId, $transactionRef, $amount, 
                $description ?: 'Setoran tabungan',
                $account['id'], $currentBalance, $newBalance,
                $paymentMethod
            ];
            
            $transactionId = $this->db->insert($transactionQuery, $transactionParams);
            
            // Update account balance
            $updateQuery = "UPDATE savings_accounts SET 
                           balance = ?, last_transaction_date = NOW(), updated_at = NOW()
                           WHERE id = ?";
            
            $this->db->update($updateQuery, [$newBalance, $account['id']]);
            
            // Log activity
            $this->dbHelper->logActivity(
                $userId,
                'savings_deposit',
                'transaction',
                $transactionId,
                ['balance' => $currentBalance],
                ['balance' => $newBalance, 'amount' => $amount]
            );
            
            // Create notification
            $this->dbHelper->createNotification(
                $userId,
                'Setoran Berhasil',
                "Setoran sebesar " . formatRupiah($amount) . " telah berhasil diproses. Saldo Anda sekarang: " . formatRupiah($newBalance),
                'success',
                'account_activity'
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Setoran berhasil diproses.',
                'transaction_id' => $transactionId,
                'new_balance' => $newBalance
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Savings deposit error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Process withdrawal
     * Simpanan wajib bersifat beku selama masih anggota (KOPPUJA RAT 2025).
     */
    public function withdraw($userId, $amount, $description = '') {
        return [
            'success' => false,
            'message' => 'Simpanan wajib tidak dapat diambil selama masih menjadi anggota koperasi. Penarikan hanya diproses saat keluar keanggotaan (oleh pengurus).'
        ];
    }
    
    /**
     * Approve withdrawal (Admin)
     */
    public function approveWithdrawal($transactionId, $adminId, $notes = '') {
        try {
            $transaction = $this->db->selectOne(
                "SELECT * FROM transactions WHERE id = ? AND type = 'savings_withdrawal' AND status = 'pending'",
                [$transactionId]
            );
            
            if (!$transaction) {
                return ['success' => false, 'message' => 'Transaksi tidak ditemukan atau sudah diproses.'];
            }
            
            $this->db->beginTransaction();
            
            // Update transaction status
            $this->db->update(
                "UPDATE transactions SET status = 'completed', processed_by = ? WHERE id = ?",
                [$adminId, $transactionId]
            );
            
            // Log activity
            $this->dbHelper->logActivity(
                $adminId,
                'withdrawal_approved',
                'transaction',
                $transactionId,
                ['status' => 'pending'],
                ['status' => 'completed', 'notes' => $notes]
            );
            
            // Notify user
            $this->dbHelper->createNotification(
                $transaction['user_id'],
                'Penarikan Disetujui',
                "Penarikan sebesar " . formatRupiah($transaction['amount']) . " telah disetujui dan akan segera ditransfer.",
                'success',
                'account_activity'
            );
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Penarikan berhasil disetujui.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Approve withdrawal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Reject withdrawal (Admin)
     */
    public function rejectWithdrawal($transactionId, $adminId, $notes = '') {
        try {
            $transaction = $this->db->selectOne(
                "SELECT t.*, sa.balance FROM transactions t
                 LEFT JOIN savings_accounts sa ON t.related_account_id = sa.id
                 WHERE t.id = ? AND t.type = 'savings_withdrawal' AND t.status = 'pending'",
                [$transactionId]
            );
            
            if (!$transaction) {
                return ['success' => false, 'message' => 'Transaksi tidak ditemukan atau sudah diproses.'];
            }
            
            $this->db->beginTransaction();
            
            // Revert balance (add back the withdrawn amount)
            $newBalance = $transaction['balance'] + $transaction['amount'];
            
            // Update savings account balance
            $this->db->update(
                "UPDATE savings_accounts SET balance = ?, updated_at = NOW() WHERE id = ?",
                [$newBalance, $transaction['related_account_id']]
            );
            
            // Update transaction status
            $this->db->update(
                "UPDATE transactions SET status = 'failed', processed_by = ?, balance_after = ? WHERE id = ?",
                [$adminId, $newBalance, $transactionId]
            );
            
            // Log activity
            $this->dbHelper->logActivity(
                $adminId,
                'withdrawal_rejected',
                'transaction',
                $transactionId,
                ['status' => 'pending', 'balance_before_rejection' => $transaction['balance']],
                ['status' => 'failed', 'balance_after_rejection' => $newBalance, 'notes' => $notes]
            );
            
            // Notify user
            $this->dbHelper->createNotification(
                $transaction['user_id'],
                'Penarikan Ditolak',
                "Penarikan sebesar " . formatRupiah($transaction['amount']) . " telah ditolak. Saldo Anda dikembalikan.",
                'error',
                'account_activity'
            );
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Penarikan berhasil ditolak dan saldo dikembalikan.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Reject withdrawal error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Calculate and credit interest
     */
    public function calculateInterest($userId = null) {
        try {
            $whereClause = $userId ? 'WHERE user_id = ?' : '';
            $params = $userId ? [$userId] : [];
            
            $accounts = $this->db->select(
                "SELECT * FROM savings_accounts $whereClause AND status = 'active'",
                $params
            );
            
            $this->db->beginTransaction();
            $totalInterestCredited = 0;
            
            foreach ($accounts as $account) {
                // Calculate monthly interest
                $monthlyInterestRate = $account['interest_rate'] / 12 / 100;
                $interestAmount = $account['balance'] * $monthlyInterestRate;
                
                if ($interestAmount > 0) {
                    $newBalance = $account['balance'] + $interestAmount;
                    
                    // Generate transaction reference
                    $transactionRef = $this->dbHelper->generateReferenceNumber('INT', 'transactions', 'reference_number');
                    
                    // Record interest transaction
                    $transactionQuery = "INSERT INTO transactions (
                        user_id, reference_number, type, amount, description,
                        related_account_id, balance_before, balance_after,
                        transaction_date
                    ) VALUES (?, ?, 'interest_credit', ?, 'Bunga tabungan bulanan', ?, ?, ?, CURDATE())";
                    
                    $this->db->insert($transactionQuery, [
                        $account['user_id'], $transactionRef, $interestAmount,
                        $account['id'], $account['balance'], $newBalance
                    ]);
                    
                    // Update account balance
                    $this->db->update(
                        "UPDATE savings_accounts SET balance = ?, updated_at = NOW() WHERE id = ?",
                        [$newBalance, $account['id']]
                    );
                    
                    // Create notification
                    $this->dbHelper->createNotification(
                        $account['user_id'],
                        'Bunga Tabungan',
                        "Bunga tabungan sebesar " . formatRupiah($interestAmount) . " telah dikreditkan ke rekening Anda.",
                        'success',
                        'account_activity'
                    );
                    
                    $totalInterestCredited += $interestAmount;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Bunga berhasil dihitung dan dikreditkan.',
                'total_interest' => $totalInterestCredited,
                'accounts_processed' => count($accounts)
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Calculate interest error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Get account statement
     */
    public function getStatement($userId, $startDate = null, $endDate = null, $limit = 50) {
        $account = $this->dbHelper->getSavingsAccountByUserId($userId);
        if (!$account) {
            return [];
        }
        
        $whereClause = 'WHERE related_account_id = ?';
        $params = [$account['id']];
        
        if ($startDate && $endDate) {
            $whereClause .= ' AND transaction_date BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
        }
        
        $query = "SELECT * FROM transactions 
                  $whereClause
                  ORDER BY transaction_date DESC, created_at DESC
                  LIMIT ?";
        
        $params[] = $limit;
        
        return $this->db->select($query, $params);
    }
    
    /**
     * Process withdrawal with bank details
     * Simpanan wajib bersifat beku selama masih anggota (KOPPUJA RAT 2025).
     */
    public function processWithdrawal($userId, $amount, $description = '', $method = 'transfer', $bankDetails = []) {
        return [
            'success' => false,
            'message' => 'Simpanan wajib tidak dapat diambil selama masih menjadi anggota koperasi. Penarikan hanya diproses saat keluar keanggotaan (oleh pengurus).'
        ];
    }
    
    /**
     * Generate account number
     */
    private function generateAccountNumber() {
        do {
            $accountNumber = '7' . date('y') . str_pad(mt_rand(1, 9999999), 7, '0', STR_PAD_LEFT);
            $existing = $this->db->selectOne(
                "SELECT id FROM savings_accounts WHERE account_number = ?",
                [$accountNumber]
            );
        } while ($existing);
        
        return $accountNumber;
    }
    
    /**
     * Get pending withdrawals for admin
     */
    public function getPendingWithdrawals() {
        $query = "SELECT t.*, u.full_name, u.nik, sa.account_number
                  FROM transactions t
                  JOIN users u ON t.user_id = u.id
                  JOIN savings_accounts sa ON t.related_account_id = sa.id
                  WHERE t.type = 'savings_withdrawal' AND t.status = 'pending'
                  ORDER BY t.created_at DESC";
        
        return $this->db->select($query);
    }
    
    /**
     * Get savings statistics
     */
    public function getStatistics() {
        $stats = [];
        
        // Total accounts
        $stats['total_accounts'] = $this->db->selectOne(
            "SELECT COUNT(*) as count FROM savings_accounts WHERE status = 'active'"
        )['count'];
        
        // Total balance
        $stats['total_balance'] = $this->db->selectOne(
            "SELECT SUM(balance) as total FROM savings_accounts WHERE status = 'active'"
        )['total'] ?: 0;
        
        // This month deposits
        $stats['month_deposits'] = $this->db->selectOne(
            "SELECT SUM(amount) as total FROM transactions 
             WHERE type = 'savings_deposit' AND MONTH(transaction_date) = MONTH(CURDATE()) 
             AND YEAR(transaction_date) = YEAR(CURDATE())"
        )['total'] ?: 0;
        
        // This month withdrawals
        $stats['month_withdrawals'] = $this->db->selectOne(
            "SELECT SUM(amount) as total FROM transactions 
             WHERE type = 'savings_withdrawal' AND MONTH(transaction_date) = MONTH(CURDATE()) 
             AND YEAR(transaction_date) = YEAR(CURDATE())"
        )['total'] ?: 0;
        
        // Average balance
        $stats['average_balance'] = $stats['total_accounts'] > 0 ? 
            $stats['total_balance'] / $stats['total_accounts'] : 0;
        
        return $stats;
    }
}

?>