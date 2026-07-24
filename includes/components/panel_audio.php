<?php
// ==============================================================================
// --- PANEL AUDIO: GENERACIÓN Y CLONACIÓN PRO (F5-TTS / INDEX-TTS / STABLE AUDIO) ---
// ==============================================================================
?>
<div class="param-group shadow-sm border-info mb-3" id="audioBlock" style="border-color: rgba(13, 202, 240, 0.4) !important; background: rgba(13, 202, 240, 0.05);">
    <div class="d-flex justify-content-between align-items-center">
        <label class="small text-info fw-bold mb-0">
            <i class="bi bi-broadcast me-1"></i> <?= __('tit_modulo_audio') ?> <?= !$is_pro ? '🔒 (Pro)' : '' ?>
        </label>
        <div class="form-check form-switch m-0">
            <input class="form-check-input pref-track" style="cursor: pointer;" type="checkbox" id="audioToggle" onchange="toggleAudioUI()" <?= !$is_pro ? 'disabled' : '' ?>>
        </div>
    </div>
    
    <div id="audioUI" class="d-none mt-3 text-start">
        <!-- Navegaci贸n entre motores de audio -->
        <ul class="nav nav-pills nav-fill mb-3 gap-1" id="audioEngineTabs" role="tablist" style="font-size: 0.85rem;">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-1 bg-dark text-info border border-info" id="tts-tab" data-bs-toggle="tab" data-bs-target="#tts-panel" type="button" role="tab">
                    <i class="bi bi-mic-fill me-1"></i> <?= __('tab_estudio_voz') ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-1 bg-dark text-light border border-secondary" id="sfx-tab" data-bs-toggle="tab" data-bs-target="#sfx-panel" type="button" role="tab">
                    <i class="bi bi-music-note-beamed me-1"></i> <?= __('tab_stable_audio') ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="audioTabsContent">
            <!-- PESTAÑA 1: ESTUDIO DE VOZ (Multi-Motor) -->
            <div class="tab-pane fade show active" id="tts-panel" role="tabpanel">
                
                <!-- NUEVO: CAJÓN PARA EL GUION DE LOCUCIÓN -->
                <div class="mb-3 mt-1">
                    <label class="small text-info fw-bold mb-1"><i class="bi bi-mic-fill"></i><?= __('opt_txt_locutar') ?? 'Texto a Locutar (Guión)' ?> </label>
                    <textarea id="ttsSpeechText" class="form-control form-control-sm bg-dark text-light border-info pref-track" rows="3" placeholder="Escribe aquí exactamente lo que quieres que diga la voz..."></textarea>
                </div>

                <!-- Selectores de Arquitectura, Idioma y Emoción -->
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <label class="small text-secondary fw-bold"><?= __('lbl_tts_engine') ?? 'Motor' ?></label>
                        <select id="ttsEngine" name="tts_engine" class="form-select form-select-sm bg-dark text-light border-info pref-track" onchange="toggleTTSOptions()">
                            <option value="indextts" selected><?= __('opt_indextts') ?? 'IndexTTS-2 (Clonación)' ?></option>
                            <option value="omnivoice">OmniVoice (Zero-Shot)</option>
                            <option value="f5"><?= __('opt_f5_tts') ?? 'F5-TTS (Legacy)' ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-secondary fw-bold"><?= __('lbl_tts_language') ?? 'Idioma' ?></label>
                        <select id="ttsLanguage" name="tts_language" class="form-select form-select-sm bg-dark text-light border-info pref-track">
                            <option value="Spanish" selected><?= __('opt_lang_es') ?? 'Español' ?></option>
                            <option value="English"><?= __('opt_lang_en') ?? 'Inglés' ?></option>
                            <option value="French"><?= __('opt_lang_fr') ?? 'Francés' ?></option>
                            <option value="German"><?= __('opt_lang_de') ?? 'Alemán' ?></option>
                            <option value="Russian"><?= __('opt_lang_ru') ?? 'Ruso' ?></option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-secondary fw-bold"><?= __('lbl_tts_emotion') ?? 'Emoción/Estilo' ?></label>
                        <select id="ttsEmotion" name="tts_emotion" class="form-select form-select-sm bg-dark text-light border-info pref-track">
                            <option value="calm" selected><?= __('opt_emo_calm') ?? 'Calmado' ?></option>
                            <option value="happy"><?= __('opt_emo_happy') ?? 'Feliz' ?></option>
                            <option value="sad"><?= __('opt_emo_sad') ?? 'Triste' ?></option>
                            <option value="angry"><?= __('opt_emo_angry') ?? 'Enfadado' ?></option>
                            <option value="whisper"><?= __('opt_emo_whisper') ?? 'Susurro (Omni)' ?></option>
                        </select>
                    </div>
                </div>

                <!-- NUEVO BLOQUE OMNIVOICE: G茅nero y Edad (Oculto por defecto) -->
                <div class="row g-2 mb-2 d-none" id="omnivoiceOptionsBlock">
                    <div class="col-md-6">
                        <label class="small text-secondary fw-bold"><?= __('lbl_tts_gender') ?? 'Género (OmniVoice)' ?></label>
                        <select id="ttsGender" name="tts_gender" class="form-select form-select-sm bg-dark text-light border-info pref-track">
                            <option value="male" selected><?= __('opt_gender_male') ?? 'Masculino' ?></option>
                            <option value="female"><?= __('opt_gender_female') ?? 'Femenino' ?></option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="small text-secondary fw-bold"><?= __('lbl_tts_age') ?? 'Edad (OmniVoice)' ?></label>
                        <select id="ttsAge" name="tts_age" class="form-select form-select-sm bg-dark text-light border-info pref-track">
                            <option value="None" selected><?= __('opt_age_auto') ?? 'Auto' ?></option>
                            <option value="child"><?= __('opt_age_child') ?? 'Niño/a' ?></option>
                            <option value="teenager"><?= __('opt_age_teen') ?? 'Adolescente' ?></option>
                            <option value="young adult"><?= __('opt_age_young') ?? 'Joven adulto' ?></option>
                            <option value="middle-aged"><?= __('opt_age_middle') ?? 'Mediana edad' ?></option>
                            <option value="elderly"><?= __('opt_age_elderly') ?? 'Anciano/a' ?></option>
                        </select>
                    </div>
                </div>

                <!-- BLOQUE DE CLONACIÓN: Archivo de referencia y transcripción (Para Index y F5) -->
                <div id="cloneOptionsBlock">
                    <div class="mb-2">
                        <label class="small text-secondary fw-bold"><?= __('lbl_audio_ref_file') ?? 'Muestra de Voz' ?></label>
                        <button type="button" class="btn btn-sm btn-outline-info w-100 mb-1" onclick="document.getElementById('audioRefInput').click()">
                            <i class="bi bi-file-earmark-music"></i> <?= __('btn_subir_audio_ref') ?? 'Subir Audio' ?>
                        </button>
                        <input type="file" id="audioRefInput" accept="audio/*" class="d-none" onchange="handleAudioRefUpload(this)">
                        <small id="audioRefName" class="text-info d-block text-truncate" style="max-width: 100%;"></small>
                    </div>
                    <div class="mb-2">
                        <label class="small text-secondary fw-bold"><?= __('lbl_audio_ref_text') ?? 'Transcripción exacta' ?></label>
                        <input type="text" class="form-control form-control-sm bg-dark text-light border-info pref-track" id="audioRefText" placeholder="<?= __('ph_audio_ref_text') ?? 'Escribe lo que dice la muestra...' ?>">
                    </div>
                </div>
                
                <!-- Opciones específicas de velocidad/silencios (Solo F5) -->
                <div class="row g-2 d-none" id="f5OptionsBlock">
                    <div class="col-md-6">
                        <label class="text-secondary small fw-bold"><?= __('lbl_tts_speed') ?? 'Velocidad' ?>: <span id="ttsSpeedLabel" class="text-light">1.0</span></label>
                        <input type="range" class="form-range pref-track" id="ttsSpeed" min="0.5" max="1.5" step="0.05" value="1.0" oninput="document.getElementById('ttsSpeedLabel').innerText = this.value;">
                    </div>
                    <div class="col-md-6 d-flex align-items-end mb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input pref-track" type="checkbox" id="ttsRemoveSilence" checked>
                            <label class="form-check-label small text-secondary" for="ttsRemoveSilence"><?= __('lbl_remove_silence') ?? 'Quitar silencios' ?></label>
                        </div>
                    </div>
                </div>

                <script>
                    function toggleTTSOptions() {
                        const engine = document.getElementById('ttsEngine').value;
                        const cloneBlock = document.getElementById('cloneOptionsBlock');
                        const omniBlock = document.getElementById('omnivoiceOptionsBlock');
                        const f5Block = document.getElementById('f5OptionsBlock');

                        if (engine === 'omnivoice') {
                            if(cloneBlock) cloneBlock.classList.add('d-none');
                            if(omniBlock) omniBlock.classList.remove('d-none');
                            if(f5Block) f5Block.classList.add('d-none');
                        } else if (engine === 'f5') {
                            if(cloneBlock) cloneBlock.classList.remove('d-none');
                            if(omniBlock) omniBlock.classList.add('d-none');
                            if(f5Block) f5Block.classList.remove('d-none');
                        } else {
                            // IndexTTS (Por defecto)
                            if(cloneBlock) cloneBlock.classList.remove('d-none');
                            if(omniBlock) omniBlock.classList.add('d-none');
                            if(f5Block) f5Block.classList.add('d-none');
                        }
                    }
                    // Ejecutar al cargar la página por si se guardó el estado en la sesión
                    document.addEventListener('DOMContentLoaded', toggleTTSOptions);
                </script>
            </div>

            <!-- PESTAÑA 2: STABLE AUDIO (SFX y Música ambiental) -->
            <div class="tab-pane fade" id="sfx-panel" role="tabpanel">
                <div class="mb-2">
                    <label class="small text-secondary fw-bold"><?= __('lbl_sfx_prompt') ?></label>
                    <textarea class="form-control form-control-sm bg-dark text-light border-info pref-track" id="sfxPrompt" rows="2" placeholder="<?= __('ph_sfx_prompt') ?>"></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="text-secondary small fw-bold"><?= __('lbl_sfx_seconds') ?>: <span id="sfxSecondsLabel" class="text-light">5.0</span>s</label>
                        <input type="range" class="form-range pref-track" id="sfxSeconds" min="1.0" max="30.0" step="0.5" value="5.0" oninput="document.getElementById('sfxSecondsLabel').innerText = this.value;">
                    </div>
                    <div class="col-md-6">
                        <label class="text-secondary small fw-bold"><?= __('lbl_sfx_steps') ?>: <span id="sfxStepsLabel" class="text-light">20</span></label>
                        <input type="range" class="form-range pref-track" id="sfxSteps" min="10" max="50" step="1" value="20" oninput="document.getElementById('sfxStepsLabel').innerText = this.value;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Opción transversal: Sincronizar con Video Combine -->
        <div class="mt-3 pt-2 border-top border-info d-flex justify-content-between align-items-center">
            <div class="form-check form-switch m-0">
                <input class="form-check-input pref-track" type="checkbox" id="syncAudioVideo" checked>
                <label class="form-check-label small text-info fw-bold" for="syncAudioVideo">
                    <i class="bi bi-film me-1"></i> <?= __('lbl_sync_video_vhs') ?>
                </label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger d-none" id="btnClearAudio" onclick="clearAudioModule()"><i class="bi bi-trash"></i></button>
        </div>

        <!-- Reproductor oculto para previsualizar muestras o resultados -->
        <div id="audioPreviewContainer" class="d-none mt-2 text-center">
            <audio id="audioPlayer" controls class="w-100 mt-1" style="height: 35px;"></audio>
        </div>
    </div>
</div>