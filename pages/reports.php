<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Dapatkan koneksi database
$pdo = get_db_connection();

// Dapatkan parameter filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Default: awal bulan ini
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Default: hari ini
$status = $_GET['status'] ?? 'all';
$user_id = is_admin() && isset($_GET['user_id']) ? (int)$_GET['user_id'] : $_SESSION['user_id'];

// Validasi tanggal
if ($start_date > $end_date) {
    $start_date = $end_date;
}

// Query untuk mendapatkan daftar backup
$where_conditions = ["DATE(bh.created_at) BETWEEN :start_date AND :end_date"];
$params = [
    'start_date' => $start_date,
    'end_date' => $end_date
];

if ($status !== 'all') {
    $where_conditions[] = "bh.status = :status";
    $params['status'] = $status;
}

if (!is_admin() || isset($_GET['user_id'])) {
    $where_conditions[] = "bh.user_id = :user_id";
    $params['user_id'] = $user_id;
}

$where_clause = implode(' AND ', $where_conditions);

// Hitung total backup
$count_sql = "SELECT COUNT(*) FROM backup_history bh WHERE $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_backups = $stmt->fetchColumn();

// Hitung total ukuran backup
$size_sql = "SELECT COALESCE(SUM(bh.size_bytes), 0) FROM backup_history bh WHERE $where_clause";
$stmt = $pdo->prepare($size_sql);
$stmt->execute($params);
$total_size = $stmt->fetchColumn();

// Hitung berdasarkan status
$status_sql = "
    SELECT 
        bh.status, 
        COUNT(*) as count,
        COALESCE(SUM(bh.size_bytes), 0) as total_size
    FROM backup_history bh
    WHERE $where_clause
    GROUP BY bh.status
    ORDER BY count DESC
";
$stmt = $pdo->prepare($status_sql);
$stmt->execute($params);
$status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung berdasarkan user (hanya untuk admin)
$user_stats = [];
if (is_admin()) {
    $user_sql = "
        SELECT 
            u.id,
            u.username,
            u.full_name,
            COUNT(bh.id) as backup_count,
            COALESCE(SUM(bh.size_bytes), 0) as total_size
        FROM users u
        LEFT JOIN backup_history bh ON u.id = bh.user_id
        WHERE $where_clause
        GROUP BY u.id, u.username, u.full_name
        ORDER BY backup_count DESC
    ";
    $stmt = $pdo->prepare($user_sql);
    $stmt->execute($params);
    $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Dapatkan daftar pengguna untuk filter (hanya untuk admin)
$users = [];
if (is_admin()) {
    $users = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
}

// Dapatkan daftar backup terbaru
$recent_sql = "
    SELECT 
        bh.*,
        u.username,
        u.full_name
    FROM backup_history bh
    JOIN users u ON bh.user_id = u.id
    WHERE $where_clause
    ORDER BY bh.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($recent_sql);
$stmt->execute($params);
$recent_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Laporan Backup</h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?php echo htmlspecialchars($_SESSION['success_message']); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); endif; ?>

<div class="btn-toolbar mb-2 mb-md-0">
    <div class="btn-group me-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="printReport">
            <i class='bx bx-printer'></i> Cetak
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="exportPdf">
            <i class='bx bx-export'></i> Ekspor PDF
        </button>
    </div>
</div>

<!-- Filter Form -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Filter Laporan</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="page" value="reports">
            
            <div class="col-md-3">
                <label for="start_date" class="form-label">Tanggal Mulai</label>
                <input type="date" class="form-control" id="start_date" name="start_date" 
                       value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            
            <div class="col-md-3">
                <label for="end_date" class="form-label">Tanggal Selesai</label>
                <input type="date" class="form-control" id="end_date" name="end_date" 
                       value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                    <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>Berhasil</option>
                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>Gagal</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Menunggu</option>
                </select>
            </div>
            
            <?php if (is_admin()): ?>
            <div class="col-md-3">
                <label for="user_id" class="form-label">Pengguna</label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">Semua Pengguna</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" 
                            <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class='bx bx-filter-alt'></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Ringkasan -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Total Backup</h6>
                <h2 class="mb-0"><?php echo number_format($total_backups); ?></h2>
                <p class="text-muted mb-0">
                    <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Total Penyimpanan Digunakan</h6>
                <h2 class="mb-0"><?php echo format_size($total_size); ?></h2>
                <p class="text-muted mb-0">
                    Rata-rata <?php echo $total_backups > 0 ? format_size($total_size / $total_backups) : '0 B'; ?> per backup
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <h6 class="card-title text-muted">Rata-rata per Hari</h6>
                <?php
                $days = max(1, (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1);
                $avg_per_day = $total_backups / $days;
                ?>
                <h2 class="mb-0"><?php echo number_format($avg_per_day, 1); ?></h2>
                <p class="text-muted mb-0">
                    Backup per hari
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Grafik dan Statistik -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">Status Backup</h6>
            </div>
            <div class="card-body">
                <canvas id="statusChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0">Aktivitas Backup Harian</h6>
            </div>
            <div class="card-body">
                <canvas id="dailyActivityChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabel Backup Terbaru -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Backup Terbaru</h6>
        <a href="?page=backup" class="btn btn-sm btn-outline-primary">
            <i class='bx bx-plus'></i> Backup Baru
        </a>
    </div>
    <div class="card-body p-0">
        <?php if (count($recent_backups) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama File</th>
                            <th>Pengguna</th>
                            <th>Ukuran</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_backups as $backup): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                <td><?php echo htmlspecialchars($backup['full_name'] ?: $backup['username']); ?></td>
                                <td><?php echo format_size($backup['size_bytes'] ?? 0); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($backup['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $backup['status'] === 'success' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $backup['status'] === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($backup['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=backup_detail&id=<?php echo $backup['id']; ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Detail">
                                            <i class='bx bx-detail'></i>
                                        </a>
                                        <?php if ($backup['status'] === 'success'): ?>
                                            <a href="download_backup.php?id=<?php echo $backup['id']; ?>" 
                                               class="btn btn-outline-success" 
                                               title="Unduh">
                                                <i class='bx bx-download'></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center p-4">
                <div class="mb-3">
                    <i class='bx bx-package text-muted' style="font-size: 3rem;"></i>
                </div>
                <h5>Data tidak ditemukan</h5>
                <p class="text-muted">Tidak ada data backup yang sesuai dengan filter yang dipilih.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php if (count($recent_backups) > 0): ?>
        <div class="card-footer text-end">
            <a href="?page=backup_list" class="btn btn-sm btn-outline-primary">
                Lihat Semua <i class='bx bx-chevron-right'></i>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Statistik Pengguna (hanya untuk admin) -->
<?php if (is_admin() && count($user_stats) > 0): ?>
<div class="card">
    <div class="card-header">
        <h6 class="mb-0">Statistik per Pengguna</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Pengguna</th>
                        <th>Jumlah Backup</th>
                        <th>Total Ukuran</th>
                        <th>Rata-rata Ukuran</th>
                        <th>Backup Terakhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_stats as $user): ?>
                        <?php
                        // Dapatkan info backup terakhir
                        $last_backup_sql = "
                            SELECT created_at 
                            FROM backup_history 
                            WHERE user_id = ? 
                            ORDER BY created_at DESC 
                            LIMIT 1
                        ";
                        $stmt = $pdo->prepare($last_backup_sql);
                        $stmt->execute([$user['id']]);
                        $last_backup = $stmt->fetchColumn();
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></td>
                            <td><?php echo number_format($user['backup_count']); ?></td>
                            <td><?php echo format_size($user['total_size']); ?></td>
                            <td>
                                <?php 
                                $avg_size = $user['backup_count'] > 0 
                                    ? $user['total_size'] / $user['backup_count'] 
                                    : 0;
                                echo format_size($avg_size);
                                ?>
                            </td>
                            <td>
                                <?php echo $last_backup ? date('d M Y H:i', strtotime($last_backup)) : '-' ; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal Ekspor -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ekspor Laporan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="exportForm" method="post" action="export_report.php" target="_blank">
                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                    <?php if (is_admin() && isset($_GET['user_id'])): ?>
                        <input type="hidden" name="user_id" value="<?php echo (int)$_GET['user_id']; ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="export_format" class="form-label">Format Ekspor</label>
                        <select class="form-select" id="export_format" name="format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="export_type" class="form-label">Tipe Laporan</label>
                        <select class="form-select" id="export_type" name="type" required>
                            <option value="summary">Ringkasan</option>
                            <option value="detailed">Detail</option>
                            <?php if (is_admin()): ?>
                                <option value="user_stats">Statistik Pengguna</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="exportForm" class="btn btn-primary">
                    <i class='bx bx-export'></i> Ekspor
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Grafik Status Backup
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusLabels = <?php echo json_encode(array_column($status_stats, 'status')); ?>;
const statusData = <?php echo json_encode(array_column($status_stats, 'count')); ?>;
const statusColors = statusLabels.map(status => {
    switch(status) {
        case 'success': return '#28a745';
        case 'failed': return '#dc3545';
        case 'pending': return '#ffc107';
        default: return '#6c757d';
    }
});

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels.map(label => {
            return label.charAt(0).toUpperCase() + label.slice(1);
        }),
        datasets: [{
            data: statusData,
            backgroundColor: statusColors,
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Grafik Aktivitas Harian
const dailyCtx = document.getElementById('dailyActivityChart').getContext('2d');

// Data aktivitas backup harian dari database
const dailyLabels = <?php 
    // Ambil data backup per hari untuk 30 hari terakhir
    $daily_sql = "SELECT 
        DATE(created_at) as backup_date, 
        COUNT(*) as backup_count 
    FROM backup_history 
    WHERE created_at >= CURRENT_DATE - INTERVAL '30 days' 
    GROUP BY DATE(created_at) 
    ORDER BY backup_date ASC";
    
    $stmt = $pdo->query($daily_sql);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buat array untuk menyimpan data 30 hari terakhir
    $dates = [];
    $counts = [];
    
    // Generate 30 hari terakhir
    $date_map = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $formatted_date = date('j M', strtotime($date));
        $dates[] = $formatted_date;
        $date_map[$date] = count($dates) - 1;
        $counts[] = 0; // Default 0 backup
    }
    
    // Isi dengan data sebenarnya
    foreach ($daily_stats as $stat) {
        $date = $stat['backup_date'];
        if (isset($date_map[$date])) {
            $counts[$date_map[$date]] = (int)$stat['backup_count'];
        }
    }
    
    echo json_encode($dates);
?>;

const dailyData = <?php echo json_encode($counts); ?>;


new Chart(dailyCtx, {
    type: 'bar',
    data: {
        labels: dailyLabels,
        datasets: [{
            label: 'Jumlah Backup',
            data: dailyData,
            backgroundColor: 'rgba(40, 167, 69, 0.5)',
            borderColor: 'rgba(40, 167, 69, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Tangani tombol cetak
$('#printReport').on('click', function() {
    window.print();
});

// Tangani tombol ekspor PDF
$('#exportPdf').on('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('exportModal'));
    modal.show();
});

// Inisialisasi tooltips
$(function () {
    $('[data-bs-toggle="tooltip"]').tooltip();
});
</script>

<!-- Gaya untuk cetak -->
<style>
@media print {
    .no-print, .card-header .btn {
        display: none !important;
    }
    
    .card {
        border: none;
        box-shadow: none;
    }
    
    .card-header {
        background-color: transparent !important;
        border-bottom: 1px solid #dee2e6;
    }
    
    .table th {
        background-color: #f8f9fa !important;
    }
    
    @page {
        size: A4 landscape;
        margin: 1cm;
    }
    
    body {
        padding: 0;
        font-size: 10pt;
    }
    
    h1, h2, h3, h4, h5, h6 {
        page-break-after: avoid;
    }
    
    .card {
        page-break-inside: avoid;
    }
}
</style>
