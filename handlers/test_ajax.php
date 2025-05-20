<?php
// File test sederhana untuk AJAX
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Test AJAX berhasil',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
