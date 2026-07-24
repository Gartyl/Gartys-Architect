
<div class="mb-4" id="upperInputBlock">
    
    <div class="input-toolbar d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-3">
            <label class="small text-secondary fw-bold mb-0" id="lblIdea"><?= __('tit_idea') ?></label>
            <div class="form-check form-switch m-0" id="modoDirectoWrapper" title="<?= __('lbl_modo_directo') ?>">
                <input class="form-check-input border-warning" style="cursor: pointer;" type="checkbox" id="modoDirectoToggle" onchange="toggleModoIngreso()">
                <label class="form-check-label small text-warning fw-bold" for="modoDirectoToggle">
                    <i class="bi bi-input-cursor-text"></i> <?= __('lbl_modo_directo') ?>
                </label>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap ms-auto justify-content-end">
            <button type="button" class="btn-tool" id="uploadBtn" onclick="document.getElementById('imageInput').click()"><i class="bi bi-paperclip"></i> <?= __('btn_subiranalisis') ?></button>
            <button type="button" class="btn-tool border-info text-info" id="btnCargarGaleria" onclick="abrirModalGaleria()"><i class="bi bi-images"></i> <?= __('btn_cargaleria') ?></button>
            <button type="button" class="btn-tool border-warning text-warning fw-bold" id="btnWildcards" onclick="abrirModalWildcards()" title="<?= __('btn_title_wildcards') ?>"><i class="bi bi-suit-spade-fill"></i> <?= __('btn_wildcards') ?></button>
            <input type="file" id="imageInput" accept="image/*,.pdf,.doc,.docx,.txt,.csv,.md,video/mp4" class="d-none">
            <button type="button" class="btn-tool border-success text-success d-none" id="audioUploadBtn" onclick="document.getElementById('audioInput').click()"><i class="bi bi-music-note-beamed"></i> <?= __('audio_pista') ?></button>
            <input type="file" id="audioInput" accept="audio/*" class="d-none">
            <button type="button" class="btn-tool" id="micBtn" title="<?= __('btn_title_dictation') ?>"><i class="bi bi-mic-fill"></i></button>
        </div>
    </div>
    
    <div id="contenedorIdea">
        <textarea class="form-control" id="descripcion" rows="3" autocomplete="off" placeholder="<?= __('txt_arrast_png') ?>"></textarea>
    </div>

    <div class="form-check form-switch mt-2 mb-3 d-flex justify-content-end d-none" id="translateToggleBlock">
        <input class="form-check-input pref-track border-info" style="cursor: pointer;" type="checkbox" id="autoTranslateToggle" checked>
        <label class="form-check-label small text-info fw-bold ms-2" for="autoTranslateToggle">
            <i class="bi bi-translate"></i> <?= __('ctrl_auto_trad2') ?>
        </label>
    </div>

    <div class="d-flex gap-2 flex-wrap mt-3" id="mainButtonsContainer">
        <button type="submit" class="btn btn-success flex-grow-1 btn-generate shadow" id="submitBtn"><i class="bi bi-chat-right-dots"></i> <?= __('btn_arquitecto') ?></button>
        <button type="button" class="btn btn-gpu flex-grow-1 text-white fw-bold shadow d-none" id="gpuDirectBtn" onclick="runGpu('directo')"><i class="bi bi-lightning-fill"></i> <?= __('btn_renderizar') ?></button>
        <button type="button" class="btn btn-primary flex-grow-1 text-white fw-bold shadow d-none" id="llmDirectBtn" onclick="runLlmDirect()"><i class="bi bi-robot"></i> <?= __('btn_textdirecto') ?></button>
        
        <button type="button" class="btn btn-info px-3 fw-bold text-dark" id="amplifyBtn" title="<?= __('btn_title_amplify') ?>"><i class="bi bi-magic fs-5"></i></button>
        <button type="button" class="btn btn-warning px-3 fw-bold" id="surpriseBtn" title="<?= __('btn_title_surprise') ?>"><i class="bi bi-dice-5-fill fs-5"></i></button>
        <button type="button" class="btn btn-secondary px-4" id="clearBtn"><?= __('btn_limpiar') ?></button>
    </div>

</div> <div class="w-100 position-relative" style="height: 0;">
    <span style="position: absolute; right: 0; top: 6px; font-size: 0.75rem; color: #4a5568; pointer-events: none; user-select: none;">
        v. <?= APP_VERSION ?>
    </span>
</div>