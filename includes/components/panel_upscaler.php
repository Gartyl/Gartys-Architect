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
            
            <!-- --- NUEVO: INTERRUPTOR VIP AURASR --- -->
            <div class="form-check form-switch mb-3 p-2 rounded shadow-sm" style="background: rgba(255, 193, 7, 0.1); border: 1px solid rgba(255, 193, 7, 0.4);">
                <input class="form-check-input ms-1 pref-track" type="checkbox" id="aurasrToggle" name="aurasrToggle" onchange="toggleAuraSR()" style="cursor: pointer;" <?= !$is_pro ? 'disabled' : '' ?>>
                <label class="form-check-label ms-2 text-warning fw-bold" for="aurasrToggle">
                    <i class="bi bi-stars"></i> <?= __('aurasr_title') ?>
                </label>
                <div class="text-light text-opacity-50 mt-1 ms-2" style="font-size: 0.75rem;"><?= __('aurasr_desc') ?></div>
            </div>
            <!-- ------------------------------------- -->

            <div class="row g-2">
                <div class="col-md-6">
                    <label class="small text-secondary fw-bold"><?= __('tit_ups_model') ?></label>
                    <select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="upscaleModelSelector" <?= !$is_pro ? 'disabled' : '' ?>>
                        <option value=""><?= __('opt_loading_models') ?></option>
                    </select>
                </div>
                
                <!-- --- DESLIZADOR DE FACTOR DE ESCALA (1.1x hasta 4.0x) --- -->
                <div class="col-md-6">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small text-secondary fw-bold mb-0"><?= __('tit_ups_factor') ?></label>
                        <span id="upscaleFactorVal" class="badge bg-primary text-light fw-bold" style="font-size: 0.8rem;">2.0x (200%)</span>
                    </div>
                    <input type="range" class="form-range pref-track" id="upscaleFactor" min="1.1" max="4.0" step="0.1" value="2.0" 
                           oninput="updateUpscaleLabel(this.value)" style="cursor: pointer;" <?= !$is_pro ? 'disabled' : '' ?>>
                </div>
                <!-- -------------------------------------------------------- -->
            </div>
        </div>
    </div>

    <!-- Script para controlar AuraSR y la etiqueta del deslizador -->
    <script>
    function toggleAuraSR() {
        const isAura = document.getElementById('aurasrToggle').checked;
        const classicSelector = document.getElementById('upscaleModelSelector');
        if (classicSelector) {
            classicSelector.disabled = isAura;
            classicSelector.style.opacity = isAura ? '0.4' : '1';
        }
    }

    function updateUpscaleLabel(val) {
        const percent = Math.round(val * 100);
        document.getElementById('upscaleFactorVal').innerText = `${val}x (${percent}%)`;
    }
    </script>