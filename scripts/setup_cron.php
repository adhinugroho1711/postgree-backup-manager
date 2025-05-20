<?php
/**
 * Script untuk mengatur cron job secara otomatis
 * Mendukung sistem operasi Linux dan macOS
 */

// Pastikan script hanya dijalankan dari web dengan parameter khusus
$is_authorized = isset($_GET['setup_key']) && $_GET['setup_key'] === 'backup_setup_key';

if (!$is_authorized) {
    header('HTTP/1.1 403 Forbidden');
    exit('Akses ditolak');
}

// Set header untuk output teks
header('Content-Type: text/plain');

// Dapatkan path absolut ke script cron_backup.php
$script_path = realpath(__DIR__ . '/cron_backup.php');

if (!$script_path) {
    echo "ERROR: Tidak dapat menemukan file cron_backup.php\n";
    exit(1);
}

// Deteksi sistem operasi
$os_type = php_uname('s');
$is_macos = (stripos($os_type, 'darwin') !== false);
$is_linux = (stripos($os_type, 'linux') !== false);

echo "Sistem Operasi Terdeteksi: $os_type\n";

if (!$is_macos && !$is_linux) {
    echo "ERROR: Sistem operasi tidak didukung. Hanya macOS dan Linux yang didukung.\n";
    exit(1);
}

// Cek apakah crontab tersedia
if (!file_exists('/usr/bin/crontab') && !file_exists('/bin/crontab')) {
    echo "ERROR: Perintah crontab tidak ditemukan. Pastikan cron terinstal di sistem Anda.\n";
    exit(1);
}

// Dapatkan user saat ini
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $current_user = posix_getpwuid(posix_geteuid());
    $username = $current_user['name'];
} else {
    // Fallback jika fungsi posix tidak tersedia
    $username = getenv('USER') ?: getenv('USERNAME') ?: 'current';
}

// Perintah untuk menambahkan cron job
// Jalankan setiap 5 menit untuk mengurangi beban server
$cron_command = "*/5 * * * * php $script_path > /dev/null 2>&1";

// Cek apakah cron job sudah ada
$check_command = "crontab -l | grep -F " . escapeshellarg($script_path);
exec($check_command, $output, $return_var);

if ($return_var === 0) {
    echo "Cron job sudah terpasang untuk user $username\n";
    echo "Perintah: $cron_command\n";
    exit(0);
}

// Tambahkan cron job baru
$temp_file = tempnam(sys_get_temp_dir(), 'cron');
exec("crontab -l > $temp_file 2>/dev/null", $output, $return_var);

// Jika user belum memiliki crontab, buat baru
if ($return_var !== 0) {
    file_put_contents($temp_file, "# Crontab untuk backup PostgreSQL otomatis\n");
}

// Tambahkan cron job baru
file_put_contents($temp_file, file_get_contents($temp_file) . "\n$cron_command\n");

// Pasang crontab baru
exec("crontab $temp_file", $output, $return_var);
unlink($temp_file);

if ($return_var === 0) {
    echo "Berhasil memasang cron job untuk user $username\n";
    echo "Perintah: $cron_command\n";
    echo "Backup akan berjalan setiap menit dan memeriksa jadwal yang perlu dieksekusi\n";
    
    // Tambahkan petunjuk khusus untuk macOS
    if ($is_macos) {
        echo "\nCatatan untuk macOS:\n";
        echo "- Pastikan PHP memiliki izin untuk mengakses sistem file\n";
        echo "- Jika menggunakan macOS Catalina atau lebih baru, Anda mungkin perlu memberikan izin Full Disk Access untuk Terminal/PHP\n";
        echo "- Untuk memeriksa apakah cron job berjalan, jalankan: 'crontab -l' di Terminal\n";
    }
    
    // Tambahkan petunjuk khusus untuk Linux
    if ($is_linux) {
        echo "\nCatatan untuk Linux:\n";
        echo "- Pastikan layanan cron berjalan dengan perintah: 'systemctl status cron' atau 'service cron status'\n";
        echo "- Jika tidak berjalan, aktifkan dengan: 'systemctl enable cron && systemctl start cron'\n";
        echo "- Untuk memeriksa log cron, lihat file: '/var/log/syslog' atau '/var/log/cron'\n";
    }
} else {
    echo "ERROR: Gagal memasang cron job\n";
    echo "Periksa apakah Anda memiliki izin untuk mengubah crontab\n";
    exit(1);
}
?>
