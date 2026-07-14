<?php
// ==============================================================================
// --- ESCÁNER DINÁMICO DE MODELOS PARA REACTOR ---
// ==============================================================================

$ruta_checkpoints = defined('COMFY_MODEL_PATH') ? rtrim(COMFY_MODEL_PATH, '/\\') : "F:/ComfyUI/ComfyUI/models/checkpoints";
$base_models_dir = dirname($ruta_checkpoints); 

// 1. Escanear Modelos de Restauración (Estos SÍ admiten archivos físicos)
$dir_facerestore = $base_models_dir . '/facerestore_models';
$facerestore_options = '<option value="none">' . __('reac_rest_none') . '</option>';
$facerestore_fallback = true;

if (is_dir($dir_facerestore)) {
    $archivos_fr = array_diff(scandir($dir_facerestore), ['.', '..']);
    foreach ($archivos_fr as $archivo) {
        if (is_file($dir_facerestore . '/' . $archivo) && preg_match('/\.(pth|onnx|pt|bin)$/i', $archivo)) {
            $selected = (stripos($archivo, 'codeformer') !== false) ? 'selected' : '';
            $facerestore_options .= "<option value=\"$archivo\" $selected>$archivo</option>";
            $facerestore_fallback = false;
        }
    }
}

if ($facerestore_fallback) {
    $facerestore_options .= '<option value="codeformer-v0.1.0.pth" selected>CodeFormer</option><option value="GFPGANv1.4.pth">GFPGAN</option>';
}

// 2. Modelos de Detección (Estrictamente Hardcodeados según el Core de ReActor)
$facedetect_options = '
    <option value="retinaface_resnet50" selected>RetinaFace ResNet50</option>
    <option value="retinaface_mobile0.25">RetinaFace Mobile 0.25</option>
    <option value="YOLOv5l">YOLOv5l</option>
    <option value="YOLOv5n">YOLOv5n</option>
';
?>

    <div class="param-group shadow-sm border-warning mb-3" id="reactorBlock" style="display: none; border-color: rgba(255, 193, 7, 0.4) !important; background: rgba(255, 193, 7, 0.05);">
        <div class="d-flex justify-content-between align-items-center">
            <label class="small text-warning fw-bold mb-0"><i class="bi bi-person-bounding-box me-1"></i> <?= __('tit_reactor') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?></label>
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="reactorToggle" onchange="toggleReactorUI()" <?= !$is_pro ? 'disabled' : '' ?>>
            </div>
        </div>
        <div id="reactorUI" class="d-none mt-3 text-center">
            <button type="button" class="btn btn-sm btn-outline-warning w-100 mb-2" onclick="document.getElementById('faceInput').click()"><i class="bi bi-upload"></i> <?= __('btn_selfrostro') ?></button>
            <input type="file" id="faceInput" accept="image/*" class="d-none">
            
            <div id="facePreviewContainer" class="d-none mt-2">
                <img id="facePreview" src="" class="img-fluid rounded border border-warning shadow-sm" style="max-height: 120px;">
                <div class="mt-2"><button type="button" class="btn btn-sm btn-danger" onclick="clearFace()"><i class="bi bi-trash"></i> <?= __('btn_quitar') ?></button></div>
            </div>
            
            <div class="form-check form-switch mt-3 text-start" id="pureFaceSwapBlock">
                <input class="form-check-input border-warning" style="cursor: pointer;" type="checkbox" id="pureFaceSwapToggle" onchange="toggleFaceSwapPuro(this.checked)">
                <label class="form-check-label small text-warning fw-bold" for="pureFaceSwapToggle">
                    <i class="bi bi-shield-lock me-1"></i> <?= __('ctrl_reac_puro') ?>
                </label>
            </div>

            <div class="row g-2 mt-3 text-start border-top border-warning pt-3" style="border-color: rgba(255, 193, 7, 0.2) !important;">
                <div class="col-md-6">
                    <label class="small text-secondary fw-bold" title="0 = primera cara, 1 = segunda, etc."><?= __('tit_reac_destino') ?></label>
                    <input type="text" class="form-control form-control-sm bg-dark text-light border-warning pref-track" id="reactorTargetIndex" value="0" placeholder="<?= __('reac_ph_target') ?>">
                </div>
                <div class="col-md-6">
                    <label class="small text-secondary fw-bold"><?= __('tit_reac_origen') ?></label>
                    <input type="text" class="form-control form-control-sm bg-dark text-light border-warning pref-track" id="reactorSourceIndex" value="0" placeholder="<?= __('reac_ph_source') ?>">
                </div>
                
                <div class="col-md-4 mt-2">
                    <label class="small text-secondary fw-bold"><?= __('tit_reac_restau') ?></label>
                    <select class="form-select form-select-sm bg-dark text-light border-warning pref-track" id="reactorRestoreModel">
                        <?= $facerestore_options ?>
                    </select>
                </div>
                <div class="col-md-4 mt-2">
                    <label class="small text-secondary fw-bold"><?= __('tit_reac_fgen') ?></label>
                    <select class="form-select form-select-sm bg-dark text-light border-warning pref-track" id="reactorGender">
                        <option value="no"><?= __('reac_gen_ignore') ?></option>
                        <option value="female"><?= __('reac_gen_female') ?></option>
                        <option value="male"><?= __('reac_gen_male') ?></option>
                    </select>
                </div>
                <div class="col-md-4 mt-2">
                    <label class="small text-secondary fw-bold"><?= __('tit_reac_detec') ?></label>
                    <select class="form-select form-select-sm bg-dark text-light border-warning pref-track" id="reactorDetector">
                        <?= $facedetect_options ?>
                    </select>
                </div>

                <div class="col-md-6 mt-3">
                    <label class="text-secondary small fw-bold"><?= __('ctrl_fidelidad') ?>: <span id="reactorFidelityLabel" class="text-light">0.75</span></label>
                    <input type="range" class="form-range pref-track" id="reactorFidelity" min="0.0" max="1.0" step="0.05" value="0.75" oninput="document.getElementById('reactorFidelityLabel').innerText = this.value;">
                </div>
                <div class="col-md-6 mt-3">
                    <label class="text-secondary small fw-bold"><?= __('ctrl_vis_restaur') ?>: <span id="reactorVisLabel" class="text-light">1.0</span></label>
                    <input type="range" class="form-range pref-track" id="reactorVisibility" min="0.0" max="1.0" step="0.05" value="1.0" oninput="document.getElementById('reactorVisLabel').innerText = this.value;">
                </div>
            </div>
        </div>
    </div>