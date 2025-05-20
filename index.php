<?php
// Mulai output buffering untuk menghindari error header already sent
ob_start();

// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Periksa apakah user sudah login
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

// Daftar halaman yang valid dan izin yang dibutuhkan
$valid_pages = [
    'dashboard' => [
        'file' => 'pages/dashboard.php',
        'require_admin' => false
    ],
    'backup' => [
        'file' => 'pages/backup.php',
        'require_admin' => false
    ],
    'backup_detail' => [
        'file' => 'pages/backup_detail.php',
        'require_admin' => false
    ],
    'restore' => [
        'file' => 'pages/restore.php',
        'require_admin' => false
    ],
    'manage_backups' => [
        'file' => 'pages/manage_backups.php',
        'require_admin' => true
    ],
    'schedule' => [
        'file' => 'pages/schedule.php',
        'require_admin' => true
    ],
    'reports' => [
        'file' => 'pages/reports.php',
        'require_admin' => false
    ],
    'users' => [
        'file' => 'pages/users.php',
        'require_admin' => true
    ],
    'settings' => [
        'file' => 'pages/settings.php',
        'require_admin' => true
    ],
    'audit_log' => [
        'file' => 'pages/audit_log.php',
        'require_admin' => true
    ],
    'export_csv' => [
        'file' => 'pages/export_csv.php',
        'require_admin' => false
    ]
];

// Dapatkan halaman yang diminta
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Catat akses halaman ke audit log jika user sudah login
if (is_logged_in() && function_exists('log_page_access')) {
    log_page_access($page);
}

// Periksa izin akses halaman
if (isset($valid_pages[$page])) {
    // Periksa apakah halaman membutuhkan hak akses admin
    if ($valid_pages[$page]['require_admin'] && !is_admin()) {
        $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengakses halaman ini';
        header('Location: index.php?page=dashboard');
        exit();
    }
} else {
    // Halaman tidak ditemukan
    $page = '404';
}

// Set judul halaman default
$page_title = 'Dashboard';

// Include header
include __DIR__ . '/includes/header.php';

// Tentukan file yang akan di-include
if (isset($valid_pages[$page])) {
    $page_file = $valid_pages[$page]['file'];
    
    // Periksa apakah file ada
    if (file_exists(__DIR__ . '/' . $page_file)) {
        include __DIR__ . '/' . $page_file;
    } else {
        include __DIR__ . '/pages/404.php';
    }
} else {
    // Tampilkan halaman 404 jika halaman tidak ditemukan
    include __DIR__ . '/pages/404.php';
}

// Include footer
include __DIR__ . '/includes/footer.php';
?>
