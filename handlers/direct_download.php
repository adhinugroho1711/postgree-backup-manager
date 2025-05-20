<?php
// Script sederhana untuk download file langsung
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (empty($file)) {
    die('Parameter file tidak ditemukan');
}

// Pastikan file berada di direktori backups
$file = basename($file);
$filepath = __DIR__ . '/../backups/' . $file;

// Pastikan direktori log ada
$log_dir = __DIR__ . '/../logs/download';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log untuk debugging
$log = fopen($log_dir . '/direct_download.log', 'a');
fwrite($log, date('Y-m-d H:i:s') . " - Mencoba download file: $file\n");
fwrite($log, date('Y-m-d H:i:s') . " - Path: $filepath\n");

// Cek apakah file ada
if (!file_exists($filepath)) {
    fwrite($log, date('Y-m-d H:i:s') . " - File tidak ditemukan\n");
    fclose($log);
    die('File tidak ditemukan');
}

// Cek apakah file bisa dibaca
if (!is_readable($filepath)) {
    fwrite($log, date('Y-m-d H:i:s') . " - File tidak bisa dibaca\n");
    fclose($log);
    die('File tidak bisa dibaca');
}

// Dapatkan ukuran file
$filesize = filesize($filepath);
fwrite($log, date('Y-m-d H:i:s') . " - Ukuran file: $filesize bytes\n");

// Tentukan tipe konten berdasarkan ekstensi file
$ext = pathinfo($file, PATHINFO_EXTENSION);
if ($ext == 'gz') {
    $content_type = 'application/gzip';
} elseif ($ext == 'sql') {
    $content_type = 'application/sql';
} else {
    $content_type = 'application/octet-stream';
}

// Bersihkan output buffer
while (ob_get_level()) {
    ob_end_clean();
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
readfile($filepath);
fwrite($log, date('Y-m-d H:i:s') . " - Selesai\n");
fclose($log);
exit;
?>
