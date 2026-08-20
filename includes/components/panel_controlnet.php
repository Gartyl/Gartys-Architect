<div class="param-group shadow-sm border-success mb-3" id="controlNetBlock" style="display: none; border-color: rgba(46, 160, 67, 0.4) !important; background: rgba(46, 160, 67, 0.05);">
    <div class="d-flex justify-content-between align-items-center">
        <label class="small text-success fw-bold mb-0">
            <i class="bi bi-bezier2 me-1"></i> <?= __('tit_controlnet') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
        </label>
        <div class="form-check form-switch m-0">
            <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="controlNetToggle" onchange="toggleControlNetUI()" <?= !$is_pro ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div id="controlNetUI" class="d-none mt-3 text-center">
        <div class="row g-2 mb-2 text-start">
            <div class="col-md-6">
                <label class="small text-secondary fw-bold"><?= __('tit_preproc') ?></label>
                <select class="form-select form-select-sm pref-track" id="cnPreprocessor" onchange="toggleDWPoseUI()">
                    <option value="none" selected><?= __('cn_prep_none') ?></option>
                    <option value="CannyEdgePreprocessor"><?= __('cn_prep_canny') ?></option>
                    <option value="LineArtPreprocessor"><?= __('cn_prep_lineart') ?></option>
                    <option value="DepthAnythingV2Preprocessor"><?= __('cn_prep_depth') ?></option>
                    <option value="DWPreprocessor"><?= __('cn_prep_pose') ?? 'DW Pose (Esqueleto)' ?></option>
                </select>
                
                <!-- Panel exclusivo para DW Pose (Oculto por defecto) -->
                <div id="dwPosePanel" class="mt-2 p-2 border border-success rounded d-none" style="background: rgba(46, 160, 67, 0.1);">
                    <label class="small text-success fw-bold d-block mb-1" style="font-size: 0.75rem;">
                        <i class="bi bi-person-bounding-box"></i> <?= __('lbl_dwpose_opts') ?>
                    </label>
                    <div class="d-flex justify-content-between">
                        <div class="form-check form-switch form-check-inline m-0">
                            <input class="form-check-input pref-track" type="checkbox" id="dwDetectBody" checked>
                            <label class="form-check-label small text-light" style="font-size: 0.8rem;" for="dwDetectBody"><?= __('lbl_dwpose_body') ?></label>
                        </div>
                        <div class="form-check form-switch form-check-inline m-0">
                            <input class="form-check-input pref-track" type="checkbox" id="dwDetectFace" checked>
                            <label class="form-check-label small text-light" style="font-size: 0.8rem;" for="dwDetectFace"><?= __('lbl_dwpose_face') ?></label>
                        </div>
                        <div class="form-check form-switch form-check-inline m-0">
                            <input class="form-check-input pref-track" type="checkbox" id="dwDetectHand" checked>
                            <label class="form-check-label small text-light" style="font-size: 0.8rem;" for="dwDetectHand"><?= __('lbl_dwpose_hands') ?></label>
                        </div>
                    </div>
                </div>
                <!-- FIN Panel DW Pose -->
            </div>
            
            <div class="col-md-6">
                <label class="small text-secondary fw-bold"><?= __('tit_mod_ctrlnet') ?></label>
                <select class="form-select form-select-sm pref-track" id="cnModelSelector">
                    <option value=""><?= __('opt_loading_models') ?></option>
                </select>
            </div>
        </div>
        
        <div class="d-flex gap-2 mb-2 w-100">
            <button type="button" class="btn btn-sm btn-outline-success flex-grow-1" onclick="document.getElementById('cnInput').click()">
                <i class="bi bi-upload"></i> <?= __('btn_subirmreferencia') ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-info" onclick="window.destinoGaleriaModal = 'controlnet'; abrirModalGaleria();" title="<?= __('btn_cargaleria') ?>">
                <i class="bi bi-images"></i> <?= __('btn_cargaleria') ?>
            </button>
        </div>
        <input type="file" id="cnInput" accept="image/*" class="d-none">
        
        <div class="row g-2 mt-2 text-start">
            <div class="col-md-12">
                <label class="small text-secondary fw-bold"><?= __('tit_mod_ctrl') ?></label>
                <select class="form-select form-select-sm bg-dark text-light border-success pref-track" id="cnMode">
                    <option value="Balanced"><?= __('cn_mode_bal') ?></option>
                    <option value="My prompt is more important"><?= __('cn_mode_prompt') ?></option>
                    <option value="ControlNet is more important"><?= __('cn_mode_cn') ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="text-secondary small fw-bold"><?= __('tit_fuerza') ?>: <span id="cnWeightLabel" class="text-light">1.0</span></label>
                <input type="range" class="form-range pref-track" id="cnWeight" min="0.1" max="1.5" step="0.05" value="1.0" oninput="document.getElementById('cnWeightLabel').innerText = this.value;">
            </div>
            <div class="col-md-4">
                <label class="text-secondary small fw-bold"><?= __('tit_inicio') ?> %: <span id="cnStartLabel" class="text-light">0.0</span></label>
                <input type="range" class="form-range pref-track" id="cnStart" min="0.0" max="1.0" step="0.05" value="0.0" oninput="document.getElementById('cnStartLabel').innerText = this.value;">
            </div>
            <div class="col-md-4">
                <label class="text-secondary small fw-bold"><?= __('tit_fin') ?> %: <span id="cnEndLabel" class="text-light">1.0</span></label>
                <input type="range" class="form-range pref-track" id="cnEnd" min="0.0" max="1.0" step="0.05" value="1.0" oninput="document.getElementById('cnEndLabel').innerText = this.value;">
            </div>
        </div>
        
        <div id="cnPreviewContainer" class="d-none mt-2">
            <img id="cnPreview" src="" class="img-fluid rounded border border-success shadow-sm" style="max-height: 120px;">
            <div class="mt-2 d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-success flex-grow-1" onclick="autoCaptionReference('cnPreview', this)">
                    <i class="bi bi-magic"></i> <?= __('btn_extraeprompt') ?>
                </button>
                <button type="button" class="btn btn-sm btn-danger" onclick="clearControlNet()">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Script para animar la visibilidad del sub-panel -->
<script>
    function toggleDWPoseUI() {
        const preproc = document.getElementById('cnPreprocessor').value;
        const dwPanel = document.getElementById('dwPosePanel');
        if (preproc === 'DWPreprocessor') {
            dwPanel.classList.remove('d-none');
        } else {
            dwPanel.classList.add('d-none');
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        toggleDWPoseUI();
    });
</script>