<div class="modal fade" id="modalMensajesAdmin" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning" style="background-color: #161b22; color: #c9d1d9;">
            <div class="modal-header bg-warning text-dark border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-envelope-open-fill me-2"></i> <?= __('mod_msg_title') ?></h5>
            </div>
            <div class="modal-body py-4">
                <?php foreach ($mensajes_pendientes as $m): ?>
                    <div class="admin-msg-box shadow-sm" data-msg-id="<?php echo $m['id']; ?>">
                        <div class="small text-warning fw-bold mb-2"><i class="bi bi-clock-history"></i> <?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></div>
                        <div class="text-light" style="white-space: pre-wrap;"><?php echo htmlspecialchars($m['mensaje']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="modal-footer border-secondary" style="border-color: rgba(255,255,255,0.1) !important;">
                <button type="button" class="btn btn-warning fw-bold text-dark w-100" onclick="marcarMensajesLeidos()"><i class="bi bi-check-all"></i> <?= __('mod_msg_btn_read') ?></button>
            </div>
        </div>
    </div>
</div>