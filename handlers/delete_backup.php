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
    
    // Hapus file backup
    if (file_exists($backup['file_path'])) {
        if (!@unlink($backup['file_path'])) {
            throw new Exception('Gagal menghapus file backup');
        }
    }
    
    // Hapus record dari database
    $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
    $stmt->execute([$backup_id]);
    
    set_flash_message('success', 'Backup berhasil dihapus');
} catch (Exception $e) {
    set_flash_message('error', 'Gagal menghapus backup: ' . $e->getMessage());
}

// Redirect kembali ke halaman backup
header('Location: backup.php');
exit();
?>
