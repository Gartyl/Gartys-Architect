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
 /**
 * Función pública que core.js o video.js invocarán antes de lanzar un prompt
 * Devuelve un objeto con la configuración de audio lista para inyectarse en el workflow, o null si está inactivo.
 */
function getActiveAudioConfig() {
    const toggle = document.getElementById('audioToggle');
    if (!toggle || !toggle.checked) return null;

    const isTTS = document.getElementById('tts-tab').classList.contains('active');
    let syncVideo = document.getElementById('syncAudioVideo') ? document.getElementById('syncAudioVideo').checked : false;

    // 🛡️ ESCUDO ANTI-IMÁGENES: Si no estamos explícitamente en la pestaña de Vídeo,
    // forzamos que el audio se genere de forma aislada para no chocar con los modelos de imagen.
    const selectorEl = document.getElementById('selector');
    if (selectorEl && selectorEl.value !== '[VIDEO]') {
        syncVideo = false; 
    }

    if (isTTS) {
        const ttsEngineEl = document.getElementById('tts_engine') || document.querySelector('[name="tts_engine"]') || document.getElementById('ttsEngine');
        const currentEngine = ttsEngineEl ? ttsEngineEl.value : 'indextts';

        // 🛡️ ESCUDO: Solo pedimos audio de referencia si NO es OmniVoice
        if (currentEngine !== 'omnivoice' && !uploadedAudioRefName) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: GartyLang.err_missing_audio_ref || 'Por favor, sube y espera a que se cargue la muestra de voz para clonar.' 
            });
            return false;
        }
        
        // --- NUEVO: CAPTURA DEL GUION DE LOCUCIÓN ---
        const ttsSpeechEl = document.getElementById('ttsSpeechText');
        const ttsSpeech = ttsSpeechEl ? ttsSpeechEl.value.trim() : '';

        if (!ttsSpeech) {
            SwalDark.fire({ 
                icon: 'warning', 
                title: GartyLang.audio_attn_title || 'Atención', 
                text: 'Por favor, escribe el guion que quieres que la voz lea.' 
            });
            return false;
        }
        // ---------------------------------------------

        // Capturamos todos los parámetros
        const ttsEmotionEl = document.getElementById('tts_emotion') || document.querySelector('[name="tts_emotion"]') || document.getElementById('ttsEmotion');
        const ttsLanguageEl = document.getElementById('ttsLanguage') || document.querySelector('[name="tts_language"]');
        const ttsGenderEl = document.getElementById('ttsGender');
        const ttsAgeEl = document.getElementById('ttsAge');

        return {
            engine: 'tts',
            prompt_text: ttsSpeech, // <--- INYECTAMOS EL GUION AQUÍ
            tts_engine: currentEngine,
            tts_emotion: ttsEmotionEl ? ttsEmotionEl.value : 'calm',
            tts_language: ttsLanguageEl ? ttsLanguageEl.value : 'Spanish',
            tts_gender: ttsGenderEl ? ttsGenderEl.value : 'male',
            tts_age: ttsAgeEl ? ttsAgeEl.value : 'None',
            ref_file: uploadedAudioRefName || '',
            ref_text: document.getElementById('audioRefText').value.trim(),
            speed: document.getElementById('ttsSpeed') ? document.getElementById('ttsSpeed').value : 1.0,
            remove_silence: document.getElementById('ttsRemoveSilence') && document.getElementById('ttsRemoveSilence').checked ? '1' : '0',
            sync_with_video: syncVideo
        };
    } else {
        let sfxPrompt = document.getElementById('sfxPrompt').value.trim();
        
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
            steps: document.getElementById('sfxSteps') ? document.getElementById('sfxSteps').value : 20,
            sync_with_video: syncVideo
        };
    }
}

