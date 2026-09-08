/**
 * ============================================================================
 * GARTY'S ARCHITECT - MÓDULO DE AUDIO PRO (v1.0.0-R2)
 * Gestión de F5-TTS y Stable Audio Open (Con SwalDark e i18n)
 * ============================================================================
 */

// Toggle principal del panel
function toggleAudioUI() {
    const toggle = document.getElementById('audioToggle');
    const ui = document.getElementById('audioUI');
    const indicator = document.getElementById('proActiveIndicator');
    
    if (toggle && ui) {
        if (toggle.checked) {
            ui.classList.remove('d-none');
            // Si el usuario activa audio pero no es pro (por manipulación de DOM), revertir
            if (typeof APP_ENV !== 'undefined' && currentUserRole !== 'pro' && !APP_ENV.isAdmin) {
                toggle.checked = false;
                ui.classList.add('d-none');
                SwalDark.fire({ 
                    icon: 'warning', 
                    title: GartyLang.audio_attn_title || 'Atención', 
                    text: GartyLang.err_pro_only || 'Módulo exclusivo para usuarios Pro.' 
                });
                return;
            }
        } else {
            ui.classList.add('d-none');
        }
    }
    
    // Disparar evento para que el monitor pro de index.php se actualice
    if (typeof updateProIndicator === 'function') {
        updateProIndicator();
    }
}

// Control visual de las pestañas internas de Audio (para cambiar estilos al hacer clic)
document.addEventListener('DOMContentLoaded', () => {
    const audioTabs = document.querySelectorAll('#audioEngineTabs .nav-link');
    audioTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            audioTabs.forEach(t => {
                t.classList.remove('text-info', 'border-info');
                t.classList.add('text-light', 'border-secondary');
            });
            this.classList.remove('text-light', 'border-secondary');
            this.classList.add('text-info', 'border-info');
        });
    });
});

// Almacén temporal del archivo de referencia para clonación
let currentAudioRefFile = null;

function handleAudioRefUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validación básica de tamaño (máx 15MB para muestras)
        if (file.size > 15 * 1024 * 1024) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: GartyLang.err_audio_size || 'El archivo de audio es demasiado grande. Usa una muestra corta (3-10 segundos).' 
            });
            input.value = '';
            return;
        }
        
        currentAudioRefFile = file;
        document.getElementById('audioRefName').innerText = file.name;
        document.getElementById('btnClearAudio').classList.remove('d-none');
        
        // Previsualizar en el reproductor oculto
        const player = document.getElementById('audioPlayer');
        const container = document.getElementById('audioPreviewContainer');
        if (player && container) {
            player.src = URL.createObjectURL(file);
            container.classList.remove('d-none');
        }
    }
}

function clearAudioModule() {
    currentAudioRefFile = null;
    document.getElementById('audioRefInput').value = '';
    document.getElementById('audioRefName').innerText = '';
    document.getElementById('audioRefText').value = '';
    document.getElementById('btnClearAudio').classList.add('d-none');
    
    const player = document.getElementById('audioPlayer');
    const container = document.getElementById('audioPreviewContainer');
    if (player && container) {
        player.pause();
        player.src = '';
        container.classList.add('d-none');
    }
}

// ============================================================================
// --- COMUNICACIÓN AJAX CON EL BACKEND (API AUDIO) ---
// ============================================================================

// Nombre del archivo de muestra ya procesado y almacenado por ComfyUI
let uploadedAudioRefName = null;

// Sobreescribimos ligeramente la función handleAudioRefUpload para añadir la subida AJAX inmediata
const originalAudioHandler = handleAudioRefUpload;
handleAudioRefUpload = async function(input) {
    // Ejecutamos la validación visual previa
    originalAudioHandler(input);
    
    if (!currentAudioRefFile) return;

    const formData = new FormData();
    formData.append('action', 'subir_audio_referencia');
    formData.append('audio_ref', currentAudioRefFile);

    const statusLabel = document.getElementById('audioRefName');
    statusLabel.innerHTML = `<span class="spinner-border spinner-border-sm text-info me-1" role="status"></span> ${GartyLang.audio_uploading || 'Subiendo a ComfyUI...'}`;

    try {
        const response = await fetch('procesar.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.file_name) {
            uploadedAudioRefName = data.file_name;
            statusLabel.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> ${data.file_name}`;
        } else {
            SwalDark.fire({ 
                icon: 'error', 
                title: GartyLang.audio_err_title || 'Error de Audio', 
                text: data.error || GartyLang.err_audio_server || 'Error al procesar el audio de referencia en el servidor.' 
            });
            clearAudioModule();
        }
    } catch (error) {
        console.error('Error AJAX en subida de audio:', error);
        SwalDark.fire({ 
            icon: 'error', 
            title: GartyLang.audio_err_title || 'Error de Audio', 
            text: GartyLang.err_audio_net || 'Error de red al intentar subir la muestra de voz.' 
        });
        clearAudioModule();
    }
};

/**
 * Función pública que core.js o video.js invocarán antes de lanzar un prompt
 * Devuelve un objeto con la configuración de audio lista para inyectarse en el workflow, o null si está inactivo.
 */
function getActiveAudioConfig() {
    const toggle = document.getElementById('audioToggle');
    if (!toggle || !toggle.checked) return null;

    // --- INTERCEPTOR INTELIGENTE PARA AUDIO CLÁSICO ---
    const ttsSpeechEl = document.getElementById('ttsSpeechText');
    const ttsSpeech = ttsSpeechEl ? ttsSpeechEl.value.trim() : '';

    if (typeof currentAudioBase64 !== 'undefined' && currentAudioBase64 !== null && ttsSpeech === '') {
        return null;
    }

    // Detectamos qué pestaña (motor) está activa
    const isTTS = document.getElementById('tts-tab') && document.getElementById('tts-tab').classList.contains('active');
    const isFoley = document.getElementById('foley-tab') && document.getElementById('foley-tab').classList.contains('active'); 
    // Si no es TTS ni Foley, asumimos que es SFX

    let syncVideo = document.getElementById('syncAudioVideo') ? document.getElementById('syncAudioVideo').checked : false;

    // ESCUDO ANTI-IMÁGENES
    const selectorEl = document.getElementById('selector');
    if (selectorEl && selectorEl.value !== '[VIDEO]') {
        syncVideo = false; 
    }

    if (isTTS) {
        const ttsEngineEl = document.getElementById('tts_engine') || document.querySelector('[name="tts_engine"]') || document.getElementById('ttsEngine');
        const currentEngine = ttsEngineEl ? ttsEngineEl.value : 'indextts';

        // ESCUDO: Solo pedimos audio de referencia si NO es OmniVoice
        if (currentEngine !== 'omnivoice' && !uploadedAudioRefName) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: GartyLang.err_missing_audio_ref || 'Por favor, sube y espera a que se cargue la muestra de voz para clonar.' 
            });
            return false;
        }
        
        if (!ttsSpeech) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: 'Por favor, escribe el guion que quieres que la voz lea.' 
            });
            return false;
        }

        const ttsEmotionEl = document.getElementById('tts_emotion') || document.querySelector('[name="tts_emotion"]') || document.getElementById('ttsEmotion');
        const ttsLanguageEl = document.getElementById('ttsLanguage') || document.querySelector('[name="tts_language"]');
        const ttsGenderEl = document.getElementById('ttsGender');
        const ttsAgeEl = document.getElementById('ttsAge');

        return {
            engine: 'tts',
            prompt_text: ttsSpeech,
            tts_engine: currentEngine,
            tts_emotion: ttsEmotionEl ? ttsEmotionEl.value : 'calm',
            tts_language: ttsLanguageEl ? ttsLanguageEl.value : 'Spanish',
            tts_gender: ttsGenderEl ? ttsGenderEl.value : 'male',
            tts_age: ttsAgeEl ? ttsAgeEl.value : 'None',
            ref_file: uploadedAudioRefName || '',
            ref_text: document.getElementById('audioRefText') ? document.getElementById('audioRefText').value.trim() : '',
            speed: document.getElementById('ttsSpeed') ? document.getElementById('ttsSpeed').value : 1.0,
            remove_silence: document.getElementById('ttsRemoveSilence') && document.getElementById('ttsRemoveSilence').checked ? '1' : '0',
            sync_with_video: syncVideo
        };
        
    } else if (isFoley) {
        // --- NUEVA RUTA PARA HUNYUAN FOLEY ---
        let foleyPrompt = document.getElementById('foleyPrompt') ? document.getElementById('foleyPrompt').value.trim() : '';
        
        // Si el usuario no escribe prompt, cogemos el principal (como en SFX)
        if (!foleyPrompt) {
            const mainPromptEl = document.getElementById('descripcion');
            if (mainPromptEl && mainPromptEl.value.trim() !== '') {
                foleyPrompt = mainPromptEl.value.trim();
            }
        }

        // Buscamos el medio disponible (priorizando el VÍDEO sobre la imagen)
        let foleyMedia = null;
        if (typeof window !== 'undefined' && window.currentVideoBase64) {
            foleyMedia = window.currentVideoBase64;
        } else if (typeof currentImageBase64 !== 'undefined' && currentImageBase64) {
            foleyMedia = currentImageBase64;
        }

        // VALIDACIÓN ESTRICTA: Foley NECESITA una base en el visor para sincronizar
        if (!foleyMedia) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: 'Hunyuan Foley necesita que cargues un vídeo en el visor principal para generar el sonido sincronizado.' 
            });
            return false;
        }

        return {
            engine: 'foley',
            prompt_text: foleyPrompt,
            sfx_steps: document.getElementById('foleySteps') ? document.getElementById('foleySteps').value : 50, 
            sync_with_video: false,
            // Enviamos el medio correcto al backend
            media_base64: foleyMedia 
        };

    } else {
        // --- RUTA CLÁSICA PARA STABLE AUDIO (SFX) ---
        let sfxPrompt = document.getElementById('sfxPrompt') ? document.getElementById('sfxPrompt').value.trim() : '';
        
        if (!sfxPrompt) {
            const mainPromptEl = document.getElementById('descripcion');
            if (mainPromptEl && mainPromptEl.value.trim() !== '') {
                sfxPrompt = mainPromptEl.value.trim();
            }
        }

        if (!sfxPrompt) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: GartyLang.err_empty_prompt || 'Por favor, escribe un prompt para generar el sonido ambiental o música.' 
            });
            return false;
        }
        
        return {
            engine: 'sfx',
            prompt_text: sfxPrompt,
            seconds: document.getElementById('sfxSeconds') ? document.getElementById('sfxSeconds').value : 5.0,
            sfx_steps: document.getElementById('sfxSteps') ? document.getElementById('sfxSteps').value : 100, 
            sync_with_video: syncVideo
        };
    }
}

// ============================================================================
// --- GESTOR DE VOCES GUARDADAS (F5-TTS ZERO SHOT) ---
// ============================================================================

window.cargarVocesGuardadas = async function() {
    const select = document.getElementById('ttsSavedVoices');
    if (!select) return;

    try {
        const fd = new FormData();
        fd.append('action', 'obtener_voces');
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success && data.voces) {
            const optTemp = typeof GartyLang !== 'undefined' && GartyLang.opt_voz_temporal ? GartyLang.opt_voz_temporal : '-- Subir audio temporal --';
            let optionsHTML = `<option value="" data-text="">${optTemp}</option>`;
            
            data.voces.forEach(v => {
                optionsHTML += `<option value="${v.id}" data-path="${v.ref_audio_path}" data-text="${v.ref_text}">${v.nombre_voz}</option>`;
            });
            
            select.innerHTML = optionsHTML;
        }
    } catch (e) {
        console.error("Error cargando voces:", e);
    }
};

window.toggleSaveVoiceForm = function() {
    const form = document.getElementById('saveVoiceFormContainer');
    if (form) {
        form.classList.toggle('d-none');
    }
};

window.handleSavedVoiceSelection = async function() {
    const select = document.getElementById('ttsSavedVoices');
    const uploadWrapper = document.getElementById('ttsUploadWrapper');
    const btnDelete = document.getElementById('btnDeleteSavedVoice');
    const textInput = document.getElementById('audioRefText');

    if (select && select.value !== "") {
        // Se ha seleccionado una voz guardada
        if (uploadWrapper) uploadWrapper.classList.add('d-none');
        if (btnDelete) btnDelete.classList.remove('d-none');
        
        const selectedOpt = select.options[select.selectedIndex];
        const text = selectedOpt.getAttribute('data-text');
        const path = selectedOpt.getAttribute('data-path');
        
        if (textInput) textInput.value = text;

        // Truco: Descargar la voz desde el servidor local y subirla a ComfyUI como si la hubieran subido a mano
        try {
            const txtLoading = typeof GartyLang !== 'undefined' && GartyLang.msg_loading_voice ? GartyLang.msg_loading_voice : 'Cargando voz...';
            SwalDark.fire({ title: txtLoading, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            
            const response = await fetch('voces/' + path);
            const errNotFound = typeof GartyLang !== 'undefined' && GartyLang.err_audio_not_found ? GartyLang.err_audio_not_found : 'Audio no encontrado en el servidor';
            if (!response.ok) throw new Error(errNotFound);
            
            const blob = await response.blob();
            const file = new File([blob], path, { type: 'audio/wav' });
            
            // Usamos tu función existente para previsualizar y mandar a ComfyUI automáticamente
            handleAudioRefUpload({ files: [file], value: path });
            
        } catch (e) {
            console.error("Error al cargar el archivo de voz guardado:", e);
            const errTitle = typeof GartyLang !== 'undefined' && GartyLang.swal_err_title ? GartyLang.swal_err_title : 'Error';
            const errLoad = typeof GartyLang !== 'undefined' && GartyLang.err_load_physical_audio ? GartyLang.err_load_physical_audio : 'No se pudo cargar el audio físico de esta voz.';
            SwalDark.fire({ icon: 'error', title: errTitle, text: errLoad });
            select.value = "";
            handleSavedVoiceSelection();
        }

    } else {
        // Volver a modo temporal
        if (uploadWrapper) uploadWrapper.classList.remove('d-none');
        if (btnDelete) btnDelete.classList.add('d-none');
        if (textInput) textInput.value = "";
        if (typeof clearAudioModule === 'function') clearAudioModule(); 
    }
};

window.guardarModeloVoz = async function() {
    const btn = document.getElementById('btnSaveVoiceModel');
    const nameInput = document.getElementById('newVoiceName');
    const refText = document.getElementById('audioRefText') ? document.getElementById('audioRefText').value.trim() : '';
    const voiceName = nameInput ? nameInput.value.trim() : '';
    
    const attnTitle = typeof GartyLang !== 'undefined' && GartyLang.audio_attn_title ? GartyLang.audio_attn_title : 'Atención';

    if (!currentAudioRefFile) { 
        const msgMissSample = typeof GartyLang !== 'undefined' && GartyLang.err_missing_audio_sample ? GartyLang.err_missing_audio_sample : 'Sube una muestra de audio primero.';
        SwalDark.fire({ icon: 'warning', title: attnTitle, text: msgMissSample }); 
        return; 
    }
    if (!refText) { 
        const msgMissTrans = typeof GartyLang !== 'undefined' && GartyLang.err_missing_transcript ? GartyLang.err_missing_transcript : 'Escribe la transcripción exacta de la muestra.';
        SwalDark.fire({ icon: 'warning', title: attnTitle, text: msgMissTrans }); 
        return; 
    }
    if (!voiceName) { 
        const msgMissName = typeof GartyLang !== 'undefined' && GartyLang.err_missing_voice_name ? GartyLang.err_missing_voice_name : 'Escribe un nombre para guardar la voz.';
        SwalDark.fire({ icon: 'warning', title: attnTitle, text: msgMissName }); 
        return; 
    }

    const originalBtnHtml = btn.innerHTML;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;
    btn.disabled = true;

    try {
        // Convertir el File temporal actual a Base64 para guardarlo en la DB de forma segura
        const reader = new FileReader();
        reader.readAsDataURL(currentAudioRefFile);
        reader.onload = async function () {
            const base64Audio = reader.result;
            
            const fd = new FormData(); 
            fd.append('action', 'guardar_voz'); 
            fd.append('nombre_voz', voiceName); 
            fd.append('ref_text', refText);
            fd.append('audio_data', base64Audio);

            const res = await fetch('procesar.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.error) throw new Error(data.error);
            
            if (data.success) {
                const msgSaved = typeof GartyLang !== 'undefined' && GartyLang.msg_voice_saved ? GartyLang.msg_voice_saved : 'Voz guardada';
                SwalDark.fire({ toast: true, position: 'top-end', icon: 'success', title: msgSaved, showConfirmButton: false, timer: 2000 });
                nameInput.value = ''; 
                toggleSaveVoiceForm();
                
                // Recargar lista
                await cargarVocesGuardadas();
            }
        };
    } catch (e) { 
        const errTitle = typeof GartyLang !== 'undefined' && GartyLang.swal_err_title ? GartyLang.swal_err_title : 'Error';
        SwalDark.fire({ icon: 'error', title: errTitle, text: e.message }); 
    } finally { 
        btn.innerHTML = originalBtnHtml; 
        btn.disabled = false; 
    }
};

window.eliminarVozGuardada = async function() {
    const select = document.getElementById('ttsSavedVoices');
    if (!select || select.value === "") return;

    const voiceId = select.value;
    const voiceName = select.options[select.selectedIndex].text;
    
    const titDelete = typeof GartyLang !== 'undefined' && GartyLang.tit_delete_voice ? GartyLang.tit_delete_voice : '¿Eliminar voz?';
    const msgConfirm = typeof GartyLang !== 'undefined' && GartyLang.msg_delete_voice_confirm ? GartyLang.msg_delete_voice_confirm : 'Vas a borrar permanentemente a "{name}". ¿Estás seguro?';
    const btnYes = typeof GartyLang !== 'undefined' && GartyLang.btn_yes_delete ? GartyLang.btn_yes_delete : 'Sí, eliminar';
    const btnCancel = typeof GartyLang !== 'undefined' && GartyLang.btn_cancelar ? GartyLang.btn_cancelar : 'Cancelar';

    const confirm = await SwalDark.fire({
        title: titDelete,
        text: msgConfirm.replace('{name}', voiceName),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: btnYes,
        cancelButtonText: btnCancel
    });

    if (!confirm.isConfirmed) return;

    try {
        let fd = new FormData();
        fd.append('action', 'eliminar_voz');
        fd.append('voz_id', voiceId);

        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.error) throw new Error(data.error);

        const msgDeleted = typeof GartyLang !== 'undefined' && GartyLang.msg_voice_deleted ? GartyLang.msg_voice_deleted : 'Voz eliminada';
        SwalDark.fire({ toast: true, position: 'top-end', icon: 'success', title: msgDeleted, showConfirmButton: false, timer: 2000 });
        
        // Volver al estado inicial
        select.value = "";
        handleSavedVoiceSelection();
        cargarVocesGuardadas();
    } catch (e) {
        const errTitle = typeof GartyLang !== 'undefined' && GartyLang.swal_err_title ? GartyLang.swal_err_title : 'Error';
        SwalDark.fire({ icon: 'error', title: errTitle, text: e.message });
    }
};

// Cargar la lista en cuanto el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    cargarVocesGuardadas();
});

