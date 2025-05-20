<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap CSS dan JavaScript -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
        }
        
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fc;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #4e73df 10%, #224abe 100%);
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 1rem;
            margin: 0.2rem 0;
            border-radius: 0.35rem;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .topbar {
            height: 4.375rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            background-color: white;
        }
        
        .page-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #4e73df;
            margin-bottom: 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            font-weight: 600;
            padding: 1rem 1.25rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        
        .alert {
            border: none;
            border-left: 0.25rem solid;
        }
        
        .alert-success {
            border-left-color: var(--success-color);
        }
        
        .alert-danger {
            border-left-color: var(--danger-color);
        }
        
        .user-dropdown {
            padding: 0.5rem 1rem;
            border-radius: 0.35rem;
            transition: all 0.2s;
        }
        
        .user-dropdown:hover {
            background-color: rgba(0, 0, 0, 0.1);
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 0.35rem;
            padding: 0.5rem 0;
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            color: #5a5c69;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fc;
            color: #4e73df;
        }
        
        .dropdown-divider {
            border-top: 1px solid #eaecf4;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-auto px-0">
                <div class="sidebar px-3 py-4">
                    <div class="d-flex align-items-center mb-4">
                        <div class="me-2">
                            <i class='bx bxs-data' style="font-size: 2rem;"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-bold"><?php echo APP_NAME; ?></h5>
                            <small class="text-white-50">v1.0.0</small>
                        </div>
                    </div>
                    
                    <hr class="my-4 bg-white-50">
                    
                    <ul class="nav flex-column">
                        <!-- Menu untuk semua pengguna -->
                        <li class="nav-item">
                            <a href="?page=dashboard" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'dashboard' || !isset($_GET['page'])) ? 'active' : ''; ?>">
                                <i class='bx bxs-dashboard'></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <!-- Menu Backup -->
                        <li class="nav-item">
                            <a href="index.php?page=backup" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'backup') ? 'active' : ''; ?>">
                                <i class='bx bxs-archive-in'></i>
                                Backup
                            </a>
                        </li>
                        
                        <!-- Menu Restore -->
                        <li class="nav-item">
                            <a href="index.php?page=restore" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'restore') ? 'active' : ''; ?>">
                                <i class='bx bxs-archive-out'></i>
                                Restore
                            </a>
                        </li>
                        
                        <!-- Menu Kelola Backup (Hanya untuk Admin) -->
                        <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'manage_backups') ? 'active' : ''; ?>" href="index.php?page=manage_backups">
                                <i class='bx bx-list-ul'></i>
                                <span>Kelola Backup</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Menu Laporan -->
                        <li class="nav-item">
                            <a href="?page=reports" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'reports') ? 'active' : ''; ?>">
                                <i class='bx bxs-report'></i>
                                Laporan
                            </a>
                        </li>
                        
                        <!-- Menu Khusus Admin -->
                        <?php if (is_admin()): ?>
                        <li class="nav-item mt-4">
                            <small class="text-uppercase fw-bold text-white-50">Administrasi</small>
                        </li>
                        
                        <!-- Menu Jadwal Backup (Hanya Admin) -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] === 'schedule') ? 'active' : ''; ?>" href="index.php?page=schedule">
                                <i class='bx bx-calendar'></i>
                                <span>Jadwal Backup</span>
                            </a>
                        </li>
                        
                        <!-- Menu Pengguna (Hanya Admin) -->
                        <li class="nav-item">
                            <a href="?page=users" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'users') ? 'active' : ''; ?>">
                                <i class='bx bxs-user-account'></i>
                                Pengguna
                            </a>
                        </li>
                        
                        <!-- Menu Pengaturan (Hanya Admin) -->
                        <li class="nav-item">
                            <a href="?page=settings" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'settings') ? 'active' : ''; ?>">
                                <i class='bx bxs-cog'></i>
                                Pengaturan
                            </a>
                        </li>
                        
                        <!-- Menu Audit Log (Hanya Admin) -->
                        <li class="nav-item">
                            <a href="?page=audit_log" class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'audit_log') ? 'active' : ''; ?>">
                                <i class='bx bxs-book-alt'></i>
                                Audit Log
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col px-0 d-flex flex-column" style="min-height: 100vh;">
                <!-- Topbar -->
                <nav class="navbar navbar-expand topbar">
                    <div class="container-fluid">
                        <!-- Sidebar Toggle (Topbar) -->
                        <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle me-3">
                            <i class='bx bx-menu'></i>
                        </button>
                        
                        <!-- Topbar Navbar -->
                        <ul class="navbar-nav ms-auto">
                            <!-- Nav Item - User Information -->
                            <li class="nav-item dropdown no-arrow">
                                <a class="nav-link dropdown-toggle user-dropdown d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="me-2 d-none d-lg-inline">
                                        <span class="text-dark small"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                                    </div>
                                    <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class='bx bxs-user text-white'></i>
                                    </div>
                                </a>
                                <!-- Dropdown - User Information -->
                                <div class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                                    <a class="dropdown-item" href="profile.php">
                                        <i class='bx bxs-user me-2'></i>
                                        Profil
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="controllers/logout.php">
                                        <i class='bx bx-log-out me-2'></i>
                                        Keluar
                                    </a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </nav>
                <!-- End of Topbar -->

                <!-- Begin Page Content -->
                <div class="container-fluid py-4 px-4">
                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800"><?php echo $page_title ?? 'Dashboard'; ?></h1>
                        <?php if (isset($page_actions)): ?>
                            <div class="d-flex">
                                <?php echo $page_actions; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content Row -->
                    <div class="row">
                        <div class="col-12">
                            <?php
                            // Tampilkan pesan flash jika ada
                            $flash = get_flash_message();
                            if ($flash): ?>
                                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                                    <?php echo htmlspecialchars($flash['message']); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endif; ?>
