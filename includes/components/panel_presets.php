    <div class="param-group shadow-sm border-secondary mb-3" id="estilosContainer" style="border-color: rgba(255,255,255,0.2) !important;">
        <div class="d-flex justify-content-between align-items-center">
            <label class="small text-light fw-bold mb-0"><i class="bi bi-aspect-ratio me-1"></i> <?= __('tit_presets') ?></label>
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="formatToggle" onchange="document.getElementById('formatUI').classList.toggle('d-none', !this.checked)">
            </div>
        </div>
        
        <div id="formatUI" class="d-none mt-3 pt-3 border-top border-secondary" style="border-color: rgba(255,255,255,0.05) !important;">
            <div class="row g-3">
                <div class="col-md-12" id="presetBlock">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="small text-secondary fw-bold mb-0"><?= __('tit_presets_global') ?></label>
                        <button type="button" class="btn btn-sm btn-outline-info py-0" onclick="addPresetRow()"><?= __('btn_anadirestilo') ?></button>
                    </div>
                    <div id="presetsWrapper">
                        </div>
                </div>
            </div>
            
            <div class="row g-3 mt-1 pt-3 border-top border-secondary" style="border-color: rgba(255,255,255,0.05) !important;">
                <div class="col-12">
                    <label class="small text-warning fw-bold mb-1"><i class="bi bi-star-fill"></i> <?= __('tit_presets_person') ?></label>
                    <div class="input-group input-group-sm shadow-sm">
                        <select class="form-select bg-dark text-light border-warning" id="personalPresetSelector">
                            <option value=""><?= __('opt_loading_presets') ?></option>
                        </select>
                        <button class="btn btn-outline-warning" type="button" onclick="loadPersonalPreset()" title="<?= __('btn_title_apply') ?>"><i class="bi bi-upload"></i> <?= __('btn_aplicar') ?></button>
                        <button class="btn btn-outline-success" type="button" onclick="savePersonalPreset()" title="<?= __('btn_title_save') ?>"><i class="bi bi-floppy"></i> <?= __('btn_guardactual') ?></button>
                        <button class="btn btn-outline-danger" type="button" onclick="deletePersonalPreset()" title="<?= __('btn_title_delete') ?>"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
        </div> 
	</div> 