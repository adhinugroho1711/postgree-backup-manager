<?php
// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include konfigurasi dan fungsi
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Pastikan user sudah login
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

// Set judul halaman
$page_title = 'Backup Database';

// Include header
include __DIR__ . '/includes/header.php';

// Include halaman backup dari folder pages
include __DIR__ . '/pages/backup.php';

// Include footer
include __DIR__ . '/includes/footer.php';
?>
