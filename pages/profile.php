<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Pastikan user sudah login
if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

// Inisialisasi variabel
$success = '';
$error = '';
$user_id = $_SESSION['user_id'];

// Dapatkan data user
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $error = 'Pengguna tidak ditemukan';
} else {
    // Proses update profil
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Validasi input
        if (empty($full_name)) {
            $errors[] = 'Nama lengkap harus diisi';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid';
        }
        
        // Cek email unik
        $check_email_sql = 'SELECT id FROM users WHERE email = ? AND id != ?';
        $stmt = $pdo->prepare($check_email_sql);
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            $errors[] = 'Email sudah digunakan oleh pengguna lain';
        }
        
        // Jika ada password baru yang dimasukkan
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = 'Masukkan password saat ini untuk mengubah password';
            } elseif (md5($current_password) !== $user['password']) {
                $errors[] = 'Password saat ini tidak valid';
            } elseif (strlen($new_password) < 6) {
                $errors[] = 'Password baru minimal 6 karakter';
            } elseif ($new_password !== $confirm_password) {
                $errors[] = 'Konfirmasi password baru tidak cocok';
            }
        }
        
        // Jika tidak ada error, update data
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                if (!empty($new_password)) {
                    // Update dengan password baru (menggunakan MD5)
                    $hashed_password = md5($new_password);
                    $sql = "UPDATE users SET full_name = ?, email = ?, password = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$full_name, $email, $hashed_password, $user_id]);
                } else {
                    // Update tanpa mengubah password
                    $sql = "UPDATE users SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$full_name, $email, $user_id]);
                }
                
                $pdo->commit();
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                $success = 'Profil berhasil diperbarui';
                
                // Refresh data user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Profil Saya</h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="mb-3">
                    <div class="avatar-xxl">
                        <span class="avatar-initial bg-primary text-white rounded-circle" style="width: 100px; height: 100px; font-size: 3rem; line-height: 100px;">
                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                        </span>
                    </div>
                </div>
                <h4 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p class="text-muted mb-3">@<?php echo htmlspecialchars($user['username']); ?></p>
                
                <div class="d-flex justify-content-center gap-2 mb-3">
                    <?php if ($user['is_admin']): ?>
                        <span class="badge bg-primary">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">User</span>
                    <?php endif; ?>
                </div>
                
                <div class="text-start mt-4">
                    <h6 class="text-uppercase text-muted">Informasi Akun</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <i class='bx bx-envelope me-2'></i> 
                            <?php echo htmlspecialchars($user['email']); ?>
                        </li>
                        <li class="mb-2">
                            <i class='bx bx-calendar me-2'></i> 
                            Bergabung pada <?php echo date('d M Y', strtotime($user['created_at'])); ?>
                        </li>
                        <li>
                            <i class='bx bx-time me-2'></i> 
                            Terakhir diperbarui <?php echo date('d M Y H:i', strtotime($user['updated_at'])); ?>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Aktivitas Terakhir</h6>
            </div>
            <div class="card-body">
                <?php
                // Dapatkan aktivitas terakhir (backup/restore)
                $activity_sql = "
                    (
                        SELECT 
                            'backup' as type,
                            created_at,
                            'Database backup: ' || database_name as description,
                            status
                        FROM backup_history 
                        WHERE user_id = ?
                    )
                    UNION ALL
                    (
                        SELECT 
                            'restore' as type,
                            created_at,
                            'Database restore: ' || database_name as description,
                            status
                        FROM restore_history 
                        WHERE user_id = ?
                    )
                    ORDER BY created_at DESC
                    LIMIT 5
                ";
                
                $stmt = $pdo->prepare($activity_sql);
                $stmt->execute([$user_id, $user_id]);
                $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (count($activities) > 0): ?>
                    <div class="timeline">
                        <?php foreach ($activities as $activity): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker">
                                    <?php if ($activity['type'] === 'backup'): ?>
                                        <i class='bx bx-cloud-upload text-<?php echo $activity['status'] === 'success' ? 'success' : 'danger'; ?>'></i>
                                    <?php else: ?>
                                        <i class='bx bx-cloud-download text-<?php echo $activity['status'] === 'success' ? 'success' : 'danger'; ?>'></i>
                                    <?php endif; ?>
                                </div>
                                <div class="timeline-content">
                                    <p class="mb-0"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <small class="text-muted">
                                        <?php echo date('d M Y H:i', strtotime($activity['created_at'])); ?>
                                        <span class="badge bg-<?php 
                                            echo $activity['status'] === 'success' ? 'success' : 'danger'; 
                                        ?> bg-opacity-10 text-<?php 
                                            echo $activity['status'] === 'success' ? 'success' : 'danger';
                                        ?> ms-2">
                                            <?php echo ucfirst($activity['status']); ?>
                                        </span>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="?page=reports" class="btn btn-sm btn-outline-primary">Lihat Semua Aktivitas</a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-3">
                        <i class='bx bx-time text-muted' style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">Belum ada aktivitas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Edit Profil</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="form-text">Username tidak dapat diubah</div>
                        </div>
                        <div class="col-md-6">
                            <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <hr class="my-4">
                    <h6 class="mb-3">Ubah Password</h6>
                    <p class="text-muted small">Biarkan kosong jika tidak ingin mengubah password</p>
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Password Saat Ini</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password">
                                <i class='bx bx-hide'></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                                <div class="form-text">Minimal 6 karakter</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password">
                                        <i class='bx bx-hide'></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">
                            Batal
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-save me-1'></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0">Zona Berbahaya</h6>
            </div>
            <div class="card-body">
                <h6 class="text-danger mb-3">Hapus Akun</h6>
                <p class="small text-muted">
                    Menghapus akun Anda akan menghapus semua data yang terkait dengan akun ini. 
                    Tindakan ini tidak dapat dibatalkan.
                </p>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    <i class='bx bx-trash me-1'></i> Hapus Akun Saya
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hapus Akun -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus Akun</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus akun Anda?</p>
                <p class="text-danger">
                    <i class='bx bx-error-circle me-1'></i>
                    <strong>Peringatan:</strong> Tindakan ini tidak dapat dibatalkan. Semua data Anda akan dihapus secara permanen.
                </p>
                
                <form id="deleteAccountForm" method="POST" action="delete_account.php">
                    <div class="mb-3">
                        <label for="verify_password" class="form-label">Masukkan password Anda untuk melanjutkan</label>
                        <input type="password" class="form-control" id="verify_password" name="password" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" form="deleteAccountForm" class="btn btn-danger">
                    <i class='bx bx-trash me-1'></i> Hapus Akun Saya
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
$('.toggle-password').on('click', function() {
    const target = $(this).data('target');
    const input = $('#' + target);
    const icon = $(this).find('i');
    
    if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('bx-hide').addClass('bx-show');
    } else {
        input.attr('type', 'password');
        icon.removeClass('bx-show').addClass('bx-hide');
    }
});

// Validasi form
$('form').on('submit', function(e) {
    const newPassword = $('#new_password').val();
    const confirmPassword = $('#confirm_password').val();
    const currentPassword = $('#current_password').val();
    
    // Jika ada password baru yang dimasukkan
    if (newPassword || confirmPassword) {
        if (!currentPassword) {
            e.preventDefault();
            alert('Harap masukkan password saat ini untuk mengubah password');
            $('#current_password').focus();
            return false;
        }
        
        if (newPassword.length > 0 && newPassword.length < 6) {
            e.preventDefault();
            alert('Password baru minimal 6 karakter');
            $('#new_password').focus();
            return false;
        }
        
        if (newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Konfirmasi password baru tidak cocok');
            return false;
        }
    }
    
    return true;
});

// Tangani penghapusan akun
$('#deleteAccountForm').on('submit', function(e) {
    if (!confirm('Apakah Anda yakin ingin menghapus akun Anda? Tindakan ini tidak dapat dibatalkan.')) {
        e.preventDefault();
        return false;
    }
    
    return true;
});
</script>

<style>
.avatar-xxl {
    width: 120px;
    height: 120px;
    margin: 0 auto 1rem;
}

.avatar-initial {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-weight: 600;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline-item {
    position: relative;
    padding-bottom: 1.5rem;
    padding-left: 1.5rem;
    border-left: 1px solid #e9ecef;
}

.timeline-item:last-child {
    padding-bottom: 0;
    border-left-color: transparent;
}

.timeline-marker {
    position: absolute;
    left: -0.5rem;
    top: 0;
    width: 1rem;
    height: 1rem;
    border-radius: 50%;
    background-color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.timeline-content {
    padding-left: 0.5rem;
}

.timeline-item:last-child .timeline-content {
    padding-bottom: 0;
}
</style>
