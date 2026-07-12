    <div id="loraContainer" style="display: none;">
        <div class="param-group shadow-sm border-secondary mb-3" style="border-color: rgba(255,255,255,0.2) !important;">
            <div class="d-flex justify-content-between align-items-center">
                <label class="small text-light fw-bold mb-0"><i class="bi bi-layers-fill me-1"></i> <?= __('tit_carga_lora') ?></label>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input pref-track" type="checkbox" id="loraToggle" onchange="document.getElementById('loraUI').classList.toggle('d-none', !this.checked)">
                </div>
            </div>
            <div id="loraUI" class="d-none mt-3 pt-2 border-top border-secondary" style="border-color: rgba(255,255,255,0.05) !important;">
                <div class="text-end mb-2">
                    <button id="addLoraBtn" type="button" class="btn btn-sm btn-outline-secondary py-0" onclick="addLoraRow()"><?= __('btn_anadirlora') ?></button>
                </div>
                <div id="lorasWrapper"></div>
            </div>
        </div>
    </div>