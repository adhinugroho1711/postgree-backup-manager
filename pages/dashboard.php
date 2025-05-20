<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Dapatkan koneksi database
$pdo = get_db_connection();

// Hitung total backup
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM backup_history");
    $total_backups = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $total_backups = 0;
}

// Hitung total pengguna
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $total_users = 0;
}

// Hitung total ukuran backup
try {
    $backup_dir = defined('BACKUP_DIR') ? BACKUP_DIR : __DIR__ . '/../../backups';
    $total_size = get_directory_size($backup_dir);
} catch (Exception $e) {
    $total_size = 0;
}

// Hitung backup sukses dan gagal
try {
    $stmt = $pdo->query("SELECT status, COUNT(*) as total FROM backup_history GROUP BY status");
    $status_counts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_counts[$row['status']] = (int)$row['total'];
    }
    $success_count = $status_counts['completed'] ?? 0;
    $failed_count = $status_counts['failed'] ?? 0;
} catch (PDOException $e) {
    $success_count = 0;
    $failed_count = 0;
}

// Hitung backup 7 hari terakhir
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM backup_history WHERE created_at >= NOW() - INTERVAL '7 days'");
    $last_7_days = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    $last_7_days = 0;
}

// Dapatkan backup terbaru
try {
    $stmt = $pdo->query("SELECT id, filename, database_name, file_size as size, status, created_at FROM backup_history ORDER BY created_at DESC LIMIT 5");
    $recent_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pastikan status menggunakan format yang benar
    foreach ($recent_backups as &$backup) {
        if ($backup['status'] === 'completed') {
            $backup['status'] = 'success';
        }
        
        // Jika ukuran tidak ada, coba dapatkan dari file
        if (empty($backup['size'])) {
            $backup_path = $backup_dir . '/' . $backup['filename'];
            if (file_exists($backup_path)) {
                $backup['size'] = filesize($backup_path);
            } else {
                $backup['size'] = 0;
            }
        }
    }
    unset($backup); // Hapus referensi
} catch (PDOException $e) {
    $recent_backups = [];
}

// Buat array stats untuk digunakan di template
$stats = [
    'total' => $total_backups,
    'success' => $success_count,
    'failed' => $failed_count,
    'last_7_days' => $last_7_days,
    'total_size' => $total_size
];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="?page=backup" class="btn btn-sm btn-outline-primary">
                <i class='bx bxs-plus-circle'></i> Backup Baru
            </a>
        </div>
    </div>
</div>

<!-- Statistik -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Backup</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_backups); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class='bx bxs-archive-in bx-lg text-gray-300'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Pengguna</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_users); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class='bx bxs-user-account bx-lg text-gray-300'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Ukuran Backup</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php
                            echo format_size($total_size);
                            ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class='bx bxs-data bx-lg text-gray-300'></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ringkasan Penyimpanan -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Ringkasan Penyimpanan</h5>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <span>Kapasitas Digunakan</span>
            <span><?php echo format_size($total_size); ?></span>
        </div>
        <div class="progress">
            <?php 
            $usage_percentage = $total_size > 0 ? min(($total_size / (1024 * 1024 * 1024)) * 100, 100) : 0; // Asumsi 1GB kapasitas
            ?>
            <div class="progress-bar bg-success" role="progressbar" 
                 style="width: <?php echo $usage_percentage; ?>%" 
                 aria-valuenow="<?php echo $usage_percentage; ?>" 
                 aria-valuemin="0" 
                 aria-valuemax="100">
                <?php echo round($usage_percentage, 1); ?>%
            </div>
        </div>
        <small class="text-muted">
            <i class='bx bx-info-circle'></i> Kapasitas penyimpanan: 1 GB
        </small>
    </div>
</div>

<!-- Backup Terbaru -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Backup Terbaru</h5>
        <a href="?page=backup" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
    </div>
    <div class="card-body p-0">
        <?php if (count($recent_backups) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama File</th>
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
                                <td><?php echo format_size($backup['size']); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($backup['created_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $backup['status'] === 'success' ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo ucfirst($backup['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=restore&id=<?php echo $backup['id']; ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Restore">
                                            <i class='bx bx-reset'></i>
                                        </a>
                                        <a href="download_backup.php?id=<?php echo $backup['id']; ?>" 
                                           class="btn btn-outline-success download-backup" 
                                           title="Unduh"
                                           data-id="<?php echo $backup['id']; ?>">
                                            <i class='bx bx-download'></i>
                                        </a>
                                        <button class="btn btn-outline-danger delete-backup" 
                                                title="Hapus"
                                                data-id="<?php echo $backup['id']; ?>">
                                            <i class='bx bx-trash'></i>
                                        </button>
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
                <h5>Belum ada backup</h5>
                <p class="text-muted">Mulai dengan membuat backup database Anda.</p>
                <a href="?page=backup" class="btn btn-primary">
                    <i class='bx bxs-plus-circle'></i> Buat Backup
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Hapus Backup -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Hapus Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus backup ini? Tindakan ini tidak dapat dibatalkan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Hapus</button>
            </div>
        </div>
    </div>
</div>

<script>
// Inisialisasi modal hapus backup
var deleteBackupModal = new bootstrap.Modal(document.getElementById('deleteBackupModal'));
var deleteUrl = '';

// Tangani klik tombol hapus
$('.delete-backup').on('click', function() {
    const id = $(this).data('id');
    deleteUrl = `ajax/delete_backup.php?id=${id}`;
    deleteBackupModal.show();
});

// Konfirmasi hapus
$('#confirmDelete').on('click', function() {
    if (deleteUrl) {
        window.location.href = deleteUrl;
    }
});
</script>
