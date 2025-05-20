<?php
// Pastikan user sudah login dan memiliki akses admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: index.php?page=dashboard');
    exit();
}

// Koneksi ke database
$pdo = get_db_connection();

// Judul halaman
$page_title = 'Audit Log';

// Filter
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_entity = isset($_GET['entity']) ? $_GET['entity'] : '';
$filter_user = isset($_GET['user_id']) ? $_GET['user_id'] : '';
$filter_date_start = isset($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) ? $_GET['date_end'] : '';
$filter_ip = isset($_GET['ip_address']) ? $_GET['ip_address'] : '';

// Cek apakah ada filter yang aktif
$is_filtered = !empty($filter_action) || !empty($filter_entity) || !empty($filter_user) || 
              !empty($filter_date_start) || !empty($filter_date_end) || !empty($filter_ip);

// Pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Query dasar
$query = "SELECT a.*, u.username, u.full_name 
          FROM audit_log a 
          LEFT JOIN users u ON a.user_id = u.id 
          WHERE 1=1";
$params = [];

// Tambahkan filter ke query
if (!empty($filter_action)) {
    $query .= " AND a.action = ?";
    $params[] = $filter_action;
}

if (!empty($filter_entity)) {
    $query .= " AND a.entity_type = ?";
    $params[] = $filter_entity;
}

if (!empty($filter_user)) {
    $query .= " AND a.user_id = ?";
    $params[] = $filter_user;
}

if (!empty($filter_date_start)) {
    $query .= " AND a.created_at >= ?";
    $params[] = $filter_date_start . ' 00:00:00';
}

if (!empty($filter_ip)) {
    $query .= " AND a.ip_address LIKE ?";
    $params[] = "%$filter_ip%";
}

if (!empty($filter_date_end)) {
    $query .= " AND a.created_at <= ?";
    $params[] = $filter_date_end . ' 23:59:59';
}

// Query untuk total records
$count_query = str_replace("SELECT a.*, u.username, u.full_name", "SELECT COUNT(*) as total", $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// Query untuk data dengan pagination
$query .= " ORDER BY a.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dapatkan daftar pengguna untuk filter
$stmt = $pdo->query("SELECT id, username, full_name FROM users ORDER BY username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dapatkan daftar jenis aksi untuk filter
$stmt = $pdo->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
$actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dapatkan daftar jenis entitas untuk filter
$stmt = $pdo->query("SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type");
$entities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid" id="audit-log-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Audit Log</h1>
        <div>
            <a href="index.php?page=export_csv&<?php echo http_build_query(array_filter(array(
                'action' => $filter_action,
                'entity' => $filter_entity,
                'user_id' => $filter_user,
                'date_start' => $filter_date_start,
                'date_end' => $filter_date_end,
                'ip_address' => $filter_ip
            ))); ?>" class="btn btn-success">
                <i class='bx bx-export'></i> Ekspor ke CSV
            </a>
        </div>
    </div>
    
    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-header d-flex align-items-center">
            <h6 class="m-0 font-weight-bold">Filter</h6>
            <button class="btn btn-sm btn-link ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                <i class='bx bx-chevron-down'></i>
            </button>
        </div>
        <div class="collapse <?php echo $is_filtered ? 'show' : ''; ?>" id="filterCollapse">
            <div class="card-body">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="audit_log">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="action" class="form-label">Jenis Aksi</label>
                            <select class="form-select" id="action" name="action">
                                <option value="">Semua Aksi</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action['action']); ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($action['action'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="entity" class="form-label">Jenis Entitas</label>
                            <select class="form-select" id="entity" name="entity">
                                <option value="">Semua Entitas</option>
                                <?php foreach ($entities as $entity): ?>
                                    <option value="<?php echo htmlspecialchars($entity['entity_type']); ?>" <?php echo $filter_entity == $entity['entity_type'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucfirst($entity['entity_type'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="user_id" class="form-label">Pengguna</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">Semua Pengguna</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_start" class="form-label">Tanggal Mulai</label>
                            <input type="date" class="form-control" id="date_start" name="date_start" value="<?php echo $filter_date_start; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="date_end" class="form-label">Tanggal Akhir</label>
                            <input type="date" class="form-control" id="date_end" name="date_end" value="<?php echo $filter_date_end; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="ip_address" class="form-label">IP Address</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" value="<?php echo htmlspecialchars($filter_ip); ?>" placeholder="Cari IP Address...">
                        </div>
                        <div class="col-md-12 text-end mt-3">
                            <a href="?page=audit_log" class="btn btn-outline-secondary">Reset</a>
                            <button type="submit" class="btn btn-primary" id="filterButton">Filter</button>
                            <input type="hidden" name="scrollPosition" id="scrollPosition" value="0">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Audit Log Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center">
            <h6 class="m-0 font-weight-bold">Daftar Aktivitas</h6>
            <span class="ms-auto badge bg-primary"><?php echo $total_records; ?> entri</span>
        </div>
        <div class="card-body">
            <?php if (empty($logs)): ?>
                <div class="text-center py-4">
                    <i class='bx bx-info-circle text-muted' style="font-size: 3rem;"></i>
                    <p class="mt-2 text-muted">Tidak ada data audit log yang ditemukan</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                                <th>Aksi</th>
                                <th>Entitas</th>
                                <th>Detail</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo date('d M Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php if ($log['username']): ?>
                                            <?php echo htmlspecialchars($log['username']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">User ID: <?php echo $log['user_id']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $badge_class = 'bg-secondary';
                                            switch ($log['action']) {
                                                case 'create': $badge_class = 'bg-success'; break;
                                                case 'update': $badge_class = 'bg-primary'; break;
                                                case 'delete': $badge_class = 'bg-danger'; break;
                                                case 'login': $badge_class = 'bg-info'; break;
                                                case 'logout': $badge_class = 'bg-warning'; break;
                                                case 'restore': $badge_class = 'bg-purple'; break;
                                            }
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst($log['action']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo ucfirst($log['entity_type']); ?></td>
                                    <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=audit_log&p=<?php echo $page-1; ?>&action=<?php echo urlencode($filter_action); ?>&entity=<?php echo urlencode($filter_entity); ?>&user_id=<?php echo urlencode($filter_user); ?>&date_start=<?php echo urlencode($filter_date_start); ?>&date_end=<?php echo urlencode($filter_date_end); ?>&ip_address=<?php echo urlencode($filter_ip); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=audit_log&p=1&action='.urlencode($filter_action).'&entity='.urlencode($filter_entity).'&user_id='.urlencode($filter_user).'&date_start='.urlencode($filter_date_start).'&date_end='.urlencode($filter_date_end).'">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            echo '<li class="page-item '.($page == $i ? 'active' : '').'"><a class="page-link" href="?page=audit_log&p='.$i.'&action='.urlencode($filter_action).'&entity='.urlencode($filter_entity).'&user_id='.urlencode($filter_user).'&date_start='.urlencode($filter_date_start).'&date_end='.urlencode($filter_date_end).'">'.$i.'</a></li>';
                        }
                        
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page=audit_log&p='.$total_pages.'&action='.urlencode($filter_action).'&entity='.urlencode($filter_entity).'&user_id='.urlencode($filter_user).'&date_start='.urlencode($filter_date_start).'&date_end='.urlencode($filter_date_end).'&ip_address='.urlencode($filter_ip).'">'.$total_pages.'</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=audit_log&p=<?php echo $page+1; ?>&action=<?php echo urlencode($filter_action); ?>&entity=<?php echo urlencode($filter_entity); ?>&user_id=<?php echo urlencode($filter_user); ?>&date_start=<?php echo urlencode($filter_date_start); ?>&date_end=<?php echo urlencode($filter_date_end); ?>&ip_address=<?php echo urlencode($filter_ip); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Menyimpan posisi scroll saat tombol filter diklik
    document.getElementById('filterButton').addEventListener('click', function() {
        document.getElementById('scrollPosition').value = window.scrollY;
    });
    
    // Memulihkan posisi scroll setelah halaman dimuat
    const urlParams = new URLSearchParams(window.location.search);
    const scrollPos = urlParams.get('scrollPosition');
    
    if (scrollPos) {
        setTimeout(function() {
            window.scrollTo(0, parseInt(scrollPos));
        }, 100);
    }
    
    // Jika ada parameter filter, buka collapse filter
    if (urlParams.has('action') || urlParams.has('entity') || urlParams.has('user_id') || 
        urlParams.has('date_start') || urlParams.has('date_end') || urlParams.has('ip_address')) {
        document.getElementById('filterCollapse').classList.add('show');
    }
});
</script>
