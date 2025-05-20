<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Pastikan hanya admin yang bisa mengakses halaman ini
if (!is_admin()) {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

// Proses hapus backup (single atau multiple)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Hapus single backup
    if ($_POST['action'] === 'delete' && isset($_POST['backup_id'])) {
        $backup_id = $_POST['backup_id'];
        
        try {
            $pdo = get_db_connection();
            
            // Ambil informasi backup
            $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
            $stmt->execute([$backup_id]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$backup) {
                throw new Exception('Backup tidak ditemukan');
            }
            
            // Hapus file dari disk
            $filepath = BACKUP_DIR . '/' . $backup['filename'];
            if (file_exists($filepath)) {
                if (!unlink($filepath)) {
                    throw new Exception('Gagal menghapus file backup dari disk');
                }
            }
            
            // Hapus dari database
            $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
            if (!$stmt->execute([$backup_id])) {
                throw new Exception('Gagal menghapus backup dari database');
            }
            
            // Catat aktivitas
            $user_id = $_SESSION['user_id'] ?? 1;
            $log_message = "Backup {$backup['filename']} dihapus oleh user ID: $user_id";
            error_log($log_message, 3, __DIR__ . '/../backup_error.log');
            
            $success = "Backup berhasil dihapus";
        } catch (Exception $e) {
            $error = 'Gagal menghapus backup: ' . $e->getMessage();
            error_log("Error saat menghapus backup: " . $e->getMessage(), 3, __DIR__ . '/../backup_error.log');
        }
    }
    
    // Hapus multiple backup
    if ($_POST['action'] === 'delete_multiple' && isset($_POST['backup_ids']) && is_array($_POST['backup_ids'])) {
        $backup_ids = $_POST['backup_ids'];
        $deleted_count = 0;
        $failed_count = 0;
        
        // Log untuk debugging
        error_log("Mencoba menghapus multiple backup: " . print_r($backup_ids, true), 3, __DIR__ . '/../backup_error.log');
        
        try {
            $pdo = get_db_connection();
            $user_id = $_SESSION['user_id'] ?? 1;
            
            foreach ($backup_ids as $backup_id) {
                try {
                    // Ambil informasi backup
                    $stmt = $pdo->prepare("SELECT * FROM backup_history WHERE id = ?");
                    $stmt->execute([$backup_id]);
                    $backup = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$backup) {
                        $failed_count++;
                        continue;
                    }
                    
                    // Hapus file dari disk
                    $filepath = BACKUP_DIR . '/' . $backup['filename'];
                    if (file_exists($filepath)) {
                        if (!unlink($filepath)) {
                            $failed_count++;
                            continue;
                        }
                    }
                    
                    // Hapus dari database
                    $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
                    if (!$stmt->execute([$backup_id])) {
                        $failed_count++;
                        continue;
                    }
                    
                    // Catat aktivitas
                    $log_message = "Backup {$backup['filename']} dihapus oleh user ID: $user_id";
                    error_log($log_message, 3, __DIR__ . '/../backup_error.log');
                    
                    $deleted_count++;
                } catch (Exception $e) {
                    $failed_count++;
                    error_log("Error saat menghapus backup ID $backup_id: " . $e->getMessage(), 3, __DIR__ . '/../backup_error.log');
                }
            }
            
            if ($deleted_count > 0) {
                $success = "$deleted_count backup berhasil dihapus";
                if ($failed_count > 0) {
                    $success .= ", $failed_count backup gagal dihapus";
                }
            } else {
                $error = "Gagal menghapus backup";
            }
        } catch (Exception $e) {
            $error = 'Gagal menghapus backup: ' . $e->getMessage();
            error_log("Error saat menghapus multiple backup: " . $e->getMessage(), 3, __DIR__ . '/../backup_error.log');
        }
    }
}

// Dapatkan daftar backup
$backups = [];
try {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT bh.*, u.username 
                         FROM backup_history bh 
                         LEFT JOIN users u ON bh.user_id = u.id 
                         ORDER BY bh.created_at DESC");
    $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Gagal mengambil daftar backup: ' . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kelola File Backup</h1>
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

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Daftar File Backup</h5>
    </div>
    <div class="card-body">
        <form id="multipleDeleteForm" method="POST" action="handlers/delete_multiple.php">
            <div class="mb-3">
                <button type="button" id="deleteSelectedBtn" class="btn btn-danger" style="display:none;">
                    <i class='bx bx-trash'></i> Hapus File Terpilih (<span id="selectedCountBtn">0</span>)
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th>ID</th>
                            <th>Nama File</th>
                            <th>Ukuran</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="7" class="text-center">Tidak ada data backup</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td>
                                    <div class="form-check">
                                        <input class="form-check-input backup-checkbox" type="checkbox" name="backup_ids[]" value="<?php echo $backup['id']; ?>">
                                    </div>
                                </td>
                                <td><?php echo $backup['id']; ?></td>
                                <td>
                                    <?php if (file_exists(BACKUP_DIR . '/' . $backup['filename'])): ?>
                                        <a href="../download_file.php?file=<?php echo urlencode($backup['filename']); ?>">
                                            <?php echo htmlspecialchars($backup['filename']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($backup['filename']); ?>
                                        <span class="badge bg-danger">File tidak ditemukan</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if (isset($backup['size_bytes']) && $backup['size_bytes'] > 0) {
                                        echo format_size($backup['size_bytes']);
                                    } else {
                                        // Coba ambil ukuran file dari disk
                                        $filepath = BACKUP_DIR . '/' . $backup['filename'];
                                        if (file_exists($filepath)) {
                                            $filesize = filesize($filepath);
                                            // Update ukuran di database
                                            try {
                                                $update_stmt = $pdo->prepare("UPDATE backup_history SET size_bytes = ? WHERE id = ?");
                                                $update_stmt->execute([$filesize, $backup['id']]);
                                            } catch (Exception $e) {
                                                // Abaikan error update
                                            }
                                            echo format_size($filesize);
                                        } else {
                                            echo 'Tidak diketahui';
                                        }
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($backup['username'] ?? 'Admin'); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($backup['created_at'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $backup['status'] === 'success' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($backup['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="?page=backup_detail&id=<?php echo $backup['id']; ?>" class="btn btn-sm btn-info">
                                            <i class='bx bx-info-circle'></i>
                                        </a>
                                        <a href="../download_file.php?file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-sm btn-success">
                                            <i class='bx bx-download'></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $backup['id']; ?>">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Modal Konfirmasi Hapus -->
                                    <div class="modal fade" id="deleteModal<?php echo $backup['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Konfirmasi Hapus</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p>Apakah Anda yakin ingin menghapus backup <strong><?php echo htmlspecialchars($backup['filename']); ?></strong>?</p>
                                                    <div class="alert alert-warning">
                                                        <i class='bx bx-error'></i> Perhatian: Tindakan ini tidak dapat dibatalkan!
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="backup_id" value="<?php echo $backup['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-danger">Hapus</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </form>
    </div>
</div>

<script>
// Tampilkan pesan sukses/error hanya untuk beberapa detik
$(document).ready(function() {
    // Fade out alert setelah 5 detik
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Checkbox select all
    $('#selectAll').change(function() {
        $('.backup-checkbox').prop('checked', $(this).prop('checked'));
        updateDeleteButton();
    });
    
    // Update tombol hapus saat checkbox diubah
    $(document).on('change', '.backup-checkbox', function() {
        updateDeleteButton();
    });
    
    // Fungsi untuk update tampilan tombol hapus
    function updateDeleteButton() {
        var count = $('.backup-checkbox:checked').length;
        if (count > 0) {
            $('#deleteSelectedBtn').show();
            $('#selectedCountBtn').text(count);
        } else {
            $('#deleteSelectedBtn').hide();
            $('#selectedCountBtn').text('0');
        }
    }
    
    // Tombol hapus terpilih - pastikan bootstrap sudah dimuat
    $('#deleteSelectedBtn').click(function() {
        if ($('.backup-checkbox:checked').length > 0 && typeof bootstrap !== 'undefined') {
            // Tampilkan konfirmasi
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteMultipleModal'));
            deleteModal.show();
        }
    });
});
</script>

<!-- Modal Konfirmasi Hapus Multiple -->
<div class="modal fade" id="deleteMultipleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus Multiple Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus <span id="selectedCount"></span> file backup yang dipilih?</p>
                <div class="alert alert-warning">
                    <i class='bx bx-error'></i> Perhatian: Tindakan ini tidak dapat dibatalkan! Semua file backup yang dipilih akan dihapus dari server dan catatan backup akan dihapus dari database.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteMultiple">Hapus</button>
            </div>
        </div>
    </div>
</div>

<script>
// Pastikan kode ini dijalankan setelah bootstrap dimuat
document.addEventListener('DOMContentLoaded', function() {
    // Tunggu sampai bootstrap dimuat sepenuhnya
    var checkBootstrap = setInterval(function() {
        if (typeof bootstrap !== 'undefined') {
            clearInterval(checkBootstrap);
            
            // Update jumlah file yang dipilih di modal konfirmasi
            var deleteMultipleModal = document.getElementById('deleteMultipleModal');
            if (deleteMultipleModal) {
                deleteMultipleModal.addEventListener('show.bs.modal', function() {
                    var count = $('.backup-checkbox:checked').length;
                    $('#selectedCount').text(count);
                });
            }
        }
    }, 100);
    
    // Submit form saat konfirmasi - tidak memerlukan bootstrap
    $('#confirmDeleteMultiple').click(function() {
        // Pastikan semua checkbox yang dipilih tetap terkirim
        $('.backup-checkbox:checked').each(function() {
            $(this).prop('disabled', false);
        });
        
        // Debug - log ke console
        console.log('Form akan disubmit dengan data:');
        var formData = [];
        $('.backup-checkbox:checked').each(function() {
            formData.push($(this).val());
        });
        console.log(formData);
        
        // Submit form
        $('#multipleDeleteForm').submit();
    });
});
</script>
