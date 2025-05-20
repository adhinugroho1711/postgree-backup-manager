<?php
// Mulai session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include konfigurasi dan fungsi
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Koneksi ke database
$pdo = get_db_connection();

// Filter
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_entity = isset($_GET['entity']) ? $_GET['entity'] : '';
$filter_user = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$filter_ip = isset($_GET['ip_address']) ? $_GET['ip_address'] : '';

// Query dasar
$query = "SELECT a.id, a.action, a.table_name, a.record_id, a.old_value, a.new_value, 
          a.ip_address, a.user_agent, a.created_at, u.username, u.full_name 
          FROM audit_log a 
          LEFT JOIN users u ON a.user_id = u.id 
          WHERE 1=1";
$params = [];

// Tambahkan filter ke query
if (!empty($filter_action)) {
    $query .= " AND a.action = ?";
    $params[] = $filter_action;
}

if (!empty($filter_entity)) {
    $query .= " AND a.entity_type = ?";
    $params[] = $filter_entity;
}

if (!empty($filter_user)) {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_date_start)) {
    $query .= " AND a.created_at >= ?";
    $params[] = $filter_date_start . ' 00:00:00';
}

if (!empty($filter_ip)) {
    $query .= " AND a.ip_address LIKE ?";
    $params[] = "%$filter_ip%";
}

if (!empty($filter_date_end)) {
    $query .= " AND a.created_at <= ?";
    $params[] = $filter_date_end . ' 23:59:59';
}

// Urutkan berdasarkan waktu terbaru
$query .= " ORDER BY a.created_at DESC";

// Eksekusi query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set header untuk file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="audit_log_export_' . date('Ymd_His') . '.csv"');

// Buat output stream
$output = fopen('php://output', 'w');

// Tambahkan BOM untuk UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header kolom
fputcsv($output, [
    'ID', 
    'Aksi', 
    'Tabel', 
    'Record ID', 
    'Nilai Lama', 
    'Nilai Baru', 
    'IP Address', 
    'User Agent', 
    'Waktu', 
    'Username', 
    'Nama Lengkap'
]);

// Data
foreach ($logs as $log) {
    // Format nilai JSON untuk CSV
    $old_value = $log['old_value'] ? json_encode(json_decode($log['old_value']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
    $new_value = $log['new_value'] ? json_encode(json_decode($log['new_value']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '';
    
    fputcsv($output, [
        $log['id'],
        $log['action'],
        $log['table_name'],
        $log['record_id'],
        $old_value,
        $new_value,
        $log['ip_address'],
        $log['user_agent'],
        $log['created_at'],
        $log['username'],
        $log['full_name']
    ]);
}

// Tutup file
fclose($output);
exit;
