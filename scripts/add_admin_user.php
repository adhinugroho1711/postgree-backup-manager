<?php
// Script untuk menambahkan user admin

// Include konfigurasi dan fungsi
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Buat koneksi ke database
try {
    $pdo = get_db_connection();
    echo "Koneksi ke database berhasil.\n";
    
    // Cek apakah user admin sudah ada
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        // Update password admin yang sudah ada
        $password_hash = md5('admin123');
        $stmt = $pdo->prepare("UPDATE users SET password = ?, is_admin = TRUE WHERE username = ?");
        $result = $stmt->execute([$password_hash, 'admin']);
        
        if ($result) {
            echo "Password user admin berhasil diperbarui.\n";
        } else {
            echo "Gagal memperbarui password user admin.\n";
        }
    } else {
        // Tambahkan user admin baru
        $password_hash = md5('admin123');
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, is_admin) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute(['admin', $password_hash, 'Administrator', 'admin@example.com', TRUE]);
        
        if ($result) {
            echo "User admin berhasil ditambahkan.\n";
        } else {
            echo "Gagal menambahkan user admin.\n";
        }
    }
    
    // Tampilkan data user admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "\nData user admin:\n";
        echo "ID: " . $admin['id'] . "\n";
        echo "Username: " . $admin['username'] . "\n";
        echo "Password Hash: " . $admin['password'] . "\n";
        echo "Full Name: " . $admin['full_name'] . "\n";
        echo "Is Admin: " . ($admin['is_admin'] ? 'Yes' : 'No') . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
