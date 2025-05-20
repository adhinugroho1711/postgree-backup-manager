<?php
// Script untuk mendapatkan daftar tabel dari database
session_start();

// Pastikan direktori log ada
$log_dir = __DIR__ . '/../logs/backup';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

// Log semua request untuk debugging
$log_file = $log_dir . '/get_tables.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$timestamp] Request diterima\n", FILE_APPEND);
file_put_contents($log_file, "[$timestamp] POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);

// Pastikan metode request adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Metode tidak diizinkan']);
    exit;
}

// Ambil parameter dari request
$db_host = $_POST['db_host'] ?? '';
$db_port = $_POST['db_port'] ?? '';
$db_user = $_POST['db_user'] ?? '';
$db_pass = $_POST['db_pass'] ?? '';
$db_name = $_POST['database'] ?? $_POST['db_name'] ?? '';

// Validasi parameter
if (empty($db_host) || empty($db_port) || empty($db_user) || empty($db_name)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Parameter tidak lengkap']);
    exit;
}

// Log untuk debugging
$log_file = __DIR__ . '/debug_ajax_tables.log';
$timestamp = date('Y-m-d H:i:s');
file_put_contents($log_file, "[$timestamp] Mencoba mendapatkan tabel untuk database: $db_name di $db_host:$db_port\n", FILE_APPEND);

try {
    // Buat koneksi ke database
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
    $conn = new PDO($dsn, $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query untuk mendapatkan daftar tabel
    $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Simpan tabel ke session untuk digunakan kembali
    $_SESSION['tables'] = $tables;
    $_SESSION['selected_db'] = $db_name;
    
    // Log hasil
    file_put_contents($log_file, "[$timestamp] Berhasil mendapatkan " . count($tables) . " tabel\n", FILE_APPEND);
    
    // Siapkan HTML untuk tabel
    $html = '';
    if (!empty($tables)) {
        $html .= '<div class="alert alert-success mb-3">';
        $html .= '<i class="bx bx-check-circle"></i> Berhasil mendapatkan ' . count($tables) . ' tabel dari database <strong>' . htmlspecialchars($db_name) . '</strong>';
        $html .= '</div>';
        $html .= '<label class="form-label">Pilih Tabel</label>';
        $html .= '<div class="table-responsive" style="max-height: 200px; overflow-y: auto;">';
        $html .= '<table class="table table-sm table-hover">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th width="30">';
        $html .= '<div class="form-check">';
        $html .= '<input class="form-check-input" type="checkbox" id="select_all_tables">';
        $html .= '</div>';
        $html .= '</th>';
        $html .= '<th>Nama Tabel</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        foreach ($tables as $table) {
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<div class="form-check">';
            $html .= '<input class="form-check-input table-checkbox" type="checkbox" name="selected_tables[]" value="' . htmlspecialchars($table) . '">';
            $html .= '</div>';
            $html .= '</td>';
            $html .= '<td>' . htmlspecialchars($table) . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '<div class="form-text">Pilih tabel yang ingin di-backup</div>';
    } else {
        $html .= '<div class="alert alert-warning">';
        $html .= '<i class="bx bx-info-circle"></i> Tidak ada tabel yang ditemukan di database <strong>' . htmlspecialchars($db_name) . '</strong>';
        $html .= '</div>';
    }
    
    // Kirim respons sukses
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Berhasil mendapatkan ' . count($tables) . ' tabel',
        'html' => $html,
        'count' => count($tables)
    ]);
    
} catch (PDOException $e) {
    // Log error
    file_put_contents($log_file, "[$timestamp] Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    // Kirim respons error
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'error' => 'Gagal mengambil daftar tabel: ' . $e->getMessage()
    ]);
}
?>
