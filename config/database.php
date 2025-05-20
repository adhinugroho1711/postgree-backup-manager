<?php
// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'backup_db');  // Diubah ke backup_db untuk konsistensi
define('DB_USER', 'postgres');
define('DB_PASS', 'jateng001');  // Diubah ke password yang benar

// Konfigurasi aplikasi
define('APP_NAME', 'PostgreSQL Backup Manager');
define('APP_URL', 'http://localhost:8000');
define('APP_DEBUG', true);

// Konfigurasi path
define('BACKUP_DIR', __DIR__ . '/../backups');

define('RETENTION_DAYS', 7);  // Masa retensi backup dalam hari

// Buat direktori backup jika belum ada
if (!file_exists(BACKUP_DIR)) {
    @mkdir(BACKUP_DIR, 0755, true);
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Set error reporting
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    error_reporting(0);
}

// Fungsi untuk mendapatkan koneksi database
function get_db_connection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";user=" . DB_USER . ";password=" . DB_PASS;
            $pdo = new PDO($dsn);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            // Set timezone database
            $pdo->exec("SET TIME ZONE 'Asia/Jakarta'");
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            // Set timezone database
            $pdo->exec("SET TIME ZONE 'Asia/Jakarta'");
            
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Fungsi untuk mendapatkan pengaturan
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        try {
            $pdo = get_db_connection();
            
            // Periksa apakah tabel settings sudah ada
            $stmt = $pdo->query("SELECT to_regclass('public.settings') as exists");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['exists']) {
                // Tabel sudah ada, ambil data
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            } else {
                // Tabel belum ada, buat tabel
                $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                    setting_key VARCHAR(255) PRIMARY KEY,
                    setting_value TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
        } catch (PDOException $e) {
            // Jika error, gunakan nilai default
            error_log("Error getting settings: " . $e->getMessage());
        }
    }
    
    return $settings[$key] ?? $default;
}

// Fungsi untuk menyimpan pengaturan
function saveSetting($key, $value) {
    try {
        $pdo = get_db_connection();
        
        // Periksa apakah tabel settings sudah ada
        $stmt = $pdo->query("SELECT to_regclass('public.settings') as exists");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result['exists']) {
            // Tabel belum ada, buat tabel
            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(255) PRIMARY KEY,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
        
        // Simpan pengaturan
        $stmt = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON CONFLICT (setting_key) 
            DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP"
        );
        
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        error_log("Error saving setting: " . $e->getMessage());
        return false;
    }
}

// Fungsi untuk memformat ukuran file
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Fungsi untuk memeriksa apakah pengguna sudah login
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fungsi untuk memeriksa apakah pengguna adalah admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Fungsi untuk redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Fungsi untuk membersihkan input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Fungsi untuk mengeksekusi perintah shell dengan aman
function executeCommand($command) {
    $output = [];
    $return_var = 0;
    
    exec($command . ' 2>&1', $output, $return_var);
    
    return [
        'success' => $return_var === 0,
        'output' => $output,
        'return_var' => $return_var
    ];
}

// Fungsi untuk memeriksa apakah perintah tersedia
function commandExists($command) {
    $output = [];
    $return_var = 0;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = "where $command";
    } else {
        $command = "command -v $command";
    }
    
    exec($command . ' 2>&1', $output, $return_var);
    
    return $return_var === 0;
}
