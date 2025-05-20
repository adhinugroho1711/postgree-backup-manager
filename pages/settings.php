<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Dapatkan koneksi database
$pdo = get_db_connection();

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

// Inisialisasi variabel
$success = '';
$error = '';

// Proses form pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'general') {
        // Update pengaturan umum
        $app_name = trim($_POST['app_name'] ?? '');
        $app_url = trim($_POST['app_url'] ?? '');
        $retention_days = (int)($_POST['retention_days'] ?? 7);
        
        if (empty($app_name)) {
            $error = 'Nama aplikasi tidak boleh kosong';
        } else {
            try {
                // Simpan pengaturan
                saveSetting('app_name', $app_name);
                saveSetting('app_url', $app_url);
                saveSetting('retention_days', $retention_days);
                
                $success = 'Pengaturan umum berhasil disimpan';
            } catch (Exception $e) {
                $error = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'database') {
        // Update pengaturan database
        $db_host = trim($_POST['db_host'] ?? '');
        $db_port = trim($_POST['db_port'] ?? '');
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = trim($_POST['db_pass'] ?? '');
        
        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            $error = 'Host, nama database, dan username tidak boleh kosong';
        } else {
            try {
                // Simpan pengaturan
                saveSetting('db_host', $db_host);
                saveSetting('db_port', $db_port);
                saveSetting('db_name', $db_name);
                saveSetting('db_user', $db_user);
                
                // Hanya simpan password jika diisi
                if (!empty($db_pass)) {
                    saveSetting('db_pass', $db_pass);
                }
                
                $success = 'Pengaturan database berhasil disimpan';
            } catch (Exception $e) {
                $error = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'backup') {
        // Update pengaturan backup
        $backup_dir = trim($_POST['backup_dir'] ?? '');
        $backup_format = trim($_POST['backup_format'] ?? 'sql');
        $compress_backup = isset($_POST['compress_backup']) ? 1 : 0;
        
        if (empty($backup_dir)) {
            $error = 'Direktori backup tidak boleh kosong';
        } else {
            try {
                // Buat direktori jika belum ada
                if (!file_exists($backup_dir)) {
                    if (!@mkdir($backup_dir, 0755, true)) {
                        throw new Exception('Gagal membuat direktori backup');
                    }
                }
                
                // Simpan pengaturan
                saveSetting('backup_dir', $backup_dir);
                saveSetting('backup_format', $backup_format);
                saveSetting('compress_backup', $compress_backup);
                
                $success = 'Pengaturan backup berhasil disimpan';
            } catch (Exception $e) {
                $error = 'Gagal menyimpan pengaturan: ' . $e->getMessage();
            }
        }
    }
}

// Ambil pengaturan saat ini
$settings = [
    'app_name' => getSetting('app_name', APP_NAME),
    'app_url' => getSetting('app_url', APP_URL),
    'retention_days' => (int)getSetting('retention_days', RETENTION_DAYS),
    'db_host' => getSetting('db_host', DB_HOST),
    'db_port' => getSetting('db_port', DB_PORT),
    'db_name' => getSetting('db_name', DB_NAME),
    'db_user' => getSetting('db_user', DB_USER),
    'backup_dir' => getSetting('backup_dir', BACKUP_DIR),
    'backup_format' => getSetting('backup_format', 'sql'),
    'compress_backup' => (bool)getSetting('compress_backup', true)
];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Pengaturan</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-3">
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="list-group list-group-flush" id="settingsTabs" role="tablist">
                    <a class="list-group-item list-group-item-action active" id="general-tab" data-bs-toggle="list" href="#general" role="tab" aria-controls="general">
                        <i class='bx bxs-cog me-2'></i> Umum
                    </a>
                    <a class="list-group-item list-group-item-action" id="database-tab" data-bs-toggle="list" href="#database" role="tab" aria-controls="database">
                        <i class='bx bxs-data me-2'></i> Database
                    </a>
                    <a class="list-group-item list-group-item-action" id="backup-tab" data-bs-toggle="list" href="#backup" role="tab" aria-controls="backup">
                        <i class='bx bxs-archive me-2'></i> Backup
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div class="tab-content" id="settingsTabsContent">
            <!-- Pengaturan Umum -->
            <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pengaturan Umum</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="general">
                            
                            <div class="mb-3">
                                <label for="app_name" class="form-label">Nama Aplikasi</label>
                                <input type="text" class="form-control" id="app_name" name="app_name" value="<?php echo htmlspecialchars($settings['app_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="app_url" class="form-label">URL Aplikasi</label>
                                <input type="url" class="form-control" id="app_url" name="app_url" value="<?php echo htmlspecialchars($settings['app_url']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="retention_days" class="form-label">Masa Retensi Backup (hari)</label>
                                <input type="number" class="form-control" id="retention_days" name="retention_days" value="<?php echo (int)$settings['retention_days']; ?>" min="1" required>
                                <div class="form-text">Backup yang lebih lama dari masa retensi akan dihapus secara otomatis.</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Pengaturan Database -->
            <div class="tab-pane fade" id="database" role="tabpanel" aria-labelledby="database-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pengaturan Database</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="database">
                            
                            <div class="mb-3">
                                <label for="db_host" class="form-label">Host Database</label>
                                <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($settings['db_host']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_port" class="form-label">Port Database</label>
                                <input type="text" class="form-control" id="db_port" name="db_port" value="<?php echo htmlspecialchars($settings['db_port']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_name" class="form-label">Nama Database</label>
                                <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($settings['db_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_user" class="form-label">Username Database</label>
                                <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($settings['db_user']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="db_pass" class="form-label">Password Database</label>
                                <input type="password" class="form-control" id="db_pass" name="db_pass" placeholder="Biarkan kosong jika tidak ingin mengubah">
                                <div class="form-text">Biarkan kosong jika tidak ingin mengubah password.</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Pengaturan Backup -->
            <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Pengaturan Backup</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="backup">
                            
                            <div class="mb-3">
                                <label for="backup_dir" class="form-label">Direktori Backup</label>
                                <input type="text" class="form-control" id="backup_dir" name="backup_dir" value="<?php echo htmlspecialchars($settings['backup_dir']); ?>" required>
                                <div class="form-text">Path absolut ke direktori tempat menyimpan file backup.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="backup_format" class="form-label">Format Backup</label>
                                <select class="form-select" id="backup_format" name="backup_format">
                                    <option value="sql" <?php echo $settings['backup_format'] === 'sql' ? 'selected' : ''; ?>>SQL</option>
                                    <option value="custom" <?php echo $settings['backup_format'] === 'custom' ? 'selected' : ''; ?>>Custom</option>
                                    <option value="directory" <?php echo $settings['backup_format'] === 'directory' ? 'selected' : ''; ?>>Directory</option>
                                </select>
                                <div class="form-text">Format file backup PostgreSQL.</div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="compress_backup" name="compress_backup" <?php echo $settings['compress_backup'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="compress_backup">Kompres Backup</label>
                                <div class="form-text">Jika dicentang, file backup akan dikompres menggunakan gzip.</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Aktifkan tab berdasarkan URL hash
    var hash = window.location.hash;
    if (hash) {
        $('#settingsTabs a[href="' + hash + '"]').tab('show');
    }
    
    // Update URL hash saat tab berubah
    $('#settingsTabs a').on('click', function (e) {
        window.location.hash = $(this).attr('href');
    });
});
</script>
