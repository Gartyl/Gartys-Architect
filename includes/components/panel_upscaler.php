<div class="param-group shadow-sm border-primary mb-3" id="hiresBlock" style="display: none; border-color: rgba(13, 110, 253, 0.4) !important; background: rgba(13, 110, 253, 0.05);">
        <div class="d-flex justify-content-between align-items-center">
            <label class="small text-primary fw-bold mb-0">
                <i class="bi bi-arrows-angle-expand"></i> <?= __('tit_upscale') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
            </label>
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track" type="checkbox" id="hiresToggle" style="cursor: pointer;" onchange="document.getElementById('upscaleOptions').classList.toggle('d-none', !this.checked)" <?= !$is_pro ? 'disabled' : '' ?>>
            </div>
        </div>
        
        <div id="upscaleOptions" class="d-none mt-3 pt-3 border-top border-primary" style="border-color: rgba(13, 110, 253, 0.2) !important;">
            
            <!-- --- INTERRUPTOR VIP AURASR --- -->
            <div class="form-check form-switch mb-2 p-2 rounded shadow-sm" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.4);">
                <input class="form-check-input ms-1 pref-track" type="checkbox" id="aurasrToggle" name="aurasrToggle" onchange="toggleAuraSR()" style="cursor: pointer;" <?= !$is_pro ? 'disabled' : '' ?>>
                <label class="form-check-label ms-2 text-warning fw-bold" for="aurasrToggle">
                    <i class="bi bi-stars"></i> <?= __('aurasr_title') ?>
                </label>
                <div class="text-light text-opacity-50 mt-1 ms-2" style="font-size: 0.75rem;"><?= __('aurasr_desc') ?></div>
            </div>

            <!-- --- NUEVO: INTERRUPTOR VIP SEEDVR2 --- -->
            <div class="form-check form-switch mb-3 p-2 rounded shadow-sm" style="background: rgba(13, 202, 240, 0.08); border: 1px solid rgba(13, 202, 240, 0.4);">
                <input class="form-check-input ms-1 pref-track" type="checkbox" id="seedvr2Toggle" name="seedvr2Toggle" onchange="toggleSeedVR2()" style="cursor: pointer;" <?= !$is_pro ? 'disabled' : '' ?>>
                <label class="form-check-label ms-2 text-info fw-bold" for="seedvr2Toggle">
                    <i class="bi bi-magic"></i> <?= __('seedvr2_title') ?>
                </label>
                <div class="text-light text-opacity-50 mt-1 ms-2" style="font-size: 0.75rem;"><?= __('seedvr2_desc') ?></div>

                <div id="seedvr2ColorContainer" class="d-none mt-2 ms-2 me-2">
                    <label class="small text-info fw-bold mb-1" style="font-size: 0.72rem;"><?= __('seedvr2_color_lbl') ?></label>
                    <select class="form-select form-select-sm bg-dark text-light border-info pref-track" id="seedvr2ColorCorrection" <?= !$is_pro ? 'disabled' : '' ?>>
                        <option value="lab" selected><?= __('seedvr2_col_lab') ?></option>
                        <option value="wavelet"><?= __('seedvr2_col_wavelet') ?></option>
                        <option value="adain"><?= __('seedvr2_col_adain') ?></option>
                        <option value="none"><?= __('seedvr2_col_none') ?></option>
                    </select>
                </div>
            </div>
            <!-- -------------------------------------- -->

            <div class="row g-3">
                
                <!-- SELECTOR DE MODELO -->
                <div class="col-12">
                    <label class="small text-secondary fw-bold mb-1"><?= __('tit_ups_model') ?></label>
                    <select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="upscaleModelSelector" <?= !$is_pro ? 'disabled' : '' ?>>
                        <option value=""><?= __('opt_loading_models') ?></option>
                    </select>
                </div>
                
                <!-- --- DESLIZADOR DE FACTOR DE ESCALA (1.0x hasta 4.0x) --- -->
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small text-secondary fw-bold mb-0"><?= __('tit_ups_factor') ?></label>
                        <span id="upscaleFactorVal" class="badge bg-primary text-light fw-bold" style="font-size: 0.8rem;">2.0x (200%)</span>
                    </div>
                    <input type="range" class="form-range pref-track" id="upscaleFactor" min="1.0" max="4.0" step="0.1" value="2.0" 
                           oninput="updateUpscaleLabel(this.value)" style="cursor: pointer;" <?= !$is_pro ? 'disabled' : '' ?>>
                </div>
                
                <!-- --- DESLIZADOR DE FUERZA (DENOISE) --- -->
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small text-secondary fw-bold mb-0"><?= __('tit_ups_denoise') ?></label>
                        <span id="upscaleDenoiseVal" class="badge bg-info text-light fw-bold" style="font-size: 0.8rem;">0.25</span>
                    </div>
                    <input type="range" class="form-range pref-track" id="upscaleDenoise" min="0.05" max="0.45" step="0.05" value="0.25" 
                           oninput="document.getElementById('upscaleDenoiseVal').innerText = this.value" style="cursor: pointer;" <?= !$is_pro ? 'disabled' : '' ?> title="<?= __('ups_denoise_title') ?>">
                </div>

            </div>
        </div>
    </div>

    <!-- Script para controlar AuraSR, SeedVR2 y la etiqueta del deslizador -->
    <script>
    function syncUpscaleEnginesUI() {
        const isAura = document.getElementById('aurasrToggle')?.checked || false;
        const isSv2  = document.getElementById('seedvr2Toggle')?.checked || false;
        const disableClassic = isAura || isSv2;

        const classicSelector = document.getElementById('upscaleModelSelector');
        const denoiseSlider   = document.getElementById('upscaleDenoise');
        const sv2ColorBox     = document.getElementById('seedvr2ColorContainer');

        if (classicSelector) {
            classicSelector.disabled = disableClassic;
            classicSelector.style.opacity = disableClassic ? '0.4' : '1';
        }
        if (denoiseSlider) {
            denoiseSlider.disabled = disableClassic;
            denoiseSlider.style.opacity = disableClassic ? '0.4' : '1';
        }
        if (sv2ColorBox) {
            sv2ColorBox.classList.toggle('d-none', !isSv2);
        }
    }

    function toggleAuraSR() {
        const auraEl = document.getElementById('aurasrToggle');
        const sv2El  = document.getElementById('seedvr2Toggle');
        if (auraEl && auraEl.checked && sv2El) sv2El.checked = false;
        syncUpscaleEnginesUI();
    }

    function toggleSeedVR2() {
        const auraEl = document.getElementById('aurasrToggle');
        const sv2El  = document.getElementById('seedvr2Toggle');
        if (sv2El && sv2El.checked && auraEl) auraEl.checked = false;
        syncUpscaleEnginesUI();
    }

    function updateUpscaleLabel(val) {
        const percent = Math.round(val * 100);
        document.getElementById('upscaleFactorVal').innerText = `${val}x (${percent}%)`;
    }
    </script>