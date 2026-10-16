<?php
require_once 'includes/init.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    if ($user['role'] === 'nasabah') {
        header('Location: dashboard.php');
    } else {
        header('Location: admin/dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

// Handle password reset request
if ($_POST) {
    $nik = SecurityHelper::sanitize($_POST['nik'] ?? '');
    $email = SecurityHelper::sanitize($_POST['email'] ?? '');
    
    if (empty($nik) || empty($email)) {
        $error = 'NIK dan email harus diisi.';
    } else {
        try {
            // Check if user exists with matching NIK and email
            $user = $auth->getUserByNikAndEmail($nik, $email);
            
            if (!$user) {
                $error = 'NIK dan email tidak cocok atau akun tidak ditemukan.';
            } else {
                // Generate password reset token
                $token = $auth->generatePasswordResetToken($user['id']);
                
                // Send reset email
                $resetLink = APP_URL . 'reset-password.php?token=' . $token;
                $emailSent = $auth->sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);
                
                if ($emailSent) {
                    $success = 'Link reset password telah dikirim ke email Anda. Silakan cek email dan ikuti instruksi yang diberikan.';
                } else {
                    $error = 'Gagal mengirim email. Silakan hubungi administrator.';
                }
            }
        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Lupa Password - ' . APP_NAME;
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
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .reset-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        
        .reset-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: #667eea;
        }
        
        .reset-body {
            padding: 2rem;
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .back-to-login {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-to-login:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .info-box {
            background: rgba(0,123,255,0.1);
            border: 1px solid rgba(0,123,255,0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .reset-card {
                margin: 1rem;
            }
            
            .reset-header {
                padding: 1.5rem;
            }
            
            .reset-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card reset-card">
                    <div class="reset-header">
                        <div class="company-logo">
                            <i class="fas fa-key"></i>
                        </div>
                        <h4 class="mb-0">Reset Password</h4>
                        <p class="mb-0 opacity-75">Masukkan NIK dan Email Anda</p>
                    </div>
                    
                    <div class="reset-body">
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
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-box">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle text-primary me-2 mt-1"></i>
                                <div>
                                    <strong>Informasi:</strong>
                                    <p class="mb-0 small">Link reset password akan dikirim ke email yang terdaftar di sistem. Pastikan NIK dan email yang Anda masukkan sesuai dengan data yang terdaftar.</p>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" id="resetForm">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="nik" name="nik" 
                                       placeholder="NIK" value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>" 
                                       required maxlength="20" pattern="[A-Za-z0-9]{1,20}">
                                <label for="nik"><i class="fas fa-id-card me-2"></i>NIK (huruf dan angka)</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="Email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-reset w-100 mb-3">
                                <i class="fas fa-paper-plane me-2"></i>
                                Kirim Link Reset
                            </button>
                            
                            <div class="text-center">
                                <a href="login.php" class="back-to-login">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Kembali ke Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // NIK input validation
        document.getElementById('nik').addEventListener('input', function(e) {
            // Allow only alphanumeric characters
            e.target.value = e.target.value.replace(/[^A-Za-z0-9]/g, '');
            
            // Limit to 20 characters
            if (e.target.value.length > 20) {
                e.target.value = e.target.value.slice(0, 20);
            }
        });
        
        // Form submission with loading state
        document.getElementById('resetForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Mengirim...';
            
            // Re-enable button after 10 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 10000);
        });
        
        // Auto dismiss alerts after 8 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    alert.querySelector('.btn-close').click();
                }
            });
        }, 8000);
    </script>
</body>
</html>
