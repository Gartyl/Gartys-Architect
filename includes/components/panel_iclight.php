<!-- ============================================================================== -->
<!-- --- PANEL IC-LIGHT (RELIGHTING NEURAL) - ESTILO PRO --- -->
<!-- ============================================================================== -->
<div class="param-group shadow-sm border-primary mb-3" id="icLightBlock" style="border-color: rgba(13, 110, 253, 0.4) !important; background: rgba(13, 110, 253, 0.05);">
    <div class="d-flex justify-content-between align-items-center">
        <label class="small text-primary fw-bold mb-0">
            <i class="bi bi-lightbulb-fill me-1"></i> <?= __('iclight_title') ?? 'Iluminación Neural (IC-Light)' ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
        </label>
        <div class="form-check form-switch m-0">
            <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="iclight_enabled" name="iclight_enabled" onchange="toggleIcLightUI()" <?= !$is_pro ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div id="icLightUI" class="d-none mt-3 text-start border-top border-primary pt-2" style="border-color: rgba(13, 110, 253, 0.2) !important;">
        <div class="row g-2">
            
            <!-- Selector de Modelo IC-Light -->
            <div class="col-md-12">
                <label class="small text-secondary fw-bold"><?= __('iclight_model_lbl') ?? 'Modo de Iluminación' ?></label>
                <select class="form-select form-select-sm bg-dark text-light border-primary pref-track" id="iclight_model" name="iclight_model" onchange="toggleIcLightBg()" <?= !$is_pro ? 'disabled' : '' ?>>
                    <option value="fc" selected><?= __('iclight_mod_fc') ?? 'Premedio / Anterior (Básico)' ?></option>
                    <option value="fcon"><?= __('iclight_mod_fcon') ?? 'Primer Plano con Ruido (Mejor detalle)' ?></option>
                    <option value="fbc"><?= __('iclight_mod_fbc') ?? 'Primer Plano y Fondo (Fusión)' ?></option>
                </select>
            </div>

            <!-- Subida de Fondo (Exclusivo para modo FBC) -->
            <div class="col-md-12 mt-1 d-none" id="iclight_bg_container">
                <label class="small text-warning fw-bold"><i class="bi bi-image"></i> <?= __('iclight_bg_lbl') ?? 'Imagen de Fondo' ?></label>
                <input type="file" class="form-control form-control-sm bg-dark text-light border-secondary" id="iclight_bg_input" accept="image/*">
                <div class="mt-2 d-none position-relative" id="iclight_bg_preview_box" style="width: 80px; height: 80px;">
                    <img id="iclight_bg_preview" src="" class="img-fluid rounded border border-warning w-100 h-100" style="object-fit: cover;">
                    <button type="button" class="btn btn-danger position-absolute top-0 start-100 translate-middle rounded-circle p-0 d-flex align-items-center justify-content-center border border-dark" style="width: 22px; height: 22px; font-size: 12px; z-index: 10;" onclick="clearIcLightBg()" title="Quitar Fondo"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <!-- Selector de Dirección -->
            <div class="col-md-12 mt-2">
                <label class="small text-secondary fw-bold"><?= __('iclight_direction') ?? 'Dirección de la Luz' ?></label>
                <select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="iclight_direction" name="iclight_direction" <?= !$is_pro ? 'disabled' : '' ?>>
                    <option value="Left Light" selected>⬅️ <?= __('iclight_dir_left') ?? 'Luz desde la Izquierda' ?></option>
                    <option value="Right Light">➡️ <?= __('iclight_dir_right') ?? 'Luz desde la Derecha' ?></option>
                    <option value="Top Light">⬆️ <?= __('iclight_dir_top') ?? 'Luz desde Arriba' ?></option>
                    <option value="Bottom Light">⬇️ <?= __('iclight_dir_bottom') ?? 'Luz desde Abajo' ?></option>
                    <option value="Detail / Ambient">💡 <?= __('iclight_dir_ambient') ?? 'Luz Ambiental / Detalle' ?></option>
                </select>
            </div>

            <!-- Deslizador de Fuerza -->
            <div class="col-md-12 mt-1">
                <label for="iclight_multiplier" class="small text-secondary fw-bold">
                    <?= __('iclight_strength_lbl') ?? 'Fuerza de Luz' ?>: <span id="iclight_multiplier_val" class="text-info">0.18</span>
                </label>
                <input type="range" class="form-range" id="iclight_multiplier" name="iclight_multiplier" min="0.05" max="0.80" step="0.01" value="0.18" oninput="document.getElementById('iclight_multiplier_val').innerText = this.value" <?= !$is_pro ? 'disabled' : '' ?>>
            </div>

            <!-- Override de Color/Ambiente -->
            <div class="col-md-12 mt-1">
                <label class="small text-secondary fw-bold"><?= __('iclight_prompt_lbl') ?? 'Override de Color (Prompt)' ?></label>
                <input type="text" class="form-control form-control-sm bg-dark text-light border-secondary pref-track" id="iclight_prompt" name="iclight_prompt" placeholder="<?= __('iclight_prompt_ph') ?? 'Ej: neon purple light' ?>" <?= !$is_pro ? 'disabled' : '' ?>>
            </div>
        </div>
    </div>
</div>