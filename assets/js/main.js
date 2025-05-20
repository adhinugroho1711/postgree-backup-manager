// Toggle sidebar
$(document).ready(function () {
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar, #content').toggleClass('active');
        $('.collapse.in').toggleClass('in');
        $('a[aria-expanded=true]').attr('aria-expanded', 'false');
    });

    // Auto-hide flash messages after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow', function() {
            $(this).remove();
        });
    }, 5000);

    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Initialize popovers
    $('[data-toggle="popover"]').popover();

    // Confirm before delete
    $('.confirm-delete').on('click', function() {
        return confirm('Apakah Anda yakin ingin menghapus item ini? Tindakan ini tidak dapat dibatalkan.');
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        const input = $(this).siblings('input');
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).find('i').toggleClass('bx-show bx-hide');
    });

    // Handle file input change
    $('.custom-file-input').on('change', function() {
        let fileName = $(this).val().split('\\').pop();
        if (fileName) {
            $(this).siblings('.custom-file-label').addClass('selected').html(fileName);
        }
    });

    // Auto-submit forms with data-auto-submit class
    $('form[data-auto-submit]').on('change', 'select', function() {
        $(this).closest('form').submit();
    });
});

// Format bytes to human-readable format
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

// Show loading state on buttons
function setLoading(button, isLoading) {
    const $button = $(button);
    if (isLoading) {
        $button.prop('disabled', true);
        $button.html(`<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memproses...`);
    } else {
        $button.prop('disabled', false);
        $button.html($button.data('original-text') || 'Simpan');
    }
}

// Handle backup form submission
$('#backupForm').on('submit', function(e) {
    e.preventDefault();
    const $form = $(this);
    const $submitBtn = $form.find('button[type="submit"]');
    const originalText = $submitBtn.html();
    
    setLoading($submitBtn, true);
    
    // Simpan data form
    const formData = new FormData(this);
    
    // Kirim permintaan AJAX
    $.ajax({
        url: 'ajax/backup.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
                // Refresh daftar backup setelah beberapa detik
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('danger', response.message || 'Terjadi kesalahan saat membuat backup');
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Terjadi kesalahan: ' + error);
        },
        complete: function() {
            setLoading($submitBtn, false);
        }
    });
});

// Handle restore form submission
$('#restoreForm').on('submit', function(e) {
    if (!confirm('Apakah Anda yakin ingin merestore database? Semua data saat ini akan diganti dengan data dari backup.')) {
        e.preventDefault();
        return false;
    }
    
    const $form = $(this);
    const $submitBtn = $form.find('button[type="submit"]');
    
    setLoading($submitBtn, true);
    
    // Simpan data form
    const formData = new FormData(this);
    
    // Kirim permintaan AJAX
    $.ajax({
        url: 'ajax/restore.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
                // Redirect ke halaman dashboard setelah beberapa detik
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 1500);
            } else {
                showAlert('danger', response.message || 'Terjadi kesalahan saat merestore database');
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Terjadi kesalahan: ' + error);
        },
        complete: function() {
            setLoading($submitBtn, false);
        }
    });
    
    return false;
});

// Show alert message
function showAlert(type, message) {
    const $alert = $(`
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    
    // Tambahkan alert ke dalam container alert atau body
    const $alertContainer = $('.alert-container');
    if ($alertContainer.length) {
        $alertContainer.append($alert);
    } else {
        $('body').prepend($alert);
    }
    
    // Auto-hide alert setelah 5 detik
    setTimeout(() => {
        $alert.alert('close');
    }, 5000);
}

// Handle delete backup
$('.delete-backup').on('click', function(e) {
    e.preventDefault();
    
    if (!confirm('Apakah Anda yakin ingin menghapus backup ini?')) {
        return false;
    }
    
    const $button = $(this);
    const backupId = $button.data('id');
    
    setLoading($button, true);
    
    // Kirim permintaan AJAX
    $.ajax({
        url: 'ajax/delete_backup.php',
        type: 'POST',
        data: { id: backupId },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
                // Hapus baris dari tabel
                $button.closest('tr').fadeOut(400, function() {
                    $(this).remove();
                });
            } else {
                showAlert('danger', response.message || 'Terjadi kesalahan saat menghapus backup');
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Terjadi kesalahan: ' + error);
        },
        complete: function() {
            setLoading($button, false);
        }
    });
});

// Initialize DataTables
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Indonesian.json"
            },
            "responsive": true,
            "order": [[0, 'desc']],
            "pageLength": 25
        });
    }
});

// Handle backup now button
$('.backup-now').on('click', function() {
    const $button = $(this);
    const database = $button.data('database');
    
    setLoading($button, true);
    
    // Kirim permintaan AJAX
    $.ajax({
        url: 'ajax/backup_now.php',
        type: 'POST',
        data: { database: database },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
                // Refresh halaman setelah beberapa detik
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('danger', response.message || 'Terjadi kesalahan saat membuat backup');
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Terjadi kesalahan: ' + error);
        },
        complete: function() {
            setLoading($button, false);
        }
    });
});

// Handle download backup
$('.download-backup').on('click', function(e) {
    e.preventDefault();
    
    const $button = $(this);
    const backupId = $button.data('id');
    
    // Tampilkan indikator loading
    $button.html('<i class="bx bx-loader bx-spin"></i> Mengunduh...');
    
    // Redirect ke URL download
    window.location.href = `download_backup.php?id=${backupId}`;
    
    // Kembalikan teks tombol setelah beberapa detik
    setTimeout(() => {
        $button.html('<i class="bx bx-download"></i> Unduh');
    }, 2000);
});

// Handle backup schedule form
$('#scheduleForm').on('submit', function(e) {
    e.preventDefault();
    
    const $form = $(this);
    const $submitBtn = $form.find('button[type="submit"]');
    
    setLoading($submitBtn, true);
    
    // Kirim permintaan AJAX
    $.ajax({
        url: 'ajax/save_schedule.php',
        type: 'POST',
        data: $form.serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
            } else {
                showAlert('danger', response.message || 'Terjadi kesalahan saat menyimpan jadwal');
            }
        },
        error: function(xhr, status, error) {
            showAlert('danger', 'Terjadi kesalahan: ' + error);
        },
        complete: function() {
            setLoading($submitBtn, false);
        }
    });
});
