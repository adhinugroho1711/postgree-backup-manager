<?php
/**
 * Script untuk menjalankan backup otomatis berdasarkan jadwal
 * Jalankan script ini melalui cron job setiap menit:
 * * * * * php /path/to/cron_backup.php > /dev/null 2>&1
 */

// Pastikan script hanya dijalankan dari CLI atau melalui web dengan parameter khusus
$is_cli = php_sapi_name() === 'cli';
$is_authorized_web = isset($_GET['run_key']) && $_GET['run_key'] === 'backup_secret_key';

if (!$is_cli && !$is_authorized_web) {
    exit('Script ini hanya dapat dijalankan melalui CLI atau dengan parameter khusus.');
}

// Jika dijalankan melalui web, set header yang sesuai
if (!$is_cli) {
    header('Content-Type: text/plain');
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Load konfigurasi dan fungsi
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/performance.php';

// Fungsi untuk mencatat pesan log
function log_cron_message($message) {
    $log_dir = __DIR__ . '/../logs/cron';
    $log_file = $log_dir . '/cron_backup.log';
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// Fungsi untuk memantau penggunaan memori
function log_memory_usage($label = '') {
    $memory = memory_get_usage() / 1024 / 1024;
    log_cron_message("Penggunaan memori {$label}: {$memory} MB");
}

log_cron_message("Memulai proses cron backup...");

try {
    // Buat koneksi ke database
    $pdo = get_db_connection();
    
    // Ambil semua jadwal yang aktif dan waktunya sudah tiba atau lewat
    // Tambahkan toleransi 10 menit untuk mengakomodasi interval cron 5 menit
    $stmt = $pdo->query("
        SELECT * FROM backup_schedules 
        WHERE is_active = TRUE 
        AND next_run <= NOW() + INTERVAL '10 minutes'
        AND (last_run IS NULL OR last_run < next_run - INTERVAL '1 minute')
    ");
    
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($schedules)) {
        log_cron_message("Tidak ada jadwal backup yang perlu dijalankan saat ini.");
        exit(0);
    }
    
    log_cron_message("Menemukan " . count($schedules) . " jadwal backup yang perlu dijalankan.");
    
    // Proses setiap jadwal
    foreach ($schedules as $schedule) {
        log_cron_message("Memproses jadwal: " . $schedule['name']);
        
        // Siapkan parameter backup
        $database = $schedule['database_name'];
        $compress = $schedule['compress'];
        $include_schema = $schedule['include_schema'];
        $include_data = $schedule['include_data'];
        $backup_type = $schedule['backup_type'] ?? 'full';
        $selected_tables = [];
        
        // Jika backup tipe tabel tertentu, ambil daftar tabel
        if ($backup_type === 'tables' && !empty($schedule['selected_tables'])) {
            try {
                $selected_tables = json_decode($schedule['selected_tables'], true);
                if (!is_array($selected_tables)) {
                    $selected_tables = [];
                }
            } catch (Exception $e) {
                log_cron_message("Error parsing selected_tables: " . $e->getMessage());
                $selected_tables = [];
            }
        }
        
        // Buat nama file backup
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $database . '_' . $timestamp;
        $filename .= $compress ? '.sql.gz' : '.sql';
        $backup_path = __DIR__ . '/backups/' . $filename;
        
        // Buat direktori backup jika belum ada
        if (!is_dir(__DIR__ . '/backups')) {
            mkdir(__DIR__ . '/backups', 0755, true);
        }
        
        // Catat penggunaan memori sebelum backup
        log_memory_usage('sebelum backup');
        
        // Siapkan opsi backup
        $backup_options = [
            'compress' => $compress,
            'include_schema' => $include_schema,
            'include_data' => $include_data,
            'backup_type' => $backup_type,
            'selected_tables' => $selected_tables
        ];
        
        // Buat perintah backup yang dioptimalkan untuk database besar
        $command = build_optimized_dump_command($database, $backup_path, $backup_options);
        
        // Log informasi tentang backup
        if ($backup_type === 'tables' && !empty($selected_tables)) {
            log_cron_message("Backup tabel tertentu: " . implode(", ", $selected_tables));
        }
        
        // Catat informasi tentang optimasi
        $cores = get_cpu_cores();
        $memory = get_available_memory();
        log_cron_message("Menggunakan optimasi performa: {$cores} core CPU, {$memory}MB memori tersedia");

        
        log_cron_message("Menjalankan perintah: " . $command);
        
        // Jalankan perintah backup
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);
        
        // Cek hasil backup
        if ($return_var === 0 && file_exists($backup_path)) {
            $file_size = filesize($backup_path);
            $status = 'success';
            $notes = 'Backup otomatis dari jadwal: ' . $schedule['name'];
            
            // Log backup ke database
            log_backup(1, $filename, $file_size, $status, $notes);
            
            log_cron_message("Backup berhasil: $filename ($file_size bytes)");
            
            // Hapus backup lama berdasarkan retensi
            if ($schedule['retention_days'] > 0) {
                $retention_date = date('Y-m-d', strtotime('-' . $schedule['retention_days'] . ' days'));
                
                $stmt = $pdo->prepare("
                    SELECT id, filename FROM backup_history 
                    WHERE created_at < ? 
                    AND notes LIKE ?
                ");
                $stmt->execute([$retention_date, '%' . $schedule['name'] . '%']);
                $old_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($old_backups as $old_backup) {
                    $old_file = __DIR__ . '/backups/' . $old_backup['filename'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                        log_cron_message("Menghapus backup lama: " . $old_backup['filename']);
                    }
                    
                    // Hapus dari database
                    $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
                    $stmt->execute([$old_backup['id']]);
                }
            }
        } else {
            $status = 'failed';
            $notes = 'Gagal melakukan backup otomatis dari jadwal: ' . $schedule['name'];
            
            // Log backup gagal
            log_backup(1, $filename, 0, $status, $notes);
            
            log_cron_message("Backup gagal: $filename. Error code: $return_var");
        }
        
        // Hitung waktu eksekusi berikutnya
        $next_run = new DateTime();
        
        switch ($schedule['frequency']) {
            case 'daily':
                // Jika waktu hari ini sudah lewat, jadwalkan untuk besok
                // Jika belum, jadwalkan untuk hari ini
                $today = new DateTime();
                $today->setTime($schedule['hour'], $schedule['minute']);
                
                if ($today > new DateTime()) {
                    $next_run = $today;
                } else {
                    $next_run->modify('+1 day');
                    $next_run->setTime($schedule['hour'], $schedule['minute']);
                }
                break;
                
            case 'weekly':
                // Pastikan kita mendapatkan hari berikutnya, bukan hari ini
                $next_run->modify('next ' . $schedule['day_of_week']);
                $next_run->setTime($schedule['hour'], $schedule['minute']);
                break;
                
            case 'monthly':
                // Jika hari ini adalah awal bulan dan waktu belum lewat, jadwalkan untuk hari ini
                $today = new DateTime();
                $first_day = new DateTime('first day of this month');
                
                if ($today->format('d') === '01' && $today->setTime($schedule['hour'], $schedule['minute']) > new DateTime()) {
                    $next_run = $today;
                } else {
                    $next_run->modify('first day of next month');
                    $next_run->setTime($schedule['hour'], $schedule['minute']);
                }
                break;
        }
        
        log_cron_message("Jadwal berikutnya dihitung: " . $next_run->format('Y-m-d H:i:s'));
        
        // Update jadwal dengan waktu eksekusi berikutnya dan terakhir
        $stmt = $pdo->prepare("
            UPDATE backup_schedules 
            SET last_run = NOW(), 
                next_run = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$next_run->format('Y-m-d H:i:s'), $schedule['id']]);
        
        log_cron_message("Jadwal diperbarui. Eksekusi berikutnya: " . $next_run->format('Y-m-d H:i:s'));
    }
    
    log_cron_message("Proses cron backup selesai.");
    
} catch (PDOException $e) {
    log_cron_message("Error database: " . $e->getMessage());
    exit(1);
} catch (Exception $e) {
    log_cron_message("Error: " . $e->getMessage());
    exit(1);
}
