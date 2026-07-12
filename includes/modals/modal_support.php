<div class="modal fade" id="modalSoportePro" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light border-0 shadow-lg" style="border-radius: 1rem; border: 1px solid #2ea043 !important;">
      
      <div class="modal-header border-secondary border-opacity-50 pb-3 pt-4 px-4">
        <h5 class="modal-title text-success fw-bold d-flex align-items-center">
            <i class="bi bi-headset me-2 fs-4"></i> <?= __('tit_modal_soporte') ?? 'Asistencia Técnica Pro' ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= __('btn_cerrar') ?? 'Cerrar' ?>"></button>
      </div>
      
      <div class="modal-body px-4 py-3">
        <div class="alert border-success bg-dark bg-opacity-50 text-white small mb-4 shadow-sm" style="border-left: 4px solid #2ea043 !important;">
            <div class="d-flex align-items-start">
                <i class="bi bi-info-circle-fill text-success me-2 fs-5 mt-0.5"></i>
                <span class="fw-medium text-white-50" style="line-height: 1.4;">
                    <?= __('txt_soporte_info') ?? 'Soporte exclusivo para usuarios Pro. Responderé a tu consulta lo antes posible al email que indiques.' ?>
                </span>
            </div>
        </div>

        <form id="formSoportePro">
            <div class="mb-3">
                <label class="form-label text-light opacity-75 fw-bold small"><?= __('lbl_sup_email') ?? 'Tu Email de contacto' ?></label>
                <input type="email" class="form-control bg-dark text-light border-secondary" id="supEmail" placeholder="usuario@email.com" required>
            </div>
            
            <div class="mb-3">
                <label class="form-label text-light opacity-75 fw-bold small"><?= __('lbl_sup_tipo') ?? 'Tipo de Consulta' ?></label>
                <select class="form-select bg-dark text-light border-secondary" id="supTipo" required>
                    <option value="" disabled selected><?= __('opt_sup_motivo') ?? 'Selecciona el motivo...' ?></option>
                    <option value="Bug"><?= __('opt_sup_bug') ?? '🐛 Fallo o Error en la interfaz' ?></option>
                    <option value="Hardware"><?= __('opt_sup_hw') ?? '💻 Problema de Hardware / VRAM' ?></option>
                    <option value="Modelos"><?= __('opt_sup_mod') ?? '🧠 Duda sobre Modelos / IA' ?></option>
                    <option value="Sugerencia"><?= __('opt_sup_sug') ?? '✨ Sugerencia de mejora' ?></option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label text-light opacity-75 fw-bold small"><?= __('lbl_sup_desc') ?? 'Descripción del problema' ?></label>
                <textarea class="form-control bg-dark text-light border-secondary" id="supMensaje" rows="4" placeholder="<?= __('ph_sup_desc') ?? 'Explica detalladamente qué ocurre...' ?>" required></textarea>
            </div>
        </form>
      </div>
      
      <div class="modal-footer border-secondary border-opacity-50 px-4 py-3">
        <button type="button" class="btn btn-outline-secondary text-light border-opacity-50 fw-bold px-4 rounded-pill shadow-sm small" data-bs-dismiss="modal">
            <?= __('btn_cancelar') ?? 'Cancelar' ?>
        </button>
        <button type="button" class="btn btn-success fw-bold px-4 rounded-pill shadow-sm" id="btnEnviarSoporte" onclick="enviarTicketSoporte()">
            <i class="bi bi-send-fill me-1"></i> <?= __('btn_sup_enviar') ?? 'Enviar Consulta' ?>
        </button>
      </div>
      
    </div>
  </div>
</div>