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
$edit_id = $_GET['edit'] ?? 0;
$is_edit = !empty($edit_id);
$user = null;

// Jika mode edit, ambil data pengguna
if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$edit_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $error = 'Pengguna tidak ditemukan';
        $is_edit = false;
    }
}

// Proses form tambah/edit pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validasi input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username harus diisi';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username hanya boleh berisi huruf, angka, dan underscore';
    }
    
    if (empty($full_name)) {
        $errors[] = 'Nama lengkap harus diisi';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid';
    }
    
    // Jika mode tambah atau mengganti password
    if (!$is_edit || !empty($password)) {
        if (empty($password)) {
            $errors[] = 'Password harus diisi';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password minimal 6 karakter';
        } elseif ($password !== $confirm_password) {
            $errors[] = 'Konfirmasi password tidak cocok';
        }
    }
    
    // Cek username unik
    $check_username_sql = 'SELECT id FROM users WHERE username = ?';
    $check_username_params = [$username];
    
    if ($is_edit) {
        $check_username_sql .= ' AND id != ?';
        $check_username_params[] = $edit_id;
    }
    
    $stmt = $pdo->prepare($check_username_sql);
    $stmt->execute($check_username_params);
    
    if ($stmt->fetch()) {
        $errors[] = 'Username sudah digunakan';
    }
    
    // Cek email unik
    $check_email_sql = 'SELECT id FROM users WHERE email = ?';
    $check_email_params = [$email];
    
    if ($is_edit) {
        $check_email_sql .= ' AND id != ?';
        $check_email_params[] = $edit_id;
    }
    
    $stmt = $pdo->prepare($check_email_sql);
    $stmt->execute($check_email_params);
    
    if ($stmt->fetch()) {
        $errors[] = 'Email sudah digunakan';
    }
    
    // Jika tidak ada error, simpan data
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($is_edit) {
                // Update user
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, full_name = ?, email = ?, is_admin = ?, password = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $full_name, $email, $is_admin, $hashed_password, $edit_id]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, full_name = ?, email = ?, is_admin = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $full_name, $email, $is_admin, $edit_id]);
                }
                
                $success = 'Data pengguna berhasil diperbarui';
            } else {
                // Tambah user baru
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, full_name, email, password, is_admin, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$username, $full_name, $email, $hashed_password, $is_admin]);
                
                $success = 'Pengguna berhasil ditambahkan';
                
                // Reset form
                $_POST = [];
            }
            
            $pdo->commit();
            
            // Redirect untuk menghindari resubmit form
            header('Location: ?page=users&success=' . urlencode($success));
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Proses hapus pengguna
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Cek apakah user yang akan dihapus ada
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$delete_id]);
    
    if ($stmt->fetch()) {
        try {
            $pdo->beginTransaction();
            
            // Hapus user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            $pdo->commit();
            
            $success = 'Pengguna berhasil dihapus';
            
            // Redirect untuk menghindari resubmit
            header('Location: ?page=users&success=' . urlencode($success));
            exit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Gagal menghapus pengguna: ' . $e->getMessage();
        }
    } else {
        $error = 'Pengguna tidak ditemukan';
    }
}

// Tampilkan pesan sukses dari URL
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Dapatkan daftar pengguna
$users = [];
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? 'all';

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($role !== 'all') {
    $sql .= " AND is_admin = ?";
    $params[] = $role === 'admin' ? 1 : 0;
}

$sql .= " ORDER BY username ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manajemen Pengguna</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class='bx bx-plus'></i> Tambah Pengguna
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- Filter dan Pencarian -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <input type="hidden" name="page" value="users">
            
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class='bx bx-search'></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Cari username, nama, atau email..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <div class="col-md-3">
                <select class="form-select" name="role">
                    <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>Semua Peran</option>
                    <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User Biasa</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class='bx bx-filter-alt'></i> Filter
                </button>
            </div>
            
            <div class="col-md-2">
                <a href="?page=users" class="btn btn-outline-secondary w-100">
                    <i class='bx bx-reset'></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabel Pengguna -->
<div class="card">
    <div class="card-body p-0">
        <?php if (count($users) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Peran</th>
                            <th>Terakhir Diperbarui</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2">
                                            <span class="avatar-initial bg-primary text-white rounded-circle">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $user['is_admin'] ? 'primary' : 'secondary'; ?>">
                                        <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small text-muted">
                                        <?php echo date('d M Y H:i', strtotime($user['updated_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=users&edit=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary" 
                                           title="Edit">
                                            <i class='bx bx-edit'></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-danger delete-user" 
                                                    data-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    title="Hapus">
                                                <i class='bx bx-trash'></i>
                                            </button>
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
                    <i class='bx bx-user-x text-muted' style="font-size: 3rem;"></i>
                </div>
                <h5>Data tidak ditemukan</h5>
                <p class="text-muted">Tidak ada data pengguna yang sesuai dengan filter yang dipilih.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah/Edit Pengguna -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo $is_edit ? 'Edit' : 'Tambah'; ?> Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required
                               value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                        <div class="form-text">Hanya boleh berisi huruf, angka, dan underscore</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required
                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">
                            <?php echo $is_edit ? 'Password Baru' : 'Password'; ?> 
                            <?php if ($is_edit): ?>
                                <small class="text-muted">(Kosongkan jika tidak ingin mengubah)</small>
                            <?php else: ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password"
                                   <?php echo !$is_edit ? 'required' : ''; ?>>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class='bx bx-hide'></i>
                            </button>
                        </div>
                        <div class="form-text">Minimal 6 karakter</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">
                            Konfirmasi Password
                            <?php if (!$is_edit): ?>
                                <span class="text-danger">*</span>
                            <?php endif; ?>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                                   <?php echo !$is_edit ? 'required' : ''; ?>>
                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                <i class='bx bx-hide'></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_admin" name="is_admin" value="1"
                               <?php echo ($user['is_admin'] ?? 0) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_admin">Admin</label>
                        <div class="form-text">Admin memiliki akses penuh ke sistem</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus pengguna <strong id="deleteUsername"></strong>?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan semua data terkait pengguna ini akan dihapus.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" id="confirmDeleteUser" class="btn btn-danger">
                    <i class='bx bx-trash'></i> Hapus
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
$('.toggle-password').on('click', function() {
    const input = $(this).siblings('input');
    const icon = $(this).find('i');
    
    if (input.attr('type') === 'password') {
        input.attr('type', 'text');
        icon.removeClass('bx-hide').addClass('bx-show');
    } else {
        input.attr('type', 'password');
        icon.removeClass('bx-show').addClass('bx-hide');
    }
});

// Tangani tombol hapus
$('.delete-user').on('click', function() {
    const id = $(this).data('id');
    const username = $(this).data('username');
    
    $('#deleteUsername').text(username);
    $('#confirmDeleteUser').attr('href', '?page=users&delete=' + id);
    
    const modal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    modal.show();
});

// Tampilkan modal jika dalam mode edit
<?php if ($is_edit): ?>
    $(document).ready(function() {
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        modal.show();
    });
<?php endif; ?>

// Validasi form
$('form').on('submit', function(e) {
    const password = $('#password').val();
    const confirmPassword = $('#confirm_password').val();
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Konfirmasi password tidak cocok');
        return false;
    }
    
    return true;
});

// Tampilkan modal tambah pengguna
$('[data-bs-target="#addUserModal"]').on('click', function() {
    // Reset form
    $('#userModal form')[0].reset();
    $('#userModal input[type="hidden"]').remove();
    
    // Update judul modal
    $('#userModal .modal-title').text('Tambah Pengguna');
    
    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();
});
</script>
