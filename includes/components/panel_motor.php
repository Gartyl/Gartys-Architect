     <div class="param-group shadow-sm border-info mb-3" id="advancedSettingsBlock" style="display: none; border-color: rgba(13, 202, 240, 0.4) !important; background: rgba(13, 202, 240, 0.05);">
         <div class="d-flex justify-content-between align-items-center">
             <label class="small text-info fw-bold mb-0"><i class="bi bi-sliders me-1"></i> <?= __('tit_ajus_motor') ?></label>
             <div class="d-flex align-items-center gap-3">
                 <small class="text-muted d-none d-md-block"><i class="bi bi-info-circle"></i> <?= __('txt_arrast_meta') ?></small>
                 <div class="form-check form-switch m-0">
                     <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="engineToggle" onchange="document.getElementById('engineUI').classList.toggle('d-none', !this.checked)">
                 </div>
             </div>
         </div>
         
         <div id="engineUI" class="d-none mt-3 pt-3 border-top border-info" style="border-color: rgba(13, 202, 240, 0.2) !important;">
             <div class="row g-2">
                 <div class="col-md-2 mb-2">
                     <label class="small text-secondary fw-bold mb-1">STEPS</label>
                     <input type="number" class="form-control form-control-sm bg-dark text-light border-secondary pref-track" id="stepsInput" value="30" min="1" max="150">
                 </div>
                
				 <div class="col-md-2 mb-2">
                     <label class="small text-secondary fw-bold mb-1" id="cfgLabel">CFG SCALE</label>
                     <input type="number" class="form-control form-control-sm bg-dark text-light border-secondary pref-track" id="cfgInput" value="5.0" min="0.1" max="99.9" step="0.1">
                 </div>
                 
                 <div class="col-md-3 mb-2" id="manualResBoxesBlock">
                     <label class="small text-secondary fw-bold mb-1"><?= __('tit_proporcion') ?> (W x H)</label>
                     <div class="input-group input-group-sm shadow-sm">
                         <input type="number" class="form-control bg-dark text-light border-secondary text-center px-1" id="imgWidth" min="256" step="8" title="<?= __('tit_ancho') ?? 'Ancho' ?>" oninput="if(typeof desmarcarProp==='function') desmarcarProp()">
                         <span class="input-group-text bg-dark border-secondary text-secondary px-1 border-start-0 border-end-0">x</span>
                         <input type="number" class="form-control bg-dark text-light border-secondary text-center px-1" id="imgHeight" min="256" step="8" title="<?= __('tit_alto') ?? 'Alto' ?>" oninput="if(typeof desmarcarProp==='function') desmarcarProp()">
                     </div>
                 </div>
                 
                 <div class="w-100 d-none d-md-block m-0"></div>

                 <div class="col-md-3 mb-2">
					<label class="small text-secondary fw-bold mb-1"><?= __('tit_sampler') ?></label>
					<select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="samplerInput">
						<option value="euler">euler</option>
						<option value="euler_ancestral">euler_ancestral</option>
						<option value="dpmpp_2m">dpmpp_2m</option>
						<option value="dpmpp_2m_sde_gpu">dpmpp_2m_sde_gpu</option>
						<option value="dpmpp_3m_sde_gpu">dpmpp_3m_sde_gpu</option>
						<option value="lcm">lcm</option>

						<option value="euler_cfg_pp" class="adv-sampler d-none">euler_cfg_pp</option>
						<option value="euler_ancestral_cfg_pp" class="adv-sampler d-none">euler_ancestral_cfg_pp</option>
						<option value="heun" class="adv-sampler d-none">heun</option>
						<option value="heunpp2" class="adv-sampler d-none">heunpp2</option>
						<option value="exp_heun_2_x0" class="adv-sampler d-none">exp_heun_2_x0</option>
						<option value="exp_heun_2_x0_sde" class="adv-sampler d-none">exp_heun_2_x0_sde</option>
						<option value="dpm_2" class="adv-sampler d-none">dpm_2</option>
						<option value="dpm_2_ancestral" class="adv-sampler d-none">dpm_2_ancestral</option>
						<option value="lms" class="adv-sampler d-none">lms</option>
						<option value="dpm_fast" class="adv-sampler d-none">dpm_fast</option>
						<option value="dpm_adaptive" class="adv-sampler d-none">dpm_adaptive</option>
						<option value="dpmpp_2s_ancestral" class="adv-sampler d-none">dpmpp_2s_ancestral</option>
						<option value="dpmpp_2s_ancestral_cfg_pp" class="adv-sampler d-none">dpmpp_2s_ancestral_cfg_pp</option>
						<option value="dpmpp_sde" class="adv-sampler d-none">dpmpp_sde</option>
						<option value="dpmpp_sde_gpu" class="adv-sampler d-none">dpmpp_sde_gpu</option>
						<option value="dpmpp_2m_cfg_pp" class="adv-sampler d-none">dpmpp_2m_cfg_pp</option>
						<option value="dpmpp_2m_sde" class="adv-sampler d-none">dpmpp_2m_sde</option>
						<option value="dpmpp_2m_sde_heun" class="adv-sampler d-none">dpmpp_2m_sde_heun</option>
						<option value="dpmpp_2m_sde_heun_gpu" class="adv-sampler d-none">dpmpp_2m_sde_heun_gpu</option>
						<option value="dpmpp_3m_sde" class="adv-sampler d-none">dpmpp_3m_sde</option>
						<option value="ddpm" class="adv-sampler d-none">ddpm</option>
						<option value="ipndm" class="adv-sampler d-none">ipndm</option>
						<option value="ipndm_v" class="adv-sampler d-none">ipndm_v</option>
						<option value="deis" class="adv-sampler d-none">deis</option>
						<option value="res_multistep" class="adv-sampler d-none">res_multistep</option>
						<option value="res_multistep_cfg_pp" class="adv-sampler d-none">res_multistep_cfg_pp</option>
						<option value="res_multistep_ancestral" class="adv-sampler d-none">res_multistep_ancestral</option>
						<option value="res_multistep_ancestral_cfg_pp" class="adv-sampler d-none">res_multistep_ancestral_cfg_pp</option>
						<option value="gradient_estimation" class="adv-sampler d-none">gradient_estimation</option>
						<option value="gradient_estimation_cfg_pp" class="adv-sampler d-none">gradient_estimation_cfg_pp</option>
						<option value="er_sde" class="adv-sampler d-none">er_sde</option>
						<option value="seeds_2" class="adv-sampler d-none">seeds_2</option>
						<option value="seeds_3" class="adv-sampler d-none">seeds_3</option>
						<option value="sa_solver" class="adv-sampler d-none">sa_solver</option>
						<option value="sa_solver_pece" class="adv-sampler d-none">sa_solver_pece</option>
						<option value="ddim" class="adv-sampler d-none">ddim</option>
						<option value="uni_pc" class="adv-sampler d-none">uni_pc</option>
						<option value="uni_pc_bh2" class="adv-sampler d-none">uni_pc_bh2</option>
						<option value="legacy_rk" class="adv-sampler d-none">legacy_rk</option>
						<option value="rk" class="adv-sampler d-none">rk</option>
						<option value="rk_beta" class="adv-sampler d-none">rk_beta</option>
						<option value="deis_3m_ode" class="adv-sampler d-none">deis_3m_ode</option>
						<option value="deis_2m_ode" class="adv-sampler d-none">deis_2m_ode</option>
						<option value="deis_3m" class="adv-sampler d-none">deis_3m</option>
						<option value="deis_2m" class="adv-sampler d-none">deis_2m</option>
						<option value="res_6s_ode" class="adv-sampler d-none">res_6s_ode</option>
						<option value="res_5s_ode" class="adv-sampler d-none">res_5s_ode</option>
						<option value="res_3s_ode" class="adv-sampler d-none">res_3s_ode</option>
						<option value="res_2s_ode" class="adv-sampler d-none">res_2s_ode</option>
						<option value="res_3m_ode" class="adv-sampler d-none">res_3m_ode</option>
						<option value="res_2m_ode" class="adv-sampler d-none">res_2m_ode</option>
						<option value="res_6s" class="adv-sampler d-none">res_6s</option>
						<option value="res_5s" class="adv-sampler d-none">res_5s</option>
						<option value="res_3s" class="adv-sampler d-none">res_3s</option>
						<option value="res_2s" class="adv-sampler d-none">res_2s</option>
						<option value="res_3m" class="adv-sampler d-none">res_3m</option>
						<option value="res_2m" class="adv-sampler d-none">res_2m</option>
					</select>
					
					<div class="form-check form-switch mt-1 ms-1">
						<input class="form-check-input" style="cursor: pointer;" type="checkbox" id="toggleSamplers" onchange="document.querySelectorAll('.adv-sampler').forEach(el => this.checked ? el.classList.remove('d-none') : el.classList.add('d-none'))">
						<label class="form-check-label text-secondary" style="font-size: 0.70rem;" for="toggleSamplers"><?= __('lbl_show_all') ?></label>
					</div>
				</div>
				
                 <div class="col-md-3 mb-2">
					<label class="small text-secondary fw-bold mb-1"><?= __('tit_scheduler') ?></label>
					<select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="schedulerInput">
						<option value="beta">beta</option>
						<option value="exponential">exponential</option>
						<option value="karras">karras</option>
						<option value="simple">simple</option>
						<option value="sgm_uniform">sgm_uniform</option>
						
						<option value="linear_quadratic" class="adv-scheduler d-none">linear_quadratic</option>
						<option value="beta57" class="adv-scheduler d-none">beta57</option>
						<option value="bong_tangent" class="adv-scheduler d-none">bong_tangent</option>
						<option value="kl_optimal" class="adv-scheduler d-none">kl_optimal</option>
						<option value="normal" class="adv-scheduler d-none">normal</option>
						<option value="ddim_uniform" class="adv-scheduler d-none">ddim_uniform</option>
					</select>
					
					<div class="form-check form-switch mt-1 ms-1">
						<input class="form-check-input" style="cursor: pointer;" type="checkbox" id="toggleSchedulers" onchange="document.querySelectorAll('.adv-scheduler').forEach(el => this.checked ? el.classList.remove('d-none') : el.classList.add('d-none'))">
						<label class="form-check-label text-secondary" style="font-size: 0.70rem;" for="toggleSchedulers"><?= __('lbl_show_all') ?></label>
					</div>
				</div>
				
                 <div class="col-md-2 mb-2">
                     <label class="small text-secondary fw-bold mb-1"><?= __('tit_semilla') ?></label>
                     <input type="number" class="form-control form-control-sm bg-dark text-light border-secondary pref-track" id="seedInput" value="-1" title="Pon -1 para aleatorio">
                 </div>
                 
				<div class="col-md-4 mb-2">
					<label class="small text-secondary fw-bold mb-1 opacity-0 d-none d-md-block"><?= __('vid_space') ?></label>
					<div class="form-check form-switch mt-1">
						<input class="form-check-input pref-track border-secondary" style="cursor: pointer;" type="checkbox" id="dynThreshToggle">
						<label class="form-check-label small text-info fw-bold" for="dynThreshToggle">
							<i class="bi bi-speedometer"></i> <?= __('vid_dyn_thresh') ?>
						</label>
					</div>
				</div>

				<div class="col-md-12 mt-2" id="videoFramesBlock" style="display:none;">
					<div class="row g-2">
						<div class="col-md-6">
							<div class="d-flex align-items-center gap-2">
								<div class="input-group input-group-sm shadow-sm flex-grow-1">
									<span class="input-group-text bg-dark border-info text-info fw-bold" title="<?= __('vid_format_label') ?>"><i class="bi bi-aspect-ratio"></i></span>
									<select name="video_aspect_ratio" id="video_aspect_ratio" class="form-select bg-dark text-light border-info pref-track" onchange="if(typeof sincResVid==='function') sincResVid()">
										<option value="832x480" selected><?= __('vid_fmt_landscape') ?></option>
										<option value="480x832"><?= __('vid_fmt_portrait') ?></option>
										<option value="640x640"><?= __('vid_fmt_square') ?></option>
									</select>
								</div>
								<div class="input-group input-group-sm shadow-sm" style="width: 140px; flex-shrink: 0;">
									<input type="number" class="form-control bg-dark text-light border-info text-center px-1" id="vidWidth" value="832" step="16" title="<?= __('tit_ancho') ?? 'Ancho' ?>" oninput="if(typeof desmarcarPropVid==='function') desmarcarPropVid()">
									<span class="input-group-text bg-dark border-info text-info px-1">x</span>
									<input type="number" class="form-control bg-dark text-light border-info text-center px-1" id="vidHeight" value="480" step="16" title="<?= __('tit_alto') ?? 'Alto' ?>" oninput="if(typeof desmarcarPropVid==='function') desmarcarPropVid()">
								</div>
							</div>
						</div>

						<div class="col-md-3">
							<div class="input-group input-group-sm shadow-sm" title="Fotogramas por Segundo">
								<span class="input-group-text bg-dark border-info text-info fw-bold"><i class="bi bi-film"></i> FPS</span>
								<select id="videoFpsSelector" class="form-select bg-dark text-light border-info pref-track" onchange="recalcularTiempoVideo()">
									<option value="8">8 (<?= __('vid_fps_8') ?? 'Stop-Motion' ?>)</option>
									<option value="16" selected>16 (<?= __('vid_fps_16') ?? 'Fluido' ?>)</option>
									<option value="24">24 (<?= __('vid_fps_24') ?? 'Cine' ?>)</option>
									<option value="30">30 (<?= __('vid_fps_30') ?? 'TV' ?>)</option>
									<option value="60">60 (<?= __('vid_fps_60') ?? 'Slow-Mo / Real' ?>)</option>
								</select>
							</div>
						</div>

						<div class="col-md-3">
							<div class="input-group input-group-sm shadow-sm">
								<span class="input-group-text bg-dark border-info text-info fw-bold" title="<?= __('vid_duration_label') ?>">
									<i class="bi bi-stopwatch"></i> <span id="videoTimeLabel" class="text-warning ms-1" style="font-size: 0.85rem;">(~2.1s)</span>
								</span>
								<input type="number" class="form-control bg-dark text-light border-info pref-track" id="videoFramesInput" value="33" min="16" max="960" step="1" oninput="recalcularTiempoVideo()">
							</div>
						</div>
						
						<div class="col-md-4 mt-2">
							<div class="input-group input-group-sm shadow-sm">
								<span class="input-group-text bg-dark border-secondary text-secondary"><i class="bi bi-file-earmark-play-fill"></i></span>
								<select id="videoFormat" class="form-select form-select-sm bg-dark text-light border-secondary">
									<option value="image/webp" selected><?= __('vid_out_webp') ?></option>
									<option value="video/h264-mp4"><?= __('vid_out_mp4') ?></option>
								</select>
							</div>
						</div>

						<div class="col-md-8 mt-2 d-flex align-items-center" id="videoOptimizeBlock" style="display: none;">
							<div class="form-check form-switch m-0">
								<input class="form-check-input border-info" type="checkbox" id="videoOptimizeToggle" onchange="recalcularImagenVideo()">
								<label class="form-check-label text-info fw-bold small mt-1 ms-1" for="videoOptimizeToggle">
									<i class="bi bi-aspect-ratio"></i> <?= __('lbl_video_ajuste') ?? 'Auto-Ajustar (Letterbox)' ?>
								</label>
							</div>
						</div>
					</div>
				</div>
             </div>
         </div>
     </div>