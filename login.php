<?php
require_once 'includes/init.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    $user = $auth->getCurrentUser();
    if ($user['role'] === 'nasabah') {
        header('Location: dashboard.php');
    } else {
        header('Location: admin-dashboard.php');
    }
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_POST) {
    $nik = SecurityHelper::sanitize($_POST['nik'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($nik) || empty($password)) {
        $error = 'NIK dan password harus diisi.';
    } else {
        $result = $auth->login($nik, $password, $rememberMe);
        
        if ($result['success']) {
            header('Location: ' . $result['redirect']);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Check for timeout parameter
if (isset($_GET['timeout'])) {
    $error = 'Sesi Anda telah berakhir. Silakan login kembali.';
}

// Check for logout parameter
if (isset($_GET['logout'])) {
    $success = 'Anda telah berhasil logout.';
}

$pageTitle = 'Login - ' . APP_NAME;
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
        
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
        }
        
        .login-header {
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
        
        .login-body {
            padding: 2rem;
        }
        
        .form-floating label {
            color: #6c757d;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .company-info {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .login-card {
                margin: 1rem;
            }
            
            .login-header {
                padding: 1.5rem;
            }
            
            .login-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">
                <div class="card login-card">
                    <div class="login-header">
                        <div class="company-logo">
                            <i class="fas fa-building"></i>
                        </div>
                        <h4 class="mb-0">Sistem Simpan Pinjam</h4>
                        <p class="mb-0 opacity-75">PT. Puninar Jaya Logistik</p>
                    </div>
                    
                    <div class="login-body">
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
                        
                        <form method="POST" id="loginForm">
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="nik" name="nik" 
                                       placeholder="NIK" value="<?php echo htmlspecialchars($_POST['nik'] ?? ''); ?>" 
                                       required maxlength="20" pattern="[A-Za-z0-9]{1,20}">
                                <label for="nik"><i class="fas fa-id-card me-2"></i>NIK (huruf dan angka)</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Password" required>
                                <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                                <div class="position-absolute end-0 top-50 translate-middle-y pe-3">
                                    <button type="button" class="btn btn-link p-0 text-muted" id="togglePassword">
                                        <i class="fas fa-eye" id="passwordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                <label class="form-check-label" for="remember_me">
                                    Ingat saya selama 30 hari
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Masuk
                            </button>
                            
                            <div class="text-center">
                                <a href="forgot-password.php" class="forgot-password">
                                    Lupa Password?
                                </a>
                            </div>
                            
                            <div class="company-info">
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <span>Cakung, Jakarta Timur</span>
                                </div>
                                <div class="d-flex align-items-center justify-content-center">
                                    <i class="fas fa-clock me-2"></i>
                                    <span>Sistem aktif 24/7</span>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Demo Accounts Info -->
                <div class="card mt-3" style="background: rgba(255,255,255,0.9);">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle text-info me-2"></i>
                            Demo Akun
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Admin:</strong><br>
                                NIK: ADMIN001<br>
                                Password: password
                            </div>
                            <div class="col-md-6">
                                <strong>Nasabah:</strong><br>
                                NIK: PUN001<br>
                                Password: password
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordField = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                passwordIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                passwordIcon.className = 'fas fa-eye';
            }
        });
        
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
        document.getElementById('loginForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            
            // Re-enable button after 5 seconds as fallback
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 5000);
        });
        
        // Auto dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.querySelector('.btn-close')) {
                    alert.querySelector('.btn-close').click();
                }
            });
        }, 5000);
    </script>
</body>
</html>
