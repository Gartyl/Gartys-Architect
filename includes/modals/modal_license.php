<div class="modal fade" id="modalLicencia" tabindex="-1" aria-labelledby="modalLicenciaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-dark text-light border-0 shadow-lg" style="border-radius: 1rem;">
      
      <div class="modal-header border-secondary border-opacity-50 pb-3 pt-4 px-4">
        <h5 class="modal-title text-warning fw-bold d-flex align-items-center" id="modalLicenciaLabel">
            <i class="bi bi-star-fill me-2 fs-4"></i> <?= __('mod_lic_title') ?>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= __('btn_cerrar') ?? 'Cerrar' ?>"></button>
      </div>
      
      <div class="modal-body px-4 py-4">
        <p class="text-secondary small mb-4 lh-base">
            <?= __('mod_lic_desc') ?>
        </p>

        <div class="p-4 mb-4 border border-warning border-opacity-25 bg-warning bg-opacity-10 text-center shadow-sm" style="border-radius: 0.75rem;">
            <div class="mb-2">
                <i class="bi bi-gem text-warning fs-3"></i>
            </div>
            <h6 class="text-light fw-bold mb-3"><?= __('mod_lic_no_key_yet') ?></h6>
			<a href="https://garty.lemonsqueezy.com/checkout/buy/70636e1a-0dde-49c5-bf97-d4d852dceee8" target="_blank" class="btn btn-warning fw-bold shadow-sm px-4 rounded-pill">
                <i class="bi bi-cart-fill me-2"></i> <?= __('mod_lic_buy_btn') ?>
            </a>
        </div>

        <div class="mb-2">
          <label for="inputLicenseKey" class="form-label text-muted fw-bold small text-uppercase" style="letter-spacing: 0.5px;"><?= __('mod_lic_lbl_key') ?></label>
          <div class="input-group input-group-lg shadow-sm">
            <span class="input-group-text bg-secondary border-secondary text-warning bg-opacity-25">
                <i class="bi bi-key-fill"></i>
            </span>
            <input type="text" class="form-control bg-dark text-warning border-secondary font-monospace fw-bold" id="inputLicenseKey" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" style="letter-spacing: 1.5px; font-size: 1.1rem;">
          </div>
        </div>
        
        <div id="licenciaMensaje" class="mt-2"></div>
        
      </div>
      
      <div class="modal-footer border-secondary border-opacity-50 px-4 py-3">
        <button type="button" class="btn btn-link text-white-50 text-decoration-none fw-bold" data-bs-dismiss="modal"><?= __('btn_cancelar') ?? 'Cancelar' ?></button>
        <button type="button" class="btn btn-warning fw-bold px-4 rounded-pill shadow-sm" id="btnValidarLicencia" onclick="validarLicencia()">
            <i class="bi bi-unlock-fill me-1"></i> <?= __('mod_lic_btn_activate') ?>
        </button>
      </div>
      
    </div>
  </div>
</div>