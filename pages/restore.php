<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/performance.php';

// Pastikan user sudah login
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Dapatkan daftar backup
$backups = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM backup_history WHERE status = 'success' ORDER BY created_at DESC");
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Gagal mengambil daftar backup: ' . $e->getMessage();
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

// Proses form restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    $backup_option = $_POST['backup_option'] ?? 'existing';
    $backup_id = $_POST['backup_id'] ?? 0;
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK && $backup_option === 'upload') {
        $error = 'Terjadi kesalahan saat mengunggah file: ' . $file['error'];
    } else {
        $filename = '';
        $filepath = '';
        
        try {
            if ($backup_option === 'upload') {
                // Handle file upload
                $filename = basename($file['name']);
                $filepath = BACKUP_DIR . '/' . uniqid() . '_' . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception('Gagal menyimpan file upload');
                }
                
                // Catat backup yang diupload
                $backup_id = log_backup(
                    $_SESSION['user_id'],
                    $filename,
                    filesize($filepath),
                    'success',
                    'Backup diupload secara manual'
                );
            } else {
                // Gunakan backup yang ada
                $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
                $stmt->execute([$backup_id]);
                $backup = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$backup) {
                    throw new Exception('Backup tidak ditemukan');
                }
                
                $filename = $backup['filename'];
                $filepath = BACKUP_DIR . '/' . $filename;
                
                if (!file_exists($filepath)) {
                    throw new Exception('File backup tidak ditemukan di server');
                }
            }
            
            // Periksa apakah file adalah gzip
            $is_gzip = (pathinfo($filepath, PATHINFO_EXTENSION) === 'gz');
            
            // Ambil parameter database dari form
            $db_host = isset($_POST['db_host']) ? $_POST['db_host'] : DB_HOST;
            $db_port = isset($_POST['db_port']) ? $_POST['db_port'] : DB_PORT;
            $db_name = isset($_POST['db_name']) ? $_POST['db_name'] : DB_NAME;
            $db_user = isset($_POST['db_user']) ? $_POST['db_user'] : DB_USER;
            $db_pass = isset($_POST['db_pass']) && !empty($_POST['db_pass']) ? $_POST['db_pass'] : DB_PASS;
            
            // Log parameter database untuk debugging
            error_log("Restore ke database: host=$db_host, port=$db_port, dbname=$db_name, user=$db_user");
            
            // Ambil opsi restore dan tabel yang dipilih
            $restore_type = $_POST['restore_type'] ?? 'full';
            $selected_tables = $_POST['selected_tables'] ?? [];
            
            // Validasi opsi restore
            if ($restore_type === 'tables' && empty($selected_tables)) {
                throw new Exception('Anda harus memilih setidaknya satu tabel untuk restore');
            }
            
            // Catat penggunaan memori sebelum restore
            $memory_start = memory_get_usage() / 1024 / 1024;
            $time_start = microtime(true);
            
            // Log informasi tentang optimasi
            $cores = get_cpu_cores();
            $memory = get_available_memory();
            $log_message = "Menggunakan optimasi performa: {$cores} core CPU, {$memory}MB memori tersedia";
            error_log($log_message);
            
            // Perintah dasar untuk restore
            if ($restore_type === 'full') {
                // Siapkan opsi restore
                $restore_options = [
                    'is_compressed' => $is_gzip,
                    'restore_type' => 'full'
                ];
                
                // Buat perintah restore yang dioptimalkan
                $command = build_optimized_restore_command($db_name, $filepath, $restore_options);
            } else {
                // Restore tabel tertentu
                // Siapkan opsi restore
                $restore_options = [
                    'is_compressed' => $is_gzip,
                    'restore_type' => 'tables',
                    'selected_tables' => $selected_tables
                ];
                
                // Deteksi jenis file backup (custom atau plain SQL)
                $is_custom_format = false;
                if (!$is_gzip) {
                    // Cek apakah file adalah format custom PostgreSQL
                    $file_header = @file_get_contents($filepath, false, null, 0, 5);
                    $is_custom_format = $file_header === 'PGDMP';
                }
                
                // Tambahkan informasi format ke opsi restore
                $restore_options['is_custom_format'] = $is_custom_format;
                
                // Buat perintah restore yang dioptimalkan
                $command = build_optimized_restore_command($db_name, $filepath, $restore_options);
                
                // Log informasi tentang tabel yang dipilih
                error_log("Restore tabel tertentu: " . implode(", ", $selected_tables));
            }
            
            // Catat waktu mulai eksekusi
            $time_start = microtime(true);
            
            // Log perintah yang akan dijalankan
            error_log("Menjalankan perintah restore: " . $command);
            
            // Eksekusi perintah restore
            $output = [];
            $return_var = 0;
            exec($command . ' 2>&1', $output, $return_var);
            
            if ($return_var === 0) {
                // Catat aktivitas restore
                // Pastikan user_id valid, jika tidak ada gunakan 1 (admin)
                $user_id = isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0 ? $_SESSION['user_id'] : 1;
                log_restore(
                    $user_id,
                    $backup_id,
                    'success',
                    'Restore berhasil dari ' . $filename
                );
                
                // Catat waktu selesai dan penggunaan memori
                $time_end = microtime(true);
                $memory_end = memory_get_usage() / 1024 / 1024;
                $execution_time = round($time_end - $time_start, 2);
                $memory_usage = round($memory_end - $memory_start, 2);
                
                // Log informasi performa
                error_log("Restore selesai dalam $execution_time detik dengan penggunaan memori $memory_usage MB");
                
                // Tampilkan hasil restore
                $success = 'Database berhasil direstore dari backup: ' . $filename;
                
                // Redirect ke halaman reports dengan pesan sukses
                $_SESSION['success_message'] = 'Database berhasil direstore dari backup: ' . $filename;
                header('Location: index.php?page=reports');
                exit();
            } else {
                throw new Exception('Gagal melakukan restore: ' . implode("\n", $output));
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            
            // Hapus file yang diupload jika gagal
            if (isset($filepath) && $backup_option === 'upload' && file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Restore Database</h1>
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
                <h5 class="mb-0">Pulihkan Database</h5>
            </div>
            <div class="card-body">
                <!-- Progress bar untuk restore (awalnya disembunyikan) -->
                <div id="restoreProgress" class="mb-4" style="display: none;">
                    <h6 class="mb-2">Proses Restore Sedang Berjalan...</h6>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                    <small class="text-muted mt-2 d-block" id="restoreStatus">Mempersiapkan restore...</small>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data" id="restoreForm">
                    <div class="mb-4">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="backup_option" id="existingBackup" value="existing" checked>
                            <label class="form-check-label fw-bold" for="existingBackup">
                                Gunakan Backup yang Ada
                            </label>
                        </div>
                        
                        <div id="existingBackupSection" class="ms-4 mb-4">
                            <select class="form-select" name="backup_id" id="backup_id">
                                <?php foreach ($backups as $backup): ?>
                                    <option value="<?php echo $backup['id']; ?>">
                                        <?php echo htmlspecialchars($backup['filename'] . ' (' . date('d M Y H:i', strtotime($backup['created_at'])) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($backups)): ?>
                                <div class="alert alert-warning mt-2 mb-0">
                                    <i class='bx bx-info-circle'></i> Tidak ada backup yang tersedia.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="backup_option" id="uploadBackup" value="upload">
                            <label class="form-check-label fw-bold" for="uploadBackup">
                                Unggah File Backup
                            </label>
                        </div>
                        
                        <div id="uploadBackupSection" class="ms-4 mb-4" style="display: none;">
                            <div class="mb-3">
                                <label for="backup_file" class="form-label">Pilih File Backup</label>
                                <input class="form-control" type="file" id="backup_file" name="backup_file" accept=".sql,.sql.gz,.backup">
                                <div class="form-text">Format yang didukung: .sql, .sql.gz, .backup</div>
                            </div>
                        </div>
                        
                        <div class="mb-3 border-top pt-3">
                            <h6 class="mb-3">Konfigurasi Database Tujuan</h6>
                            
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Host Database</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo DB_HOST; ?>" required>
                                <div class="form-text">Alamat server database PostgreSQL</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_port" class="form-label">Port</label>
                                <input type="text" class="form-control" id="db_port" name="db_port" value="<?php echo DB_PORT; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Nama Database</label>
                                <select class="form-select" id="db_name" name="db_name" required>
                                    <?php foreach ($databases as $db): ?>
                                        <option value="<?php echo htmlspecialchars($db); ?>" 
                                            <?php echo ($db == DB_NAME) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($db); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Database harus sudah ada sebelum melakukan restore</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Username</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo DB_USER; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Password</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" value="<?php echo DB_PASS; ?>">
                                <div class="form-text">Biarkan kosong untuk menggunakan password default</div>
                            </div>
                            
                            <div class="mb-3 border-top pt-3">
                                <h6 class="mb-3">Opsi Restore</h6>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="restore_type" id="restore_full" value="full" checked>
                                    <label class="form-check-label" for="restore_full">
                                        Restore Database Lengkap
                                    </label>
                                    <div class="form-text">Mengembalikan seluruh database termasuk semua tabel dan data</div>
                                </div>
                                
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="restore_type" id="restore_tables" value="tables">
                                    <label class="form-check-label" for="restore_tables">
                                        Restore Tabel Tertentu
                                    </label>
                                    <div class="form-text">Hanya mengembalikan tabel-tabel yang dipilih</div>
                                </div>
                                
                                <div id="tablesLoading" class="mt-2" style="display:none;">
                                    <div class="d-flex align-items-center">
                                        <div class="spinner-border spinner-border-sm text-secondary me-2" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span>Mengambil daftar tabel...</span>
                                    </div>
                                </div>
                                
                                <div id="tables_selection" class="mt-3" style="display:none;">
                                    <!-- Daftar tabel akan dimuat di sini via AJAX -->
                                </div>
                                
                                <div class="d-grid mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="showTablesBtn">Tampilkan Tabel</button>
                                </div>
                                <div class="form-text">Klik untuk menampilkan daftar tabel dari database yang dipilih</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class='bx bx-error'></i> Peringatan!</h6>
                        <p class="mb-0">
                            Proses restore akan menimpa semua data yang ada di database saat ini.
                            Pastikan Anda sudah membuat backup terbaru sebelum melanjutkan.
                        </p>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-danger" id="restoreBtn" <?php echo empty($backups) ? 'disabled' : ''; ?>>
                            <i class='bx bx-reset'></i> Restore Database
                        </button>
                    </div>
                    
                    <!-- Modal Konfirmasi -->
                    <div class="modal fade" id="confirmRestoreModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Konfirmasi Restore</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p id="confirmMessage">Apakah Anda yakin ingin memulihkan database dari backup ini?</p>
                                    <div class="alert alert-danger">
                                        <i class='bx bx-error'></i> Tindakan ini tidak dapat dibatalkan. Data yang sudah ada mungkin akan ditimpa.
                                    </div>
                                    <div id="selectedTablesInfo" class="mb-3" style="display:none;">
                                        <h6>Tabel yang akan direstore:</h6>
                                        <ul id="selectedTablesList" class="mb-3"></ul>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmText" class="form-label">Ketik "RESTORE" untuk mengonfirmasi:</label>
                                        <input type="text" class="form-control" id="confirmText" placeholder="RESTORE">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger" id="confirmRestoreBtn" disabled>
                                        Ya, Lanjutkan Restore
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Panduan Restore</h6>
            </div>
            <div class="card-body">
                <h6>Kapan harus merestore?</h6>
                <ul class="small">
                    <li>Ketika terjadi kehilangan data</li>
                    <li>Setelah kesalahan operasi database</li>
                    <li>Migrasi ke server baru</li>
                    <li>Testing dengan data produksi</li>
                </ul>
                
                <h6 class="mt-3">Yang perlu diperhatikan:</h6>
                <ul class="small">
                    <li>Backup database saat ini terlebih dahulu</li>
                    <li>Pastikan versi database kompatibel</li>
                    <li>Restore bisa memakan waktu lama untuk database besar</li>
                    <li>Aplikasi mungkin perlu di-restart setelah restore</li>
                </ul>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Backup Terbaru</h6>
                <a href="?page=backup" class="btn btn-sm btn-outline-primary btn-sm">Buat Baru</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($backups)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($backups, 0, 5) as $backup): ?>
                            <div class="list-group-item list-group-item-action">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($backup['filename']); ?></h6>
                                    <small><?php echo date('d M', strtotime($backup['created_at'])); ?></small>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php 
                                        if (isset($backup['size']) && $backup['size'] > 0) {
                                            echo format_size($backup['size']);
                                        } else {
                                            // Coba ambil ukuran file dari disk
                                            $filepath = BACKUP_DIR . '/' . $backup['filename'];
                                            if (file_exists($filepath)) {
                                                $filesize = filesize($filepath);
                                                echo format_size($filesize);
                                                
                                                // Update ukuran di database
                                                try {
                                                    $update_stmt = $pdo->prepare("UPDATE backup_history SET size = ? WHERE id = ?");
                                                    $update_stmt->execute([$filesize, $backup['id']]);
                                                } catch (Exception $e) {
                                                    // Abaikan error update
                                                }
                                            } else {
                                                echo 'Ukuran tidak diketahui';
                                            }
                                        }
                                        ?>
                                    </small>
                                    <span class="badge bg-<?php echo $backup['status'] === 'success' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $backup['status'] === 'success' ? 'success' : 'danger'; ?> small">
                                        <?php echo ucfirst($backup['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4">
                        <div class="mb-2">
                            <i class='bx bx-package text-muted' style="font-size: 2rem;"></i>
                        </div>
                        <p class="text-muted small mb-0">Belum ada backup yang tersedia</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Toggle tampilan upload file
    $('input[name="backup_option"]').change(function() {
        if ($(this).val() === 'upload') {
            $('#uploadBackupSection').show();
            $('#existingBackupSection').hide();
        } else {
            $('#uploadBackupSection').hide();
            $('#existingBackupSection').show();
        }
    });
    
    // Sembunyikan section upload saat pertama kali dimuat
    if ($('input[name="backup_option"]:checked').val() === 'existing') {
        $('#existingBackupSection').show();
        $('#uploadBackupSection').hide();
    } else {
        $('#existingBackupSection').hide();
        $('#uploadBackupSection').show();
    }
    
    // Nonaktifkan tombol restore jika tidak ada backup
    if ($('#backup_id option').length === 0) {
        $('#restoreBtn').prop('disabled', true);
    }
    
    // Toggle tampilan pemilihan tabel
    $('input[name="restore_type"]').change(function() {
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
        var db_name = $('#db_name').val();
        
        // Kirim request AJAX
        $.ajax({
            url: 'handlers/get_tables_restore.php',
            type: 'POST',
            dataType: 'json',
            data: {
                db_host: db_host,
                db_port: db_port,
                db_user: db_user,
                db_pass: db_pass,
                db_name: db_name
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
                    
                    // Set opsi restore ke tabel tertentu jika ada tabel
                    if (response.count > 0) {
                        $('#restore_tables').prop('checked', true);
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
    });
    
    // Validasi input konfirmasi
    $('#confirmText').on('input', function() {
        if ($(this).val() === 'RESTORE') {
            $('#confirmRestoreBtn').prop('disabled', false);
        } else {
            $('#confirmRestoreBtn').prop('disabled', true);
        }
    });
    
    // Tampilkan modal konfirmasi saat tombol restore diklik
    $('#restoreBtn').click(function() {
        // Validasi pemilihan tabel jika opsi restore tabel dipilih
        if ($('#restore_tables').is(':checked') && $('.table-checkbox:checked').length === 0) {
            alert('Anda harus memilih setidaknya satu tabel untuk restore');
            return false;
        }
        
        // Update informasi di modal konfirmasi
        var dbName = $('#db_name').val();
        var restoreType = $('input[name="restore_type"]:checked').val();
        var confirmMessage = '';
        
        if (restoreType === 'full') {
            confirmMessage = 'Anda akan melakukan restore SELURUH database <strong>' + dbName + '</strong>.';
        } else {
            var selectedTables = [];
            $('.table-checkbox:checked').each(function() {
                selectedTables.push($(this).val());
            });
            
            confirmMessage = 'Anda akan melakukan restore <strong>' + selectedTables.length + '</strong> tabel ke database <strong>' + dbName + '</strong>.';
            
            // Tampilkan daftar tabel yang dipilih
            if (selectedTables.length > 0) {
                $('#selectedTablesInfo').show();
                var tableList = '';
                for (var i = 0; i < selectedTables.length; i++) {
                    tableList += '<li>' + selectedTables[i] + '</li>';
                }
                $('#selectedTablesList').html(tableList);
            } else {
                $('#selectedTablesInfo').hide();
            }
        }
        
        $('#confirmMessage').html(confirmMessage);
        
        const modal = new bootstrap.Modal(document.getElementById('confirmRestoreModal'));
        modal.show();
    });
    
    // Simulasi progress saat restore dimulai
    $('#confirmRestoreBtn').click(function() {
        $('#restoreProgress').show();
        $('#restoreBtn').prop('disabled', true);
        
        // Simulasi progress (karena kita tidak bisa mendapatkan progress sebenarnya dari psql)
        let progress = 0;
        const progressBar = $('#restoreProgress .progress-bar');
        const statusText = $('#restoreStatus');
        
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
                    statusText.text('Mempersiapkan restore database...');
                } else if (progress < 40) {
                    statusText.text('Mengimpor struktur tabel...');
                } else if (progress < 60) {
                    statusText.text('Mengimpor data...');
                } else if (progress < 80) {
                    statusText.text('Memproses indeks dan constraint...');
                } else {
                    statusText.text('Menyelesaikan restore...');
                }
            }
        }, 800); // Update setiap 800ms
        
        // Submit form
        setTimeout(function() {
            $('#restoreForm').submit();
        }, 1500);
    });
});
</script>
