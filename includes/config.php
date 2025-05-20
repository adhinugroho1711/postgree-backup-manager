<?php
// Include konfigurasi database
require_once __DIR__ . '/../config/database.php';

// Inisialisasi database (buat tabel jika belum ada)
function init_database() {
    $pdo = get_db_connection();
    
    // Buat tabel users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            is_admin BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Buat tabel backup_history
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS backup_history (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id),
            filename VARCHAR(255) NOT NULL,
            size BIGINT NOT NULL,
            status VARCHAR(20) NOT NULL,
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Buat tabel restore_history
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS restore_history (
            id SERIAL PRIMARY KEY,
            user_id INTEGER REFERENCES users(id),
            backup_id INTEGER REFERENCES backup_history(id),
            status VARCHAR(20) NOT NULL,
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Buat user admin default jika belum ada
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, is_admin) 
            VALUES ('admin', ?, 'Administrator', 'admin@example.com', TRUE)
        ")->execute([$hashed_password]);
    }
}

// Panggil fungsi inisialisasi database
init_database();

// Fungsi untuk mencatat aktivitas backup
function log_backup($user_id, $filename, $size, $status, $message = '') {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        INSERT INTO backup_history (user_id, filename, size, status, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $filename, $size, $status, $message]);
    return $pdo->lastInsertId();
}

// Fungsi untuk mencatat aktivitas restore
function log_restore($user_id, $backup_id, $status, $message = '') {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("
        INSERT INTO restore_history (user_id, backup_id, status, message)
        VALUES (?, ?, ?, ?)
    ");
    return $stmt->execute([$user_id, $backup_id, $status, $message]);
}

// Fungsi untuk mendapatkan daftar backup
function get_backup_list() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("
        SELECT bh.*, u.username 
        FROM backup_history bh
        JOIN users u ON bh.user_id = u.id
        ORDER BY bh.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan statistik backup
function get_backup_stats() {
    $pdo = get_db_connection();
    
    $stats = [
        'total' => 0,
        'success' => 0,
        'failed' => 0,
        'total_size' => 0,
        'last_7_days' => 0
    ];
    
    // Total backup
    $stmt = $pdo->query("SELECT COUNT(*) FROM backup_history");
    $stats['total'] = $stmt->fetchColumn();
    
    // Total backup berhasil
    $stmt = $pdo->query("SELECT COUNT(*) FROM backup_history WHERE status = 'success'");
    $stats['success'] = $stmt->fetchColumn();
    
    // Total backup gagal
    $stats['failed'] = $stats['total'] - $stats['success'];
    
    // Total ukuran backup
    $stmt = $pdo->query("SELECT COALESCE(SUM(size), 0) FROM backup_history WHERE status = 'success'");
    $stats['total_size'] = $stmt->fetchColumn();
    
    // Backup 7 hari terakhir
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM backup_history 
        WHERE created_at >= NOW() - INTERVAL '7 days'
    ");
    $stats['last_7_days'] = $stmt->fetchColumn();
    
    return $stats;
}

// Format ukuran file
function format_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');
