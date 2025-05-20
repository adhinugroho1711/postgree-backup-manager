<?php
// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include konfigurasi dan fungsi
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/audit_middleware.php';

// Set default timezone
date_default_timezone_set('Asia/Jakarta');

// Set error reporting
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// Redirect ke halaman utama jika sudah login
if (is_logged_in()) {
    header('Location: index.php');
    exit();
}

$error = '';

// Proses form login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } elseif (login($username, $password)) {
        // Catat aktivitas login berhasil ke audit log
        if (function_exists('log_login_activity')) {
            log_login_activity($_SESSION['user_id'], $username, true);
        }
        
        // Redirect ke halaman yang diminta sebelumnya atau halaman utama
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        
        // Pastikan redirect ke root, bukan ke folder controllers
        if (strpos($redirect, 'controllers/') === 0) {
            $redirect = substr($redirect, strlen('controllers/'));
        }
        
        header('Location: ' . $redirect);
        exit();
    } else {
        // Catat aktivitas login gagal ke audit log
        if (function_exists('log_login_activity')) {
            log_login_activity(0, $username, false);
        }
        
        $error = 'Username atau password salah';
    }
}

// Set judul halaman
$page_title = 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .login-container {
            max-width: 450px;
            margin: 0 auto;
            padding-top: 10vh;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background-color: #4e73df;
            color: white;
            text-align: center;
            padding: 1.5rem;
            border-bottom: none;
        }
        
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><?php echo APP_NAME; ?></h4>
            </div>
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-4">Login</h5>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bxs-user'></i></span>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bxs-lock-alt'></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="text-center mt-4 text-muted">
            <small>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</small>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
