<div class="modal fade show" id="onboardingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" style="display: block; background: rgba(0,0,0,0.9); z-index: 99999;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-info shadow-lg" style="background-color: #0d1117; color: #c9d1d9;">
            <div class="modal-header border-secondary bg-dark">
                <h4 class="modal-title fw-bold text-info"><i class="bi bi-stars text-warning"></i> <?= __('bien_001') ?></h4>
            </div>
            <div class="modal-body p-4 text-center">
                <h5 class="mb-4"><?= __('bien_002') ?></h5>
                
                <div class="row text-start mb-4">
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
                </div> <div class="alert border-warning text-start p-3 mb-4 shadow-sm" style="background-color: rgba(255, 193, 7, 0.05);">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle text-warning fs-3 me-3"></i>
                        <p class="mb-0 small text-light" style="line-height: 1.5;">
                            <?= __('bien_026') ?>
                        </p>
                    </div>
                </div>
                <div id="onboardingAcciones" class="d-none">
                    <hr class="border-secondary">
                    <h5 class="text-success mb-3"><i class="bi bi-check-circle-fill"></i> <?= __('bien_009') ?></h5>
                    <p class="text-light mb-4"><?= __('bien_010') ?>:</p>
                    
                    <div class="d-grid gap-3 col-8 mx-auto">
                        <button class="btn btn-outline-info fw-bold shadow-sm" id="btnInstalarLlama" onclick="instalarModeloBasico('llama3', this)">
                            <i class="bi bi-download"></i> <?= __('bien_011') ?>
                        </button>
                        <button class="btn btn-outline-warning fw-bold shadow-sm" id="btnInstalarSDXL" onclick="instalarModeloBasico('sdxl', this)">
                            <i class="bi bi-download"></i> <?= __('bien_012') ?>
                        </button>
                        
                        <div class="text-center mt-3 mb-1 px-3">
                            <small class="text-secondary" style="font-size: 0.8rem;">
                                <i class="bi bi-info-circle"></i> <?= __('bien_013') ?>
                            </small>
                        </div>

                        <button class="btn btn-success fw-bold shadow mt-1 fs-5 py-2" onclick="cerrarOnboarding()">
                            <i class="bi bi-rocket-takeoff-fill"></i> <?= __('bien_014') ?>
                        </button>
                    </div>
                </div>

                <<div id="onboardingError" class="d-none mt-4 alert alert-danger border-danger text-start">
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
function escanearServiciosOnboarding() {
    let uiOllama = document.getElementById('statusOllama');
    let uiComfy = document.getElementById('statusComfy');
    let boxAcciones = document.getElementById('onboardingAcciones');
    let boxError = document.getElementById('onboardingError');

    boxError.classList.add('d-none');
    uiOllama.className = 'fw-bold text-warning';
    uiOllama.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_005') ?>`;
    uiComfy.className = 'fw-bold text-warning';
    uiComfy.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_008') ?>`;

    let formData = new FormData();
    formData.append('action', 'ping_servicios');

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        let ollamaOK = data.ollama;
        let comfyOK = data.comfy;

        if (ollamaOK) {
            uiOllama.className = 'fw-bold text-success';
            uiOllama.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_017') ?? 'Ollama Detectado' ?>`;
        } else {
            uiOllama.className = 'fw-bold text-danger';
            uiOllama.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_018') ?? 'Ollama No Detectado' ?>`;
        }

        if (comfyOK) {
            uiComfy.className = 'fw-bold text-success';
            uiComfy.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_019') ?? 'ComfyUI Detectado' ?>`;
        } else {
            uiComfy.className = 'fw-bold text-danger';
            uiComfy.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_020') ?? 'ComfyUI No Detectado' ?>`;
        }

        // Desactivamos individualmente el botón del motor que no esté encendido
        const btnLlama = document.getElementById('btnInstalarLlama');
        const btnSDXL = document.getElementById('btnInstalarSDXL');
        if (btnLlama) btnLlama.disabled = !ollamaOK;
        if (btnSDXL) btnSDXL.disabled = !comfyOK;

        if (ollamaOK && comfyOK) {
            boxAcciones.classList.remove('d-none');
        } else {
            boxError.classList.remove('d-none');
            // Si al menos uno de los dos está activo, mostramos también las acciones para que pueda usarlo
            if (ollamaOK || comfyOK) {
                boxAcciones.classList.remove('d-none');
            }
        }
    })
    .catch(e => {
        console.error(`<?= __('bien_021') ?? 'Error de escaneo' ?>:`, e);
        boxError.classList.remove('d-none');
    });
}

document.addEventListener('DOMContentLoaded', escanearServiciosOnboarding);

function instalarModeloBasico(tipo, btnElement) {
    let originalHtml = btnElement.innerHTML;
    let originalClass = btnElement.className;
    btnElement.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_022') ?? 'Instalando...' ?>`;
    btnElement.disabled = true;

    let formData = new FormData();
    formData.append('action', 'instalar_basico');
    formData.append('tipo', tipo);

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            btnElement.className = "btn btn-success fw-bold";
            btnElement.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_023') ?? 'Instalado' ?>`;
        } else {
            btnElement.className = "btn btn-danger fw-bold";
            btnElement.innerHTML = `<i class="bi bi-x-circle-fill"></i> Error: ` + (data.error || `<?= __('bien_024') ?? 'Fallo desconocido' ?>`);
            setTimeout(() => { btnElement.disabled = false; btnElement.innerHTML = originalHtml; btnElement.className = originalClass; }, 3500);
        }
    })
    .catch(e => {
        btnElement.className = "btn btn-danger fw-bold";
        btnElement.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_025') ?? 'Error de red' ?>`;
        setTimeout(() => { btnElement.disabled = false; btnElement.innerHTML = originalHtml; btnElement.className = originalClass; }, 3500);
    });
}

function cerrarOnboarding() {
    document.cookie = "garty_onboarding_done=1; path=/; max-age=31536000";
    location.reload();
}
</script>