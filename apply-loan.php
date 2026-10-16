<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$loanManager = new LoanManager();

$error = '';
$success = '';
$autoApproved = false;

// Check eligibility
$eligibility = canApplyLoan($user, $dbHelper);
if (!$eligibility['can_apply']) {
    header('Location: dashboard.php');
    exit;
}

$fullUser = $dbHelper->getUserById($user['id']);
$savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
$simpananWajib = (float)($savingsAccount['balance'] ?? 0);
$loanMultiplier = getLoanMultiplierForUser($fullUser ?: $user, $dbHelper);
$maxLoanAmount = getMaxLoanAmount($fullUser ?: $user, $simpananWajib, $dbHelper);
$maxLoanTerm = getMaxLoanTerm($dbHelper);
$standardLoanTerm = getStandardLoanTerm($dbHelper);
$interestRate = $dbHelper->getSystemSetting('loan_interest_rate') ?: DEFAULT_LOAN_INTEREST_RATE;

// Handle form submission
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $loanAmount = (float)str_replace(['.', ','], ['', '.'], SecurityHelper::sanitize($_POST['loan_amount'] ?? ''));
        $termMonths = (int)SecurityHelper::sanitize($_POST['term_months'] ?? '');
        $purpose = SecurityHelper::sanitize($_POST['purpose'] ?? '');
        $description = SecurityHelper::sanitize($_POST['description'] ?? '');
        
        $formCheck = validateLoanFormInput($loanAmount, $termMonths, $maxLoanAmount, $maxLoanTerm);
        if (!$formCheck['valid']) {
            $error = $formCheck['message'];
        } elseif (empty($purpose)) {
            $error = 'Tujuan pinjaman harus diisi.';
        } else {
            // Submit application
            $result = $loanManager->submitApplication($user['id'], $loanAmount, $termMonths, $purpose, $description);
            
            if ($result['success']) {
                $success = $result['message'];
                $autoApproved = !empty($result['auto_approved']);
                // Clear form
                $_POST = [];
            } else {
                $error = $result['message'];
            }
        }
    }
}

$pageTitle = 'Pengajuan Pinjaman - ' . APP_NAME;
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
        .loan-calculator {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .calculator-result {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .amount-input {
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .slider-container {
            position: relative;
            margin: 2rem 0;
        }
        
        .range-slider {
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #ddd;
            outline: none;
            -webkit-appearance: none;
        }
        
        .range-slider::-webkit-slider-thumb {
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        
        .range-slider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #667eea;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
        }
        
        .range-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
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
            'Pengajuan Pinjaman' => ''
        ]); ?>
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center">
                    <i class="fas fa-hand-holding-usd text-primary me-3 fa-2x"></i>
                    <div>
                        <h2 class="mb-0">Pengajuan Pinjaman</h2>
                        <p class="text-muted mb-0">Ajukan pinjaman dengan mudah dan cepat</p>
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
                <i class="fas fa-<?php echo $autoApproved ? 'bolt' : 'check-circle'; ?> me-2"></i>
                <?php if ($autoApproved): ?>
                    <strong>Disetujui Otomatis!</strong>
                <?php endif; ?>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="mt-3">
                    <?php if ($autoApproved): ?>
                        <a href="loans.php" class="btn btn-success btn-sm">Lihat Pinjaman Aktif</a>
                    <?php else: ?>
                        <a href="loans.php" class="btn btn-success btn-sm">Lihat Status Pengajuan</a>
                    <?php endif; ?>
                    <a href="dashboard.php" class="btn btn-outline-success btn-sm">Kembali ke Dashboard</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Loan Calculator -->
            <div class="col-lg-5 mb-4">
                <div class="loan-calculator">
                    <h4 class="mb-3">
                        <i class="fas fa-calculator me-2"></i>
                        Kalkulator Pinjaman
                    </h4>
                    
                    <div class="mb-3">
                        <label class="form-label">Jumlah Pinjaman</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white">Rp</span>
                            <input type="text" class="form-control amount-input" id="calc-amount" 
                                   placeholder="0" value="<?php echo number_format(min(max($maxLoanAmount, 0), 5000000), 0, ',', '.'); ?>">
                        </div>
                        <div class="slider-container">
                            <input type="range" class="range-slider" id="amount-slider" 
                                   min="<?php echo max(MIN_LOAN_AMOUNT, min(1000000, max($maxLoanAmount, MIN_LOAN_AMOUNT))); ?>" max="<?php echo max($maxLoanAmount, MIN_LOAN_AMOUNT); ?>" 
                                   value="<?php echo min(max($maxLoanAmount, 0), 5000000); ?>" step="50000">
                            <div class="range-labels">
                                <span><?php echo formatRupiah(max(MIN_LOAN_AMOUNT, min(1000000, max($maxLoanAmount, MIN_LOAN_AMOUNT)))); ?></span>
                                <span><?php echo formatRupiah(max($maxLoanAmount, 0)); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jangka Waktu</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="calc-term" 
                                   min="1" max="<?php echo (int)$maxLoanTerm; ?>" value="<?php echo $_POST['term_months'] ?? min(12, $maxLoanTerm); ?>">
                            <span class="input-group-text bg-white">Bulan</span>
                        </div>
                        <div class="slider-container">
                            <input type="range" class="range-slider" id="term-slider" 
                                   min="1" max="<?php echo (int)$maxLoanTerm; ?>" value="<?php echo $_POST['term_months'] ?? min(12, $maxLoanTerm); ?>">
                            <div class="range-labels">
                                <span>1 Bulan</span>
                                <span><?php echo (int)$maxLoanTerm; ?> Bulan</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="calculator-result">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="opacity-75">Angsuran per Bulan</small>
                                </div>
                                <div class="h5 mb-0" id="monthly-payment"><?php echo formatRupiah(calculateInstallment(5000000, $interestRate, 12)); ?></div>
                            </div>
                            <div class="col-6">
                                <div class="mb-2">
                                    <small class="opacity-75">Total Pembayaran</small>
                                </div>
                                <div class="h5 mb-0" id="total-payment"><?php echo formatRupiah(calculateTotalPayment(5000000, $interestRate, 12)); ?></div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="opacity-75">Bunga per Bulan</small>
                                <div class="h6 mb-0"><?php echo $interestRate; ?>%</div>
                            </div>
                            <div class="col-6">
                                <small class="opacity-75">Total Bunga</small>
                                <div class="h6 mb-0" id="total-interest"><?php echo formatRupiah(calculateTotalPayment(5000000, $interestRate, 12) - 5000000); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-light mt-3">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Plafon pinjaman: <strong><?php echo formatRupiah($maxLoanAmount); ?></strong>
                            (<?php echo (int)$loanMultiplier; ?>x simpanan wajib <?php echo formatRupiah($simpananWajib); ?>)
                        </small>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="col-lg-7">
                <div class="form-section">
                    <h4 class="mb-4">
                        <i class="fas fa-file-alt me-2 text-primary"></i>
                        Formulir Pengajuan
                    </h4>

                    <form method="POST" id="loanForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Jumlah Pinjaman <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control amount-input" name="loan_amount" 
                                           id="loan-amount" required 
                                           value="<?php
                                               $postedAmount = $_POST['loan_amount'] ?? '';
                                               if ($postedAmount === '' || $postedAmount === null) {
                                                   echo '';
                                               } else {
                                                   $postedNum = is_numeric($postedAmount)
                                                       ? (float)$postedAmount
                                                       : (float)str_replace(['.', ','], ['', '.'], (string)$postedAmount);
                                                   echo number_format($postedNum, 0, ',', '.');
                                               }
                                           ?>">
                                </div>
                                <div class="form-text">Maksimal <?php echo formatRupiah($maxLoanAmount); ?> (<?php echo (int)$loanMultiplier; ?>x simpanan wajib)</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Jangka Waktu <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="term_months" 
                                           id="term-months" min="1" max="<?php echo (int)$maxLoanTerm; ?>" required 
                                           value="<?php echo $_POST['term_months'] ?? ''; ?>">
                                    <span class="input-group-text">Bulan</span>
                                </div>
                                <div class="form-text">Standar maks. <?php echo (int)$standardLoanTerm; ?> bulan; kebijakan khusus hingga <?php echo (int)$maxLoanTerm; ?> bulan (butuh surat pernyataan)</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tujuan Pinjaman <span class="text-danger">*</span></label>
                            <select class="form-select" name="purpose" required>
                                <option value="">Pilih tujuan pinjaman</option>
                                <option value="Kebutuhan Darurat" <?php echo ($_POST['purpose'] ?? '') === 'Kebutuhan Darurat' ? 'selected' : ''; ?>>Kebutuhan Darurat</option>
                                <option value="Pendidikan" <?php echo ($_POST['purpose'] ?? '') === 'Pendidikan' ? 'selected' : ''; ?>>Pendidikan</option>
                                <option value="Kesehatan" <?php echo ($_POST['purpose'] ?? '') === 'Kesehatan' ? 'selected' : ''; ?>>Kesehatan</option>
                                <option value="Renovasi Rumah" <?php echo ($_POST['purpose'] ?? '') === 'Renovasi Rumah' ? 'selected' : ''; ?>>Renovasi Rumah</option>
                                <option value="Modal Usaha" <?php echo ($_POST['purpose'] ?? '') === 'Modal Usaha' ? 'selected' : ''; ?>>Modal Usaha</option>
                                <option value="Konsumtif" <?php echo ($_POST['purpose'] ?? '') === 'Konsumtif' ? 'selected' : ''; ?>>Konsumtif</option>
                                <option value="Lainnya" <?php echo ($_POST['purpose'] ?? '') === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Keterangan Tambahan</label>
                            <textarea class="form-control" name="description" rows="3" 
                                      placeholder="Jelaskan secara detail penggunaan dana pinjaman..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- Summary -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <i class="fas fa-file-contract text-primary me-2"></i>
                                    Ringkasan Pinjaman
                                </h6>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled mb-0">
                                            <li><strong>Jumlah Pinjaman:</strong> <span id="summary-amount">-</span></li>
                                            <li><strong>Jangka Waktu:</strong> <span id="summary-term">-</span></li>
                                            <li><strong>Bunga per Bulan:</strong> <?php echo $interestRate; ?>%</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled mb-0">
                                            <li><strong>Angsuran per Bulan:</strong> <span id="summary-monthly">-</span></li>
                                            <li><strong>Total Pembayaran:</strong> <span id="summary-total">-</span></li>
                                            <li><strong>Total Bunga:</strong> <span id="summary-interest">-</span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms-agreement" required>
                                <label class="form-check-label" for="terms-agreement">
                                    Saya menyetujui <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">syarat dan ketentuan</a> yang berlaku
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="loans.php" class="btn btn-outline-secondary me-md-2">
                                <i class="fas fa-arrow-left me-1"></i>
                                Kembali
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>
                                Ajukan Pinjaman
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Syarat dan Ketentuan Pinjaman</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="terms-content">
                        <h6>1. Persyaratan Umum</h6>
                        <ul>
                            <li>Anggota aktif Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)</li>
                            <li>Memiliki simpanan wajib minimal <?php echo formatRupiah(MIN_MANDATORY_SAVINGS); ?></li>
                            <li>Status kepegawaian aktif</li>
                            <li>Tidak memiliki pinjaman aktif</li>
                        </ul>
                        
                        <h6>2. Ketentuan Pinjaman (RAT 2025)</h6>
                        <ul>
                            <li>Plafon maksimal <?php echo (int)$loanMultiplier; ?>x simpanan wajib
                                (2x bila potongan lewat HRD/Yayasan; 1x bila outsourcing / angsur mandiri)</li>
                            <li>Jangka waktu standar maksimal <?php echo (int)$standardLoanTerm; ?> bulan</li>
                            <li>Kebijakan khusus hingga <?php echo (int)$maxLoanTerm; ?> bulan dengan surat pernyataan bermaterai diketahui Kepala Unit</li>
                            <li>Bunga flat <?php echo $interestRate; ?>% per bulan</li>
                            <li>Angsuran karyawan/driver dicek kemampuan bayar bersama HRD / Ketua Unit</li>
                        </ul>
                        
                        <h6>3. Denda Keterlambatan</h6>
                        <ul>
                            <li>Denda <?php echo LATE_FEE_PERCENTAGE; ?>% per hari dari jumlah angsuran</li>
                            <li>Maksimal denda 30% dari jumlah angsuran</li>
                            <li>Penagihan melalui HRD untuk keterlambatan > 7 hari</li>
                        </ul>
                        
                        <h6>4. Pelunasan Dipercepat</h6>
                        <ul>
                            <li>Diperbolehkan tanpa penalti</li>
                            <li>Bunga dihitung proporsional</li>
                            <li>Notifikasi minimal 7 hari sebelum pelunasan</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Mengerti</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const interestRate = <?php echo $interestRate; ?>;
        const maxLoanAmount = <?php echo (float)$maxLoanAmount; ?>;
        const maxLoanTerm = <?php echo (int)$maxLoanTerm; ?>;
        const standardLoanTerm = <?php echo (int)$standardLoanTerm; ?>;
        
        // Format number with thousand separator
        function formatNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        }
        
        // Format currency
        function formatCurrency(num) {
            return 'Rp ' + formatNumber(num);
        }
        
        // Calculate loan details
        function calculateLoan(amount, term) {
            const monthlyInterest = amount * interestRate / 100;
            const principal = amount / term;
            const monthlyPayment = principal + monthlyInterest;
            const totalPayment = monthlyPayment * term;
            const totalInterest = totalPayment - amount;
            
            return {
                monthlyPayment: monthlyPayment,
                totalPayment: totalPayment,
                totalInterest: totalInterest
            };
        }
        
        // Update calculator
        function updateCalculator() {
            const amount = parseFloat(document.getElementById('calc-amount').value.replace(/[.,]/g, '')) || 0;
            const term = parseInt(document.getElementById('calc-term').value) || 1;
            
            const loan = calculateLoan(amount, term);
            
            document.getElementById('monthly-payment').textContent = formatCurrency(loan.monthlyPayment);
            document.getElementById('total-payment').textContent = formatCurrency(loan.totalPayment);
            document.getElementById('total-interest').textContent = formatCurrency(loan.totalInterest);
            
            // Update form
            document.getElementById('loan-amount').value = formatNumber(amount);
            document.getElementById('term-months').value = term;
            
            // Update summary
            updateSummary();
        }
        
        // Update summary
        function updateSummary() {
            const amountInput = document.getElementById('loan-amount').value.replace(/[.,]/g, '');
            const amount = parseFloat(amountInput) || 0;
            const term = parseInt(document.getElementById('term-months').value) || 1;
            
            if (amount > 0) {
                const loan = calculateLoan(amount, term);
                
                document.getElementById('summary-amount').textContent = formatCurrency(amount);
                document.getElementById('summary-term').textContent = term + ' bulan';
                document.getElementById('summary-monthly').textContent = formatCurrency(loan.monthlyPayment);
                document.getElementById('summary-total').textContent = formatCurrency(loan.totalPayment);
                document.getElementById('summary-interest').textContent = formatCurrency(loan.totalInterest);
            } else {
                document.getElementById('summary-amount').textContent = '-';
                document.getElementById('summary-term').textContent = '-';
                document.getElementById('summary-monthly').textContent = '-';
                document.getElementById('summary-total').textContent = '-';
                document.getElementById('summary-interest').textContent = '-';
            }
        }
        
        // Event listeners
        document.getElementById('calc-amount').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value) {
                e.target.value = formatNumber(parseInt(value));
                document.getElementById('amount-slider').value = parseInt(value);
            }
            updateCalculator();
        });
        
        document.getElementById('calc-term').addEventListener('input', function(e) {
            document.getElementById('term-slider').value = e.target.value;
            updateCalculator();
        });
        
        document.getElementById('amount-slider').addEventListener('input', function(e) {
            document.getElementById('calc-amount').value = formatNumber(e.target.value);
            updateCalculator();
        });
        
        document.getElementById('term-slider').addEventListener('input', function(e) {
            document.getElementById('calc-term').value = e.target.value;
            updateCalculator();
        });
        
        document.getElementById('loan-amount').addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');
            if (value) {
                e.target.value = formatNumber(parseInt(value));
            }
            updateSummary();
        });
        
        document.getElementById('term-months').addEventListener('input', updateSummary);
        
        // Form validation
        document.getElementById('loanForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('loan-amount').value.replace(/[.,]/g, '')) || 0;
            const term = parseInt(document.getElementById('term-months').value) || 0;
            
            if (amount < <?php echo (int)MIN_LOAN_AMOUNT; ?>) {
                e.preventDefault();
                alert('Jumlah pinjaman minimal <?php echo formatRupiah(MIN_LOAN_AMOUNT); ?>');
                return;
            }
            
            if (maxLoanAmount <= 0 || amount > maxLoanAmount) {
                e.preventDefault();
                alert(maxLoanAmount <= 0
                    ? 'Plafon pinjaman belum tersedia. Pastikan simpanan wajib mencukupi.'
                    : 'Jumlah pinjaman melebihi batas maksimal ' + formatCurrency(maxLoanAmount));
                return;
            }
            
            if (term < 1 || term > maxLoanTerm) {
                e.preventDefault();
                alert('Jangka waktu harus antara 1-' + maxLoanTerm + ' bulan');
                return;
            }

            if (term > standardLoanTerm) {
                if (!confirm('Tenor di atas ' + standardLoanTerm + ' bulan memerlukan surat pernyataan bermaterai dan akan direview pengurus. Lanjutkan?')) {
                    e.preventDefault();
                    return;
                }
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Memproses...';
            
            // Re-enable after 10 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        });
        
        // Initialize calculator on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCalculator();
            updateSummary();
        });
    </script>
</body>
</html>
