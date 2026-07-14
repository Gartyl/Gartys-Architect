	<div class="param-group shadow-sm border-info mb-3" id="adetailerBlock" style="border-color: rgba(13, 202, 240, 0.4) !important; background: rgba(13, 202, 240, 0.05);">
        <div class="d-flex justify-content-between align-items-center">
            <label class="small text-info fw-bold mb-0">
                <i class="bi bi-magic me-1"></i> <?= __('tit_adetailer') ?? 'Reparador de Rostros' ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
            </label>
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="adetailer" name="adetailer" onchange="document.getElementById('adetailerUI').classList.toggle('d-none', !this.checked)" <?= !$is_pro ? 'disabled' : '' ?>>
            </div>
        </div>
        <div id="adetailerUI" class="d-none mt-3 pt-3 border-top border-info" style="border-color: rgba(13, 202, 240, 0.2) !important;">
            <div class="row g-2 align-items-center">
                
                <div class="col-12 mb-2 mt-1">
                    <div class="d-flex align-items-center">
                        <div class="form-check form-switch m-0 ps-0 d-flex align-items-center">
                            <input class="form-check-input pref-track ms-0 me-2" type="checkbox" id="pureAdetailerToggle" style="cursor: pointer;" onchange="if(typeof toggleAdetailerPuro === 'function') toggleAdetailerPuro(this.checked)">
                            <label class="form-check-label text-warning small fw-bold mb-0" for="pureAdetailerToggle" style="cursor: pointer;">
                                <i class="bi bi-shield-check me-1"></i> <?= __('ctrl_ad_pure_mode') ?? 'Modo Puro (Solo Rostros)' ?>
                            </label>
                        </div>
                    </div>
                    <div class="text-muted ms-5" style="font-size: 0.75rem; margin-top: 2px;">
                        <?= __('txt_ad_pure_hint') ?? 'Restaura rostros en la foto cargada sin modificar nada más.' ?>
                    </div>
                </div>

                <div class="col-12">
                    <label class="text-secondary small fw-bold"><?= __('tit_inp_fuerza') ?? 'Fuerza de reparación (Denoise)' ?>: <span id="adDenoiseLabel" class="text-light">0.4</span></label>
                    <input type="range" class="form-range pref-track" id="adetailerDenoise" min="0.1" max="1.0" step="0.05" value="0.4" oninput="document.getElementById('adDenoiseLabel').innerText = this.value;">
                    <div class="text-muted" style="font-size: 0.7rem; margin-top: -5px;"><?= __('txt_ad_hint') ?? '* Usa 0.25 o 0.30 para mantener el parecido de los LoRAs.' ?></div>
                </div>
            </div>
        </div>
    </div>