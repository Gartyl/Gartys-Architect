<div class="modal fade show" id="onboardingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" style="display: block; background: rgba(0,0,0,0.9); z-index: 99999;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-info shadow-lg" style="background-color: #0d1117; color: #c9d1d9;">
            <div class="modal-header border-secondary bg-dark">
                <h4 class="modal-title fw-bold text-info"><i class="bi bi-stars text-warning"></i> <?= __('bien_001') ?></h4>
            </div>
            <div class="modal-body p-4 text-center">
                <h5 class="mb-4"><?= __('bien_002') ?></h5>
                
                <div class="row text-start mb-3 g-3">
                    <div class="col-md-6">
                        <div class="card bg-darker border-secondary h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-light"><i class="bi bi-cpu"></i> <?= __('bien_003') ?></h6>
                                <p class="small text-secondary mb-2"><?= __('bien_004') ?></p>
                                <div id="statusOllama" class="fw-bold text-warning"><i class="bi bi-arrow-repeat spin"></i> <?= __('bien_005') ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card bg-darker border-secondary h-100">
                            <div class="card-body">
                                <h6 class="fw-bold text-light"><i class="bi bi-gpu-card"></i> <?= __('bien_006') ?></h6>
                                <p class="small text-secondary mb-2"><?= __('bien_007') ?></p>
                                <div id="statusComfy" class="fw-bold text-warning"><i class="bi bi-arrow-repeat spin"></i> <?= __('bien_008') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SELECTOR DE RUTA COMFY_MODELS_DIR -->
                <div class="card bg-darker border-secondary text-start mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-light mb-0">
                                <i class="bi bi-folder2-open text-info me-1"></i> <?= __('bien_ruta_comfy_tit') ?? 'Carpeta de modelos de ComfyUI' ?>
                            </h6>
                            <span id="statusComfyDir" class="badge bg-secondary">...</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="text" id="onboardingComfyDirInput" class="form-control bg-dark text-light border-secondary font-monospace" placeholder="C:/ComfyUI/models">
                            <button type="button" class="btn btn-info fw-bold" id="btnGuardarComfyDir" onclick="guardarRutaComfyOnboarding()">
                                <i class="bi bi-floppy"></i> <?= __('btn_guardar') ?? 'Guardar' ?>
                            </button>
                        </div>
                        <div id="onboardingSuggestedDirs" class="d-none mt-2 d-flex flex-wrap align-items-center gap-1"></div>
                        <div id="onboardingComfyDirMsg" class="small mt-1 text-secondary">
                            <?= __('bien_ruta_comfy_help') ?? 'Pega la ruta a tu carpeta ComfyUI/models (si pegas la carpeta raíz de ComfyUI, añadimos /models automáticamente).' ?>
                        </div>
                    </div>
                </div>

                <div class="alert border-warning text-start p-3 mb-4 shadow-sm" style="background-color: rgba(255, 193, 7, 0.05);">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle text-warning fs-3 me-3"></i>
                        <p class="mb-0 small text-light" style="line-height: 1.5;">
                            <?= __('bien_026') ?>
                        </p>
                    </div>
                </div>

                <div id="onboardingAcciones" class="d-none">
                    <hr class="border-secondary">
                    <h5 class="text-success mb-2"><i class="bi bi-check-circle-fill"></i> <?= __('bien_009') ?></h5>
                    <p class="text-light mb-3"><?= __('bien_010') ?>:</p>
                    
                    <div class="d-grid gap-3 col-md-9 mx-auto">
                        <button class="btn btn-outline-info fw-bold shadow-sm" id="btnInstalarLlama" onclick="instalarModeloBasico('llama3', this)">
                            <i class="bi bi-download"></i> <?= __('bien_011') ?> <span class="badge bg-dark text-info border border-info ms-1">~5 GB</span>
                        </button>
                        <button class="btn btn-outline-warning fw-bold shadow-sm" id="btnInstalarSDXL" onclick="instalarModeloBasico('sdxl', this)">
                            <i class="bi bi-download"></i> <?= __('bien_012') ?> <span class="badge bg-dark text-warning border border-warning ms-1">~6.6 GB</span>
                        </button>

                        <!-- BARRA DE PROGRESO DE DESCARGA -->
                        <div id="onboardingProgressWrap" class="d-none text-start">
                            <div class="d-flex justify-content-between small mb-1">
                                <span id="onboardingProgressLabel" class="text-info fw-bold"><?= __('bien_downloading') ?></span>
                                <span id="onboardingProgressPct" class="text-warning fw-bold">0%</span>
                            </div>
                            <div class="progress bg-dark border border-secondary" style="height: 12px;">
                                <div id="onboardingProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%;"></div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-2 mb-1 px-3">
                            <small class="text-secondary" style="font-size: 0.8rem;">
                                <i class="bi bi-info-circle"></i> <?= __('bien_013') ?>
                            </small>
                        </div>

                        <button class="btn btn-success fw-bold shadow mt-1 fs-5 py-2" onclick="cerrarOnboarding()">
                            <i class="bi bi-rocket-takeoff-fill"></i> <?= __('bien_014') ?>
                        </button>
                    </div>
                </div>

                <div id="onboardingError" class="d-none mt-4 alert alert-danger border-danger text-start">
                    <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> <?= __('bien_015') ?></h6>
                    <p class="mb-3 small"><?= __('bien_016') ?></p>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-sm btn-outline-light" onclick="escanearServiciosOnboarding()">
                            <i class="bi bi-arrow-clockwise"></i> <?= __('btn_retry') ?? 'Reintentar' ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-warning" onclick="cerrarOnboarding()">
                            <?= __('btn_skip_onboarding') ?? 'Omitir y entrar' ?> <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
const ONB_I18N = <?= json_encode([
    'dir_not_found' => __('bien_dir_not_found'),
    'dir_detected'  => __('bien_dir_detected'),
    'dir_saved_ok'  => __('bien_dir_saved_ok'),
    'dir_invalid'   => __('bien_dir_invalid'),
    'dir_conn_err'  => __('bien_dir_conn_err'),
    'err_dir_first' => __('bien_err_dir_first'),
    'ollama_models' => __('bien_ollama_models'),
    'dl_sdxl'       => __('bien_dl_sdxl'),
    'dl_llama'      => __('bien_dl_llama'),
    'in_progress'   => __('bien_in_progress'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

let onboardingState = { ollamaOK: false, comfyOK: false, comfyDirOK: false };
let onboardingProgressTimer = null;

function actualizarBotonesOnboarding() {
    const btnLlama = document.getElementById('btnInstalarLlama');
    const btnSDXL  = document.getElementById('btnInstalarSDXL');
    if (btnLlama && !btnLlama.dataset.installed) {
        btnLlama.disabled = !onboardingState.ollamaOK;
    }
    if (btnSDXL && !btnSDXL.dataset.installed) {
        btnSDXL.disabled = !(onboardingState.comfyOK && onboardingState.comfyDirOK);
        btnSDXL.title = !onboardingState.comfyDirOK ? ONB_I18N.err_dir_first : '';
    }
}

function escanearServiciosOnboarding() {
    const uiOllama    = document.getElementById('statusOllama');
    const uiComfy     = document.getElementById('statusComfy');
    const uiDirBadge  = document.getElementById('statusComfyDir');
    const dirInput    = document.getElementById('onboardingComfyDirInput');
    const dirSugg     = document.getElementById('onboardingSuggestedDirs');
    const boxAcciones = document.getElementById('onboardingAcciones');
    const boxError    = document.getElementById('onboardingError');

    boxError.classList.add('d-none');
    uiOllama.className = 'fw-bold text-warning';
    uiOllama.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_005') ?>`;
    uiComfy.className = 'fw-bold text-warning';
    uiComfy.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_008') ?>`;

    const formData = new FormData();
    formData.append('action', 'ping_servicios');

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        onboardingState.ollamaOK   = Boolean(data.ollama);
        onboardingState.comfyOK    = Boolean(data.comfy);
        onboardingState.comfyDirOK = Boolean(data.comfy_models_ok);

        if (onboardingState.ollamaOK) {
            const extra = (data.ollama_models_count > 0)
                ? ` <span class="badge bg-dark text-info border border-info ms-1">${data.ollama_models_count} ${ONB_I18N.ollama_models}</span>`
                : '';
            uiOllama.className = 'fw-bold text-success';
            uiOllama.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_017') ?? 'Ollama Detectado' ?>${extra}`;
        } else {
            uiOllama.className = 'fw-bold text-danger';
            uiOllama.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_018') ?? 'Ollama No Detectado' ?>`;
        }

        if (onboardingState.comfyOK) {
            uiComfy.className = 'fw-bold text-success';
            uiComfy.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_019') ?? 'ComfyUI Detectado' ?>`;
        } else {
            uiComfy.className = 'fw-bold text-danger';
            uiComfy.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_020') ?? 'ComfyUI No Detectado' ?>`;
        }

        if (dirInput && data.comfy_models_dir) {
            dirInput.value = data.comfy_models_dir;
        }
        if (uiDirBadge) {
            if (onboardingState.comfyDirOK) {
                uiDirBadge.className = 'badge bg-success';
                uiDirBadge.innerHTML = '<i class="bi bi-check-lg"></i> OK';
            } else {
                uiDirBadge.className = 'badge bg-danger';
                uiDirBadge.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${ONB_I18N.dir_not_found}`;
            }
        }

        if (dirSugg) {
            const paths = Array.isArray(data.suggested_paths) ? data.suggested_paths : [];
            if (paths.length > 0) {
                dirSugg.classList.remove('d-none');
                dirSugg.innerHTML = `<span class="small text-secondary me-1">${ONB_I18N.dir_detected}</span>` +
                    paths.map(p => `<button type="button" class="btn btn-outline-info py-0 px-2" style="font-size: 0.72rem;" onclick="aplicarRutaSugeridaOnboarding('${p.replace(/'/g, "\\'")}')"><i class="bi bi-folder-check"></i> ${p}</button>`).join('');
            } else {
                dirSugg.classList.add('d-none');
                dirSugg.innerHTML = '';
            }
        }

        actualizarBotonesOnboarding();

        if (onboardingState.ollamaOK && onboardingState.comfyOK) {
            boxAcciones.classList.remove('d-none');
        } else {
            boxError.classList.remove('d-none');
            if (onboardingState.ollamaOK || onboardingState.comfyOK) {
                boxAcciones.classList.remove('d-none');
            }
        }
    })
    .catch(e => {
        console.error(`<?= __('bien_021') ?? 'Error de escaneo' ?>:`, e);
        boxError.classList.remove('d-none');
    });
}

function aplicarRutaSugeridaOnboarding(ruta) {
    const input = document.getElementById('onboardingComfyDirInput');
    if (input) input.value = ruta;
    guardarRutaComfyOnboarding();
}

async function guardarRutaComfyOnboarding() {
    const input = document.getElementById('onboardingComfyDirInput');
    const btn   = document.getElementById('btnGuardarComfyDir');
    const badge = document.getElementById('statusComfyDir');
    const msg   = document.getElementById('onboardingComfyDirMsg');
    if (!input || !btn) return;

    const origHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append('action', 'guardar_ruta_comfy');
    fd.append('comfy_models_dir', input.value.trim());

    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            input.value = data.ruta;
            onboardingState.comfyDirOK = true;
            if (badge) { badge.className = 'badge bg-success'; badge.innerHTML = '<i class="bi bi-check-lg"></i> OK'; }
            if (msg) { msg.className = 'small mt-1 text-success'; msg.textContent = ONB_I18N.dir_saved_ok; }
            actualizarBotonesOnboarding();
        } else {
            onboardingState.comfyDirOK = false;
            if (badge) { badge.className = 'badge bg-danger'; badge.innerHTML = '<i class="bi bi-x-lg"></i> Error'; }
            if (msg) { msg.className = 'small mt-1 text-danger'; msg.textContent = data.error || ONB_I18N.dir_invalid; }
            actualizarBotonesOnboarding();
        }
    } catch (e) {
        if (msg) { msg.className = 'small mt-1 text-danger'; msg.textContent = ONB_I18N.dir_conn_err; }
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }
}

document.addEventListener('DOMContentLoaded', escanearServiciosOnboarding);

function iniciarMonitorProgresoOnboarding(tipo) {
    const wrap  = document.getElementById('onboardingProgressWrap');
    const bar   = document.getElementById('onboardingProgressBar');
    const label = document.getElementById('onboardingProgressLabel');
    const pctEl = document.getElementById('onboardingProgressPct');
    if (!wrap || !bar) return;

    wrap.classList.remove('d-none');
    if (tipo === 'sdxl') {
        label.textContent = ONB_I18N.dl_sdxl;
        pctEl.textContent = '0%';
        bar.style.width = '0%';
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-warning';

        clearInterval(onboardingProgressTimer);
        onboardingProgressTimer = setInterval(async () => {
            try {
                const fd = new FormData();
                fd.append('action', 'leer_progreso_descarga');
                const r = await fetch('procesar.php', { method: 'POST', body: fd });
                const d = await r.json();
                const pct = Math.max(0, Math.min(100, parseInt(d.porcentaje || '0', 10)));
                bar.style.width = pct + '%';
                pctEl.textContent = pct + '%';
            } catch (e) {}
        }, 1500);
    } else {
        label.textContent = ONB_I18N.dl_llama;
        pctEl.textContent = ONB_I18N.in_progress;
        bar.style.width = '100%';
        bar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
    }
}

function detenerMonitorProgresoOnboarding(exito) {
    clearInterval(onboardingProgressTimer);
    onboardingProgressTimer = null;
    const wrap  = document.getElementById('onboardingProgressWrap');
    const bar   = document.getElementById('onboardingProgressBar');
    const pctEl = document.getElementById('onboardingProgressPct');
    if (!wrap || !bar) return;

    if (exito) {
        bar.style.width = '100%';
        bar.className = 'progress-bar bg-success';
        if (pctEl) pctEl.textContent = '100%';
    } else {
        wrap.classList.add('d-none');
    }
}

function instalarModeloBasico(tipo, btnElement) {
    const originalHtml  = btnElement.innerHTML;
    const originalClass = btnElement.className;
    btnElement.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_022') ?? 'Instalando...' ?>`;
    btnElement.disabled = true;

    iniciarMonitorProgresoOnboarding(tipo);

    const formData = new FormData();
    formData.append('action', 'instalar_basico');
    formData.append('tipo', tipo);

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            detenerMonitorProgresoOnboarding(true);
            btnElement.dataset.installed = '1';
            btnElement.className = "btn btn-success fw-bold";
            btnElement.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_023') ?? 'Instalado' ?>`;
        } else {
            detenerMonitorProgresoOnboarding(false);
            btnElement.className = "btn btn-danger fw-bold";
            btnElement.innerHTML = `<i class="bi bi-x-circle-fill"></i> Error: ` + (data.error || `<?= __('bien_024') ?? 'Fallo desconocido' ?>`);
            setTimeout(() => { btnElement.disabled = false; btnElement.innerHTML = originalHtml; btnElement.className = originalClass; actualizarBotonesOnboarding(); }, 3500);
        }
    })
    .catch(e => {
        detenerMonitorProgresoOnboarding(false);
        btnElement.className = "btn btn-danger fw-bold";
        btnElement.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_025') ?? 'Error de red' ?>`;
        setTimeout(() => { btnElement.disabled = false; btnElement.innerHTML = originalHtml; btnElement.className = originalClass; actualizarBotonesOnboarding(); }, 3500);
    });
}

function cerrarOnboarding() {
    document.cookie = "garty_onboarding_done=1; path=/; max-age=31536000";
    location.reload();
}
</script>