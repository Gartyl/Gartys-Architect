<!-- ============================================================================== -->
<!-- --- PANEL IC-LIGHT (RELIGHTING NEURAL) - ESTILO PRO --- -->
<!-- ============================================================================== -->
<div class="param-group shadow-sm border-primary mb-3" id="icLightBlock" style="border-color: rgba(13, 110, 253, 0.4) !important; background: rgba(13, 110, 253, 0.05);">
    <div class="d-flex justify-content-between align-items-center">
        <label class="small text-primary fw-bold mb-0">
            <i class="bi bi-lightbulb-fill me-1"></i> <?= __('iclight_title') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
        </label>
        <div class="form-check form-switch m-0">
            <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="iclight_enabled" name="iclight_enabled" onchange="toggleIcLightUI()" <?= !$is_pro ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div id="icLightUI" class="d-none mt-3 text-start border-top border-primary pt-2" style="border-color: rgba(13, 110, 253, 0.2) !important;">
        <div class="row g-2">
            <!-- Selector de Dirección -->
            <div class="col-md-12">
                <label class="small text-secondary fw-bold"><?= __('iclight_direction') ?></label>
                <select class="form-select form-select-sm bg-dark text-light border-primary pref-track" id="iclight_direction" name="iclight_direction" <?= !$is_pro ? 'disabled' : '' ?>>
                    <option value="Left Light" selected>⬅️ <?= __('iclight_dir_left') ?></option>
                    <option value="Right Light">➡️ <?= __('iclight_dir_right') ?></option>
                    <option value="Top Light">⬆️ <?= __('iclight_dir_top') ?></option>
                    <option value="Bottom Light">⬇️ <?= __('iclight_dir_bottom') ?></option>
                    <option value="Detail / Ambient">💡 <?= __('iclight_dir_ambient') ?></option>
                </select>
            </div>

            <!-- Nuevo Deslizador de Fuerza -->
            <div class="col-md-12 mt-1">
                <label for="iclight_multiplier" class="small text-secondary fw-bold">
                    <?= __('iclight_strength_lbl') ?>: <span id="iclight_multiplier_val" class="text-info">0.18</span>
                </label>
                <input type="range" class="form-range" id="iclight_multiplier" name="iclight_multiplier" min="0.05" max="0.80" step="0.01" value="0.18" oninput="document.getElementById('iclight_multiplier_val').innerText = this.value" <?= !$is_pro ? 'disabled' : '' ?>>
            </div>

            <!-- Override de Color/Ambiente (Opcional) -->
            <div class="col-md-12 mt-1">
                <label class="small text-secondary fw-bold"><?= __('iclight_prompt_lbl') ?></label>
                <input type="text" class="form-control form-control-sm bg-dark text-light border-primary pref-track" id="iclight_prompt" name="iclight_prompt" placeholder="<?= __('iclight_prompt_ph') ?>" <?= !$is_pro ? 'disabled' : '' ?>>
                <div class="text-muted mt-1" style="font-size: 0.75rem;"><?= __('iclight_prompt_help') ?></div>
            </div>
        </div>
    </div>
</div>