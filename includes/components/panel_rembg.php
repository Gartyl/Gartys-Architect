<!-- ======================================================= -->
<!-- 5.7. ELIMINADOR DE FONDOS (Rembg) -->
<!-- ======================================================= -->

<div class="param-group shadow-sm border-success mb-3" id="rembgBlock" style="display: none; border-color: rgba(46, 160, 67, 0.4) !important; background: rgba(46, 160, 67, 0.05);">
        <div class="d-flex justify-content-between align-items-center">
            <label class="small text-success fw-bold mb-0" style="cursor: pointer;">
                <i class="bi bi-scissors"></i> <?= __('tit_rembg') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
            </label>
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track" type="checkbox" id="rembgToggle" style="cursor: pointer;" 
                       onchange="document.getElementById('rembgSubOptions').classList.toggle('d-none', !this.checked); if(!this.checked) { document.getElementById('pureRembgToggle').checked = false; toggleRembgPuro(false); }"
                       <?= !$is_pro ? 'disabled title="'.(__('msg_solo_pro') ?? 'Función exclusiva PRO').'"' : '' ?>>
            </div>
        </div>
        <div id="rembgSubOptions" class="d-none mt-3 pt-3 border-top border-success" style="border-color: rgba(46, 160, 67, 0.2) !important;">
            <div class="form-check form-switch m-0">
                <input class="form-check-input border-success" style="cursor: pointer;" type="checkbox" id="pureRembgToggle" onchange="toggleRembgPuro(this.checked)" <?= !$is_pro ? 'disabled' : '' ?>>
                <label class="form-check-label small text-success fw-bold" for="pureRembgToggle" style="cursor: pointer;">
                    <i class="bi bi-shield-lock me-1"></i> <?= __('ctrl_rembg_puro') ?>
                </label>
            </div>
        </div>
    </div>