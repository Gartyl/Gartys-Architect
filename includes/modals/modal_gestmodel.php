<?php
// ==============================================================================
// --- ESCÁNER DE DIRECTORIOS COMFYUI (Para Datalists) ---
// ==============================================================================
function scanComfyFolder($subfolder) {
    if (!defined('COMFY_MODELS_DIR')) return [];
    $dir = rtrim(COMFY_MODELS_DIR, '/\\') . DIRECTORY_SEPARATOR . $subfolder;
    $results = [];
    if (!is_dir($dir)) return $results;

    try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['safetensors', 'ckpt', 'pt', 'pth', 'bin', 'gguf', 'sft'])) {
                    $rel_path = substr($file->getPathname(), strlen($dir) + 1);
                    $results[] = str_replace('\\', '/', $rel_path);
                }
            }
        }
    } catch (Exception $e) {}
    sort($results);
    return $results;
}

// Generamos las listas una sola vez al cargar el modal
$lista_modelos = array_merge(scanComfyFolder('checkpoints'), scanComfyFolder('unet'));
$lista_vaes    = scanComfyFolder('vae');
$lista_clips   = array_unique(array_merge(scanComfyFolder('clip'), scanComfyFolder('text_encoders')));
?>

<!-- DATALISTS INVISIBLES PARA AUTOCOMPLETADO -->
<datalist id="dl_modelos">
    <?php foreach(array_unique($lista_modelos) as $file) echo "<option value=\"$file\">"; ?>
</datalist>
<datalist id="dl_vaes">
    <?php foreach($lista_vaes as $file) echo "<option value=\"$file\">"; ?>
</datalist>
<datalist id="dl_clips">
    <?php foreach($lista_clips as $file) echo "<option value=\"$file\">"; ?>
</datalist>

<div class="modal fade" id="modalGestorModelos" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-success" style="background-color: #161b22; color: #c9d1d9;">
            <div class="modal-header border-0 bg-dark">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-database-fill-gear me-2"></i> <?= __('tit_paneladm') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                
                <ul class="nav nav-tabs border-secondary mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active bg-dark text-success fw-bold border-secondary border-bottom-0" data-bs-toggle="tab" data-bs-target="#tab-modelos" type="button" role="tab"><i class="bi bi-cpu"></i> <?= __('tit_pan_motores') ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link bg-dark text-info fw-bold border-secondary border-bottom-0" data-bs-toggle="tab" data-bs-target="#tab-prompts" type="button" role="tab" onclick="cargarTablaPrompts()"><i class="bi bi-chat-quote"></i> <?= __('tit_pan_prompts') ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link bg-dark text-warning fw-bold border-secondary border-bottom-0" data-bs-toggle="tab" data-bs-target="#tab-idiomas" type="button" role="tab" onclick="cargarIdiomasAdmin()"><i class="bi bi-translate"></i> <?= __('tit_pan_idiomas') ?></button>
                    </li>
                </ul>

                <div class="tab-content">
                    
                    <div class="tab-pane fade show active" id="tab-modelos" role="tabpanel">
                        <div class="card bg-dark border-secondary mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-success fw-bold m-0"><i class="bi bi-plus-circle"></i> <?= __('tit_pan_anmodel') ?></h6>
                                    
                                    <div class="d-flex gap-2 align-items-center flex-nowrap">
                                        
                                        <select class="form-select form-select-sm bg-dark text-light border-secondary" 
                                                style="max-width: 180px;" 
                                                onchange="filtrarTablaAdmin('tablaModelosBody', 4, this.value)">
                                            <option value=""><?= __('adm_cat_todas') ?></option>
                                            <option value="chat">💬 <?= __('adm_cat_chat') ?></option>
                                            <option value="sd15">🎨 <?= __('adm_cat_sd15') ?></option>
                                            <option value="sdxl">⚡ <?= __('adm_cat_sdxl') ?></option>
                                            <option value="flux">💎 <?= __('adm_cat_flux') ?></option>
                                            <option value="video">🎬 <?= __('adm_cat_video') ?></option>
                                            <option value="sys_">⚙️ <?= __('adm_cat_sys') ?></option>
                                        </select>
                                        
                                        <?php if ($is_pro): ?>
                                            <button class="btn btn-sm btn-primary fw-bold shadow-sm flex-shrink-0" onclick="abrirDescargadorCivitai()">
                                                <i class="bi bi-cloud-arrow-down-fill"></i> <?= __('tit_pan_civitai') ?>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary fw-bold disabled flex-shrink-0">
                                                <i class="bi bi-cloud-arrow-down-fill"></i> <?= __('tit_pan_civitai') ?> 🔒
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <form id="formNuevoModelo" class="row g-2 align-items-end">
                                    <input type="hidden" id="modId" value="">
                                    <div class="col-md-2">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_nom') ?></label>
                                        <input type="text" class="form-control bg-dark text-light border-secondary" id="modNombre" placeholder="<?= __('adm_ph_nom') ?>">
                                    </div>
                                    <!-- AHORA ESTE CAMPO USA EL DATALIST DE MODELOS -->
                                    <div class="col-md-3">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_arxex') ?></label>
                                        <input type="text" list="dl_modelos" class="form-control bg-dark text-light border-secondary" id="modArchivo" placeholder="<?= __('adm_ph_arx') ?>" autocomplete="off">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_motor') ?></label>
                                        <select class="form-select bg-dark text-light border-secondary" id="modMotor">
                                            <option value="ollama"><?= __('adm_opt_ollama') ?></option>
                                            <option value="comfyui"><?= __('adm_opt_comfy') ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_categ') ?></label>
                                        <select class="form-select bg-dark text-light border-secondary" id="modCat">
                                            <option value="chat">💬 <?= __('adm_cat_chat_conv') ?></option>
                                            <option value="sd15">🎨 <?= __('adm_cat_img_sd15') ?></option>
                                            <option value="sdxl">⚡ <?= __('adm_cat_img_sdxl') ?></option>
                                            <option value="flux" <?= !$is_pro ? 'disabled' : '' ?>>💎 <?= __('adm_cat_img_flux') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?></option>
                                            <option value="video" <?= !$is_pro ? 'disabled' : '' ?>>🎬 <?= __('adm_cat_vid_wan') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?></option>
                                            <option value="sys_llm">⚙️ <?= __('adm_cat_hid_txt') ?></option>
                                            <option value="sys_vision">👁️‍🗨️ <?= __('adm_cat_hid_vis') ?></option>
                                            <option value="sys_refiner">🛠️ <?= __('adm_cat_hid_ref') ?? 'Refinador / Rostros (DiT)' ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-warning fw-bold"><?= __('tit_pan_nivel') ?></label>
                                        <select class="form-select bg-dark text-light border-warning" id="modNivel">
                                            <option value="usuario">👤 <?= __('adm_lvl_user') ?></option>
                                            <option value="avanzado" <?= !$is_pro ? 'disabled' : '' ?>>⭐ <?= __('adm_lvl_adv') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" id="btnSubmitModelo" class="btn btn-success w-100 fw-bold shadow px-0" onclick="guardarModeloBD()" title="<?= __('adm_btn_save_title') ?>"><i class="bi bi-save"></i></button>
                                    </div>

                                    <!-- ============================================================== -->
                                    <!-- NUEVO BLOQUE: TEXT ENCODERS Y VAE INDEPENDIENTES (DATALISTS) -->
                                    <!-- ============================================================== -->
                                    <div class="col-12 mt-3">
                                        <div class="p-2 border border-secondary rounded shadow-sm" style="background-color: rgba(25, 135, 84, 0.05);">
                                            <div class="d-flex justify-content-between">
                                                <label class="small text-success fw-bold mb-2"><i class="bi bi-cpu-fill"></i> <?= __('tit_pan_arch_avanzados') ?? 'Arquitectura Desmembrada (Dejar en blanco para Default)' ?></label>
                                                <div class="form-check form-switch m-0 pb-1">
                                                    <input class="form-check-input border-success" type="checkbox" id="modUnbundled" value="1">
                                                    <label class="form-check-label small text-info fw-bold ms-1" for="modUnbundled">
                                                        <i class="bi bi-puzzle"></i> <?= __('adm_lbl_unbundled') ?? 'UNET Puro' ?>
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="row g-2">
                                                <div class="col-md-2">
                                                    <label class="small text-secondary fw-bold"><?= __('tit_pan_vae') ?></label>
                                                    <input type="text" list="dl_vaes" id="modVae" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_auto') ?>" autocomplete="off">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="small text-secondary fw-bold"><?= __('tit_pan_te1') ?></label>
                                                    <input type="text" list="dl_clips" id="modTE1" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_auto') ?>" autocomplete="off">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="small text-secondary fw-bold"><?= __('tit_pan_te2') ?></label>
                                                    <input type="text" list="dl_clips" id="modTE2" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_auto') ?>" autocomplete="off">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="small text-secondary fw-bold"><?= __('tit_pan_te3') ?></label>
                                                    <input type="text" list="dl_clips" id="modTE3" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_auto') ?>" autocomplete="off">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="small text-secondary fw-bold"><?= __('tit_pan_te4') ?></label>
                                                    <input type="text" list="dl_clips" id="modTE4" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_auto') ?>" autocomplete="off">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- ============================================================== -->
                                    
                                    <div class="col-12 mt-3 text-start">
                                        <label class="small text-info fw-bold mb-1"><i class="bi bi-tags-fill"></i> <?= __('tit_pan_tags') ?? 'Tags Semánticos (Auto-Arquitecto)' ?></label>
                                        <input type="text" class="form-control bg-dark text-warning border-info" id="modTags" placeholder="<?= __('adm_ph_tags') ?? 'Ej: fotorealismo, anime, tipografia, logos...' ?>">
                                        <small class="text-muted d-block mt-1" style="font-size: 0.7rem;"><?= __('adm_hlp_tags') ?? 'Palabras clave separadas por comas. El LLM leerá esto para saber cuándo debe auto-seleccionar este modelo.' ?></small>
                                    </div>
                                    
                                    <div class="col-12 mt-3 text-start">
                                        <label class="small text-success fw-bold mb-1"><i class="bi bi-robot"></i> <?= __('tit_pan_reglas_arq') ?></label>
                                        <textarea class="form-control bg-dark text-light border-success" id="modReglasArq" rows="2" placeholder="<?= __('adm_ph_reglas_arq') ?>"></textarea>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> <?= __('adm_hlp_reglas_arq') ?></small>
                                    </div>
                                    
                                    <div class="col-12 mt-2 mb-3 text-start">
                                        <label class="small text-danger fw-bold mb-1"><i class="bi bi-dash-circle"></i> <?= __('adm_lbl_default_neg') ?></label>
                                        <textarea class="form-control bg-dark text-light border-secondary" id="modDefaultNegative" rows="2" placeholder="<?= __('adm_ph_default_neg') ?>"></textarea>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> <?= __('adm_desc_default_neg') ?></small>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <div class="p-2 border border-secondary rounded shadow-sm" style="background-color: rgba(13, 202, 240, 0.05);">
                                            <label class="small text-info fw-bold mb-2"><i class="bi bi-sliders"></i> <?= __('adm_lbl_params_def') ?></label>
                                            <div class="row g-2">
                                                <div class="col-md-1">
                                                    <input type="number" id="modSteps" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_steps') ?>" title="Steps">
                                                </div>
                                                <div class="col-md-1">
                                                    <input type="number" id="modCfg" step="0.1" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_cfg') ?>" title="CFG Scale">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="number" id="modDenoise" step="0.01" min="0.00" max="1.00" class="form-control form-control-sm bg-dark text-info border-info" placeholder="<?= __('adm_ph_denoise') ?? 'Denoise (0.75)' ?>" title="<?= __('adm_hlp_denoise') ?? 'Fuerza de alteración en Img2Img (0.00 a 1.00)' ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" id="modKeepAlive" class="form-control form-control-sm bg-dark text-warning border-warning" placeholder="<?= __('adm_ph_keepalive') ?? 'Keep Alive (10m, 0, -1)' ?>" title="<?= __('adm_hlp_keepalive') ?? 'Tiempo en VRAM (0 = Descargar rápido, -1 = Infinito)' ?>">
                                                </div>
                                                
                                                <!-- LISTA COMPLETA DE SAMPLERS RECUPERADA -->
                                                <div class="col-md-3">
                                                    <select id="modSampler" class="form-select form-select-sm bg-dark text-light border-secondary">
                                                        <option value=""><?= __('adm_ph_sampler') ?? 'Sampler (Auto)' ?></option>
                                                        <optgroup label="<?= __('adm_opt_estandar') ?? 'Estándar' ?>">
                                                            <option value="euler">euler</option>
                                                            <option value="euler_ancestral">euler_ancestral</option>
                                                            <option value="dpmpp_2m">dpmpp_2m</option>
                                                            <option value="dpmpp_2m_sde_gpu">dpmpp_2m_sde_gpu</option>
                                                            <option value="dpmpp_3m_sde_gpu">dpmpp_3m_sde_gpu</option>
                                                            <option value="lcm">lcm</option>
                                                        </optgroup>
                                                        <optgroup label="<?= __('adm_opt_avanzados') ?? 'Avanzados' ?>">
                                                            <option value="euler_cfg_pp">euler_cfg_pp</option>
                                                            <option value="euler_ancestral_cfg_pp">euler_ancestral_cfg_pp</option>
                                                            <option value="heun">heun</option>
                                                            <option value="heunpp2">heunpp2</option>
                                                            <option value="exp_heun_2_x0">exp_heun_2_x0</option>
                                                            <option value="exp_heun_2_x0_sde">exp_heun_2_x0_sde</option>
                                                            <option value="dpm_2">dpm_2</option>
                                                            <option value="dpm_2_ancestral">dpm_2_ancestral</option>
                                                            <option value="lms">lms</option>
                                                            <option value="dpm_fast">dpm_fast</option>
                                                            <option value="dpm_adaptive">dpm_adaptive</option>
                                                            <option value="dpmpp_2s_ancestral">dpmpp_2s_ancestral</option>
                                                            <option value="dpmpp_2s_ancestral_cfg_pp">dpmpp_2s_ancestral_cfg_pp</option>
                                                            <option value="dpmpp_sde">dpmpp_sde</option>
                                                            <option value="dpmpp_sde_gpu">dpmpp_sde_gpu</option>
                                                            <option value="dpmpp_2m_cfg_pp">dpmpp_2m_cfg_pp</option>
                                                            <option value="dpmpp_2m_sde">dpmpp_2m_sde</option>
                                                            <option value="dpmpp_2m_sde_heun">dpmpp_2m_sde_heun</option>
                                                            <option value="dpmpp_2m_sde_heun_gpu">dpmpp_2m_sde_heun_gpu</option>
                                                            <option value="dpmpp_3m_sde">dpmpp_3m_sde</option>
                                                            <option value="ddpm">ddpm</option>
                                                            <option value="ipndm">ipndm</option>
                                                            <option value="ipndm_v">ipndm_v</option>
                                                            <option value="deis">deis</option>
                                                            <option value="res_multistep">res_multistep</option>
                                                            <option value="res_multistep_cfg_pp">res_multistep_cfg_pp</option>
                                                            <option value="res_multistep_ancestral">res_multistep_ancestral</option>
                                                            <option value="res_multistep_ancestral_cfg_pp">res_multistep_ancestral_cfg_pp</option>
                                                            <option value="gradient_estimation">gradient_estimation</option>
                                                            <option value="gradient_estimation_cfg_pp">gradient_estimation_cfg_pp</option>
                                                            <option value="er_sde">er_sde</option>
                                                            <option value="seeds_2">seeds_2</option>
                                                            <option value="seeds_3">seeds_3</option>
                                                            <option value="sa_solver">sa_solver</option>
                                                            <option value="sa_solver_pece">sa_solver_pece</option>
                                                            <option value="ddim">ddim</option>
                                                            <option value="uni_pc">uni_pc</option>
                                                            <option value="uni_pc_bh2">uni_pc_bh2</option>
                                                            <option value="legacy_rk">legacy_rk</option>
                                                            <option value="rk">rk</option>
                                                            <option value="rk_beta">rk_beta</option>
                                                            <option value="deis_3m_ode">deis_3m_ode</option>
                                                            <option value="deis_2m_ode">deis_2m_ode</option>
                                                            <option value="deis_3m">deis_3m</option>
                                                            <option value="deis_2m">deis_2m</option>
                                                            <option value="res_6s_ode">res_6s_ode</option>
                                                            <option value="res_5s_ode">res_5s_ode</option>
                                                            <option value="res_3s_ode">res_3s_ode</option>
                                                            <option value="res_2s_ode">res_2s_ode</option>
                                                            <option value="res_3m_ode">res_3m_ode</option>
                                                            <option value="res_2m_ode">res_2m_ode</option>
                                                            <option value="res_6s">res_6s</option>
                                                            <option value="res_5s">res_5s</option>
                                                            <option value="res_3s">res_3s</option>
                                                            <option value="res_2s">res_2s</option>
                                                            <option value="res_3m">res_3m</option>
                                                            <option value="res_2m">res_2m</option>
                                                        </optgroup>
                                                    </select>
                                                </div>

                                                <!-- LISTA COMPLETA DE SCHEDULERS RECUPERADA -->
                                                <div class="col-md-3">
                                                    <select id="modScheduler" class="form-select form-select-sm bg-dark text-light border-secondary">
                                                        <option value=""><?= __('adm_ph_scheduler') ?? 'Scheduler (Auto)' ?></option>
                                                        <optgroup label="<?= __('adm_opt_estandar') ?? 'Estándar' ?>">
                                                            <option value="beta">beta</option>
                                                            <option value="exponential">exponential</option>
                                                            <option value="karras">karras</option>
                                                            <option value="simple">simple</option>
                                                            <option value="sgm_uniform">sgm_uniform</option>
                                                        </optgroup>
                                                        <optgroup label="<?= __('adm_opt_avanzados') ?? 'Avanzados' ?>">
                                                            <option value="linear_quadratic">linear_quadratic</option>
                                                            <option value="beta57">beta57</option>
                                                            <option value="bong_tangent">bong_tangent</option>
                                                            <option value="kl_optimal">kl_optimal</option>
                                                            <option value="normal">normal</option>
                                                            <option value="ddim_uniform">ddim_uniform</option>
                                                        </optgroup>
                                                    </select>
                                                </div>
                                            </div>
                                            <small class="text-muted mt-1 d-block" style="font-size: 0.7rem;"><i class="bi bi-info-circle"></i> <?= __('adm_desc_params_def') ?></small>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-dark table-hover table-bordered border-secondary text-center align-middle m-0">
                                <thead class="table-active text-success" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th><?= __('tit_pan_id') ?></th>
                                        <th><?= __('tit_pan_nomenu') ?></th>
                                        <th><?= __('tit_pan_arxsist') ?></th>
                                        <th><?= __('tit_pan_motor') ?></th>
                                        <th><?= __('tit_pan_categ') ?></th>
                                        <th><?= __('tit_pan_parametros') ?? 'Parámetros' ?></th>
                                        <th><?= __('tit_pan_unbundled') ?></th>
                                        <th><?= __('tit_pan_nivel') ?></th>
                                        <th><?= __('tit_pan_estado') ?></th>
                                        <th><?= __('tit_pan_accion') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tablaModelosBody"></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- PESTAÑA PROMPTS SE MANTIENE EXACTAMENTE IGUAL -->
                    <div class="tab-pane fade" id="tab-prompts" role="tabpanel">
                        <!-- Tu código original tab-prompts intacto -->
                    </div>
                    
                    <!-- PESTAÑA IDIOMAS SE MANTIENE EXACTAMENTE IGUAL -->
                    <div class="tab-pane fade" id="tab-idiomas" role="tabpanel">
                        <!-- Tu código original tab-idiomas intacto -->
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>