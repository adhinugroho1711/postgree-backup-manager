<?php
// Halaman Detail Backup
$page_title = 'Detail Backup';

// Pastikan ID backup ada
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect ke halaman backup jika tidak ada ID
    header('Location: index.php?page=backup');
    exit();
}

$backup_id = (int)$_GET['id'];
$pdo = get_db_connection();

// Ambil data backup
$stmt = $pdo->prepare("
    SELECT b.*, u.username, u.full_name 
    FROM backup_history b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.id = :id
");
$stmt->bindParam(':id', $backup_id, PDO::PARAM_INT);
$stmt->execute();
$backup = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika backup tidak ditemukan
if (!$backup) {
    $error = 'Backup dengan ID tersebut tidak ditemukan';
}

// Format ukuran file
function format_size($size) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}
?>

<div class="container py-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <a href="index.php?page=backup" class="btn btn-primary">
            <i class="bx bx-arrow-back"></i> Kembali ke Daftar Backup
        </a>
    <?php else: ?>
        <div class="row mb-3">
            <div class="col-md-8">
                <h2>Detail Backup #<?php echo $backup_id; ?></h2>
            </div>
            <div class="col-md-4 text-end">
                <a href="index.php?page=backup" class="btn btn-outline-primary">
                    <i class="bx bx-arrow-back"></i> Kembali
                </a>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Informasi Backup</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">ID Backup</th>
                                <td><?php echo $backup['id']; ?></td>
                            </tr>
                            <tr>
                                <th>Nama File</th>
                                <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                            </tr>
                            <tr>
                                <th>Ukuran</th>
                                <td><?php echo format_size($backup['size_bytes']); ?> (<?php echo number_format($backup['size_bytes']); ?> bytes)</td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php if ($backup['status'] == 'success'): ?>
                                        <span class="badge bg-success">Berhasil</span>
                                    <?php elseif ($backup['status'] == 'failed'): ?>
                                        <span class="badge bg-danger">Gagal</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $backup['status']; ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th width="150">Dibuat Oleh</th>
                                <td><?php echo htmlspecialchars($backup['full_name'] ?? $backup['username'] ?? 'Unknown'); ?></td>
                            </tr>
                            <tr>
                                <th>Tanggal Dibuat</th>
                                <td><?php echo date('d M Y H:i:s', strtotime($backup['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Catatan</th>
                                <td><?php echo !empty($backup['notes']) ? htmlspecialchars($backup['notes']) : '<em>Tidak ada catatan</em>'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Aksi</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (file_exists(__DIR__ . '/../backups/' . $backup['filename'])): ?>
                                <a href="../download_file.php?file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-success">
                                    <i class="bx bx-download"></i> Download Backup
                                </a>
                                <a href="index.php?page=restore&backup_id=<?php echo $backup['id']; ?>" class="btn btn-warning">
                                    <i class="bx bx-reset"></i> Restore dari Backup Ini
                                </a>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteBackupModal">
                                    <i class="bx bx-trash"></i> Hapus Backup
                                </button>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bx bx-error"></i> File backup tidak ditemukan di server.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Riwayat Restore</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        // Ambil riwayat restore untuk backup ini
                        $stmt = $pdo->prepare("
                            SELECT r.*, u.username, u.full_name 
                            FROM restore_history r
                            LEFT JOIN users u ON r.user_id = u.id
                            WHERE r.backup_id = :backup_id
                            ORDER BY r.created_at DESC
                        ");
                        $stmt->bindParam(':backup_id', $backup_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $restores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        
                        <?php if (count($restores) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>User</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($restores as $restore): ?>
                                            <tr>
                                                <td><?php echo date('d M Y H:i', strtotime($restore['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($restore['full_name'] ?? $restore['username'] ?? 'Unknown'); ?></td>
                                                <td>
                                                    <?php if ($restore['status'] == 'success'): ?>
                                                        <span class="badge bg-success">Berhasil</span>
                                                    <?php elseif ($restore['status'] == 'failed'): ?>
                                                        <span class="badge bg-danger">Gagal</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?php echo $restore['status']; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Belum ada riwayat restore untuk backup ini.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
