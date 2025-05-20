<?php
/**
 * File ini berisi fungsi-fungsi untuk mengoptimalkan performa backup dan restore
 * untuk database berukuran besar (>10GB)
 */

/**
 * Mendapatkan jumlah core CPU yang tersedia
 * 
 * @return int Jumlah core CPU
 */
function get_cpu_cores() {
    $cores = 1; // Default jika tidak dapat mendeteksi
    
    if (PHP_OS === 'Linux') {
        $cmd = "grep -c ^processor /proc/cpuinfo";
        exec($cmd, $output, $return_var);
        if ($return_var === 0 && isset($output[0]) && is_numeric($output[0])) {
            $cores = (int)$output[0];
        }
    } elseif (PHP_OS === 'Darwin') { // macOS
        $cmd = "sysctl -n hw.ncpu";
        exec($cmd, $output, $return_var);
        if ($return_var === 0 && isset($output[0]) && is_numeric($output[0])) {
            $cores = (int)$output[0];
        }
    }
    
    return max(1, $cores);
}

/**
 * Mendapatkan jumlah memori yang tersedia (dalam MB)
 * 
 * @return int Jumlah memori dalam MB
 */
function get_available_memory() {
    $memory = 1024; // Default 1GB jika tidak dapat mendeteksi
    
    if (PHP_OS === 'Linux') {
        $cmd = "grep MemAvailable /proc/meminfo | awk '{print $2}'";
        exec($cmd, $output, $return_var);
        if ($return_var === 0 && isset($output[0]) && is_numeric($output[0])) {
            $memory = (int)($output[0] / 1024); // Convert KB to MB
        }
    } elseif (PHP_OS === 'Darwin') { // macOS
        $cmd = "vm_stat | grep 'Pages free' | awk '{print $3}' | sed 's/\\.//'";
        exec($cmd, $output, $return_var);
        if ($return_var === 0 && isset($output[0]) && is_numeric($output[0])) {
            // Convert pages to MB (page size is typically 4KB on macOS)
            $memory = (int)($output[0] * 4 / 1024);
        }
    }
    
    return max(1024, $memory); // Minimal 1GB
}

/**
 * Mendapatkan opsi pg_dump optimal berdasarkan ukuran database dan resource sistem
 * 
 * @param string $database Nama database
 * @param bool $compress Apakah menggunakan kompresi
 * @return array Opsi pg_dump dan perintah kompresi
 */
function get_optimal_dump_options($database, $compress = true) {
    $cores = get_cpu_cores();
    $memory = get_available_memory();
    
    // Dapatkan ukuran database
    $db_size_cmd = sprintf(
        'PGPASSWORD="%s" psql -h %s -p %s -U %s -d %s -t -c "SELECT pg_database_size(\'%s\') / (1024*1024*1024)::float"',
        DB_PASS, DB_HOST, DB_PORT, DB_USER, $database, $database
    );
    
    exec($db_size_cmd, $size_output, $return_var);
    $db_size_gb = 1; // Default 1GB jika tidak dapat mendeteksi
    
    if ($return_var === 0 && isset($size_output[0])) {
        $db_size_gb = (float)trim($size_output[0]);
    }
    
    // Opsi dasar
    $options = [
        'jobs' => min($cores, 4), // Gunakan maksimal 4 core
        'compress_level' => 1,    // Level kompresi rendah untuk kecepatan
        'format' => 'custom',     // Format custom untuk performa dan fitur
        'blobs' => true,          // Sertakan BLOB
        'compress_cmd' => '',     // Perintah kompresi eksternal
        'buffer_size' => '16MB',  // Buffer size default
    ];
    
    // Sesuaikan berdasarkan ukuran database
    if ($db_size_gb > 10) {
        // Untuk database >10GB
        $options['jobs'] = min($cores, 8);                  // Gunakan lebih banyak core
        $options['buffer_size'] = min($memory / 8, 64) . 'MB'; // Buffer lebih besar
        
        if ($compress) {
            // Gunakan pigz untuk kompresi paralel jika tersedia
            exec('which pigz', $pigz_output, $pigz_return);
            if ($pigz_return === 0) {
                $options['compress_cmd'] = 'pigz -p ' . $options['jobs'];
            } else {
                $options['compress_level'] = 1; // Gunakan level kompresi rendah jika pigz tidak tersedia
            }
        }
    } elseif ($db_size_gb > 5) {
        // Untuk database 5-10GB
        $options['jobs'] = min($cores, 6);
        $options['buffer_size'] = min($memory / 10, 32) . 'MB';
    }
    
    return $options;
}

/**
 * Membuat perintah pg_dump yang dioptimalkan untuk database besar
 * 
 * @param string $database Nama database
 * @param string $output_file Path file output
 * @param array $options Opsi tambahan
 * @return string Perintah pg_dump lengkap
 */
function build_optimized_dump_command($database, $output_file, $options = []) {
    // Gabungkan dengan opsi default
    $default_options = [
        'compress' => true,
        'include_schema' => true,
        'include_data' => true,
        'selected_tables' => [],
        'backup_type' => 'full'
    ];
    
    $options = array_merge($default_options, $options);
    
    // Dapatkan opsi optimal
    $optimal = get_optimal_dump_options($database, $options['compress']);
    
    // Buat perintah dasar
    $command = sprintf(
        'PGPASSWORD="%s" pg_dump -h %s -p %s -U %s -v',
        DB_PASS, DB_HOST, DB_PORT, DB_USER
    );
    
    // Tambahkan opsi format
    $command .= ' -F ' . ($optimal['format'] === 'custom' ? 'c' : 'p');
    
    // Tambahkan opsi jobs jika format custom
    if ($optimal['format'] === 'custom') {
        $command .= ' -j ' . $optimal['jobs'];
    }
    
    // Tambahkan opsi buffer
    $command .= ' --buffer=' . $optimal['buffer_size'];
    
    // Tambahkan opsi blobs
    if ($optimal['blobs']) {
        $command .= ' -b';
    }
    
    // Tambahkan opsi schema/data
    if (!$options['include_schema']) {
        $command .= ' --data-only';
    }
    if (!$options['include_data']) {
        $command .= ' --schema-only';
    }
    
    // Tambahkan opsi tabel jika backup tipe tabel tertentu
    if ($options['backup_type'] === 'tables' && !empty($options['selected_tables'])) {
        foreach ($options['selected_tables'] as $table) {
            $command .= ' -t ' . escapeshellarg($table);
        }
    }
    
    // Tambahkan database
    $command .= ' ' . escapeshellarg($database);
    
    // Tambahkan kompresi jika diperlukan
    if ($options['compress']) {
        if (!empty($optimal['compress_cmd'])) {
            // Gunakan kompresi eksternal jika tersedia
            $command .= ' | ' . $optimal['compress_cmd'] . ' > ' . escapeshellarg($output_file);
        } else {
            // Gunakan gzip dengan level kompresi yang ditentukan
            $command .= ' | gzip -' . $optimal['compress_level'] . ' > ' . escapeshellarg($output_file);
        }
    } else {
        $command .= ' -f ' . escapeshellarg($output_file);
    }
    
    return $command;
}

/**
 * Membuat perintah pg_restore yang dioptimalkan untuk database besar
 * 
 * @param string $database Nama database
 * @param string $input_file Path file input
 * @param array $options Opsi tambahan
 * @return string Perintah pg_restore lengkap
 */
function build_optimized_restore_command($database, $input_file, $options = []) {
    // Gabungkan dengan opsi default
    $default_options = [
        'is_compressed' => true,
        'is_custom_format' => false,
        'selected_tables' => [],
        'restore_type' => 'full'
    ];
    
    $options = array_merge($default_options, $options);
    
    // Dapatkan opsi optimal
    $cores = get_cpu_cores();
    $memory = get_available_memory();
    
    // Deteksi format file
    $is_custom_format = $options['is_custom_format'];
    if (!$is_custom_format && !$options['is_compressed']) {
        // Cek apakah file adalah format custom PostgreSQL
        $file_header = @file_get_contents($input_file, false, null, 0, 5);
        $is_custom_format = $file_header === 'PGDMP';
    }
    
    // Buat perintah restore
    if ($is_custom_format) {
        // Gunakan pg_restore untuk format custom
        $command = sprintf(
            'PGPASSWORD="%s" pg_restore -h %s -p %s -U %s -d %s -v --clean --if-exists',
            DB_PASS, DB_HOST, DB_PORT, DB_USER, $database
        );
        
        // Tambahkan opsi jobs untuk restore paralel
        $command .= ' -j ' . min($cores, 4);
        
        // Tambahkan opsi tabel jika restore tipe tabel tertentu
        if ($options['restore_type'] === 'tables' && !empty($options['selected_tables'])) {
            foreach ($options['selected_tables'] as $table) {
                $command .= ' -t ' . escapeshellarg($table);
            }
        }
        
        // Tambahkan file input
        if ($options['is_compressed']) {
            // Gunakan pigz untuk dekompresi paralel jika tersedia
            exec('which pigz', $pigz_output, $pigz_return);
            if ($pigz_return === 0) {
                $command = 'pigz -d -c -p ' . min($cores, 4) . ' ' . escapeshellarg($input_file) . ' | ' . $command;
            } else {
                $command = 'gunzip -c ' . escapeshellarg($input_file) . ' | ' . $command;
            }
        } else {
            $command .= ' ' . escapeshellarg($input_file);
        }
    } else {
        // Perintah restore berbeda berdasarkan tipe restore (full atau tabel tertentu)
        if ($options['restore_type'] === 'full') {
            // Untuk restore penuh, kita bisa menghapus seluruh skema terlebih dahulu
            $drop_schema_command = sprintf(
                'PGPASSWORD="%s" psql -h %s -p %s -U %s -d %s -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"',
                DB_PASS, DB_HOST, DB_PORT, DB_USER, $database
            );
            
            // Perintah utama restore
            $main_command = sprintf(
                'PGPASSWORD="%s" psql -h %s -p %s -U %s -d %s -v ON_ERROR_STOP=1',
                DB_PASS, DB_HOST, DB_PORT, DB_USER, $database
            );
            
            // Tambahkan file input
            if ($options['is_compressed']) {
                // Gunakan pigz untuk dekompresi paralel jika tersedia
                exec('which pigz', $pigz_output, $pigz_return);
                if ($pigz_return === 0) {
                    $main_command = 'pigz -d -c -p ' . min($cores, 4) . ' ' . escapeshellarg($input_file) . ' | ' . $main_command;
                } else {
                    $main_command = 'gunzip -c ' . escapeshellarg($input_file) . ' | ' . $main_command;
                }
            } else {
                $main_command .= ' -f ' . escapeshellarg($input_file);
            }
            
            // Gabungkan perintah drop schema dan restore
            $command = $drop_schema_command . ' && ' . $main_command;
        } else {
            // Untuk restore tabel tertentu, kita hanya drop tabel yang akan direstore
            $drop_tables_commands = [];
            foreach ($options['selected_tables'] as $table) {
                $drop_tables_commands[] = sprintf(
                    'PGPASSWORD="%s" psql -h %s -p %s -U %s -d %s -c "DROP TABLE IF EXISTS %s CASCADE;"',
                    DB_PASS, DB_HOST, DB_PORT, DB_USER, $database, $table
                );
            }
            
            // Perintah utama restore
            $main_command = sprintf(
                'PGPASSWORD="%s" psql -h %s -p %s -U %s -d %s -v ON_ERROR_STOP=1',
                DB_PASS, DB_HOST, DB_PORT, DB_USER, $database
            );
            
            // Tambahkan file input
            if ($options['is_compressed']) {
                // Gunakan pigz untuk dekompresi paralel jika tersedia
                exec('which pigz', $pigz_output, $pigz_return);
                if ($pigz_return === 0) {
                    $main_command = 'pigz -d -c -p ' . min($cores, 4) . ' ' . escapeshellarg($input_file) . ' | ' . $main_command;
                } else {
                    $main_command = 'gunzip -c ' . escapeshellarg($input_file) . ' | ' . $main_command;
                }
            } else {
                $main_command .= ' -f ' . escapeshellarg($input_file);
            }
            
            // Gabungkan perintah drop tabel dan restore
            $command = implode(' && ', $drop_tables_commands) . ' && ' . $main_command;
        }
    }
    
    return $command;
}
?>
