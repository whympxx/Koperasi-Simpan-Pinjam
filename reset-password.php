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
$token = $_GET['token'] ?? '';

// Validate token
if (empty($token)) {
    $error = 'Token reset password tidak valid.';
} else {
    $tokenData = $auth->validatePasswordResetToken($token);
    if (!$tokenData) {
        $error = 'Token reset password tidak valid atau sudah kadaluarsa.';
    }
}

// Handle password reset form submission
if ($_POST && !$error) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Password baru dan konfirmasi password harus diisi.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Password baru dan konfirmasi password tidak cocok.';
    } else {
        // Validate password strength
        $passwordErrors = $auth->validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            $error = implode(' ', $passwordErrors);
        } else {
            try {
                // Reset password
                $result = $auth->resetPasswordWithToken($token, $newPassword);
                
                if ($result['success']) {
                    $success = 'Password berhasil direset. Silakan login dengan password baru.';
                    // Redirect to login after 3 seconds
                    header('refresh:3;url=login.php');
                } else {
                    $error = $result['message'];
                }
            } catch (Exception $e) {
                error_log("Password reset error: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        }
    }
}

$pageTitle = 'Reset Password - ' . APP_NAME;
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
        
        .password-requirements {
            background: rgba(0,123,255,0.1);
            border: 1px solid rgba(0,123,255,0.2);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 1.2rem;
        }
        
        .password-requirements li {
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
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
                            <i class="fas fa-lock"></i>
                        </div>
                        <h4 class="mb-0">Reset Password</h4>
                        <p class="mb-0 opacity-75">Masukkan Password Baru</p>
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
                        
                        <?php if (!$error && !$success): ?>
                            <div class="password-requirements">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-shield-alt text-primary me-2 mt-1"></i>
                                    <div>
                                        <strong>Persyaratan Password:</strong>
                                        <ul>
                                            <li>Minimal 8 karakter</li>
                                            <li>Mengandung huruf besar (A-Z)</li>
                                            <li>Mengandung huruf kecil (a-z)</li>
                                            <li>Mengandung angka (0-9)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" id="resetForm">
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Password Baru" required>
                                    <label for="new_password"><i class="fas fa-lock me-2"></i>Password Baru</label>
                                    <div class="position-absolute end-0 top-50 translate-middle-y pe-3">
                                        <button type="button" class="btn btn-link p-0 text-muted" id="toggleNewPassword">
                                            <i class="fas fa-eye" id="newPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Konfirmasi Password" required>
                                    <label for="confirm_password"><i class="fas fa-lock me-2"></i>Konfirmasi Password</label>
                                    <div class="position-absolute end-0 top-50 translate-middle-y pe-3">
                                        <button type="button" class="btn btn-link p-0 text-muted" id="toggleConfirmPassword">
                                            <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-reset w-100 mb-3">
                                    <i class="fas fa-save me-2"></i>
                                    Reset Password
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <div class="text-center">
                            <a href="login.php" class="back-to-login">
                                <i class="fas fa-arrow-left me-2"></i>
                                Kembali ke Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility for new password
        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('new_password');
            const passwordIcon = document.getElementById('newPasswordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        });
        
        // Toggle password visibility for confirm password
        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordField = document.getElementById('confirm_password');
            const passwordIcon = document.getElementById('confirmPasswordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form submission with loading state
        document.getElementById('resetForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            
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
