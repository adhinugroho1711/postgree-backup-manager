<?php
// Script sederhana untuk mendownload file backup

// Matikan pelaporan error untuk menghindari output yang tidak diinginkan
error_reporting(0);

// Pastikan parameter filename ada
if (!isset($_GET['filename']) || empty($_GET['filename'])) {
    die("Error: Nama file tidak valid");
}

// Bersihkan nama file untuk keamanan
$filename = basename($_GET['filename']);
$filepath = __DIR__ . '/backups/' . $filename;

// Tulis ke file log untuk debugging
$log_file = fopen(__DIR__ . '/download_error.log', 'a');
fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Mencoba download file: $filename\n");
fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Path file: $filepath\n");

// Periksa apakah file ada
if (!file_exists($filepath)) {
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error: File tidak ditemukan\n");
    fclose($log_file);
    die("Error: File tidak ditemukan");
}

// Periksa apakah file dapat dibaca
if (!is_readable($filepath)) {
    fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Error: File tidak dapat dibaca\n");
    fclose($log_file);
    die("Error: File tidak dapat dibaca");
}

// Dapatkan ukuran file
$filesize = filesize($filepath);
fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] Ukuran file: $filesize bytes\n");

// Hapus semua output yang mungkin sudah ada
if (ob_get_level()) {
    ob_end_clean();
}

// Set header untuk download
header('Content-Description: File Transfer');

// Set tipe konten berdasarkan ekstensi file
$extension = pathinfo($filename, PATHINFO_EXTENSION);
if ($extension === 'gz') {
    header('Content-Type: application/gzip');
} else {
    header('Content-Type: application/octet-stream');
}

header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Transfer-Encoding: binary');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');
header('Content-Length: ' . $filesize);

// Baca file dan kirim ke output
readfile($filepath);

// Catat ke log
fwrite($log_file, "[" . date('Y-m-d H:i:s') . "] File berhasil didownload\n");
fclose($log_file);

// Keluar untuk menghindari output tambahan
exit();
