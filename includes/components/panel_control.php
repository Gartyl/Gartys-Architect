
<div class="mb-4" id="upperInputBlock">
    
<!-- Contenedor principal: Aumentamos el padding-bottom a 15px -->
    <!-- ENVOLTORIO CON FLECHAS DE SCROLL -->
	<div class="d-flex align-items-center mb-2 w-100 gap-1">
		<!-- Flecha Izquierda (Oculta por defecto) -->
		<button type="button" id="btnScrollL" class="btn btn-link text-info px-1 py-0 border-0" style="display: none;" onclick="document.getElementById('toolbarScroll').scrollBy({left: -200, behavior: 'smooth'})">
			<i class="bi bi-chevron-left fs-5"></i>
		</button>

		<!-- TU CONTENEDOR ORIGINAL MODIFICADO -->
		<div id="toolbarScroll" class="input-toolbar ocultar-scroll d-flex justify-content-between align-items-center flex-nowrap gap-2 flex-grow-1" style="overflow-x: auto; scroll-behavior: smooth;" onscroll="checkToolbarScroll()">
			
			<!-- GRUPO IZQUIERDO: Interruptores -->
			<div class="d-flex align-items-center flex-nowrap gap-3">
				
				<div class="form-check form-switch m-0 text-nowrap" id="modoDirectoWrapper" title="<?= __('lbl_modo_directo') ?>">
					<input class="form-check-input border-warning" style="cursor: pointer;" type="checkbox" id="modoDirectoToggle" onchange="toggleModoIngreso()">
					<label class="form-check-label small text-warning fw-bold" for="modoDirectoToggle">
						<i class="bi bi-input-cursor-text"></i> <?= __('lbl_modo_directo') ?>
					</label>
				</div>
				
				<div class="vr text-secondary" id="separadorBotones" style="opacity: 0.3; height: 1.2rem;"></div> 
				
				<div class="d-flex align-items-center text-nowrap" id="multiInputWrapper">
					<div class="form-check form-switch m-0" title="<?= __('title_multicarga') ?>">
						<input class="form-check-input border-info" style="cursor: pointer;" type="checkbox" id="multiInputToggle">
						<label class="form-check-label small text-info fw-bold" for="multiInputToggle">
							<i class="bi bi-collection"></i> <?= __('lbl_multicarga') ?>
						</label>
					</div>
				</div>

			</div>

			<!-- GRUPO DERECHO -->
			<div class="d-flex gap-2 align-items-center ms-auto flex-nowrap justify-content-end">
				<button type="button" class="btn-tool text-nowrap" id="uploadBtn" onclick="document.getElementById('imageInput').click()"><i class="bi bi-paperclip"></i> <?= __('btn_subiranalisis') ?></button>
				<button type="button" class="btn-tool border-info text-info text-nowrap" id="btnCargarGaleria" onclick="window.destinoGaleriaModal = 'principal'; abrirModalGaleria();"><i class="bi bi-images"></i> <?= __('btn_cargaleria') ?></button>
				<button type="button" class="btn-tool border-warning text-warning fw-bold text-nowrap" id="btnWildcards" onclick="abrirModalWildcards()" title="<?= __('btn_title_wildcards') ?>"><i class="bi bi-suit-spade-fill"></i> <?= __('btn_wildcards') ?></button>
				
				<input type="file" id="imageInput" accept="image/*,.pdf,.doc,.docx,.txt,.csv,.md,video/mp4" class="d-none">
				
				<button type="button" class="btn-tool border-success text-success text-nowrap d-none" id="audioUploadBtn" onclick="document.getElementById('audioInput').click()"><i class="bi bi-music-note-beamed"></i> <?= __('audio_pista') ?></button>
				<input type="file" id="audioInput" accept="audio/*" class="d-none">
				
				<button type="button" class="btn-tool" id="micBtn" title="<?= __('btn_title_dictation') ?>"><i class="bi bi-mic-fill"></i></button>
			</div>
			
		</div>

		<!-- Flecha Derecha (Oculta por defecto) -->
		<button type="button" id="btnScrollR" class="btn btn-link text-info px-1 py-0 border-0" style="display: none;" onclick="document.getElementById('toolbarScroll').scrollBy({left: 200, behavior: 'smooth'})">
			<i class="bi bi-chevron-right fs-5"></i>
		</button>
	</div>
    
    <!-- ============================================================== -->
    <!-- NUEVA BANDEJA MULTIENTRADA (Oculta por defecto) -->
    <!-- ============================================================== -->
    <div id="bandejaImagenes" class="d-flex flex-wrap gap-2 mb-2 px-1" style="display: none !important;">
        <!-- Las miniaturas se inyectarán aquí desde motor.js -->
    </div>
    <!-- ============================================================== -->

    <div id="contenedorIdea">
        <textarea class="form-control" id="descripcion" rows="3" autocomplete="off" placeholder="<?= __('txt_arrast_png') ?>"></textarea>
    </div>

    <div class="d-flex justify-content-end align-items-center gap-3 mt-2 mb-3">
        <!-- Switch de Internet (NUEVO) -->
        <div class="form-check form-switch m-0 d-none" id="internetToggleBlock">
            <input class="form-check-input pref-track border-primary" style="cursor: pointer;" type="checkbox" id="internetToggle">
            <label class="form-check-label small text-primary fw-bold ms-2" for="internetToggle">
				<i class="bi bi-globe"></i> <?= __('ctrl_search_internet') ?>
			</label>
        </div>

        <!-- Switch de Traducción (ORIGINAL) -->
        <div class="form-check form-switch m-0 d-none" id="translateToggleBlock">
            <input class="form-check-input pref-track border-info" style="cursor: pointer;" type="checkbox" id="autoTranslateToggle" checked>
            <label class="form-check-label small text-info fw-bold ms-2" for="autoTranslateToggle">
                <i class="bi bi-translate"></i> <?= __('ctrl_auto_trad2') ?>
            </label>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap mt-3" id="mainButtonsContainer">
        <button type="submit" class="btn btn-success flex-grow-1 btn-generate shadow" id="submitBtn"><i class="bi bi-chat-right-dots"></i> <?= __('btn_arquitecto') ?></button>
        <button type="button" class="btn btn-gpu flex-grow-1 text-white fw-bold shadow d-none" id="gpuDirectBtn" onclick="runGpu('directo')"><i class="bi bi-lightning-fill"></i> <?= __('btn_renderizar') ?></button>
        <button type="button" class="btn btn-primary flex-grow-1 text-white fw-bold shadow d-none" id="llmDirectBtn" onclick="runLlmDirect()"><i class="bi bi-robot"></i> <?= __('btn_textdirecto') ?></button>
        
        <!-- NUEVO BOTÓN: AUTO-ARQUITECTO (Con control PRO y variables de idioma) -->
        <?php if (isset($is_pro) && $is_pro): ?>
            <button type="button" class="btn btn-primary px-3 fw-bold shadow border-0" id="autoArchitectBtn" onclick="ejecutarAutoArquitecto()" title="<?= __('btn_title_autoarch') ?>" style="background: linear-gradient(45deg, #0d6efd, #6610f2);">
                <i class="bi bi-stars fs-5 text-warning"></i>
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-secondary px-3 fw-bold shadow-sm" id="autoArchitectBtn" title="<?= __('btn_title_autoarch_free') ?>" disabled>
                <i class="bi bi-stars fs-5 text-muted"></i> 🔒
            </button>
        <?php endif; ?>

        <button type="button" class="btn btn-info px-3 fw-bold text-dark" id="amplifyBtn" title="<?= __('btn_title_amplify') ?>"><i class="bi bi-magic fs-5"></i></button>
        <button type="button" class="btn btn-warning px-3 fw-bold" id="surpriseBtn" title="<?= __('btn_title_surprise') ?>"><i class="bi bi-dice-5-fill fs-5"></i></button>
        <button type="button" class="btn btn-secondary px-4" id="clearBtn"><?= __('btn_limpiar') ?></button>
    </div>

</div> <div class="w-100 position-relative" style="height: 0;">
    <span style="position: absolute; right: 0; top: 6px; font-size: 0.75rem; color: #4a5568; pointer-events: none; user-select: none;">
        v. <?= APP_VERSION ?>
    </span>
</div>