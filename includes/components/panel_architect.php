<div id="results" class="mt-4 d-none border-top border-secondary pt-4">
      <div class="alert alert-dark border-secondary shadow-sm mb-4">
          <i class="bi bi-info-circle text-info"></i> <b><?= __('btn_arquitecto') ?>:</b> <?= __('txt_laiahaes') ?>
      </div>
      
      <div id="descriptionArea" class="d-none">
          <label class="text-warning fw-bold small"><?= __('tit_analisis_vis') ?></label>
          <div class="description-box editable-content" id="descriptionContent" contenteditable="true"></div>
          <button type="button" class="btn btn-info text-dark fw-bold w-100 py-2 mb-3" onclick="visionToPrompt(this)">
              <i class="bi bi-magic me-1"></i> <?= __('btn_generarprompt') ?>
          </button>
      </div>
      
	  <div id="promptArea" class="d-none">
		  <label class="text-success fw-bold small"><?= __('tit_promptp') ?></label>
		  <div class="prompt-box position-relative editable-content" style="resize: vertical; overflow: hidden; min-height: 80px; padding: 0;">
			  <button class="btn btn-sm btn-dark position-absolute top-0 end-0 m-2" onclick="copyText('posContent', this)" data-bs-toggle="tooltip" data-bs-placement="left" title="<?= __('btn_title_copy') ?>" style="z-index: 10;"><i class="bi bi-copy"></i></button>
			  <code id="posContent" contenteditable="true" style="display: block; width: 100%; height: 100%; min-height: 80px; padding: 10px 40px 10px 10px; overflow-y: auto; outline: none; background: transparent; border: none;"></code>
		  </div>
	  </div>

      <div id="negativeArea" class="d-none mt-3">
		  <label class="text-danger fw-bold small"><?= __('tit_promptn') ?></label>
		  <div class="prompt-box position-relative editable-content" style="resize: vertical; overflow: hidden; min-height: 80px; padding: 0;">
			  <button class="btn btn-sm btn-dark position-absolute top-0 end-0 m-2" onclick="copyText('negContent', this)" data-bs-toggle="tooltip" data-bs-placement="left" title="<?= __('btn_title_copy') ?>" style="z-index: 10;"><i class="bi bi-copy"></i></button>
			  <code id="negContent" contenteditable="true" style="display: block; width: 100%; height: 100%; min-height: 80px; padding: 10px 40px 10px 10px; overflow-y: auto; outline: none; background: transparent; border: none;"></code>
		  </div>
	  </div>

      <div id="manualNegativeToggleContainer" class="d-none mb-3 text-end">
          <div class="form-check form-switch d-inline-block m-0">
              <input class="form-check-input border-danger" type="checkbox" id="manualNegativeToggle" style="cursor:pointer;" onchange="toggleManualNegative(this.checked)">
              <label class="form-check-label small text-danger fw-bold ms-1" for="manualNegativeToggle" style="cursor:pointer;">
                  <i class="bi bi-plus-slash-minus"></i> <?= __('ctrl_pr_nega') ?>
              </label>
          </div>
      </div>
      
      <!-- BOTÓN TRAS EL ARQUITECTO -->
      <div id="arquitectoActionArea" class="mt-2">
          <button id="gpuArquitectoBtn" class="btn btn-gpu w-100 py-2 text-white fw-bold shadow" onclick="runGpu('arquitecto')"><i class="bi bi-gpu-card"></i> <?= __('btn_rendprompt') ?></button>
      </div>
      
      <div id="llmActionArea" class="d-none mt-3">
          <button class="btn btn-primary w-100 py-2 fw-bold" onclick="runLlm()"><?= __('btn_run_llm_now') ?></button>
          <div class="llm-execution-box d-none mt-3 border-start border-info ps-3 py-2 text-info" id="llmResponse"></div>
      </div>
 </div>