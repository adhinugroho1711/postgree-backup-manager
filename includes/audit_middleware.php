<?php
/**
 * File ini berisi fungsi-fungsi untuk mencatat aktivitas pengguna ke audit log
 */

// Pastikan session sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Mencatat akses ke halaman/menu
 * 
 * @param string $page Nama halaman yang diakses
 * @return void
 */
function log_page_access($page) {
    if (!function_exists('log_audit') || !function_exists('get_current_user')) {
        return;
    }
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $action = 'page_access';
    $entity_type = 'page';
    $entity_id = null;
    $details = "Mengakses halaman: $page";
    
    log_audit($user_id, $action, $entity_type, $entity_id, $details);
}

/**
 * Mencatat aktivitas login
 * 
 * @param int $user_id ID pengguna yang login
 * @param string $username Username pengguna
 * @param bool $success Status login (berhasil/gagal)
 * @return void
 */
function log_login_activity($user_id, $username, $success = true) {
    if (!function_exists('log_audit')) {
        return;
    }
    
    $action = $success ? 'login_success' : 'login_failed';
    $entity_type = 'user';
    $entity_id = $user_id;
    $details = $success ? "Login berhasil: $username" : "Login gagal: $username";
    
    log_audit($user_id, $action, $entity_type, $entity_id, $details);
}

/**
 * Mencatat aktivitas logout
 * 
 * @param int $user_id ID pengguna yang logout
 * @return void
 */
function log_logout_activity($user_id) {
    if (!function_exists('log_audit')) {
        return;
    }
    
    $action = 'logout';
    $entity_type = 'user';
    $entity_id = $user_id;
    $details = "User logout";
    
    log_audit($user_id, $action, $entity_type, $entity_id, $details);
}

/**
 * Mencatat aktivitas form submission
 * 
 * @param string $form_name Nama form yang disubmit
 * @param string $action Aksi yang dilakukan (create, update, delete)
 * @param array $data Data yang disubmit (opsional)
 * @return void
 */
function log_form_submission($form_name, $action, $data = []) {
    if (!function_exists('log_audit')) {
        return;
    }
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    $entity_type = 'form';
    $entity_id = null;
    
    // Filter data sensitif
    $filtered_data = $data;
    if (isset($filtered_data['password'])) {
        $filtered_data['password'] = '******';
    }
    
    $details = "Form submission: $form_name, Action: $action";
    if (!empty($filtered_data)) {
        $details .= ", Data: " . json_encode($filtered_data, JSON_UNESCAPED_UNICODE);
    }
    
    log_audit($user_id, $action, $entity_type, $entity_id, $details);
}
