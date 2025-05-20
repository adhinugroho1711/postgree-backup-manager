<?php
// Mulai output buffering untuk menghindari error header already sent
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Periksa apakah user sudah login
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Proses form backup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $backup_name = $_POST['backup_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $compress = isset($_POST['compress']);
    $include_data = isset($_POST['include_data']);
    $include_schema = isset($_POST['include_schema']);
    
    // Ambil konfigurasi database dari form
    $db_host = $_POST['db_host'] ?? DB_HOST;
    $db_port = $_POST['db_port'] ?? DB_PORT;
    $db_user = $_POST['db_user'] ?? DB_USER;
    $db_pass = $_POST['db_pass'] ?? DB_PASS;
    $db_name = $_POST['database'] ?? DB_NAME;
    
    // Ambil tabel yang dipilih jika ada
    $selected_tables = $_POST['selected_tables'] ?? [];
    $backup_type = $_POST['backup_type'] ?? 'full';
    
    // Validasi input
    if (empty($backup_name)) {
        $error = 'Nama backup harus diisi';
    } else {
        // Format nama file backup
        $timestamp = date('Ymd_His');
        $filename = "backup_{$backup_name}_{$timestamp}.sql";
        $filepath = BACKUP_DIR . '/' . $filename;
        
        // Buat direktori backup jika belum ada
        if (!file_exists(BACKUP_DIR)) {
            mkdir(BACKUP_DIR, 0755, true);
        }
        
        // Tulis ke file log untuk debugging
        $debug_log = fopen(__DIR__ . '/../debug_backup.log', 'a');
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Memulai proses backup\n");
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Nama file: $filename\n");
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Path file: $filepath\n");
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Host: $db_host, Port: $db_port, DB: $db_name\n");
        
        // Perintah pg_dump dengan parameter dari form
        $command = 'PGPASSWORD="' . $db_pass . '" pg_dump -h ' . $db_host . ' -p ' . $db_port . ' -U ' . $db_user;
        
        // Tambahkan opsi sesuai konfigurasi
        if (!$include_schema) {
            $command .= ' --data-only';
        }
        if (!$include_data) {
            $command .= ' --schema-only';
        }
        
        // Tambahkan tabel spesifik jika dipilih
        if ($backup_type === 'tables' && !empty($selected_tables)) {
            foreach ($selected_tables as $table) {
                $command .= ' -t ' . escapeshellarg($table);
            }
        }
        
        // Tambahkan database dan output
        $command .= ' ' . escapeshellarg($db_name);
        
        // Tambahkan output ke file
        if ($compress) {
            $filepath_final = $filepath . '.gz';
            $command .= ' | gzip > ' . escapeshellarg($filepath_final);
        } else {
            $filepath_final = $filepath;
            $command .= ' > ' . escapeshellarg($filepath_final);
        }
        
        // Set environment variable untuk password
        putenv("PGPASSWORD=" . $db_pass);
        
        // Log perintah untuk debugging (hapus password)
        $log_command = str_replace($db_pass, '******', $command);
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Menjalankan perintah: $log_command\n");
        
        // Eksekusi perintah
        $output = [];
        $return_var = 0;
        exec($command . ' 2>&1', $output, $return_var);
        
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Hasil eksekusi: $return_var\n");
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Output: " . implode("\n", $output) . "\n");
        
        if ($return_var === 0 && file_exists($filepath_final)) {
            fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Berhasil membuat file backup: $filepath_final\n");
            $return_var = 0; // Sukses
        } else {
            fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Gagal membuat file backup\n");
            $return_var = 1; // Gagal
        }
        
        fclose($debug_log);
        
        if ($return_var === 0) {
            // Catat ke database
            $filesize = filesize($filepath_final);
            // Pastikan user_id valid, jika tidak ada gunakan 1 (admin)
            $user_id = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0 ? $_SESSION['user_id'] : 1;
            $backup_id = log_backup(
                $user_id,
                basename($filepath_final),
                $filesize,
                'success',
                $description
            );
            
            if ($backup_id) {
                $success = 'Backup berhasil dibuat: ' . basename($filepath);
                
                // Redirect ke halaman detail backup
                header('Location: index.php?page=backup_detail&id=' . $backup_id);
                exit();
            } else {
                $error = 'Gagal mencatat backup ke database';
                // Hapus file backup yang sudah dibuat
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
        } else {
            $error = 'Gagal membuat backup: ' . implode("\n", $output);
            
            // Hapus file backup yang gagal dibuat
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}

// Dapatkan daftar database
$databases = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false AND datname != 'postgres' AND datname != 'template1' AND datname != 'template0'");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = 'Gagal mengambil daftar database: ' . $e->getMessage();
}

// Debug untuk melihat nilai POST dan SESSION
$debug_log = fopen(__DIR__ . '/../debug_tables.log', 'a');
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] ====== MULAI DEBUG ======\n");
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] POST data: " . print_r($_POST, true) . "\n");
fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] SESSION data: " . print_r($_SESSION, true) . "\n");

// Dapatkan daftar tabel jika database dipilih
$tables = [];
$show_tables_section = false;

// Jika tombol tampilkan tabel diklik
if (isset($_POST['get_tables']) && $_POST['get_tables'] == '1' && !empty($_POST['database'])) {
    fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Mencoba mendapatkan tabel untuk database: {$_POST['database']}\n");
    $selected_db = $_POST['database'];
    $db_host = $_POST['db_host'] ?? DB_HOST;
    $db_port = $_POST['db_port'] ?? DB_PORT;
    $db_user = $_POST['db_user'] ?? DB_USER;
    $db_pass = $_POST['db_pass'] ?? DB_PASS;
    
    try {
        // Buat koneksi ke database yang dipilih
        $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$selected_db";
        $conn = new PDO($dsn, $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Query untuk mendapatkan daftar tabel
        $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Berhasil mendapatkan " . count($tables) . " tabel\n");
        
        // Set backup_type ke 'tables' jika ada tabel yang ditemukan
        if (!empty($tables)) {
            $_POST['backup_type'] = 'tables';
            $show_tables_section = true;
        }
    } catch (PDOException $e) {
        $error = 'Gagal mengambil daftar tabel: ' . $e->getMessage();
        fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n");
    }
}

// Jika tidak ada tabel dari POST, cek apakah ada di session
if (empty($tables) && isset($_SESSION['tables']) && !empty($_SESSION['tables'])) {
    $tables = $_SESSION['tables'];
    $show_tables_section = true;
    fwrite($debug_log, "[" . date('Y-m-d H:i:s') . "] Menggunakan " . count($tables) . " tabel dari session\n");
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Buat Backup Database</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?page=dashboard" class="btn btn-sm btn-outline-secondary">
            <i class='bx bx-arrow-back'></i> Kembali
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Buat Backup Baru</h5>
            </div>
            <div class="card-body">
                <!-- Progress bar untuk backup (awalnya disembunyikan) -->
                <div id="backupProgress" class="mb-4" style="display: none;">
                    <h6 class="mb-2">Proses Backup Sedang Berjalan...</h6>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                    <small class="text-muted mt-2 d-block" id="backupStatus">Mempersiapkan backup...</small>
                </div>
                
                <form method="POST" action="" id="backupForm">
                    <div class="mb-3">
                        <label for="backup_name" class="form-label">Nama Backup <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="backup_name" name="backup_name" required 
                               placeholder="Contoh: backup_harian" value="<?php echo htmlspecialchars($_POST['backup_name'] ?? ''); ?>">
                        <div class="form-text">Gunakan nama yang deskriptif untuk memudahkan identifikasi</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi (Opsional)</label>
                        <textarea class="form-control" id="description" name="description" rows="2" 
                                  placeholder="Contoh: Backup harian untuk database produksi"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Konfigurasi Server Database -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Konfigurasi Server Database</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_host" class="form-label">Host Database</label>
                                        <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($_POST['db_host'] ?? DB_HOST); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_port" class="form-label">Port</label>
                                        <input type="text" class="form-control" id="db_port" name="db_port" value="<?php echo htmlspecialchars($_POST['db_port'] ?? DB_PORT); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_user" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($_POST['db_user'] ?? DB_USER); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="db_pass" class="form-label">Password</label>
                                        <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo htmlspecialchars($_POST['db_pass'] ?? DB_PASS); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="database" class="form-label">Database</label>
                                <select class="form-select" id="database" name="database" required>
                                    <?php foreach ($databases as $db): ?>
                                        <option value="<?php echo htmlspecialchars($db); ?>" 
                                            <?php echo (isset($_POST['database']) && $_POST['database'] === $db) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($db); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="d-grid mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="showTablesBtn">Tampilkan Tabel</button>
                                </div>
                                <div class="form-text">Klik untuk menampilkan daftar tabel dari database yang dipilih</div>
                                <div id="tablesLoading" class="mt-2" style="display:none;">
                                    <div class="d-flex align-items-center">
                                        <div class="spinner-border spinner-border-sm text-secondary me-2" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span>Mengambil daftar tabel...</span>
                                    </div>
                                </div>
                                <div id="tables_selection" class="mt-3" style="display:none;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pemilihan Tabel -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Pilihan Backup</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="backup_type" id="backup_full" value="full" <?php echo (!isset($_POST['backup_type']) || $_POST['backup_type'] === 'full') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_full">
                                        Backup Seluruh Database
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="backup_type" id="backup_tables" value="tables" <?php echo (isset($_POST['backup_type']) && $_POST['backup_type'] === 'tables') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_tables">
                                        Backup Tabel Tertentu
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Bagian ini akan diisi oleh AJAX -->
                            
                            <div class="mb-3">
                                <label class="form-label">Opsi Backup</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="compress" name="compress" <?php echo isset($_POST['compress']) ? 'checked' : 'checked'; ?>>
                                    <label class="form-check-label" for="compress">
                                        Kompresi (GZIP)
                                    </label>
                                    <div class="form-text">Mengurangi ukuran file backup</div>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_schema" name="include_schema" <?php echo !isset($_POST['include_data']) || (isset($_POST['include_schema']) && $_POST['include_schema']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_schema">
                                        Sertakan Skema Database
                                    </label>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include_data" name="include_data" <?php echo !isset($_POST['include_schema']) || (isset($_POST['include_data']) && $_POST['include_data']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="include_data">
                                        Sertakan Data
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary" name="action" value="backup" id="backupButton">
                            <i class='bx bxs-save'></i> Buat Backup
                        </button>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#backupModal">
                            <i class='bx bx-info-circle'></i> Informasi Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Panduan Backup</h6>
            </div>
            <div class="card-body">
                <h6>Tips Backup Aman:</h6>
                <ul class="small">
                    <li>Buat backup secara berkala</li>
                    <li>Simpan backup di lokasi yang aman</li>
                    <li>Verifikasi backup setelah dibuat</li>
                    <li>Enkripsi backup yang berisi data sensitif</li>
                </ul>
                
                <h6 class="mt-3">Ukuran Backup:</h6>
                <ul class="small">
                    <li>Tanpa kompresi: Lebih cepat, lebih besar</li>
                    <li>Dengan kompresi: Lebih lambat, lebih kecil</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Backup Otomatis</h6>
            </div>
            <div class="card-body">
                <p class="small">Atur jadwal backup otomatis untuk memastikan data Anda selalu aman.</p>
                <a href="?page=schedule" class="btn btn-sm btn-outline-primary w-100">
                    <i class='bx bx-time'></i> Atur Jadwal
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Backup -->
<div class="modal fade" id="backupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin membuat backup database?</p>
                
                <div class="mb-3">
                    <strong>Server:</strong> <span id="confirm_db_host"></span><br>
                    <strong>Database:</strong> <span id="confirm_db_name"></span>
                </div>
                
                <div id="confirm_tables" style="display: none;">
                    <strong>Tabel yang dipilih:</strong>
                    <p id="confirm_tables_list" class="mb-2"></p>
                </div>
                
                <div class="alert alert-info">
                    <p class="mb-0"><i class='bx bx-info-circle'></i> Proses backup mungkin memakan waktu beberapa saat tergantung ukuran database.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="confirmBackup">Ya, Buat Backup</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Jika ada tabel yang ditampilkan dan opsi 'tables' dipilih, tampilkan bagian pemilihan tabel
    if ($('#backup_tables').is(':checked')) {
        $('#tables_selection').show();
    }
    
    // Toggle tampilan pemilihan tabel berdasarkan tipe backup
    $('input[name="backup_type"]').change(function() {
        if ($(this).val() === 'tables') {
            $('#tables_selection').show();
        } else {
            $('#tables_selection').hide();
        }
    });
    
    // Tampilkan tabel menggunakan AJAX saat tombol diklik
    $('#showTablesBtn').click(function(e) {
        // Mencegah form disubmit
        e.preventDefault();
        
        // Tampilkan loading spinner
        $('#tablesLoading').show();
        $('#tables_selection').hide();
        
        // Ambil nilai dari form
        var db_host = $('#db_host').val();
        var db_port = $('#db_port').val();
        var db_user = $('#db_user').val();
        var db_pass = $('#db_pass').val();
        var db_name = $('#database').val();
        
        // Tes sederhana dengan ajax_test.php
        $.ajax({
            url: 'handlers/ajax_test.php',
            type: 'POST',
            dataType: 'json',
            data: {
                db_host: db_host,
                db_port: db_port,
                db_user: db_user,
                db_pass: db_pass,
                database: db_name,
                test: 'true'
            },
            success: function(response) {
                // Jika test berhasil, lanjutkan dengan get_tables_ajax.php
                if (response.success) {
                    // Kirim request AJAX untuk mendapatkan tabel
                    $.ajax({
                        url: 'handlers/get_tables_ajax.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            db_host: db_host,
                            db_port: db_port,
                            db_user: db_user,
                            db_pass: db_pass,
                            database: db_name
                        },
                        success: function(response) {
                            // Sembunyikan loading spinner
                            $('#tablesLoading').hide();
                            
                            if (response.success) {
                                // Tampilkan hasil
                                $('#tables_selection').html(response.html).show();
                                
                                // Aktifkan checkbox "Select All"
                                $('#select_all_tables').change(function() {
                                    $('.table-checkbox').prop('checked', $(this).prop('checked'));
                                });
                                
                                // Update checkbox "Select All" saat checkbox tabel berubah
                                $(document).on('change', '.table-checkbox', function() {
                                    if ($('.table-checkbox:checked').length === $('.table-checkbox').length) {
                                        $('#select_all_tables').prop('checked', true);
                                    } else {
                                        $('#select_all_tables').prop('checked', false);
                                    }
                                });
                                
                                // Set opsi backup ke tabel tertentu jika ada tabel
                                if (response.count > 0) {
                                    $('#backup_tables').prop('checked', true);
                                }
                            } else {
                                alert('Error: ' + response.error);
                            }
                        },
                        error: function(xhr, status, error) {
                            // Sembunyikan loading spinner
                            $('#tablesLoading').hide();
                            
                            // Tampilkan pesan error
                            var errorMsg = 'Gagal mengambil daftar tabel';
                            if (xhr.responseJSON && xhr.responseJSON.error) {
                                errorMsg = xhr.responseJSON.error;
                            }
                            
                            alert(errorMsg);
                        }
                    });
                } else {
                    // Sembunyikan loading spinner
                    $('#tablesLoading').hide();
                    alert('Gagal melakukan test koneksi AJAX');
                }
            },
            error: function(xhr, status, error) {
                // Sembunyikan loading spinner
                $('#tablesLoading').hide();
                alert('Gagal menghubungi server: ' + error);
            }
        });
    });
});

// Tangani submit form
$('#backupForm').on('submit', function(e) {
    // Jika tombol 'get_tables' yang diklik, biarkan form disubmit normal
    if ($('#showTablesBtn').is(':focus') || $('button[name="get_tables"]').is(':focus')) {
        return true;
    }
    
    // Untuk aksi backup normal, tampilkan konfirmasi
    e.preventDefault();
    
    // Tampilkan modal konfirmasi
    const modal = new bootstrap.Modal(document.getElementById('backupModal'));
    modal.show();
    
    // Tangani konfirmasi
    $('#confirmBackup').off('click').on('click', function() {
        // Sembunyikan modal
        modal.hide();
        
        // Tampilkan progress bar
        $('#backupProgress').show();
        $('#backupButton').prop('disabled', true);
        $('#backupStatus').text('Memulai proses backup...');
        
        // Simulasi progress (karena kita tidak bisa mendapatkan progress sebenarnya dari pg_dump)
        let progress = 0;
        const progressBar = $('#backupProgress .progress-bar');
        const statusText = $('#backupStatus');
        
        const progressInterval = setInterval(function() {
            // Tingkatkan progress secara bertahap
            if (progress < 90) {
                progress += Math.floor(Math.random() * 5) + 1; // Tambah 1-5% setiap kali
                if (progress > 90) progress = 90; // Jangan lebih dari 90% sampai selesai
                
                // Update progress bar
                progressBar.css('width', progress + '%');
                progressBar.attr('aria-valuenow', progress);
                progressBar.text(progress + '%');
                
                // Update status text berdasarkan progress
                if (progress < 20) {
                    statusText.text('Mempersiapkan backup database...');
                } else if (progress < 40) {
                    statusText.text('Mengekspor struktur tabel...');
                } else if (progress < 60) {
                    statusText.text('Mengekspor data...');
                } else if (progress < 80) {
                    statusText.text('Memproses indeks dan constraint...');
                } else {
                    statusText.text('Menyelesaikan backup...');
                }
            }
        }, 800); // Update setiap 800ms
        
        // Submit form
        setTimeout(function() {
            $('#backupForm').off('submit').submit();
        }, 1000);
    });
});
</script>
