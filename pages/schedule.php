<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Pastikan jQuery tersedia
echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

// Dapatkan jadwal backup yang ada
$schedules = [];
try {
    $pdo = get_db_connection();
    
    // Cek apakah kolom backup_type dan selected_tables sudah ada
    $check_columns = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'backup_schedules' AND column_name IN ('backup_type', 'selected_tables')");
    $existing_columns = $check_columns->fetchAll(PDO::FETCH_COLUMN);
    
    // Jika kolom belum ada, tambahkan kolom baru
    if (!in_array('backup_type', $existing_columns) || !in_array('selected_tables', $existing_columns)) {
        $alter_query = "ALTER TABLE backup_schedules ";
        if (!in_array('backup_type', $existing_columns)) {
            $alter_query .= "ADD COLUMN backup_type VARCHAR(20) DEFAULT 'full'";
        }
        if (!in_array('backup_type', $existing_columns) && !in_array('selected_tables', $existing_columns)) {
            $alter_query .= ", ";
        }
        if (!in_array('selected_tables', $existing_columns)) {
            $alter_query .= "ADD COLUMN selected_tables TEXT";
        }
        $pdo->exec($alter_query);
    }
    
    $stmt = $pdo->query("SELECT * FROM backup_schedules ORDER BY created_at DESC");
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika tabel belum ada, buat tabel
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS backup_schedules (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                database_name VARCHAR(100) NOT NULL,
                frequency VARCHAR(50) NOT NULL,
                day_of_week VARCHAR(20),
                hour INTEGER NOT NULL,
                minute INTEGER NOT NULL,
                retention_days INTEGER DEFAULT 30,
                compress BOOLEAN DEFAULT TRUE,
                include_schema BOOLEAN DEFAULT TRUE,
                include_data BOOLEAN DEFAULT TRUE,
                is_active BOOLEAN DEFAULT TRUE,
                backup_type VARCHAR(20) DEFAULT 'full',
                selected_tables TEXT,
                last_run TIMESTAMP,
                next_run TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $error = 'Tabel jadwal backup dibuat. Silakan tambahkan jadwal baru.';
    } catch (PDOException $e2) {
        $error = 'Gagal membuat tabel jadwal backup: ' . $e2->getMessage();
    }
}

// Dapatkan daftar database
$databases = [];
try {
    $stmt = $pdo->query("SELECT datname FROM pg_database WHERE datistemplate = false AND datname != 'postgres' AND datname != 'template1' AND datname != 'template0'");
    $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = 'Gagal mengambil daftar database: ' . $e->getMessage();
}

// Proses form tambah/edit jadwal
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        // Validasi input
        $name = $_POST['name'] ?? '';
        $database_name = $_POST['database_name'] ?? '';
        $frequency = $_POST['frequency'] ?? '';
        $day_of_week = $_POST['day_of_week'] ?? '';
        $hour = (int)($_POST['hour'] ?? 0);
        $minute = (int)($_POST['minute'] ?? 0);
        $retention_days = (int)($_POST['retention_days'] ?? 30);
        $compress = isset($_POST['compress']);
        $include_schema = isset($_POST['include_schema']);
        $include_data = isset($_POST['include_data']);
        $is_active = isset($_POST['is_active']);
        $backup_type = $_POST['backup_type'] ?? 'full';
        $selected_tables = [];
        
        // Jika tipe backup adalah tabel tertentu, ambil tabel yang dipilih
        if ($backup_type === 'tables' && isset($_POST['selected_tables'])) {
            $selected_tables = $_POST['selected_tables'];
            // Validasi: pastikan ada tabel yang dipilih
            if (empty($selected_tables)) {
                $error = 'Anda harus memilih setidaknya satu tabel untuk backup';
            }
        }
        
        if (empty($name) || empty($database_name) || empty($frequency)) {
            $error = 'Nama, database, dan frekuensi harus diisi';
        } else {
            try {
                // Hitung next_run
                $now = new DateTime();
                $next_run = clone $now;
                
                switch ($frequency) {
                    case 'daily':
                        $next_run->setTime($hour, $minute);
                        if ($next_run < $now) {
                            $next_run->modify('+1 day');
                        }
                        break;
                    case 'weekly':
                        $next_run->modify('next ' . $day_of_week);
                        $next_run->setTime($hour, $minute);
                        break;
                    case 'monthly':
                        $next_run->modify('first day of next month');
                        $next_run->setTime($hour, $minute);
                        break;
                }
                
                // Tambahkan jadwal baru
                $stmt = $pdo->prepare("
                    INSERT INTO backup_schedules 
                    (name, database_name, frequency, day_of_week, hour, minute, 
                     retention_days, compress, include_schema, include_data, is_active, backup_type, selected_tables, next_run) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                // Konversi array tabel menjadi string JSON jika ada
                $selected_tables_json = '';
                if (!empty($selected_tables)) {
                    $selected_tables_json = json_encode($selected_tables);
                }
                
                $stmt->execute([
                    $name, $database_name, $frequency, $day_of_week, $hour, $minute,
                    $retention_days, $compress, $include_schema, $include_data, $is_active, $backup_type, $selected_tables_json,
                    $next_run->format('Y-m-d H:i:s')
                ]);
                
                $success = 'Jadwal backup berhasil ditambahkan';
                
                // Refresh daftar jadwal
                $stmt = $pdo->query("SELECT * FROM backup_schedules ORDER BY created_at DESC");
                $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                $error = 'Gagal menambahkan jadwal backup: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['schedule_id'])) {
        $schedule_id = $_POST['schedule_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM backup_schedules WHERE id = ?");
            $stmt->execute([$schedule_id]);
            
            $success = 'Jadwal backup berhasil dihapus';
            
            // Refresh daftar jadwal
            $stmt = $pdo->query("SELECT * FROM backup_schedules ORDER BY created_at DESC");
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'Gagal menghapus jadwal backup: ' . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'toggle' && isset($_POST['schedule_id'])) {
        $schedule_id = $_POST['schedule_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE backup_schedules SET is_active = ? WHERE id = ?");
            $stmt->execute([$is_active, $schedule_id]);
            
            $success = 'Status jadwal backup berhasil diperbarui';
            
            // Refresh daftar jadwal
            $stmt = $pdo->query("SELECT * FROM backup_schedules ORDER BY created_at DESC");
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'Gagal memperbarui status jadwal backup: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Jadwal Backup Otomatis</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
            <i class='bx bx-plus'></i> Tambah Jadwal
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Informasi Jadwal Backup -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Daftar Jadwal Backup</h5>
    </div>
    <div class="card-body">
        <?php if (empty($schedules)): ?>
            <div class="text-center p-4">
                <div class="mb-3">
                    <i class='bx bx-calendar text-muted' style="font-size: 3rem;"></i>
                </div>
                <h5>Belum ada jadwal backup</h5>
                <p class="text-muted">Klik tombol "Tambah Jadwal" untuk membuat jadwal backup otomatis.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Database</th>
                            <th>Frekuensi</th>
                            <th>Waktu</th>
                            <th>Retensi</th>
                            <th>Status</th>
                            <th>Eksekusi Berikutnya</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['name']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['database_name']); ?></td>
                                <td>
                                    <?php
                                    switch ($schedule['frequency']) {
                                        case 'daily':
                                            echo 'Harian';
                                            break;
                                        case 'weekly':
                                            echo 'Mingguan (' . ucfirst($schedule['day_of_week']) . ')';
                                            break;
                                        case 'monthly':
                                            echo 'Bulanan';
                                            break;
                                        default:
                                            echo htmlspecialchars($schedule['frequency']);
                                    }
                                    ?>
                                </td>
                                <td><?php echo sprintf('%02d:%02d', $schedule['hour'], $schedule['minute']); ?></td>
                                <td><?php echo $schedule['retention_days']; ?> hari</td>
                                <td>
                                    <form method="POST" action="" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input toggle-status" type="checkbox" name="is_active" 
                                                <?php echo $schedule['is_active'] ? 'checked' : ''; ?> 
                                                data-id="<?php echo $schedule['id']; ?>">
                                        </div>
                                    </form>
                                </td>
                                <td>
                                    <?php 
                                    if ($schedule['next_run']) {
                                        echo date('d M Y H:i', strtotime($schedule['next_run']));
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-info view-schedule" 
                                                data-bs-toggle="modal" data-bs-target="#viewScheduleModal"
                                                data-id="<?php echo $schedule['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($schedule['name']); ?>"
                                                data-database="<?php echo htmlspecialchars($schedule['database_name']); ?>"
                                                data-frequency="<?php echo $schedule['frequency']; ?>"
                                                data-day="<?php echo $schedule['day_of_week']; ?>"
                                                data-hour="<?php echo $schedule['hour']; ?>"
                                                data-minute="<?php echo $schedule['minute']; ?>"
                                                data-retention="<?php echo $schedule['retention_days']; ?>"
                                                data-compress="<?php echo $schedule['compress']; ?>"
                                                data-schema="<?php echo $schedule['include_schema']; ?>"
                                                data-data="<?php echo $schedule['include_data']; ?>"
                                                data-active="<?php echo $schedule['is_active']; ?>">
                                            <i class='bx bx-info-circle'></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" 
                                                data-bs-target="#deleteScheduleModal" 
                                                data-id="<?php echo $schedule['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($schedule['name']); ?>">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Informasi Cara Kerja -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Cara Kerja Backup Otomatis</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h6 class="alert-heading"><i class='bx bx-info-circle'></i> Informasi</h6>
            <p>Backup otomatis memerlukan cron job yang dijalankan pada server. Berikut adalah contoh konfigurasi cron job:</p>
            <div class="bg-dark text-light p-3 rounded">
                <code>* * * * * php <?php echo realpath(__DIR__ . '/../cron_backup.php'); ?> > /dev/null 2>&1</code>
            </div>
            <p class="mt-2">Cron job di atas akan memeriksa jadwal backup setiap menit dan menjalankan backup yang sudah waktunya.</p>
            <div class="mt-3">
                <button type="button" id="setup_cron_btn" class="btn btn-primary">
                    <i class='bx bx-cog'></i> Setup Cron Job Otomatis
                </button>
                <div id="cron_setup_result" class="mt-2" style="display: none;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Jadwal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Jadwal Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addScheduleForm" method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Nama Jadwal</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   placeholder="Contoh: Backup Harian Database Utama">
                        </div>
                        <div class="col-md-6">
                            <label for="database_name" class="form-label">Database</label>
                            <div class="input-group">
                                <select class="form-select" id="database_name" name="database_name" required>
                                    <?php foreach ($databases as $db): ?>
                                        <option value="<?php echo htmlspecialchars($db); ?>">
                                            <?php echo htmlspecialchars($db); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-secondary" type="button" id="get_tables_btn">Tampilkan Tabel</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="frequency" class="form-label">Frekuensi</label>
                            <select class="form-select" id="frequency" name="frequency" required>
                                <option value="daily">Harian</option>
                                <option value="weekly">Mingguan</option>
                                <option value="monthly">Bulanan</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="dayOfWeekContainer" style="display: none;">
                            <label for="day_of_week" class="form-label">Hari</label>
                            <select class="form-select" id="day_of_week" name="day_of_week">
                                <option value="monday">Senin</option>
                                <option value="tuesday">Selasa</option>
                                <option value="wednesday">Rabu</option>
                                <option value="thursday">Kamis</option>
                                <option value="friday">Jumat</option>
                                <option value="saturday">Sabtu</option>
                                <option value="sunday">Minggu</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="hour" class="form-label">Jam</label>
                            <select class="form-select" id="hour" name="hour" required>
                                <?php for ($i = 0; $i < 24; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo sprintf('%02d', $i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="minute" class="form-label">Menit</label>
                            <select class="form-select" id="minute" name="minute" required>
                                <?php for ($i = 0; $i < 60; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo sprintf('%02d', $i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="retention_days" class="form-label">Retensi (hari)</label>
                        <input type="number" class="form-control" id="retention_days" name="retention_days" 
                               min="1" max="365" value="30" required>
                        <div class="form-text">Backup yang lebih lama dari periode retensi akan dihapus otomatis.</div>
                    </div>
                    
                    <!-- Opsi Tipe Backup -->
                    <div class="mb-3">
                        <label class="form-label">Tipe Backup</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" id="backup_type_full" value="full" checked>
                            <label class="form-check-label" for="backup_type_full">
                                Backup Database Lengkap
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backup_type" id="backup_type_tables" value="tables">
                            <label class="form-check-label" for="backup_type_tables">
                                Backup Tabel Tertentu
                            </label>
                        </div>
                    </div>

                    <!-- Bagian Pemilihan Tabel -->
                    <div class="mb-3" id="tables_section" style="display: none;">
                        <label class="form-label">Pilih Tabel</label>
                        <div class="alert alert-info">
                            <i class='bx bx-info-circle'></i> Klik tombol "Tampilkan Tabel" di atas untuk melihat daftar tabel.
                        </div>
                        <div id="tables_list" class="row" style="max-height: 200px; overflow-y: auto;">
                            <!-- Daftar tabel akan ditampilkan di sini -->
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="select_all_tables">Pilih Semua</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect_all_tables">Batalkan Semua</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Opsi Backup</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="compress" name="compress" checked>
                            <label class="form-check-label" for="compress">
                                Kompresi (GZIP)
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_schema" name="include_schema" checked>
                            <label class="form-check-label" for="include_schema">
                                Sertakan Skema Database
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_data" name="include_data" checked>
                            <label class="form-check-label" for="include_data">
                                Sertakan Data
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Aktif
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="addScheduleForm" class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Lihat Detail Jadwal -->
<div class="modal fade" id="viewScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Jadwal Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6>Nama Jadwal</h6>
                    <p id="viewName" class="mb-0"></p>
                </div>
                <div class="mb-3">
                    <h6>Database</h6>
                    <p id="viewDatabase" class="mb-0"></p>
                </div>
                <div class="mb-3">
                    <h6>Frekuensi</h6>
                    <p id="viewFrequency" class="mb-0"></p>
                </div>
                <div class="mb-3">
                    <h6>Waktu</h6>
                    <p id="viewTime" class="mb-0"></p>
                </div>
                <div class="mb-3">
                    <h6>Retensi</h6>
                    <p id="viewRetention" class="mb-0"></p>
                </div>
                <div class="mb-3">
                    <h6>Opsi Backup</h6>
                    <ul class="list-unstyled">
                        <li><i id="viewCompress" class='bx'></i> Kompresi (GZIP)</li>
                        <li><i id="viewSchema" class='bx'></i> Sertakan Skema Database</li>
                        <li><i id="viewData" class='bx'></i> Sertakan Data</li>
                    </ul>
                </div>
                <div class="mb-0">
                    <h6>Status</h6>
                    <p id="viewStatus" class="mb-0"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hapus Jadwal -->
<div class="modal fade" id="deleteScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus jadwal backup <strong id="deleteScheduleName"></strong>?</p>
                <div class="alert alert-warning">
                    <i class='bx bx-error'></i> Perhatian: Tindakan ini tidak dapat dibatalkan!
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="schedule_id" id="deleteScheduleId">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Tampilkan/sembunyikan hari berdasarkan frekuensi
    $('#frequency').change(function() {
        if ($(this).val() === 'weekly') {
            $('#dayOfWeekContainer').show();
        } else {
            $('#dayOfWeekContainer').hide();
        }
    });
    
    // Tampilkan/sembunyikan bagian tabel berdasarkan tipe backup
    $('input[name="backup_type"]').change(function() {
        if ($(this).val() === 'tables') {
            $('#tables_section').show();
        } else {
            $('#tables_section').hide();
        }
    });
    
    // Toggle status jadwal
    $('.toggle-status').change(function() {
        $(this).closest('form').submit();
    });
    
    // Tampilkan detail jadwal
    $('.view-schedule').click(function() {
        const name = $(this).data('name');
        const database = $(this).data('database');
        let frequency = $(this).data('frequency');
        const day = $(this).data('day');
        const hour = $(this).data('hour');
        const minute = $(this).data('minute');
        const retention = $(this).data('retention');
        const compress = $(this).data('compress');
        const schema = $(this).data('schema');
        const data = $(this).data('data');
        const active = $(this).data('active');
        
        // Format frekuensi
        switch (frequency) {
            case 'daily':
                frequency = 'Harian';
                break;
            case 'weekly':
                frequency = 'Mingguan (' + day.charAt(0).toUpperCase() + day.slice(1) + ')';
                break;
            case 'monthly':
                frequency = 'Bulanan';
                break;
        }
        
        $('#viewName').text(name);
        $('#viewDatabase').text(database);
        $('#viewFrequency').text(frequency);
        $('#viewTime').text(hour.toString().padStart(2, '0') + ':' + minute.toString().padStart(2, '0'));
        $('#viewRetention').text(retention + ' hari');
        
        // Set ikon untuk opsi
        $('#viewCompress').attr('class', compress ? 'bx bx-check text-success' : 'bx bx-x text-danger');
        $('#viewSchema').attr('class', schema ? 'bx bx-check text-success' : 'bx bx-x text-danger');
        $('#viewData').attr('class', data ? 'bx bx-check text-success' : 'bx bx-x text-danger');
        
        // Set status
        $('#viewStatus').html(active ? 
            '<span class="badge bg-success">Aktif</span>' : 
            '<span class="badge bg-danger">Tidak Aktif</span>');
    });
    
    // Set ID jadwal untuk hapus
    $('#deleteScheduleModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const id = button.data('id');
        const name = button.data('name');
        
        $('#deleteScheduleId').val(id);
        $('#deleteScheduleName').text(name);
    });
    
    // Atur modal hapus jadwal
    $('#deleteScheduleModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        var name = button.data('name');
        
        $('#deleteScheduleId').val(id);
        $('#deleteScheduleName').text(name);
    });
    
    // Atur modal lihat jadwal
    $('#viewScheduleModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var name = button.data('name');
        var database = button.data('database');
        var frequency = button.data('frequency');
        var day = button.data('day');
        var hour = button.data('hour');
        var minute = button.data('minute');
        var retention = button.data('retention');
        var compress = button.data('compress');
        var schema = button.data('schema');
        var data = button.data('data');
        var active = button.data('active');
        
        $('#viewName').text(name);
        $('#viewDatabase').text(database);
        
        // Format frekuensi
        var frequencyText = '';
        switch (frequency) {
            case 'daily':
                frequencyText = 'Harian';
                break;
            case 'weekly':
                frequencyText = 'Mingguan (' + formatDay(day) + ')';
                break;
            case 'monthly':
                frequencyText = 'Bulanan';
                break;
        }
        $('#viewFrequency').text(frequencyText);
        
        // Format waktu
        $('#viewTime').text(formatTime(hour, minute));
        
        // Format retensi
        $('#viewRetention').text(retention + ' hari');
        
        // Format opsi
        $('#viewCompress').attr('class', compress == 1 ? 'bx bx-check text-success' : 'bx bx-x text-danger');
        $('#viewSchema').attr('class', schema == 1 ? 'bx bx-check text-success' : 'bx bx-x text-danger');
        $('#viewData').attr('class', data == 1 ? 'bx bx-check text-success' : 'bx bx-x text-danger');
        
        // Format status
        var statusClass = active == 1 ? 'badge bg-success' : 'badge bg-danger';
        var statusText = active == 1 ? 'Aktif' : 'Tidak Aktif';
        $('#viewStatus').html('<span class="' + statusClass + '">' + statusText + '</span>');
    });
    
    // Fungsi format hari
    function formatDay(day) {
        switch (day) {
            case 'monday': return 'Senin';
            case 'tuesday': return 'Selasa';
            case 'wednesday': return 'Rabu';
            case 'thursday': return 'Kamis';
            case 'friday': return 'Jumat';
            case 'saturday': return 'Sabtu';
            case 'sunday': return 'Minggu';
            default: return day;
        }
    }
    
    // Fungsi format waktu
    function formatTime(hour, minute) {
        return (hour < 10 ? '0' + hour : hour) + ':' + (minute < 10 ? '0' + minute : minute);
    }
    
    // Ambil daftar tabel saat tombol Tampilkan Tabel diklik
    $('#get_tables_btn').click(function() {
        var database = $('#database_name').val();
        var db_host = '<?php echo DB_HOST; ?>';
        var db_port = '<?php echo DB_PORT; ?>';
        var db_user = '<?php echo DB_USER; ?>';
        
        if (!database) {
            alert('Silakan pilih database terlebih dahulu');
            return;
        }
        
        // Tampilkan loading
        $('#tables_list').html('<div class="col-12 text-center"><i class="bx bx-loader bx-spin"></i> Memuat daftar tabel...</div>');
        
        // Ambil daftar tabel dengan AJAX
        $.ajax({
            url: '../get_tables.php',
            type: 'POST',
            data: {
                database: database,
                db_host: db_host,
                db_port: db_port,
                db_user: db_user
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Jika response.html tersedia, gunakan langsung
                    if (response.html) {
                        $('#tables_list').html(response.html);
                    } 
                    // Jika response.tables tersedia, buat HTML dari daftar tabel
                    else if (response.tables && Array.isArray(response.tables)) {
                        var tables = response.tables;
                        var html = '';
                        
                        if (tables.length > 0) {
                            // Tampilkan daftar tabel dalam bentuk checkbox
                            for (var i = 0; i < tables.length; i++) {
                                html += '<div class="col-md-4 mb-2">' +
                                        '<div class="form-check">' +
                                        '<input class="form-check-input table-checkbox" type="checkbox" name="selected_tables[]" value="' + tables[i] + '" id="table_' + i + '">' +
                                        '<label class="form-check-label" for="table_' + i + '">' + tables[i] + '</label>' +
                                        '</div>' +
                                        '</div>';
                            }
                        } else {
                            html = '<div class="col-12"><div class="alert alert-warning">Tidak ada tabel yang ditemukan di database ini.</div></div>';
                        }
                        
                        $('#tables_list').html(html);
                    }
                    // Jika tidak ada data tabel yang valid
                    else {
                        $('#tables_list').html('<div class="col-12"><div class="alert alert-warning">Tidak ada data tabel yang valid diterima dari server.</div></div>');
                    }
                    
                    // Aktifkan radio button untuk tabel tertentu
                    $('#backup_type_tables').prop('checked', true);
                    $('#tables_section').show();
                } else {
                    $('#tables_list').html('<div class="col-12"><div class="alert alert-danger">Error: ' + response.message + '</div></div>');
                }
            },
            error: function() {
                $('#tables_list').html('<div class="col-12"><div class="alert alert-danger">Gagal mengambil daftar tabel. Silakan coba lagi.</div></div>');
            }
        });
    });
    
    // Pilih semua tabel
    $('#select_all_tables').click(function() {
        $('.table-checkbox').prop('checked', true);
    });
    
    
    // Setup cron job otomatis
    $('#setup_cron_btn').click(function() {
        var $btn = $(this);
        var $result = $('#cron_setup_result');
        
        // Ubah tombol menjadi loading
        $btn.prop('disabled', true);
        $btn.html('<i class="bx bx-loader bx-spin"></i> Memproses...');
        
        // Tampilkan area hasil
        $result.show().html('<div class="alert alert-info">Sedang mengatur cron job, mohon tunggu...</div>');
        
        // Kirim permintaan ke script setup_cron.php
        $.ajax({
            url: '../setup_cron.php',
            type: 'GET',
            data: {
                setup_key: 'backup_setup_key'
            },
            success: function(response) {
                // Tampilkan hasil dalam format yang mudah dibaca
                var html = '<div class="alert alert-success">';
                html += '<h6 class="alert-heading"><i class="bx bx-check-circle"></i> Berhasil!</h6>';
                html += '<pre style="margin-top: 10px; white-space: pre-wrap;">' + response + '</pre>';
                html += '</div>';
                $result.html(html);
            },
            error: function(xhr) {
                // Tampilkan pesan error
                var html = '<div class="alert alert-danger">';
                html += '<h6 class="alert-heading"><i class="bx bx-error-circle"></i> Gagal!</h6>';
                html += '<p>Tidak dapat mengatur cron job secara otomatis. Silakan lakukan setup manual.</p>';
                if (xhr.responseText) {
                    html += '<pre style="margin-top: 10px; white-space: pre-wrap;">' + xhr.responseText + '</pre>';
                }
                html += '</div>';
                $result.html(html);
            },
            complete: function() {
                // Kembalikan tombol ke keadaan semula
                $btn.prop('disabled', false);
                $btn.html('<i class="bx bx-cog"></i> Setup Cron Job Otomatis');
            }
        });
    });
    
    // Fade out alert setelah 5 detik
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});
</script>
