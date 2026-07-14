	<div class="param-group shadow-sm border-primary mb-3" id="hiresBlock" style="display: none; border-color: rgba(13, 110, 253, 0.4) !important; background: rgba(13, 110, 253, 0.05);">
		<div class="d-flex justify-content-between align-items-center">
			<label class="small text-primary fw-bold mb-0">
				<i class="bi bi-arrows-angle-expand"></i> <?= __('tit_upscale') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
			</label>
			<div class="form-check form-switch m-0">
				<input class="form-check-input pref-track" type="checkbox" id="hiresToggle" style="cursor: pointer;" onchange="document.getElementById('upscaleOptions').classList.toggle('d-none', !this.checked)" <?= !$is_pro ? 'disabled' : '' ?>>
			</div>
		</div>
		<div id="upscaleOptions" class="d-none mt-3 pt-3 border-top border-primary" style="border-color: rgba(13, 110, 253, 0.2) !important;">
			<div class="row g-2">
				<div class="col-md-6">
					<label class="small text-secondary fw-bold"><?= __('tit_ups_model') ?></label>
					<select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="upscaleModelSelector">
						<option value=""><?= __('opt_loading_models') ?></option>
					</select>
				</div>
				<div class="col-md-6">
					<label class="small text-secondary fw-bold"><?= __('tit_ups_factor') ?></label>
					<select class="form-select form-select-sm bg-dark text-light border-secondary pref-track" id="upscaleFactor">
						<option value="1.5"><?= __('ups_fac_15') ?></option>
						<option value="2.0"><?= __('ups_fac_20') ?></option>
					</select>
				</div>
			</div>
		</div>
	</div>