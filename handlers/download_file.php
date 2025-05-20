<?php
// Script sederhana untuk download file langsung tanpa redirect
// Matikan semua pelaporan error
error_reporting(0);
ini_set('display_errors', 0);

// Pastikan parameter file ada
if (!isset($_GET['file']) || empty($_GET['file'])) {
    echo "Error: Parameter file tidak ditemukan";
    exit;
}

// Bersihkan nama file
$file = basename($_GET['file']);
$filepath = __DIR__ . '/../backups/' . $file;

// Pastikan direktori log ada
$log_dir = __DIR__ . '/../logs/download';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log untuk debugging
$log = fopen($log_dir . '/download_file.log', 'a');
fwrite($log, date('Y-m-d H:i:s') . " - Mencoba download file: $file\n");
fwrite($log, date('Y-m-d H:i:s') . " - Path lengkap: $filepath\n");

// Cek apakah file ada
if (!file_exists($filepath)) {
    fwrite($log, date('Y-m-d H:i:s') . " - File tidak ditemukan\n");
    fclose($log);
    echo "Error: File $file tidak ditemukan";
    exit;
}

// Cek apakah file bisa dibaca
if (!is_readable($filepath)) {
    fwrite($log, date('Y-m-d H:i:s') . " - File tidak bisa dibaca\n");
    fclose($log);
    echo "Error: File $file tidak bisa dibaca";
    exit;
}

// Dapatkan ukuran file
$filesize = filesize($filepath);
fwrite($log, date('Y-m-d H:i:s') . " - Ukuran file: $filesize bytes\n");

// Bersihkan semua output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Tentukan tipe konten berdasarkan ekstensi file
$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if ($ext == 'gz') {
    $content_type = 'application/gzip';
} elseif ($ext == 'sql') {
    $content_type = 'application/sql';
} else {
    $content_type = 'application/octet-stream';
}

// Set header untuk download
header("Content-Type: $content_type");
header("Content-Disposition: attachment; filename=\"$file\"");
header("Content-Length: $filesize");
header('Pragma: public');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Expires: 0');

// Baca file dan kirim ke output
fwrite($log, date('Y-m-d H:i:s') . " - Mengirim file ke browser\n");

// Gunakan readfile untuk file kecil, atau streaming untuk file besar
if ($filesize < 10 * 1024 * 1024) { // Kurang dari 10MB
    readfile($filepath);
} else {
    // Streaming untuk file besar
    $handle = fopen($filepath, 'rb');
    if ($handle) {
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
    }
}

fwrite($log, date('Y-m-d H:i:s') . " - Selesai\n");
fclose($log);
exit;
?>
