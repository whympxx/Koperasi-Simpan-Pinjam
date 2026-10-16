<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$loanManager = new LoanManager();

$error = '';
$success = '';

// Get active loan
$activeLoan = $dbHelper->getActiveLoanByUserId($user['id']);

if (!$activeLoan) {
    // Redirect to loans page with error message
    $_SESSION['error_message'] = 'Anda tidak memiliki pinjaman aktif.';
    header('Location: loans.php');
    exit;
}

// Get specific schedule if provided
$scheduleId = $_GET['schedule_id'] ?? null;
$selectedSchedule = null;

if ($scheduleId) {
    $selectedSchedule = $db->selectOne(
        "SELECT * FROM loan_schedules WHERE id = ? AND loan_id = ?",
        [$scheduleId, $activeLoan['id']]
    );
    
    if (!$selectedSchedule) {
        $_SESSION['error_message'] = 'Jadwal pembayaran tidak ditemukan.';
        header('Location: loans.php');
        exit;
    }
    
    if ($selectedSchedule['status'] === 'paid') {
        $_SESSION['error_message'] = 'Jadwal pembayaran ini sudah dibayar.';
        header('Location: loans.php');
        exit;
    }
}

// Get overdue and upcoming payments
$overduePayments = $db->select(
    "SELECT * FROM loan_schedules 
     WHERE loan_id = ? AND due_date < CURDATE() AND status != 'paid'
     ORDER BY due_date ASC",
    [$activeLoan['id']]
);

$upcomingPayments = $db->select(
    "SELECT * FROM loan_schedules 
     WHERE loan_id = ? AND due_date >= CURDATE() AND status != 'paid'
     ORDER BY due_date ASC
     LIMIT 3",
    [$activeLoan['id']]
);

// Handle payment form submission
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $paymentScheduleId = (int)SecurityHelper::sanitize($_POST['schedule_id'] ?? '');
        $paymentAmount = (float)str_replace(['.', ','], ['', '.'], SecurityHelper::sanitize($_POST['payment_amount'] ?? ''));
        $paymentMethod = SecurityHelper::sanitize($_POST['payment_method'] ?? 'transfer');
        
        // Validate payment
        if (empty($paymentScheduleId)) {
            $error = 'Pilih jadwal pembayaran yang akan dibayar.';
        } elseif (empty($paymentAmount) || $paymentAmount <= 0) {
            $error = 'Jumlah pembayaran harus diisi dan lebih dari 0.';
        } else {
            // Process payment
            $result = $loanManager->processPayment($paymentScheduleId, $paymentAmount, $paymentMethod);
            
            if ($result['success']) {
                $success = $result['message'];
                if ($result['late_fee'] > 0) {
                    $success .= ' (Termasuk denda keterlambatan: ' . formatRupiah($result['late_fee']) . ')';
                }
                
                // Refresh data
                $activeLoan = $dbHelper->getActiveLoanByUserId($user['id']);
                $overduePayments = $db->select(
                    "SELECT * FROM loan_schedules 
                     WHERE loan_id = ? AND due_date < CURDATE() AND status != 'paid'
                     ORDER BY due_date ASC",
                    [$activeLoan['id'] ?? 0]
                );
                $upcomingPayments = $db->select(
                    "SELECT * FROM loan_schedules 
                     WHERE loan_id = ? AND due_date >= CURDATE() AND status != 'paid'
                     ORDER BY due_date ASC
                     LIMIT 3",
                    [$activeLoan['id'] ?? 0]
                );
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Pembayaran Pinjaman - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <style>
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method-card:hover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .payment-method-card.selected {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.1);
        }
        
        .schedule-payment-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .schedule-payment-card:hover {
            border-color: #28a745;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.2);
        }
        
        .schedule-payment-card.selected {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.05);
        }
        
        .overdue-card {
            border-left: 4px solid #dc3545;
            background-color: rgba(220, 53, 69, 0.05);
        }
        
        .upcoming-card {
            border-left: 4px solid #ffc107;
        }
        
        .payment-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-building"></i>
                <strong><?php echo APP_NAME; ?></strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="savings.php">
                            <i class="fas fa-piggy-bank me-1"></i> Tabungan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="loans.php">
                            <i class="fas fa-hand-holding-usd me-1"></i> Pinjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transaksi
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo $user['name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profil</a></li>
                            <li><a class="dropdown-item" href="change-password.php"><i class="fas fa-key me-2"></i> Ubah Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <?php echo generateBreadcrumb([
            'Dashboard' => 'dashboard.php',
            'Pinjaman' => 'loans.php',
            'Pembayaran' => ''
        ]); ?>
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center">
                    <i class="fas fa-credit-card text-primary me-3 fa-2x"></i>
                    <div>
                        <h2 class="mb-0">Pembayaran Pinjaman</h2>
                        <p class="text-muted mb-0">Bayar angsuran pinjaman Anda dengan mudah</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="mt-3">
                    <a href="loans.php" class="btn btn-success btn-sm">Kembali ke Pinjaman</a>
                    <a href="transactions.php" class="btn btn-outline-success btn-sm">Lihat Riwayat</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Payment Form -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Form Pembayaran
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="paymentForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                            
                            <!-- Select Payment Schedule -->
                            <div class="mb-4">
                                <h6 class="mb-3">Pilih Jadwal Pembayaran</h6>
                                
                                <?php if (empty($overduePayments) && empty($upcomingPayments)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h5>Semua Pembayaran Up-to-Date!</h5>
                                        <p class="text-muted">Tidak ada pembayaran yang perlu dilakukan saat ini.</p>
                                        <a href="loans.php" class="btn btn-primary">
                                            <i class="fas fa-arrow-left me-1"></i>
                                            Kembali ke Pinjaman
                                        </a>
                                    </div>
                                <?php else: ?>
                                
                                <?php if (!empty($overduePayments)): ?>
                                    <div class="mb-3">
                                        <h6 class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Pembayaran Terlambat (<?php echo count($overduePayments); ?>)
                                        </h6>
                        <?php foreach ($overduePayments as $schedule): ?>
                                            <?php 
                                            $daysLate = abs(getDaysUntilDue($schedule['due_date']));
                                            $lateFeeRate = LATE_FEE_PERCENTAGE;
                                            $lateFee = ($daysLate > 0) ? $schedule['total_amount'] * $lateFeeRate / 100 * $daysLate : 0;
                                            $totalAmount = $schedule['total_amount'] + $lateFee;
                                            ?>
                                            <div class="schedule-payment-card overdue-card mb-2 p-3 <?php echo ($selectedSchedule && $selectedSchedule['id'] == $schedule['id']) ? 'selected' : ''; ?>" 
                                                 onclick="selectSchedule(<?php echo $schedule['id']; ?>, <?php echo $totalAmount; ?>)">
                                                <div class="row align-items-center">
                                                    <div class="col-md-1">
                                                        <input type="radio" name="schedule_id" value="<?php echo $schedule['id']; ?>" 
                                                               <?php echo ($selectedSchedule && $selectedSchedule['id'] == $schedule['id']) ? 'checked' : ''; ?>>
                                                    </div>
                                                    <div class="col-md-7">
                                                        <strong>Angsuran ke-<?php echo $schedule['installment_number']; ?></strong>
                                                        <br><small class="text-muted">Jatuh tempo: <?php echo formatDateIndo($schedule['due_date']); ?></small>
                                                        <br><small class="text-danger">Terlambat <?php echo $daysLate; ?> hari</small>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <div class="fw-bold"><?php echo formatRupiah($schedule['total_amount']); ?></div>
                                                        <div class="text-danger">Denda: <?php echo formatRupiah($lateFee); ?></div>
                                                        <div class="fw-bold text-danger">Total: <?php echo formatRupiah($totalAmount); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($upcomingPayments)): ?>
                                    <div class="mb-3">
                                        <h6 class="text-warning">
                                            <i class="fas fa-clock me-1"></i>
                                            Pembayaran Mendatang
                                        </h6>
                                        <?php foreach ($upcomingPayments as $schedule): ?>
                                            <div class="schedule-payment-card upcoming-card mb-2 p-3 <?php echo ($selectedSchedule && $selectedSchedule['id'] == $schedule['id']) ? 'selected' : ''; ?>" 
                                                 onclick="selectSchedule(<?php echo $schedule['id']; ?>, <?php echo $schedule['total_amount']; ?>)">
                                                <div class="row align-items-center">
                                                    <div class="col-md-1">
                                                        <input type="radio" name="schedule_id" value="<?php echo $schedule['id']; ?>" 
                                                               <?php echo ($selectedSchedule && $selectedSchedule['id'] == $schedule['id']) ? 'checked' : ''; ?>>
                                                    </div>
                                                    <div class="col-md-7">
                                                        <strong>Angsuran ke-<?php echo $schedule['installment_number']; ?></strong>
                                                        <br><small class="text-muted">Jatuh tempo: <?php echo formatDateIndo($schedule['due_date']); ?></small>
                                                        <?php 
                                                        $daysUntil = getDaysUntilDue($schedule['due_date']);
                                                        if ($daysUntil > 0) {
                                                            echo '<br><small class="text-success">' . $daysUntil . ' hari lagi</small>';
                                                        } elseif ($daysUntil == 0) {
                                                            echo '<br><small class="text-warning">Jatuh tempo hari ini</small>';
                                                        }
                                                        ?>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <div class="fw-bold"><?php echo formatRupiah($schedule['total_amount']); ?></div>
                                                        <small class="text-muted">Pokok: <?php echo formatRupiah($schedule['principal_amount']); ?></small>
                                                        <br><small class="text-muted">Bunga: <?php echo formatRupiah($schedule['interest_amount']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; // End of payment availability check ?>
                            </div>
                            
                            <?php if (!empty($overduePayments) || !empty($upcomingPayments)): ?>
                            <!-- Payment Amount -->
                            <div class="mb-3">
                                <label class="form-label">Jumlah Pembayaran <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" name="payment_amount" id="payment_amount" required readonly>
                                </div>
                                <div class="form-text">Jumlah akan otomatis terisi sesuai jadwal yang dipilih</div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="mb-4">
                                <label class="form-label">Metode Pembayaran <span class="text-danger">*</span></label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="payment-method-card selected p-3" onclick="selectPaymentMethod('transfer')">
                                            <input type="radio" name="payment_method" value="transfer" checked hidden>
                                            <div class="text-center">
                                                <i class="fas fa-university fa-2x text-primary mb-2"></i>
                                                <h6>Transfer Bank</h6>
                                                <small class="text-muted">Transfer ke rekening perusahaan</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="payment-method-card p-3" onclick="selectPaymentMethod('payroll_deduction')">
                                            <input type="radio" name="payment_method" value="payroll_deduction" hidden>
                                            <div class="text-center">
                                                <i class="fas fa-cut fa-2x text-success mb-2"></i>
                                                <h6>Potong Gaji</h6>
                                                <small class="text-muted">Dipotong dari gaji bulanan</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bank Transfer Info -->
                            <div id="transfer-info" class="mb-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6><i class="fas fa-info-circle text-primary me-2"></i>Informasi Transfer</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <strong>Bank BCA</strong><br>
                                                <span>No. Rek: 1234567890</span><br>
                                                <span>A/N: PT. Puninar Jaya Logistik</span>
                                            </div>
                                            <div class="col-md-6">
                                                <strong>Bank Mandiri</strong><br>
                                                <span>No. Rek: 0987654321</span><br>
                                                <span>A/N: PT. Puninar Jaya Logistik</span>
                                            </div>
                                        </div>
                                        <hr>
                                        <small class="text-muted">
                                            <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                            Konfirmasi pembayaran akan diverifikasi dalam 1x24 jam kerja
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="loans.php" class="btn btn-outline-secondary me-md-2">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Kembali
                                </a>
                                <button type="submit" class="btn btn-primary" id="submit-btn" disabled>
                                    <i class="fas fa-credit-card me-1" id="submit-icon"></i>
                                    <span id="submit-text">Proses Pembayaran</span>
                                </button>
                            </div>
                        </form>
                        <?php endif; // End of form display check ?>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="col-lg-4">
                <div class="payment-summary mb-4">
                    <h5 class="mb-3">
                        <i class="fas fa-file-invoice me-2"></i>
                        Ringkasan Pembayaran
                    </h5>
                    
                    <div id="payment-summary-content">
                        <div class="text-center text-white-50 py-4">
                            <i class="fas fa-hand-pointer fa-2x mb-2"></i>
                            <p>Pilih jadwal pembayaran untuk melihat ringkasan</p>
                        </div>
                    </div>
                </div>

                <!-- Loan Info -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Informasi Pinjaman
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <small class="text-muted">Nomor Pinjaman:</small><br>
                            <strong><?php echo $activeLoan['loan_number']; ?></strong>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Pinjaman Awal:</small><br>
                            <strong><?php echo formatRupiah($activeLoan['original_amount']); ?></strong>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Sisa Pinjaman:</small><br>
                            <strong class="text-danger"><?php echo formatRupiah($activeLoan['remaining_balance']); ?></strong>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Sisa Cicilan:</small><br>
                            <strong><?php echo $activeLoan['remaining_months']; ?> dari <?php echo $activeLoan['loan_term_months']; ?> bulan</strong>
                        </div>
                        <div class="mb-2">
                            <small class="text-muted">Bunga per Bulan:</small><br>
                            <strong><?php echo $activeLoan['interest_rate']; ?>%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Format number with thousand separator
        function formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        }
        
        // Format currency
        function formatCurrency(num) {
            return 'Rp ' + formatNumber(num);
        }
        
        // Select payment schedule
        function selectSchedule(scheduleId, amount) {
            // Update radio button
            const radios = document.querySelectorAll('input[name="schedule_id"]');
            radios.forEach(radio => {
                radio.checked = radio.value == scheduleId;
            });
            
            // Update selected cards
            const cards = document.querySelectorAll('.schedule-payment-card');
            cards.forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Update payment amount
            document.getElementById('payment_amount').value = formatNumber(amount);
            
            // Update summary
            updatePaymentSummary(scheduleId, amount);
            
            // Enable submit button
            document.getElementById('submit-btn').disabled = false;
        }
        
        // Select payment method
        function selectPaymentMethod(method) {
            // Update radio button
            const radios = document.querySelectorAll('input[name="payment_method"]');
            radios.forEach(radio => {
                radio.checked = radio.value == method;
            });
            
            // Update selected cards
            const cards = document.querySelectorAll('.payment-method-card');
            cards.forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            
            // Show/hide transfer info
            const transferInfo = document.getElementById('transfer-info');
            if (transferInfo) {
                transferInfo.style.display = method === 'transfer' ? 'block' : 'none';
            }
        }
        
        // Update payment summary
        function updatePaymentSummary(scheduleId, amount) {
            const summaryContent = document.getElementById('payment-summary-content');
            
            summaryContent.innerHTML = `
                <div class="mb-3">
                    <div class="d-flex justify-content-between">
                        <span class="text-white-50">Angsuran ke:</span>
                        <span class="fw-bold">#${scheduleId}</span>
                    </div>
                </div>
                <hr class="my-3">
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-white-50">Jumlah Pembayaran:</span>
                        <span class="fw-bold">${formatCurrency(amount)}</span>
                    </div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-white-50">Biaya Admin:</span>
                        <span>Rp 0</span>
                    </div>
                </div>
                <hr class="my-3">
                <div class="mb-0">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Total Bayar:</span>
                        <span class="fw-bold h5">${formatCurrency(amount)}</span>
                    </div>
                </div>
            `;
        }
        
        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const scheduleId = document.querySelector('input[name="schedule_id"]:checked');
            const paymentAmount = document.getElementById('payment_amount').value;
            
            if (!scheduleId) {
                e.preventDefault();
                alert('Pilih jadwal pembayaran yang akan dibayar.');
                return;
            }
            
            if (!paymentAmount || paymentAmount === '0' || paymentAmount === '') {
                e.preventDefault();
                alert('Jumlah pembayaran tidak valid.');
                return;
            }
            
            // Confirm payment
            if (!confirm('Apakah Anda yakin ingin memproses pembayaran ini?')) {
                e.preventDefault();
                return;
            }
            
            const submitBtn = document.getElementById('submit-btn');
            const submitIcon = document.getElementById('submit-icon');
            const submitText = document.getElementById('submit-text');
            
            // Prevent double submission
            if (submitBtn.disabled) {
                e.preventDefault();
                return;
            }
            
            submitBtn.disabled = true;
            submitIcon.className = 'fas fa-spinner fa-spin me-1';
            submitText.textContent = 'Memproses...';
            
            // Re-enable after 15 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitIcon.className = 'fas fa-credit-card me-1';
                submitText.textContent = 'Proses Pembayaran';
            }, 15000);
        });
        
        // Initialize with selected schedule if any
        <?php if ($selectedSchedule): ?>
            <?php 
            $daysLate = isOverdue($selectedSchedule['due_date']) ? abs(getDaysUntilDue($selectedSchedule['due_date'])) : 0;
            $lateFeeRate = LATE_FEE_PERCENTAGE;
            $lateFee = $daysLate > 0 ? $selectedSchedule['total_amount'] * $lateFeeRate / 100 * $daysLate : 0;
            $totalAmount = $selectedSchedule['total_amount'] + $lateFee;
            ?>
            selectSchedule(<?php echo $selectedSchedule['id']; ?>, <?php echo $totalAmount; ?>);
        <?php endif; ?>
    </script>
</body>
</html>