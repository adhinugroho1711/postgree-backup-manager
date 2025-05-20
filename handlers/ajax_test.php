<?php
// File test sederhana untuk AJAX
header('Content-Type: application/json');

// Pastikan direktori log ada
$log_dir = __DIR__ . '/../logs/ajax';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Buat log file
$log_file = $log_dir . '/ajax_test.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$timestamp] Request diterima\n", FILE_APPEND);
file_put_contents($log_file, "[$timestamp] POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Kirim respons
echo json_encode([
    'success' => true,
    'message' => 'Test AJAX berhasil',
    'timestamp' => date('Y-m-d H:i:s'),
    'post_data' => $_POST
]);
?>