<?php
// Script khusus untuk menghapus multiple file backup
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Pastikan hanya admin yang bisa mengakses
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Pastikan direktori log ada
$log_dir = __DIR__ . '/../logs/delete';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log untuk debugging
$log_file = fopen($log_dir . '/delete_multiple.log', 'a');
fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Memulai proses hapus multiple file\n");

// Proses hapus multiple backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup_ids'])) {
    $backup_ids = $_POST['backup_ids'];
    
    // Log data yang diterima
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Data yang diterima: " . print_r($backup_ids, true) . "\n");
    
    $deleted_count = 0;
    $failed_count = 0;
    
    try {
        $pdo = get_db_connection();
        $user_id = $_SESSION['user_id'] ?? 1;
        
        // Jika hanya satu ID yang dikirim (bukan array)
        if (!is_array($backup_ids)) {
            $backup_ids = [$backup_ids];
        }
        
        foreach ($backup_ids as $backup_id) {
            fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Memproses backup ID: $backup_id\n");
            
            try {
                // Ambil informasi backup
                $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
                $stmt->execute([$backup_id]);
                $backup = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$backup) {
                    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Backup ID $backup_id tidak ditemukan\n");
                    $failed_count++;
                    continue;
                }
                
                // Hapus file dari disk
                $filepath = BACKUP_DIR . '/' . $backup['filename'];
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba menghapus file: $filepath\n");
                
                if (file_exists($filepath)) {
                    if (!unlink($filepath)) {
                        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Gagal menghapus file: $filepath\n");
                        $failed_count++;
                        continue;
                    }
                }
                
                // Hapus dari database
                $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
                if (!$stmt->execute([$backup_id])) {
                    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Gagal menghapus dari database: $backup_id\n");
                    $failed_count++;
                    continue;
                }
                
                // Catat aktivitas
                $log_message = "Backup {$backup['filename']} dihapus oleh user ID: $user_id";
                error_log($log_message, 3, __DIR__ . '/backup_error.log');
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Berhasil menghapus backup ID: $backup_id\n");
                
                $deleted_count++;
            } catch (Exception $e) {
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error saat menghapus backup ID $backup_id: " . $e->getMessage() . "\n");
                $failed_count++;
            }
        }
        
        // Set pesan hasil
        if ($deleted_count > 0) {
            $message = "$deleted_count backup berhasil dihapus";
            if ($failed_count > 0) {
                $message .= ", $failed_count backup gagal dihapus";
            }
            $status = 'success';
        } else {
            $message = "Gagal menghapus backup";
            $status = 'error';
        }
    } catch (Exception $e) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error umum: " . $e->getMessage() . "\n");
        $message = 'Gagal menghapus backup: ' . $e->getMessage();
        $status = 'error';
    }
    
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Hasil: $message\n");
    fclose($log_file);
    
    // Redirect kembali ke halaman manage_backups dengan pesan hasil
    header("Location: ../index.php?page=manage_backups&status=$status&message=" . urlencode($message));
    exit();
} else {
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Tidak ada data backup_ids yang diterima\n");
    fclose($log_file);
    
    // Redirect kembali ke halaman manage_backups dengan pesan error
    header("Location: ../index.php?page=manage_backups&status=error&message=" . urlencode("Tidak ada backup yang dipilih"));
    exit();
}
?>
