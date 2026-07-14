<?php $is_avanzado = ($user_rol === 'pro' || $is_admin); ?>
<!-- ============================================================================== -->
<!-- VISOR DE IMAGEN Y HERRAMIENTAS DE EDICIÓN AVANZADA (Inpaint / Outpaint / LaMa) -->
<!-- ============================================================================== -->
<div id="visorEdicionContainer" style="display: none;">
    <div style="position: relative; width: 100%;">
        <!-- Lienzo con Zoom -->
        <div id="lienzoScroll" style="width: 100%; max-height: 65vh; overflow: auto; border-radius: 8px; background: #010409;" class="shadow border border-info">
            <div id="lienzoZoom" style="position: relative; display: block; width: 100%; transform-origin: top left; transition: width 0.1s ease-out;">
                <img id="imgPreview" src="" onclick="if(typeof abrirVisor === 'function') abrirVisor(this.src)" style="display: block; width: 100%; height: auto; cursor: zoom-in;" title="<?= __('img_zoom_title') ?? 'Zoom' ?>">
                <canvas id="maskCanvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0.6; z-index: 10; touch-action: none; display: none;"></canvas>
            </div>
        </div>
        
        <!-- Controles flotantes de Zoom -->
        <div id="lienzoZoomControls" style="position: absolute; top: 10px; right: 25px; z-index: 100; display: none; flex-direction: column; gap: 8px;">
            <button type="button" class="btn btn-dark border border-secondary shadow" style="border-radius: 50%; width: 36px; height: 36px; padding: 0; opacity: 0.9;" onclick="if(typeof zoomLienzo === 'function') zoomLienzo(30)" title="<?= __('img_zoom_in') ?? 'Acercar' ?>"><i class="bi bi-zoom-in"></i></button>
            <button type="button" class="btn btn-dark border border-secondary shadow" style="border-radius: 50%; width: 36px; height: 36px; padding: 0; opacity: 0.9;" onclick="if(typeof zoomLienzo === 'function') zoomLienzo(-30)" title="<?= __('img_zoom_out') ?? 'Alejar' ?>"><i class="bi bi-zoom-out"></i></button>
            <button type="button" class="btn btn-dark border border-secondary shadow" style="border-radius: 50%; width: 36px; height: 36px; padding: 0; opacity: 0.9;" onclick="if(typeof resetZoomLienzo === 'function') resetZoomLienzo()" title="<?= __('img_zoom_fit') ?? 'Ajustar' ?>"><i class="bi bi-arrows-collapse"></i></button>
            <button type="button" class="btn btn-info border border-secondary shadow mt-3" style="border-radius: 50%; width: 36px; height: 36px; padding: 0; opacity: 0.9; color: black;" onclick="if(typeof abrirVisor === 'function') abrirVisor(document.getElementById('imgPreview').src)" title="<?= __('img_zoom_full') ?? 'Pantalla Completa' ?>"><i class="bi bi-arrows-fullscreen"></i></button>
        </div>
    </div>
    
    <!-- Panel principal Inpaint / Outpaint -->
    <div id="inpaintToolbar" class="mt-3 param-group shadow-sm border-info flex-column w-100" style="border-color: rgba(13, 202, 240, 0.4) !important; background: rgba(13, 202, 240, 0.05); display: none;">
        <div class="d-flex justify-content-between align-items-center w-100">
            <label class="small text-info fw-bold mb-0" style="cursor: pointer;" onclick="document.getElementById('editToolsToggle').click();">
                <i class="bi bi-brush"></i> <?= __('tit_inpaint') ?? 'EDICIÓn AVANZADA (Inpaint / Outpaint)' ?> 
                <?php if (!$is_avanzado): ?>🔒 (Pro)<?php endif; ?>
            </label>
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track border-info" type="checkbox" id="editToolsToggle" style="cursor: pointer;" onchange="document.getElementById('editToolsUI').classList.toggle('d-none', !this.checked); window.updateCursor();">
            </div>
        </div>
        
        <!-- UI de Herramientas Desplegable -->
        <div id="editToolsUI" class="d-none mt-3 pt-3 w-100 border-top border-info" style="border-color: rgba(13, 202, 240, 0.2) !important;">
            
            <!-- NUEVO: Interruptor de Borrado Mágico (LaMa Remover) integrado -->
            <div class="d-flex align-items-center justify-content-between p-2 mb-3 rounded border border-danger shadow-sm" style="background: rgba(220, 53, 69, 0.08); border-color: rgba(220, 53, 69, 0.4) !important;">
                <label class="form-check-label small text-danger fw-bold mb-0" for="toggleLamaMode" style="cursor: pointer;">
                    <i class="bi bi-magic me-1"></i> <?= __('ctrl_lama_remover') ?? "Borrado Mágico (LaMa Remover)" ?>
                </label>
                <div class="form-check form-switch m-0">
                    <input class="form-check-input border-danger" type="checkbox" id="toggleLamaMode" name="lama_enabled" value="1" 
                           style="cursor: pointer;" onchange="if(typeof toggleLamaUI === 'function') toggleLamaUI(this.checked)">
                </div>
            </div>

            <!-- Controles de Pincel y Goma -->
            <div class="p-2 bg-dark border border-secondary rounded flex-wrap align-items-center justify-content-between gap-2 d-flex">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-sm btn-danger" id="btnBrush" onclick="setBrushMode('paint')" title="<?= __('inp_brush_title') ?? 'Pincel' ?>"><i class="bi bi-brush-fill"></i> <?= __('btn_pincel') ?? 'Pincel' ?></button>
                        <button type="button" class="btn btn-sm btn-outline-light" id="btnEraser" onclick="setBrushMode('erase')" title="<?= __('inp_eraser_title') ?? 'Goma' ?>"><i class="bi bi-eraser-fill"></i> <?= __('btn_goma') ?? 'Goma' ?></button>
                    </div>
                    <div class="d-flex align-items-center ms-2">
                        <label class="text-secondary small fw-bold me-2 mb-0 d-none d-md-block"><?= __('ctrl_grosor') ?? 'Grosor' ?>:</label>
                        <input type="range" class="form-range" id="brushSize" min="10" max="250" value="40" style="width: 120px;" oninput="window.updateCursor()">
                    </div>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="undoMask()" title="<?= __('inp_undo_title') ?? 'Deshacer' ?>"><i class="bi bi-arrow-counterclockwise"></i> <?= __('btn_deshacer') ?? 'Deshacer' ?></button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearMask()"><i class="bi bi-trash3-fill"></i> <?= __('btn_limpiar') ?? 'Limpiar' ?></button>
                </div>
                
                <!-- Parámetros exclusivos de Inpaint (se ocultan si activas LaMa) -->
                <div class="row mt-3 w-100 inpaint-only-params" id="inpaint-advanced-controls">
                    <div class="col-md-3">
                        <label for="denoiseSlider" class="form-label text-info small fw-bold mb-1" title="<?= __('inp_denoise_title') ?? 'Fuerza' ?>"><i class="bi bi-magic"></i> <?= __('tit_inp_fuerza') ?? 'FUERZA (DENOISE)' ?></label>
                        <div class="d-flex align-items-center">
                            <input type="range" class="form-range flex-grow-1 me-2 pref-track" id="denoiseSlider" min="0.1" max="1.0" step="0.05" value="0.65" oninput="document.getElementById('denoiseVal').innerText = this.value">
                            <span id="denoiseVal" class="badge bg-secondary">0.65</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="maskBlur" class="form-label text-info small fw-bold mb-1"><i class="bi bi-droplet-half"></i> <?= __('tit_inp_desen') ?? 'DESENFOQUE' ?></label>
                        <div class="d-flex align-items-center">
                            <input type="range" class="form-range flex-grow-1 me-2" id="maskBlur" min="0" max="32" step="1" value="4" oninput="document.getElementById('maskBlurVal').innerText = this.value">
                            <span id="maskBlurVal" class="badge bg-secondary">4</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="inpaintFill" class="form-label text-info small fw-bold mb-1"><i class="bi bi-paint-bucket"></i> <?= __('tit_inp_cont') ?? 'CONTENIDO' ?></label>
                        <select class="form-select form-select-sm bg-dark text-white border-secondary" id="inpaintFill">
                            <option value="original" selected="<?= __('inp_fill_orig') ?? 'Original' ?>"><?= __('inp_fill_orig') ?? 'Original' ?></option>
                            <option value="fill"><?= __('inp_fill_fill') ?? 'Rellenar' ?></option>
                            <option value="latent_noise"><?= __('inp_fill_noise') ?? 'Ruido Latente' ?></option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="inpaintArea" class="form-label text-info small fw-bold mb-1"><i class="bi bi-aspect-ratio"></i> <?= __('tit_inp_area') ?? 'ÁREA' ?></label>
                        <select class="form-select form-select-sm bg-dark text-white border-secondary" id="inpaintArea">
                            <option value="Whole Picture"><?= __('inp_area_whole') ?? 'Imagen Completa' ?></option>
                            <option value="Only Masked" selected><?= __('inp_area_mask') ?? 'Solo Máscara' ?></option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Barra Outpaint (se oculta si activas LaMa) -->
            <div id="outpaintToolbar" class="mt-2 p-2 bg-dark border border-secondary rounded flex-wrap align-items-center justify-content-between gap-2 d-flex inpaint-only-params">
                <div class="w-100 mb-2 d-flex justify-content-between align-items-center">
                    <label class="text-info small fw-bold mb-0"><i class="bi bi-arrows-fullscreen"></i> <?= __('tit_outpaint') ?? 'OUTPAINT (Expandir bordes del lienzo)' ?></label>
                    <span class="badge bg-secondary"><?= __('btn_pixeles') ?? 'Píxeles' ?></span>
                </div>
                <div class="d-flex gap-2 w-100">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-secondary border-secondary text-light" title="<?= __('outp_top') ?? 'Arriba' ?>"><i class="bi bi-arrow-up"></i></span>
                        <input type="number" id="outTop" class="form-control bg-dark text-light border-secondary" value="0" min="0" step="64" max="2048">
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-secondary border-secondary text-light" title="<?= __('outp_bottom') ?? 'Abajo' ?>"><i class="bi bi-arrow-down"></i></span>
                        <input type="number" id="outBottom" class="form-control bg-dark text-light border-secondary" value="0" min="0" step="64" max="2048">
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-secondary border-secondary text-light" title="<?= __('outp_left') ?? 'Izquierda' ?>"><i class="bi bi-arrow-left"></i></span>
                        <input type="number" id="outLeft" class="form-control bg-dark text-light border-secondary" value="0" min="0" step="64" max="2048">
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-secondary border-secondary text-light" title="<?= __('outp_right') ?? 'Derecha' ?>"><i class="bi bi-arrow-right"></i></span>
                        <input type="number" id="outRight" class="form-control bg-dark text-light border-secondary" value="0" min="0" step="64" max="2048">
                    </div>
                </div> 
            </div> 
            <div class="img2img-hint mt-3 mb-1 inpaint-only-params">
                <i class="bi bi-magic text-info me-2"></i> <b><?= __('txt_edic_acti') ?? 'Modo Edición Activado' ?>:</b> <?= __('txt_inpa_txt') ?? 'Dibuja con el ratón sobre la imagen para hacer Inpainting en una zona, o déjala limpia para aplicar Image-to-Image. También puedes expandir los bordes (Outpaint).' ?>
            </div>
        </div> 
    </div> 
</div>