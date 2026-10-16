<?php
/**
 * Loan Management Class
 *
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

class LoanManager {
    private $db;
    private $dbHelper;
    private $ruleEngine;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->dbHelper = new DatabaseHelper();
        $this->ruleEngine = class_exists('RuleEngine') ? new RuleEngine() : null;
    }
    
    /**
     * Rule engine hanya dipakai bila tabel basis aturan tersedia dan
     * kelompok aturan terkait memiliki minimal satu aturan aktif.
     */
    private function rulesAvailable($group) {
        return $this->ruleEngine && $this->ruleEngine->hasActiveRules($group);
    }
    
    /**
     * Kumpulkan fakta nasabah dan pengajuan sebagai masukan rule engine.
     * Fakta pengajuan hanya disertakan bila nominal dan tenor diketahui,
     * sehingga aturan terkait pengajuan dilewati saat pengecekan awal.
     */
    public function buildLoanFacts($user, $loanAmount = null, $termMonths = null) {
        // Lengkapi data user bila dipanggil dengan payload session
        if (empty($user['employment_date']) || !isset($user['monthly_salary']) || !isset($user['member_category'])) {
            $fullUser = !empty($user['id']) ? $this->dbHelper->getUserById($user['id']) : null;
            if ($fullUser) {
                $user = $fullUser;
            }
        }
        
        $salary = (float)($user['monthly_salary'] ?? $user['salary'] ?? 0);
        $savingsAccount = !empty($user['id']) ? $this->dbHelper->getSavingsAccountByUserId($user['id']) : null;
        $simpananWajib = (float)($savingsAccount['balance'] ?? 0);
        $batasKelipatan = getLoanMultiplierForUser($user, $this->dbHelper);
        $plafonMaksimal = getMaxLoanAmount($user, $simpananWajib, $this->dbHelper);
        $potonganHrd = hasPayrollDeduction($user) ? 1 : 0;
        
        $facts = [
            'masa_kerja_bulan'        => $this->calculateEmploymentMonths($user['employment_date'] ?? null),
            'status_karyawan'         => $user['status'] ?? 'active',
            'kategori_anggota'        => $user['member_category'] ?? 'karyawan',
            'potongan_hrd'            => $potonganHrd,
            'gaji_bulanan'            => $salary,
            'simpanan_wajib'          => $simpananWajib,
            'batas_kelipatan'         => $batasKelipatan,
            'plafon_maksimal'         => $plafonMaksimal,
            'punya_pinjaman_aktif'    => $this->dbHelper->getActiveLoanByUserId($user['id']) ? 1 : 0,
            'punya_pengajuan_pending' => $this->hasPendingApplication($user['id']) ? 1 : 0,
            'riwayat_tunggakan'       => $this->countLatePayments($user['id']),
            'pinjaman_lunas'          => $this->countCompletedLoans($user['id']),
            'auto_approve_aktif'      => $this->isAutoApproveEnabled() ? 1 : 0,
        ];
        
        if ($loanAmount !== null && $termMonths !== null && $loanAmount > 0 && $termMonths > 0) {
            $interestRate = $this->dbHelper->getSystemSetting('loan_interest_rate') ?: DEFAULT_LOAN_INTEREST_RATE;
            $installment = calculateInstallment($loanAmount, $interestRate, $termMonths);
            
            $facts['jumlah_pinjaman']     = (float)$loanAmount;
            $facts['tenor_bulan']         = (int)$termMonths;
            $facts['angsuran_bulanan']    = round($installment, 2);
            $facts['kelipatan_simpanan']  = $simpananWajib > 0 ? round($loanAmount / $simpananWajib, 2) : 999;
            $facts['kelipatan_gaji']      = $salary > 0 ? round($loanAmount / $salary, 2) : 0;
            $facts['rasio_angsuran_gaji'] = $salary > 0 ? round($installment / $salary * 100, 2) : 0;
        }
        
        return $facts;
    }
    
    private function calculateEmploymentMonths($employmentDate) {
        if (empty($employmentDate)) {
            return null;
        }
        
        try {
            $start = new DateTime($employmentDate);
            $diff = $start->diff(new DateTime());
            return ($diff->y * 12) + $diff->m;
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function hasPendingApplication($userId) {
        return $this->db->selectOne(
            "SELECT id FROM loan_applications WHERE user_id = ? AND status IN ('pending', 'reviewing')",
            [$userId]
        );
    }
    
    private function countLatePayments($userId) {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total
             FROM loan_schedules ls
             JOIN active_loans al ON ls.loan_id = al.id
             WHERE al.user_id = ?
               AND (ls.status = 'overdue'
                    OR (ls.paid_date IS NOT NULL AND ls.paid_date > ls.due_date)
                    OR (ls.status != 'paid' AND ls.due_date < CURDATE()))",
            [$userId]
        );
        
        return (int)($row['total'] ?? 0);
    }
    
    private function countCompletedLoans($userId) {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total FROM active_loans WHERE user_id = ? AND status = 'completed'",
            [$userId]
        );
        
        return (int)($row['total'] ?? 0);
    }
    
    private function isAutoApproveEnabled() {
        $enabled = $this->dbHelper->getSystemSetting('loan_auto_approve_enabled');
        return $enabled === null ? LOAN_AUTO_APPROVE_ENABLED : (bool)$enabled;
    }
    
    /**
     * Submit loan application
     */
    public function submitApplication($userId, $loanAmount, $termMonths, $purpose, $description = '', $documents = []) {
        try {
            // Get user data
            $user = $this->dbHelper->getUserById($userId);
            if (!$user) {
                return ['success' => false, 'message' => 'Data pengguna tidak ditemukan.'];
            }
            
            // Check eligibility (rule-based)
            $eligibility = $this->checkEligibility($user, $loanAmount, $termMonths);
            if (!$eligibility['eligible']) {
                if (!empty($eligibility['evaluation'])) {
                    $this->ruleEngine->logEvaluation($eligibility['evaluation'], $userId, 'loan_application', null);
                }
                
                return [
                    'success' => false,
                    'message' => $eligibility['reason'],
                    'rule_code' => $eligibility['rule_code'] ?? null
                ];
            }
            
            // Batas plafon dicek di sini hanya bila basis aturan belum aktif
            if (!$this->rulesAvailable(RuleEngine::GROUP_ELIGIBILITY)) {
                $maxLoan = getMaxLoanAmount($user, null, $this->dbHelper);
                if ($loanAmount > $maxLoan) {
                    $multiplier = getLoanMultiplierForUser($user, $this->dbHelper);
                    return [
                        'success' => false,
                        'message' => "Jumlah pinjaman melebihi batas maksimal " . formatRupiah($maxLoan) . " ({$multiplier}x simpanan wajib)."
                    ];
                }

                $maxTerm = getMaxLoanTerm($this->dbHelper);
                if ($termMonths > $maxTerm) {
                    return [
                        'success' => false,
                        'message' => "Jangka waktu pinjaman maksimal {$maxTerm} bulan."
                    ];
                }
            }
            
            // Get interest rate
            $interestRate = $this->dbHelper->getSystemSetting('loan_interest_rate') ?: DEFAULT_LOAN_INTEREST_RATE;
            
            // Calculate loan details
            $monthlyInstallment = calculateInstallment($loanAmount, $interestRate, $termMonths);
            $totalPayment = $monthlyInstallment * $termMonths;
            
            // Generate application number
            $applicationNumber = $this->dbHelper->generateReferenceNumber('LOAN', 'loan_applications', 'application_number');
            
            // Determine whether this application qualifies for automatic approval
            $approval = $this->evaluateApproval($user, $loanAmount, $termMonths);
            $autoApprove = $approval['auto_approve'];
            $initialStatus = $autoApprove ? 'approved' : 'pending';
            $decisionNote = ($approval['rule_code'] ? '[' . $approval['rule_code'] . '] ' : '') . $approval['message'];
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Insert loan application
            if ($autoApprove) {
                $query = "INSERT INTO loan_applications (
                    user_id, application_number, loan_amount, interest_rate, 
                    loan_term_months, monthly_installment, total_payment, 
                    purpose, description, documents, status,
                    reviewed_at, approved_at, review_notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)";
                
                $params = [
                    $userId, $applicationNumber, $loanAmount, $interestRate,
                    $termMonths, $monthlyInstallment, $totalPayment,
                    $purpose, $description, json_encode($documents), $initialStatus,
                    $decisionNote
                ];
            } else {
                $query = "INSERT INTO loan_applications (
                    user_id, application_number, loan_amount, interest_rate, 
                    loan_term_months, monthly_installment, total_payment, 
                    purpose, description, documents, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    $userId, $applicationNumber, $loanAmount, $interestRate,
                    $termMonths, $monthlyInstallment, $totalPayment,
                    $purpose, $description, json_encode($documents), $initialStatus
                ];
            }
            
            $applicationId = $this->db->insert($query, $params);
            
            // Log activity
            $this->dbHelper->logActivity(
                $userId, 
                $autoApprove ? 'loan_application_auto_approved' : 'loan_application_submitted', 
                'loan_application', 
                $applicationId,
                null,
                [
                    'application_number' => $applicationNumber,
                    'amount' => $loanAmount,
                    'term' => $termMonths,
                    'auto_approved' => $autoApprove,
                    'rule_code' => $approval['rule_code']
                ]
            );
            
            // Simpan jejak penalaran agar keputusan dapat ditelusuri
            if (!empty($eligibility['evaluation'])) {
                $this->ruleEngine->logEvaluation($eligibility['evaluation'], $userId, 'loan_application', $applicationId);
            }
            if (!empty($approval['evaluation'])) {
                $this->ruleEngine->logEvaluation($approval['evaluation'], $userId, 'loan_application', $applicationId);
            }
            
            if ($autoApprove) {
                // Build application payload for active loan creation
                $application = [
                    'id' => $applicationId,
                    'user_id' => $userId,
                    'loan_amount' => $loanAmount,
                    'monthly_installment' => $monthlyInstallment,
                    'interest_rate' => $interestRate,
                    'loan_term_months' => $termMonths
                ];
                
                // Create active loan immediately
                $this->createActiveLoan($application);
                
                // Notify user about automatic approval
                $this->dbHelper->createNotification(
                    $userId,
                    'Pinjaman Disetujui Otomatis',
                    "Selamat! Pengajuan pinjaman Anda sebesar " . formatRupiah($loanAmount) . " telah disetujui otomatis oleh sistem dan akan segera dicairkan. Nomor aplikasi: " . $applicationNumber,
                    'success',
                    'loan_approval'
                );
            } else {
                // Create notification
                $this->dbHelper->createNotification(
                    $userId,
                    'Pengajuan Pinjaman Diterima',
                    "Pengajuan pinjaman Anda sebesar " . formatRupiah($loanAmount) . " telah diterima dan sedang diproses. Nomor aplikasi: " . $applicationNumber,
                    'info',
                    'loan_approval'
                );
                
                // Notify admin
                $adminUsers = $this->db->select("SELECT id FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active'");
                foreach ($adminUsers as $admin) {
                    $this->dbHelper->createNotification(
                        $admin['id'],
                        'Pengajuan Pinjaman Baru',
                        "Pengajuan pinjaman baru dari " . $user['full_name'] . " sebesar " . formatRupiah($loanAmount) . " perlu direview.",
                        'warning',
                        'loan_approval',
                        'admin/loan-detail.php?id=' . $applicationId
                    );
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => $autoApprove
                    ? 'Pengajuan pinjaman Anda telah disetujui otomatis dan akan segera dicairkan.'
                    : 'Pengajuan pinjaman berhasil disubmit.',
                'application_id' => $applicationId,
                'application_number' => $applicationNumber,
                'auto_approved' => $autoApprove,
                'rule_code' => $approval['rule_code']
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Loan application error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Check loan eligibility.
     * Aturan diambil dari basis pengetahuan (tabel rules); bila belum tersedia
     * sistem memakai aturan bawaan yang tertanam di kode.
     */
    public function checkEligibility($user, $loanAmount = null, $termMonths = null) {
        if (!$this->rulesAvailable(RuleEngine::GROUP_ELIGIBILITY)) {
            return $this->checkEligibilityLegacy($user);
        }
        
        $facts = $this->buildLoanFacts($user, $loanAmount, $termMonths);
        $evaluation = $this->ruleEngine->evaluate(
            RuleEngine::GROUP_ELIGIBILITY,
            $facts,
            'eligible',
            'Memenuhi seluruh syarat pengajuan.'
        );
        
        return [
            'eligible'   => $evaluation['decision'] !== 'reject',
            'reason'     => $evaluation['decision'] === 'reject' ? $evaluation['message'] : '',
            'rule_code'  => $evaluation['rule_code'],
            'evaluation' => $evaluation
        ];
    }
    
    /**
     * Aturan kelayakan bawaan (dipakai bila basis aturan belum dimigrasikan)
     */
    private function checkEligibilityLegacy($user) {
        // Check employment duration
        $employmentDate = new DateTime($user['employment_date']);
        $now = new DateTime();
        $employmentMonths = $employmentDate->diff($now)->m + ($employmentDate->diff($now)->y * 12);
        
        $minEmploymentMonths = $this->dbHelper->getSystemSetting('min_employment_months') ?: MIN_EMPLOYMENT_MONTHS;
        
        if ($employmentMonths < $minEmploymentMonths) {
            return [
                'eligible' => false,
                'reason' => "Masa kerja minimal $minEmploymentMonths bulan. Masa kerja Anda: $employmentMonths bulan."
            ];
        }
        
        // Check active loan
        $activeLoan = $this->dbHelper->getActiveLoanByUserId($user['id']);
        if ($activeLoan) {
            return [
                'eligible' => false,
                'reason' => 'Anda sudah memiliki pinjaman aktif. Selesaikan pinjaman yang ada terlebih dahulu.'
            ];
        }
        
        // Check pending applications
        $pendingApplication = $this->db->selectOne(
            "SELECT * FROM loan_applications WHERE user_id = ? AND status IN ('pending', 'reviewing')",
            [$user['id']]
        );
        
        if ($pendingApplication) {
            return [
                'eligible' => false,
                'reason' => 'Anda memiliki pengajuan pinjaman yang sedang diproses.'
            ];
        }
        
        return ['eligible' => true];
    }
    
    /**
     * Jalankan aturan keputusan persetujuan untuk sebuah pengajuan.
     * Mengembalikan kesimpulan beserta aturan yang memicunya.
     */
    public function evaluateApproval($user, $loanAmount, $termMonths) {
        if (!$this->rulesAvailable(RuleEngine::GROUP_DECISION)) {
            $autoApprove = $this->shouldAutoApproveLegacy($loanAmount, $termMonths);
            
            return [
                'auto_approve' => $autoApprove,
                'decision'     => $autoApprove ? 'approve' : 'manual_review',
                'rule_code'    => null,
                'message'      => $autoApprove
                    ? 'Disetujui otomatis oleh sistem (memenuhi kriteria auto-approve).'
                    : 'Pengajuan diteruskan ke review admin.',
                'evaluation'   => null
            ];
        }
        
        $facts = $this->buildLoanFacts($user, $loanAmount, $termMonths);
        $evaluation = $this->ruleEngine->evaluate(
            RuleEngine::GROUP_DECISION,
            $facts,
            'manual_review',
            'Tidak ada aturan persetujuan otomatis yang terpenuhi, pengajuan diteruskan ke review admin.'
        );
        
        return [
            'auto_approve' => $evaluation['decision'] === 'approve',
            'decision'     => $evaluation['decision'],
            'rule_code'    => $evaluation['rule_code'],
            'message'      => $evaluation['message'],
            'evaluation'   => $evaluation
        ];
    }
    
    /**
     * Determine whether a loan application qualifies for automatic approval.
     * Dipertahankan untuk kompatibilitas; keputusan diambil lewat rule engine
     * bila basis aturan tersedia.
     */
    public function shouldAutoApprove($loanAmount, $termMonths, $user = null) {
        if ($user !== null && $this->rulesAvailable(RuleEngine::GROUP_DECISION)) {
            $result = $this->evaluateApproval($user, $loanAmount, $termMonths);
            return $result['auto_approve'];
        }
        
        return $this->shouldAutoApproveLegacy($loanAmount, $termMonths);
    }
    
    /**
     * Kriteria auto-approve bawaan (dipakai bila basis aturan belum dimigrasikan)
     */
    private function shouldAutoApproveLegacy($loanAmount, $termMonths) {
        // Global toggle
        $enabled = $this->dbHelper->getSystemSetting('loan_auto_approve_enabled');
        if ($enabled === null) {
            $enabled = LOAN_AUTO_APPROVE_ENABLED;
        }
        if (!$enabled) {
            return false;
        }
        
        // Maximum amount eligible for auto-approval
        $maxAmount = $this->dbHelper->getSystemSetting('loan_auto_approve_max_amount');
        if ($maxAmount === null || $maxAmount <= 0) {
            $maxAmount = LOAN_AUTO_APPROVE_MAX_AMOUNT;
        }
        if ($loanAmount > $maxAmount) {
            return false;
        }
        
        // Maximum term (months) eligible for auto-approval
        $maxTerm = $this->dbHelper->getSystemSetting('loan_auto_approve_max_term_months');
        if ($maxTerm === null || $maxTerm <= 0) {
            $maxTerm = LOAN_AUTO_APPROVE_MAX_TERM_MONTHS;
        }
        if ($termMonths > $maxTerm) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Review loan application (Admin)
     */
    public function reviewApplication($applicationId, $adminId, $status, $notes = '') {
        try {
            $application = $this->db->selectOne(
                "SELECT * FROM loan_applications WHERE id = ?",
                [$applicationId]
            );
            
            if (!$application) {
                return ['success' => false, 'message' => 'Pengajuan tidak ditemukan.'];
            }
            
            $this->db->beginTransaction();
            
            if ($status === 'approved') {
                // Approve application
                $query = "UPDATE loan_applications SET 
                         status = 'approved', reviewed_by = ?, reviewed_at = NOW(), 
                         approved_by = ?, approved_at = NOW(), review_notes = ?
                         WHERE id = ?";
                
                $this->db->update($query, [$adminId, $adminId, $notes, $applicationId]);
                
                // Create active loan
                $this->createActiveLoan($application);
                
                // Notify user
                $this->dbHelper->createNotification(
                    $application['user_id'],
                    'Pinjaman Disetujui',
                    "Pinjaman Anda sebesar " . formatRupiah($application['loan_amount']) . " telah disetujui dan akan segera dicairkan.",
                    'success',
                    'loan_approval'
                );
                
            } else if ($status === 'rejected') {
                // Reject application
                $query = "UPDATE loan_applications SET 
                         status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), 
                         rejected_reason = ?
                         WHERE id = ?";
                
                $this->db->update($query, [$adminId, $notes, $applicationId]);
                
                // Notify user
                $this->dbHelper->createNotification(
                    $application['user_id'],
                    'Pinjaman Ditolak',
                    "Pinjaman Anda sebesar " . formatRupiah($application['loan_amount']) . " ditolak. Alasan: " . $notes,
                    'error',
                    'loan_approval'
                );
            }
            
            // Log activity
            $this->dbHelper->logActivity(
                $adminId,
                "loan_application_$status",
                'loan_application',
                $applicationId,
                ['status' => $application['status']],
                ['status' => $status, 'notes' => $notes]
            );
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Status pengajuan berhasil diupdate.'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Loan review error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Create active loan from approved application
     */
    private function createActiveLoan($application) {
        // Generate loan number
        $loanNumber = $this->dbHelper->generateReferenceNumber('ACT', 'active_loans', 'loan_number');
        
        // Calculate start date (next month)
        $startDate = date('Y-m-01', strtotime('+1 month'));
        $nextDueDate = date('Y-m-d', strtotime($startDate . ' +1 month'));
        
        // Insert active loan
        $query = "INSERT INTO active_loans (
            user_id, application_id, loan_number, original_amount, 
            remaining_balance, monthly_installment, interest_rate, 
            loan_term_months, remaining_months, start_date, next_due_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $application['user_id'], $application['id'], $loanNumber,
            $application['loan_amount'], $application['loan_amount'],
            $application['monthly_installment'], $application['interest_rate'],
            $application['loan_term_months'], $application['loan_term_months'],
            $startDate, $nextDueDate
        ];
        
        $loanId = $this->db->insert($query, $params);
        
        // Generate payment schedule
        $this->generatePaymentSchedule($loanId, $application);
        
        return $loanId;
    }
    
    /**
     * Generate payment schedule for active loan
     */
    private function generatePaymentSchedule($loanId, $application) {
        $startDate = new DateTime(date('Y-m-01', strtotime('+1 month')));
        $principalAmount = $application['loan_amount'] / $application['loan_term_months'];
        $interestAmount = $application['loan_amount'] * $application['interest_rate'] / 100;
        
        for ($i = 1; $i <= $application['loan_term_months']; $i++) {
            $dueDate = clone $startDate;
            $dueDate->add(new DateInterval("P{$i}M"));
            
            $query = "INSERT INTO loan_schedules (
                loan_id, installment_number, due_date, 
                principal_amount, interest_amount, total_amount
            ) VALUES (?, ?, ?, ?, ?, ?)";
            
            $params = [
                $loanId, $i, $dueDate->format('Y-m-d'),
                $principalAmount, $interestAmount, $application['monthly_installment']
            ];
            
            $this->db->insert($query, $params);
        }
    }
    
    /**
     * Process loan payment
     */
    public function processPayment($scheduleId, $amount, $paymentMethod = 'transfer') {
        try {
            $schedule = $this->db->selectOne(
                "SELECT ls.*, al.user_id, al.remaining_balance, al.monthly_installment 
                 FROM loan_schedules ls 
                 JOIN active_loans al ON ls.loan_id = al.id 
                 WHERE ls.id = ?",
                [$scheduleId]
            );
            
            if (!$schedule) {
                return ['success' => false, 'message' => 'Jadwal pembayaran tidak ditemukan.'];
            }
            
            if ($schedule['status'] === 'paid') {
                return ['success' => false, 'message' => 'Angsuran ini sudah dibayar.'];
            }
            
            $this->db->beginTransaction();
            
            // Calculate late fee if overdue
            $lateFee = 0;
            if (strtotime($schedule['due_date']) < strtotime(date('Y-m-d'))) {
                $daysLate = (strtotime(date('Y-m-d')) - strtotime($schedule['due_date'])) / (60 * 60 * 24);
                $lateFeeRate = $this->dbHelper->getSystemSetting('late_fee_percentage') ?: LATE_FEE_PERCENTAGE;
                $lateFee = $schedule['total_amount'] * $lateFeeRate / 100 * $daysLate;
            }
            
            $totalAmount = $amount + $lateFee;
            
            // Generate transaction reference
            $transactionRef = $this->dbHelper->generateReferenceNumber('PAY', 'transactions', 'reference_number');
            
            // Record transaction
            $transactionQuery = "INSERT INTO transactions (
                user_id, reference_number, type, amount, description,
                related_loan_id, payment_method, transaction_date
            ) VALUES (?, ?, 'loan_payment', ?, ?, ?, ?, CURDATE())";
            
            $transactionParams = [
                $schedule['user_id'], $transactionRef, $totalAmount,
                "Pembayaran angsuran ke-{$schedule['installment_number']}",
                $schedule['loan_id'], $paymentMethod
            ];
            
            $transactionId = $this->db->insert($transactionQuery, $transactionParams);
            
            // Update schedule
            $scheduleQuery = "UPDATE loan_schedules SET 
                             paid_amount = ?, paid_date = CURDATE(), late_fee = ?, 
                             status = 'paid', payment_transaction_id = ?
                             WHERE id = ?";
            
            $this->db->update($scheduleQuery, [$amount, $lateFee, $transactionId, $scheduleId]);
            
            // Update active loan
            $newBalance = $schedule['remaining_balance'] - $schedule['principal_amount'];
            $remainingMonths = $schedule['remaining_months'] - 1;
            
            if ($newBalance <= 0 || $remainingMonths <= 0) {
                // Loan completed
                $loanQuery = "UPDATE active_loans SET 
                             remaining_balance = 0, remaining_months = 0, 
                             status = 'completed', total_paid = total_paid + ?
                             WHERE id = ?";
                $this->db->update($loanQuery, [$totalAmount, $schedule['loan_id']]);
                
                // Notify completion
                $this->dbHelper->createNotification(
                    $schedule['user_id'],
                    'Pinjaman Lunas',
                    'Selamat! Pinjaman Anda telah lunas.',
                    'success',
                    'loan_approval'
                );
            } else {
                $loanQuery = "UPDATE active_loans SET 
                             remaining_balance = ?, remaining_months = ?, 
                             total_paid = total_paid + ?
                             WHERE id = ?";
                $this->db->update($loanQuery, [$newBalance, $remainingMonths, $totalAmount, $schedule['loan_id']]);
            }
            
            // Log activity
            $this->dbHelper->logActivity(
                $schedule['user_id'],
                'loan_payment',
                'loan_payment',
                $transactionId,
                null,
                ['amount' => $totalAmount, 'schedule_id' => $scheduleId]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Pembayaran berhasil diproses.',
                'transaction_id' => $transactionId,
                'late_fee' => $lateFee
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Loan payment error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }
    
    /**
     * Get loan applications for admin
     */
    public function getApplications($status = '', $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $whereClause = '';
        $params = [];
        
        if ($status) {
            $whereClause = 'WHERE la.status = ?';
            $params[] = $status;
        }
        
        $query = "SELECT la.*, u.full_name, u.nik, u.position, u.division_id, d.name as division_name
                  FROM loan_applications la
                  JOIN users u ON la.user_id = u.id
                  LEFT JOIN divisions d ON u.division_id = d.id
                  $whereClause
                  ORDER BY la.created_at DESC
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->db->select($query, $params);
    }
    
    /**
     * Get active loans
     */
    public function getActiveLoans($userId = null) {
        $whereClause = '';
        $params = [];
        
        if ($userId) {
            $whereClause = 'WHERE al.user_id = ?';
            $params[] = $userId;
        }
        
        $query = "SELECT al.*, u.full_name, u.nik
                  FROM active_loans al
                  JOIN users u ON al.user_id = u.id
                  $whereClause
                  ORDER BY al.created_at DESC";
        
        return $this->db->select($query, $params);
    }
    
    /**
     * Get overdue payments
     */
    public function getOverduePayments() {
        $query = "SELECT ls.*, al.user_id, u.full_name, u.nik, u.phone
                  FROM loan_schedules ls
                  JOIN active_loans al ON ls.loan_id = al.id
                  JOIN users u ON al.user_id = u.id
                  WHERE ls.due_date < CURDATE() AND ls.status != 'paid'
                  ORDER BY ls.due_date ASC";
        
        return $this->db->select($query);
    }
}

?>