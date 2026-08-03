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
											<option value="vision">👁️ <?= __('adm_cat_vision') ?></option>
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
									<div class="col-md-3">
										<label class="small text-secondary fw-bold"><?= __('tit_pan_arxex') ?></label>
										<input type="text" class="form-control bg-dark text-light border-secondary" id="modArchivo" placeholder="<?= __('adm_ph_arx') ?>">
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
											<option value="vision">👁️ <?= __('adm_cat_vis_ana') ?></option>
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
									
									<!-- CHECKBOX DE MODELO DESMEMBRADO (UNBUNDLED) -->
                                    <div class="col-12 mt-2 text-start">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input border-success" type="checkbox" id="modUnbundled" value="1">
                                            <label class="form-check-label small text-info fw-bold ms-1" for="modUnbundled">
                                                <i class="bi bi-puzzle"></i> <?= __('adm_lbl_unbundled') ?? 'Modelo Desmembrado (Solo UNET. Usa nodos separados para CLIP/VAE)' ?>
                                            </label>
                                        </div>
                                    </div>

                                    <!-- NUEVOS CAMPOS: PARÁMETROS POR DEFECTO DEL MOTOR -->
									<div class="col-12 mt-3">
										<div class="p-2 border border-secondary rounded shadow-sm" style="background-color: rgba(13, 202, 240, 0.05);">
											<label class="small text-info fw-bold mb-2"><i class="bi bi-sliders"></i> <?= __('adm_lbl_params_def') ?></label>
											<div class="row g-2">
												<div class="col-md-3">
													<input type="number" id="modSteps" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_steps') ?>">
												</div>
												<div class="col-md-3">
													<input type="number" id="modCfg" step="0.1" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="<?= __('adm_ph_cfg') ?>">
												</div>
												<div class="col-md-3">
                                                    <select id="modSampler" class="form-select form-select-sm bg-dark text-light border-secondary">
                                                        <option value=""><?= __('adm_ph_sampler') ?? 'Sampler (Automático)' ?></option>
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
                                                <div class="col-md-3">
                                                    <select id="modScheduler" class="form-select form-select-sm bg-dark text-light border-secondary">
                                                        <option value=""><?= __('adm_ph_scheduler') ?? 'Scheduler (Automático)' ?></option>
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
                    
                    <div class="tab-pane fade" id="tab-prompts" role="tabpanel">
                        <div class="card bg-dark border-secondary mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="text-info fw-bold m-0"><i class="bi bi-plus-circle"></i> <?= __('tit_pan_tit_prompt') ?></h6>
                                    
                                    <select class="form-select form-select-sm bg-dark text-light border-secondary w-auto" onchange="filtrarTablaAdmin('tablaPromptsBody', 2, this.value)">
										<option value=""><?= __('adm_type_all') ?></option>
										
										<option value="<?= __('flt_seed') ?? 'Semilla' ?>">🌱 <?= __('adm_type_seeds') ?></option>
										<option value="<?= __('flt_random') ?? 'Aleatorio' ?>">🎲 <?= __('adm_type_randoms') ?></option>
										<option value="<?= __('flt_persona') ?? 'Personalidad' ?>">🗣️ <?= __('adm_type_personas') ?></option>
										
										<option value="<?= __('flt_assistant') ?? 'Asistente' ?>">🤖 <?= __('adm_pr_chat_def') ?? 'Asistente (Defecto)' ?></option>
										<option value="<?= __('flt_system') ?? 'Sistema' ?>">💬 <?= __('adm_pr_chat_sys') ?? 'Sistema Chat Directo' ?></option>
										
										<option value="<?= __('flt_rule') ?? 'Reglas' ?>">⚙️ <?= __('adm_type_rules') ?></option>
										<option value="<?= __('flt_style') ?? 'Estilo' ?>">🎨 <?= __('adm_type_styles') ?></option>
										<option value="<?= __('flt_analyst') ?? 'Analista' ?>">👁️ <?= __('adm_type_analysts') ?></option>
										<option value="<?= __('flt_enhancer') ?? 'Amplificador' ?>">✨ <?= __('adm_type_amps') ?></option>
									</select>
                                </div>
                                <form id="formNuevoPrompt" class="row g-2">
                                    <div class="col-md-3">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_titulo') ?></label>
                                        <input type="text" class="form-control bg-dark text-light border-secondary" id="prTitulo" placeholder="<?= __('adm_ph_pr_tit') ?>">
                                    </div>
									<div class="col-md-3">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_tipo_pr') ?></label>
									<select class="form-select bg-dark text-light border-secondary" id="prTipo">
										<option value="seed_image">🌱 <?= __('adm_pr_sd_img') ?></option>
										<option value="seed_chat">🌱 <?= __('adm_pr_sd_chat') ?></option>
										
										<option value="seed_video" <?= !$is_pro ? 'disabled' : '' ?>>
											🌱 <?= __('adm_pr_sd_vid') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?>
										</option>
										
										<option value="random_prompt">🎲 <?= __('adm_pr_rnd_char') ?></option>
										
										<option value="chat_personality" <?= !$is_pro ? 'disabled' : '' ?>>
											🗣️ <?= __('adm_pr_chat_pers') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?>
										</option>
										
										<option value="chat_default">🤖 <?= __('adm_pr_chat_def') ?? 'Asistente Chat (Defecto)' ?></option>
										<option value="sys_prompt_chat">💬 <?= __('adm_pr_chat_sys') ?? 'Sistema Chat Directo' ?></option>
										<option value="enhance_prompt">✨ <?= __('adm_pr_amp_trad') ?></option>
										<option value="vision_analyst">👁️ <?= __('adm_pr_vis_ana') ?></option>
										<option value="core_architect">⚙️ <?= __('adm_pr_core_arq') ?></option>
										<option value="estilo_llm">📝 <?= __('adm_pr_sty_txt') ?></option>
										<option value="estilo_sd15">🎨 <?= __('adm_pr_sty_sd15') ?></option>
										<option value="estilo_sdxl">⚡ <?= __('adm_pr_sty_sdxl') ?></option>
										
										<option value="estilo_flux" <?= !$is_pro ? 'disabled' : '' ?>>
											💎 <?= __('adm_pr_sty_flux') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?>
										</option>
										<option value="estilo_video" <?= !$is_pro ? 'disabled' : '' ?>>
											🎬 <?= __('adm_pr_sty_vid') ?> <?= !$is_pro ? '🔒 ' . __('adm_lbl_pro') : '' ?>
										</option>
									</select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_idioma') ?></label>
                                        <select class="form-select bg-dark text-light border-secondary" id="prIdioma">
                                            <?php
                                            // Escaneamos la carpeta de idiomas para montar el desplegable automáticamente
                                            $lang_dir_admin = __DIR__ . '/../../lang/';
                                            
                                            // 1. Leemos los idiomas personalizados del JSON (Si existe)
                                            $json_path_admin = $lang_dir_admin . 'idiomas_meta.json';
                                            $nombres_json_admin = [];
                                            if (file_exists($json_path_admin)) {
                                                $nombres_json_admin = json_decode(file_get_contents($json_path_admin), true) ?? [];
                                            }

                                            if (is_dir($lang_dir_admin)) {
                                                $archivos_admin = glob($lang_dir_admin . '*.php');
                                                
                                                // 2. Diccionario visual base (igual que el del menú superior)
                                                $nombres_base_admin = [
                                                    'es' => '🇪🇸 Español',
                                                    'en' => '🇬🇧 English',
                                                    'ca' => '<img src="assets/img/ca.svg" alt="CAT" style="width: 20px; height: 20px; border-radius: 2px; vertical-align: middle; margin-right: 6px; margin-top: -2px;"> Català',
                                                    'fr' => '🇫🇷 Français',
                                                    'it' => '🇮🇹 Italiano',
                                                    'de' => '🇩🇪 Deutsch',
                                                    'pt' => '🇵🇹 Português'
                                                ];

                                                // 3. Fusionamos los diccionarios. El JSON manda si hay coincidencias o idiomas nuevos.
                                                $nombres_vis_admin = array_merge($nombres_base_admin, $nombres_json_admin);

                                                foreach ($archivos_admin as $archivo_lang) {
                                                    $iso = basename($archivo_lang, '.php');
                                                    // Si está en el diccionario fusionado usamos su nombre, sino ponemos las siglas en mayúsculas
                                                    $nombre = $nombres_vis_admin[$iso] ?? strtoupper($iso);
                                                    
                                                    // Usamos strip_tags() para el texto visible nativo y data-content para plugins
                                                    echo '<option value="' . htmlspecialchars($iso) . '" data-content="' . htmlspecialchars($nombre) . '">' . strip_tags($nombre) . '</option>';
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_parametros') ?></label>
                                        <input type="text" class="form-control bg-dark text-info border-secondary" id="prParams" placeholder='<?= __('adm_ph_params') ?>'>
                                    </div>
                                    <div class="col-12 mt-2">
                                        <label class="small text-secondary fw-bold"><?= __('tit_pan_txtprompt') ?></label>
                                        <textarea class="form-control bg-dark text-light border-secondary" id="prTexto" rows="3" placeholder="<?= __('adm_ph_pr_txt') ?>"></textarea>
                                    </div>
                                    <div class="col-12 text-end mt-2">
                                        <button type="button" class="btn btn-info fw-bold shadow text-dark" onclick="guardarPromptBD()"><i class="bi bi-save"></i> <?= __('adm_btn_save_pr') ?></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-dark table-hover table-bordered border-secondary text-center align-middle m-0">
                                <thead class="table-active text-info text-nowrap" style="position: sticky; top: 0; z-index: 1;">
									<tr>
										<th style="width: 5%;"><?= __('tit_pan_id') ?></th>
										<th class="text-wrap" style="width: 40%; min-width: 250px;"><?= __('tit_pan_titulo') ?></th>
										<th><?= __('tit_pan_tipo') ?></th>
										<th><?= __('tit_pan_idioma') ?></th>
										<th><?= __('tit_pan_param') ?></th>
										<th><?= __('tit_pan_estado') ?></th>
										<th><?= __('tit_pan_accion') ?></th>
									</tr>
								</thead>
                                <tbody id="tablaPromptsBody"></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="tab-idiomas" role="tabpanel">
                        <div class="card bg-dark border-secondary mb-4 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="text-warning fw-bold m-0"><i class="bi bi-plus-circle"></i> <?= __('tit_pan_gedioma') ?></h6>
                                    <button class="btn btn-sm btn-warning fw-bold shadow-sm text-dark" onclick="crearNuevoIdioma()">
                                        <i class="bi bi-file-earmark-plus-fill"></i> <?= __('adm_btn_new_lang') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-dark table-hover table-bordered border-secondary text-center align-middle m-0">
                                <thead class="table-active text-warning" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width: 15%;"><?= __('tit_pan_codiso') ?></th>
                                        <th style="width: 55%;"><?= __('tit_pan_descidioma') ?></th>
                                        <th style="width: 30%;"><?= __('tit_pan_accion') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="tablaIdiomasBody">
                                    <tr>
                                        <td colspan="3" class="text-muted text-center py-4">
                                            <?= __('adm_msg_load_langs') ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>