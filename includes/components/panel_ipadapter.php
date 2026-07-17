    <div class="param-group shadow-sm border-primary mb-3" id="ipAdapterBlock" style="display: none; border-color: rgba(13, 110, 253, 0.4) !important; background: rgba(13, 110, 253, 0.05);">
		<div class="d-flex justify-content-between align-items-center">
			<label class="small text-primary fw-bold mb-0"><i class="bi bi-images me-1"></i> <?= __('tit_ipadapter') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?></label>
			<div class="form-check form-switch m-0">
				<input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="ipAdapterToggle" onchange="toggleIpAdapterUI()" <?= !$is_pro ? 'disabled' : '' ?>>
			</div>
		</div>
		<div id="ipAdapterUI" class="d-none mt-3 text-center">
			<button type="button" class="btn btn-sm btn-outline-primary w-100 mb-2" onclick="document.getElementById('ipaInput').click()"><i class="bi bi-upload"></i> <?= __('btn_subirireferencia') ?></button>
			<input type="file" id="ipaInput" accept="image/*" class="d-none">
			
			<div class="row g-2 mt-2 text-start border-top border-primary pt-2" style="border-color: rgba(13, 110, 253, 0.2) !important;">
				<div class="col-md-12">
					<label class="small text-secondary fw-bold"><?= __('tit_ip_model') ?></label>
					<select class="form-select form-select-sm bg-dark text-light border-primary pref-track" id="ipaModel">
						<!--option value="flux-ip-adapter.safetensors">💎 < ?= __('ipa_mod_flux') ?></option-->
						
						<option value="ip-adapter-plus_sdxl_vit-h.safetensors" selected><?= __('ipa_mod_sdxl_plus') ?></option>
						<option value="ip-adapter-plus-face_sdxl_vit-h.safetensors"><?= __('ipa_mod_sdxl_face') ?></option>
						
						<option value="ip-adapter_sd15.safetensors"><?= __('ipa_mod_sd15_std') ?></option>
						<option value="ip-adapter-plus_sd15.safetensors"><?= __('ipa_mod_sd15_plus') ?></option>
					</select>
				</div>
				
				<div class="col-md-6 mt-2">
					<label class="small text-secondary fw-bold"><?= __('tit_ip_modo') ?></label>
					<select class="form-select form-select-sm bg-dark text-light border-primary pref-track" id="ipaWeightType">
						<option value="linear" selected><?= __('ipa_wt_linear') ?? 'Estándar (Lineal)' ?></option>
						<option value="ease in"><?= __('ipa_wt_ease_in') ?? 'Entrada Suave (Ease In)' ?></option>
						<option value="ease out"><?= __('ipa_wt_ease_out') ?? 'Salida Suave (Ease Out)' ?></option>
						<option value="style transfer"><?= __('ipa_wt_style') ?? 'Solo Estilo (Más Creativo)' ?></option>
						<option value="composition"><?= __('ipa_wt_comp') ?? 'Solo Composición' ?></option>
						<option value="weak input"><?= __('ipa_wt_weak_in') ?? 'Forma libre, Color Fiel' ?></option>
					</select>
				</div>
				<div class="col-md-6 mt-2">
					<label class="small text-secondary fw-bold"><?= __('tit_ip_ruido') ?></label>
					<input type="range" class="form-range pref-track" id="ipaNoise" min="0.0" max="1.0" step="0.05" value="0.0" oninput="document.getElementById('ipaNoiseLabel').innerText = this.value;">
					<div class="text-end small text-light mt-n2" id="ipaNoiseLabel">0.0</div>
				</div>

				<div class="col-md-4 mt-2">
					<label class="text-secondary small fw-bold"><?= __('tit_fuerza') ?>: <span id="ipaWeightLabel" class="text-light">0.8</span></label>
					<input type="range" class="form-range pref-track" id="ipaWeight" min="0.0" max="2.0" step="0.05" value="0.8" oninput="document.getElementById('ipaWeightLabel').innerText = this.value;">
				</div>
				<div class="col-md-4 mt-2">
					<label class="text-secondary small fw-bold"><?= __('tit_inicio') ?> %: <span id="ipaStartLabel" class="text-light">0.0</span></label>
					<input type="range" class="form-range pref-track" id="ipaStart" min="0.0" max="1.0" step="0.05" value="0.0" oninput="document.getElementById('ipaStartLabel').innerText = this.value;">
				</div>
				<div class="col-md-4 mt-2">
					<label class="text-secondary small fw-bold"><?= __('tit_fin') ?> %: <span id="ipaEndLabel" class="text-light">1.0</span></label>
					<input type="range" class="form-range pref-track" id="ipaEnd" min="0.0" max="1.0" step="0.05" value="1.0" oninput="document.getElementById('ipaEndLabel').innerText = this.value;">
				</div>
			</div>

			<div id="ipaPreviewContainer" class="d-none mt-2">
				<img id="ipaPreview" src="" class="img-fluid rounded border border-primary shadow-sm" style="max-height: 120px;">
				<div class="mt-2 d-flex gap-2">
					<button type="button" class="btn btn-sm btn-outline-primary flex-grow-1" onclick="autoCaptionReference('ipaPreview', this)"><i class="bi bi-magic"></i> <?= __('btn_extraeprompt') ?></button>
					<button type="button" class="btn btn-sm btn-danger" onclick="clearIpAdapter()"><i class="bi bi-trash"></i></button>
				</div>
			</div>
		</div>
	</div>