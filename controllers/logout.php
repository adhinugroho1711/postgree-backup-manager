<?php
// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include konfigurasi dan fungsi
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit_middleware.php';

// Catat aktivitas logout ke audit log jika user sudah login
if (is_logged_in() && function_exists('log_logout_activity')) {
    $user_id = $_SESSION['user_id'] ?? 0;
    log_logout_activity($user_id);
}

// Hapus semua data session
logout();

// Set pesan sukses
set_flash_message('success', 'Anda telah berhasil keluar.');

// Redirect ke halaman login di direktori root
header('Location: ../login.php');
exit();
