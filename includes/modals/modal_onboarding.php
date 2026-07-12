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

                <div id="onboardingError" class="d-none mt-4 alert alert-danger border-danger text-start">
                    <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> <?= __('bien_015') ?></h6>
                    <p class="mb-1 small"><?= __('bien_016') ?></p>
                </div>
                
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Escaneo silencioso al backend para saltarnos las restricciones de CORS del navegador
    let formData = new FormData();
    formData.append('action', 'ping_servicios');

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        let ollamaOK = data.ollama;
        let comfyOK = data.comfy;

        let uiOllama = document.getElementById('statusOllama');
        let uiComfy = document.getElementById('statusComfy');

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

        if (ollamaOK && comfyOK) {
            document.getElementById('onboardingAcciones').classList.remove('d-none');
        } else {
            document.getElementById('onboardingError').classList.remove('d-none');
        }
    })
    .catch(e => console.error(`<?= __('bien_021') ?? 'Error de escaneo' ?>:`, e));
});

function instalarModeloBasico(tipo, btnElement) {
    let originalHtml = btnElement.innerHTML;
    btnElement.innerHTML = `<i class="bi bi-arrow-repeat spin"></i> <?= __('bien_022') ?? 'Instalando...' ?>`;
    btnElement.disabled = true;

    let formData = new FormData();
    formData.append('action', 'instalar_basico');
    formData.append('tipo', tipo);

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            btnElement.className = "btn btn-success fw-bold";
            btnElement.innerHTML = `<i class="bi bi-check-circle-fill"></i> <?= __('bien_023') ?? 'Instalado' ?>`;
        } else {
            btnElement.className = "btn btn-danger fw-bold";
            // Aquí sumamos la variable segura con el error del servidor
            btnElement.innerHTML = `<i class="bi bi-x-circle-fill"></i> Error: ` + (data.error || `<?= __('bien_024') ?? 'Fallo desconocido' ?>`);
            setTimeout(() => { btnElement.disabled = false; btnElement.innerHTML = originalHtml; btnElement.className = "btn btn-outline-info fw-bold"; }, 3000);
        }
    })
    .catch(e => {
        btnElement.innerHTML = `<i class="bi bi-x-circle-fill"></i> <?= __('bien_025') ?? 'Error de red' ?>`;
    });
}

function cerrarOnboarding() {
    // 1. Creamos una cookie que caduca en 1 año
    document.cookie = "garty_onboarding_done=1; path=/; max-age=31536000";
    
    // 2. Recargamos la página. Ahora PHP verá la cookie y no volverá a inyectar el Modal.
    location.reload();
}

</script>