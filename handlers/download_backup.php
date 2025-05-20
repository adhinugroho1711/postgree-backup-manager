<?php
// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include konfigurasi dan fungsi
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Pastikan user sudah login
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Pastikan ada parameter ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'ID backup tidak valid');
    header('Location: backup.php');
    exit();
}

$backup_id = (int)$_GET['id'];

try {
    $pdo = get_db_connection();
    
    // Dapatkan informasi backup
    $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
    $stmt->execute([$backup_id]);
    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$backup) {
        throw new Exception('Backup tidak ditemukan');
    }
    
    // Pastikan file ada
    if (!file_exists($backup['file_path'])) {
        throw new Exception('File backup tidak ditemukan');
    }
    
    // Set header untuk download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backup['file_path']) . '"');
    header('Content-Length: ' . filesize($backup['file_path']));
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Baca file dan kirim ke output
    readfile($backup['file_path']);
    exit();
    
} catch (Exception $e) {
    set_flash_message('error', 'Gagal mengunduh backup: ' . $e->getMessage());
    header('Location: backup.php');
    exit();
}
?>
