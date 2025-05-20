<?php
/**
 * Format size in bytes to human readable format
 * 
 * @param int $bytes Size in bytes
 * @param int $precision Number of decimal places
 * @return string Formatted size with unit
 */
function format_size($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Log backup activity to database
 * 
 * @param int $user_id User ID
 * @param string $filename Backup filename
 * @param int $file_size File size in bytes
 * @param string $status Backup status (success, failed)
 * @param string $notes Additional notes
 * @return int Backup ID
 */
/**
 * Inisialisasi tabel-tabel yang diperlukan untuk aplikasi
 */
function initialize_tables() {
    $pdo = get_db_connection();
    
    // Buat tabel backup_history jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS backup_history (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        filename VARCHAR(255) NOT NULL,
        size_bytes BIGINT,
        status VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Buat tabel restore_history jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS restore_history (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        backup_id INTEGER NOT NULL,
        status VARCHAR(50) NOT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Buat tabel audit_log jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INTEGER,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Periksa apakah kolom size_bytes sudah ada di tabel backup_history
    try {
        $pdo->query("SELECT size_bytes FROM backup_history LIMIT 1");
    } catch (PDOException $e) {
        // Jika kolom tidak ada, tambahkan kolom baru
        if (strpos($e->getMessage(), "column \"size_bytes\" does not exist") !== false) {
            $pdo->exec("ALTER TABLE backup_history ADD COLUMN IF NOT EXISTS size_bytes BIGINT");
        }
    }
    
    // Periksa apakah kolom file_size ada di tabel backup_history (kolom lama)
    try {
        $pdo->query("SELECT file_size FROM backup_history LIMIT 1");
        // Jika berhasil, artinya kolom file_size masih ada, migrasi data ke size_bytes
        $pdo->exec("UPDATE backup_history SET size_bytes = file_size WHERE size_bytes IS NULL");
        // Hapus kolom lama (opsional, bisa dikomentari jika tidak ingin menghapus)
        // $pdo->exec("ALTER TABLE backup_history DROP COLUMN file_size");
    } catch (PDOException $e) {
        // Kolom file_size tidak ada, tidak perlu melakukan apa-apa
    }
}

/**
 * Log aktivitas pengguna ke audit log
 * 
 * @param int $user_id ID pengguna
 * @param string $action Aksi yang dilakukan (create, update, delete, login, dll)
 * @param string $entity_type Jenis entitas (backup, restore, user, setting, dll)
 * @param int $entity_id ID entitas (opsional)
 * @param string $details Detail tambahan
 * @return int ID log
 */
function log_audit($user_id, $action, $entity_type, $entity_id = null, $details = '') {
    $pdo = get_db_connection();
    
    try {
        // Dapatkan informasi tambahan
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO audit_log 
            (user_id, action, entity_type, entity_id, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id");
        
        $stmt->execute([
            $user_id,
            $action,
            $entity_type,
            $entity_id,
            $details,
            $ip_address,
            $user_agent
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error logging audit: " . $e->getMessage());
        return 0;
    }
}

function log_backup($user_id, $filename, $file_size, $status, $notes = '') {
    // Pastikan direktori log backup ada
    $log_dir = __DIR__ . '/../logs/backup';
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Tulis ke file log untuk debugging
    $log_file = fopen($log_dir . '/backup_error.log', 'a');
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba mencatat backup: $filename, size: $file_size\n");
    
    // Validasi user_id - pastikan user ada di database
    // Jika user_id tidak valid, gunakan user_id=1 (admin)
    $pdo = get_db_connection();
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Koneksi database berhasil\n");
    
    // Periksa apakah user_id valid
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] User ID $user_id tidak valid, menggunakan user_id=1 (admin)\n");
        $user_id = 1; // Default ke admin jika user tidak ditemukan
    }
    
    // Pendekatan khusus PostgreSQL dengan prepared statement yang aman
    try {
        // Gunakan prepared statement untuk keamanan
        $stmt = $pdo->prepare("INSERT INTO backup_history (user_id, filename, size_bytes, status, notes) 
                             VALUES (:user_id, :filename, :size_bytes, :status, :notes) RETURNING id");
        
        // Bind parameter
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindParam(':size_bytes', $file_size, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Menjalankan query dengan user_id=$user_id\n");
        
        // Eksekusi query
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Hasil query: " . print_r($row, true) . "\n");
        
        if ($row && isset($row['id'])) {
            $backup_id = $row['id'];
            fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] ID backup: $backup_id\n");
            
            // Catat ke audit log
            try {
                log_audit($user_id, 'create', 'backup', $backup_id, "Backup dibuat: $filename ($status)");
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Berhasil mencatat ke audit log\n");
            } catch (Exception $e) {
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error saat mencatat ke audit log: " . $e->getMessage() . "\n");
            }
            
            fclose($log_file);
            return $backup_id;
        } else {
            fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Tidak mendapatkan ID dari RETURNING\n");
        }
    } catch (PDOException $e) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n");
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n");
    }
    
    // Jika sampai di sini, berarti pendekatan pertama gagal
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba pendekatan kedua dengan sequence\n");
    
    try {
        // Pendekatan kedua: Gunakan INSERT tanpa RETURNING dan dapatkan ID dari sequence
        $stmt = $pdo->prepare("INSERT INTO backup_history (user_id, filename, size_bytes, status, notes) 
                             VALUES (:user_id, :filename, :size_bytes, :status, :notes)");
        
        // Bind parameter
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindParam(':size_bytes', $file_size, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Menjalankan query dengan user_id=$user_id\n");
        
        // Eksekusi query
        $stmt->execute();
        
        // Dapatkan ID dari sequence
        $backup_id = $pdo->lastInsertId('backup_history_id_seq');
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] ID dari sequence: $backup_id\n");
        
        if ($backup_id) {
            // Catat ke audit log
            try {
                log_audit($user_id, 'create', 'backup', $backup_id, "Backup dibuat: $filename ($status)");
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Berhasil mencatat ke audit log\n");
            } catch (Exception $e) {
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error saat mencatat ke audit log: " . $e->getMessage() . "\n");
            }
            
            fclose($log_file);
            return $backup_id;
        }
    } catch (PDOException $e) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error pendekatan kedua: " . $e->getMessage() . "\n");
    }
    
    // Jika sampai di sini, berarti kedua pendekatan gagal
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba pendekatan ketiga dengan query langsung\n");
    
    try {
        // Pendekatan ketiga: Gunakan query langsung untuk mendapatkan ID terakhir
        $stmt = $pdo->prepare("INSERT INTO backup_history (user_id, filename, size_bytes, status, notes) 
                             VALUES (:user_id, :filename, :size_bytes, :status, :notes)");
        
        // Bind parameter
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindParam(':size_bytes', $file_size, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        
        // Eksekusi query
        $stmt->execute();
        
        $stmt = $pdo->query("SELECT MAX(id) as last_id FROM backup_history");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $backup_id = $row['last_id'] ?? 0;
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] ID dari MAX(id): $backup_id\n");
        
        if ($backup_id) {
            // Catat ke audit log
            try {
                log_audit($user_id, 'create', 'backup', $backup_id, "Backup dibuat: $filename ($status)");
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Berhasil mencatat ke audit log\n");
            } catch (Exception $e) {
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error saat mencatat ke audit log: " . $e->getMessage() . "\n");
            }
            
            fclose($log_file);
            return $backup_id;
        }
    } catch (PDOException $e) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error pendekatan ketiga: " . $e->getMessage() . "\n");
    }
    
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Semua pendekatan gagal, tidak dapat mencatat backup\n");
    fclose($log_file);
    return 0;
}

/**
 * Log restore activity to database
 * 
 * @param int $user_id User ID
 * @param int $backup_id Backup ID
 * @param string $status Restore status (success, failed)
 * @param string $notes Additional notes
 * @return int Restore ID
 */
function log_restore($user_id, $backup_id, $status, $notes = '') {
    // Tulis ke file log untuk debugging
    $log_file = fopen(__DIR__ . '/../restore_error.log', 'a');
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba mencatat restore: backup_id=$backup_id, status=$status\n");
    
    // Validasi user_id - pastikan user ada di database
    // Jika user_id tidak valid, gunakan user_id=1 (admin)
    $pdo = get_db_connection();
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Koneksi database berhasil\n");
    
    // Periksa apakah user_id valid
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] User ID $user_id tidak valid, menggunakan user_id=1 (admin)\n");
        $user_id = 1; // Default ke admin jika user tidak ditemukan
    }
    
    // Pendekatan khusus PostgreSQL dengan prepared statement yang aman
    try {
        // Gunakan prepared statement untuk keamanan
        $stmt = $pdo->prepare("INSERT INTO restore_history (user_id, backup_id, status, notes) 
                             VALUES (:user_id, :backup_id, :status, :notes) RETURNING id");
        
        // Bind parameter
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':backup_id', $backup_id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Menjalankan query dengan user_id=$user_id\n");
        
        // Eksekusi query
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Hasil query: " . print_r($row, true) . "\n");
        
        if ($row && isset($row['id'])) {
            $restore_id = $row['id'];
            fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] ID restore: $restore_id\n");
            
            // Catat ke audit log
            try {
                log_audit($user_id, 'create', 'restore', $restore_id, "Restore dilakukan: $status");
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Berhasil mencatat ke audit log\n");
            } catch (Exception $e) {
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error saat mencatat ke audit log: " . $e->getMessage() . "\n");
            }
            
            fclose($log_file);
            return $restore_id;
        } else {
            fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Tidak mendapatkan ID dari RETURNING\n");
        }
    } catch (PDOException $e) {
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n");
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Trace: " . $e->getTraceAsString() . "\n");
    }
    
    // Jika sampai di sini, berarti pendekatan pertama gagal
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba pendekatan kedua dengan sequence\n");
    
    try {
        // Pendekatan kedua: Gunakan INSERT tanpa RETURNING dan dapatkan ID dari sequence
        $stmt = $pdo->prepare("INSERT INTO restore_history (user_id, backup_id, status, notes) 
                             VALUES (:user_id, :backup_id, :status, :notes)");
        
        // Bind parameter
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':backup_id', $backup_id, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
        
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Menjalankan query dengan user_id=$user_id\n");
        
        // Eksekusi query
        $stmt->execute();
        
        // Dapatkan ID dari sequence
        $restore_id = $pdo->lastInsertId('restore_history_id_seq');
        fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] ID dari sequence: $restore_id\n");
        
        if ($restore_id) {
            // Catat ke audit log
            try {
                log_audit($user_id, 'create', 'restore', $restore_id, "Restore dilakukan: $status");
                fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Berhasil mencatat ke audit log\n");
            } catch (Exception $e) {
                // Abaikan error di audit log
                error_log("Error saat mencatat ke audit log: " . $e->getMessage());
            }
            
            return $restore_id;
        } else {
            error_log("Gagal mendapatkan ID restore yang baru dibuat");
            return 0;
        }
    } catch (PDOException $e) {
        error_log("Error saat mencatat restore: " . $e->getMessage());
        
        // Coba pendekatan alternatif jika pendekatan utama gagal
        try {
            // Gunakan query langsung
            $query = "INSERT INTO restore_history (user_id, backup_id, status, notes) 
                     VALUES ('$user_id', '$backup_id', '$status', '$notes') RETURNING id";
            
            $result = $pdo->query($query);
            if ($result) {
                $row = $result->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['id'])) {
                    $restore_id = $row['id'];
                    
                    // Dapatkan nama file backup
                    $backup_filename = '';
                    try {
                        $stmt = $pdo->query("SELECT filename FROM backup_history WHERE id = $backup_id");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($result) {
                            $backup_filename = $result['filename'];
                        }
                    } catch (PDOException $e) {
                        // Abaikan error jika tidak bisa mendapatkan nama file
                    }
                    
                    // Log ke audit log
                    try {
                        $details = "Restore dilakukan" . ($backup_filename ? " dari backup: $backup_filename" : "") . " ($status)";
                        log_audit($user_id, 'restore', 'database', $restore_id, $details);
                    } catch (Exception $e) {
                        // Abaikan error di audit log
                    }
                    
                    return $restore_id;
                }
            }
            
            error_log("Pendekatan alternatif juga gagal untuk restore");
            return 0;
        } catch (PDOException $e2) {
            error_log("Pendekatan alternatif error untuk restore: " . $e2->getMessage());
            return 0;
        }
    }
}

/**
 * Get the total size of a directory
 * 
 * @param string $directory Path to directory
 * @return int Total size in bytes
 */
function get_directory_size($directory) {
    $size = 0;
    
    if (!is_dir($directory)) {
        return $size;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    
    foreach ($files as $file) {
        if ($file->isFile()) {
            $size += $file->getSize();
        }
    }
    
    return $size;
}

/**
 * Get the latest backup files
 * 
 * @param int $limit Number of recent backups to return
 * @return array Array of backup files
 */
function get_recent_backups($limit = 5) {
    $backup_dir = defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../backups';
    $backups = [];
    
    if (is_dir($backup_dir)) {
        $files = glob($backup_dir . '/*.sql*');
        
        // Sort by filemtime (newest first)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Limit the number of results
        $backups = array_slice($files, 0, $limit);
    }
    
    return $backups;
}
