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
        
        <!-- SELECTOR DE MODELO DE RECORTE -->
        <div class="mb-3">
            <label class="small text-secondary fw-bold mb-1"><?= __('rembg_model_lbl') ?? 'Modelo de Recorte' ?></label>
            <select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="rembg_model" name="rembg_model" <?= !$is_pro ? 'disabled' : '' ?>>
                <option value="isnet-general-use" selected><?= __('rembg_mod_isnet') ?? 'ISNet General (Alta Precisión)' ?></option>
                <option value="isnet-anime"><?= __('rembg_mod_isnet_anime') ?? 'ISNet Anime (Ilustraciones)' ?></option>
                <option value="u2net_human_seg"><?= __('rembg_mod_u2net_human') ?? 'U2Net (Solo Humanos)' ?></option>
                <option value="u2net"><?= __('rembg_mod_u2net') ?? 'U2Net (Clásico / Rápido)' ?></option>
                <option value="silueta"><?= __('rembg_mod_silueta') ?? 'Silueta (Ultra Rápido)' ?></option>
            </select>
        </div>

        <div class="form-check form-switch m-0">
            <input class="form-check-input border-success" style="cursor: pointer;" type="checkbox" id="pureRembgToggle" onchange="toggleRembgPuro(this.checked)" <?= !$is_pro ? 'disabled' : '' ?>>
            <label class="form-check-label small text-success fw-bold" for="pureRembgToggle" style="cursor: pointer;">
                <i class="bi bi-shield-lock me-1"></i> <?= __('ctrl_rembg_puro') ?>
            </label>
        </div>
    </div>
</div>