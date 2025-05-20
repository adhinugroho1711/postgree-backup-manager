<!-- Modal Konfirmasi Hapus Backup -->
<div class="modal fade" id="deleteBackupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Konfirmasi Hapus Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus backup <strong><?php echo htmlspecialchars($backup['filename']); ?></strong>?</p>
                <div class="alert alert-warning">
                    <i class="bx bx-error"></i> Perhatian: Tindakan ini tidak dapat dibatalkan. File backup akan dihapus secara permanen dari server.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="../delete_backup.php?id=<?php echo $backup['id']; ?>&from=detail" class="btn btn-danger">
                    <i class="bx bx-trash"></i> Hapus Permanen
                </a>
            </div>
        </div>
    </div>
</div>
