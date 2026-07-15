// ==============================================================================
// --- MOTOR.JS: NÚCLEO DE GENERACIÓN, RUTEO, CHAT Y LÓGICA PRINCIPAL ---
// ==============================================================================

const isAvanzado = APP_ENV.isAvanzado; 
const isAdmin = APP_ENV.isAdmin;

let lastGeneratedPrompt = { pos: "", neg: "" }; 
let currentPromptId = 0;
let currentDocumentText = "";
let currentImageBase64 = null;
let currentAudioBase64 = null;
let currentFaceBase64 = null;
let currentIpAdapterBase64 = null;
let currentCnBase64 = null;
window.rawUploadedDataUrl = null;
let maskCanvas = null;
let maskCtx = null;
let isDrawing = false;
let hasMaskStrokes = false;
window.isErasing = false; 

// --- GESTIÓN DE PINCEL Y CANVAS ---
window.setBrushMode = function(mode) {
    window.isErasing = (mode === 'erase');
    document.getElementById('btnBrush').className = window.isErasing ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-danger';
    document.getElementById('btnEraser').className = window.isErasing ? 'btn btn-sm btn-light' : 'btn btn-sm btn-outline-light';
    window.updateCursor();
};

window.updateCursor = function() {
    if (!maskCanvas) return;
    const editToggle = document.getElementById('editToolsToggle');
    if (editToggle && !editToggle.checked) {
        maskCanvas.style.cursor = 'default';
        maskCanvas.style.pointerEvents = 'none'; 
        return;
    } else { maskCanvas.style.pointerEvents = 'auto'; }
    
    const size = parseInt(document.getElementById('brushSize').value);
    const rect = maskCanvas.getBoundingClientRect();
    const scaleX = rect.width / maskCanvas.width;
    const displaySize = Math.max(6, Math.round(size * scaleX)); 
    
    const color = window.isErasing ? 'rgba(255,255,255,0.7)' : 'rgba(255,0,0,0.5)';
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${displaySize}" height="${displaySize}" viewBox="0 0 ${displaySize} ${displaySize}"><circle cx="${displaySize/2}" cy="${displaySize/2}" r="${(displaySize/2) - 1}" fill="${color}" stroke="white" stroke-width="1"/><circle cx="${displaySize/2}" cy="${displaySize/2}" r="${(displaySize/2) - 2}" fill="none" stroke="black" stroke-width="1"/></svg>`;
    const encoded = encodeURIComponent(svg);
    maskCanvas.style.cursor = `url('data:image/svg+xml;charset=utf-8,${encoded}') ${Math.round(displaySize/2)} ${Math.round(displaySize/2)}, crosshair`;
};
window.addEventListener('resize', () => { if(maskCanvas) window.updateCursor(); });

window.maskHistory = [];
window.maskStep = -1;

window.saveMaskState = function() {
    if (!maskCanvas) return;
    window.maskStep++;
    if (window.maskStep < window.maskHistory.length) window.maskHistory.length = window.maskStep;
    window.maskHistory.push(maskCanvas.toDataURL());
};

window.undoMask = function() {
    if (window.maskStep > 0) {
        window.maskStep--;
        let canvasPic = new Image();
        canvasPic.onload = function() { maskCtx.clearRect(0, 0, maskCanvas.width, maskCanvas.height); maskCtx.drawImage(canvasPic, 0, 0); }
        canvasPic.src = window.maskHistory[window.maskStep];
    } else if (window.maskStep === 0) { window.clearMask(false); }
};

window.clearMask = function(saveState = true) {
    if (maskCtx && maskCanvas) {
        maskCtx.clearRect(0, 0, maskCanvas.width, maskCanvas.height);
        hasMaskStrokes = false;
        if (saveState) window.saveMaskState();
    }
};

function extractMaskBase64() {
    if (!hasMaskStrokes || !maskCanvas) return null;
    const exportCanvas = document.createElement('canvas');
    exportCanvas.width = maskCanvas.width; exportCanvas.height = maskCanvas.height;
    const eCtx = exportCanvas.getContext('2d');
    eCtx.fillStyle = "black"; eCtx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
    eCtx.drawImage(maskCanvas, 0, 0); 
    return exportCanvas.toDataURL('image/png').split(',')[1];
}

function setBaseImageFromDataUrl(dataUrl) {
    window.rawUploadedDataUrl = dataUrl;
    const img = new Image();
    img.onload = () => {
        const sel = document.getElementById('selector').value; 
        let width = img.width; let height = img.height; const MAX_DIM = 1280; 
        const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
        
        if (sel === '[VIDEO]') {
            const optimizeToggle = document.getElementById('videoOptimizeToggle');
            const isOptimized = optimizeToggle ? optimizeToggle.checked : false; 
            if (isOptimized) {
                const targetRes = document.getElementById('video_aspect_ratio').value; 
                const [targetWidth, targetHeight] = targetRes.split('x').map(Number);
                width = targetWidth; height = targetHeight; canvas.width = width; canvas.height = height;
                const scale = Math.min(targetWidth / img.width, targetHeight / img.height);
                const drawWidth = img.width * scale; const drawHeight = img.height * scale;
                const offsetX = (targetWidth - drawWidth) / 2; const offsetY = (targetHeight - drawHeight) / 2;
                ctx.fillStyle = '#000000'; ctx.fillRect(0, 0, width, height);
                ctx.imageSmoothingEnabled = true; ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img, offsetX, offsetY, drawWidth, drawHeight);
                currentImageBase64 = canvas.toDataURL('image/png');
            } else { currentImageBase64 = dataUrl; }
        } else {
            if (width > height && width > MAX_DIM) { height = Math.round(height * (MAX_DIM / width)); width = MAX_DIM; } 
            else if (height >= width && height > MAX_DIM) { width = Math.round(width * (MAX_DIM / height)); height = MAX_DIM; }
            canvas.width = width; canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            currentImageBase64 = canvas.toDataURL('image/jpeg', 0.85);
        }

        const isGraphical = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]'].includes(sel);
        const canEdit = isGraphical && isAvanzado;
        const displayToolbar = canEdit ? 'flex' : 'none';
        const displayMask = canEdit ? 'block' : 'none';
        
        // --- NUEVO LOGICA LIMPIA: Solo actualizamos la imagen y mostramos el panel PHP ---
        const imgPreviewContainer = document.getElementById('imgPreviewContainer');
        const visorContainer = document.getElementById('visorEdicionContainer');
        const imgPreview = document.getElementById('imgPreview');
        
        if (imgPreview) imgPreview.src = currentImageBase64;
        if (visorContainer) visorContainer.style.display = 'block';
        if (imgPreviewContainer) imgPreviewContainer.style.display = 'block';
        
        const zoomControls = document.getElementById('lienzoZoomControls');
        if (zoomControls) zoomControls.style.display = displayToolbar;
        // ----------------------------------------------------------------------------------

        currentDocumentText = ""; 
        
        maskCanvas = document.getElementById('maskCanvas');
        maskCanvas.style.display = displayMask; // Aseguramos mostrar/ocultar el canvas según permisos
        maskCanvas.width = width; 
        maskCanvas.height = height;
        maskCtx = maskCanvas.getContext('2d');
        maskCtx.lineCap = 'round';
        maskCtx.lineJoin = 'round';
        hasMaskStrokes = false;
        window.isErasing = false; 
        
        window.updateCursor(); 

        const startDraw = (ev) => {
            isDrawing = true;
            const rect = maskCanvas.getBoundingClientRect();
            const clientX = ev.clientX || (ev.touches && ev.touches[0].clientX);
            const clientY = ev.clientY || (ev.touches && ev.touches[0].clientY);
            lastX = (clientX - rect.left) * (maskCanvas.width / rect.width);
            lastY = (clientY - rect.top) * (maskCanvas.height / rect.height);
        };

        const draw = (ev) => {
            if (!isDrawing) return;
            ev.preventDefault(); 
            const rect = maskCanvas.getBoundingClientRect();
            const clientX = ev.clientX || (ev.touches && ev.touches[0].clientX);
            const clientY = ev.clientY || (ev.touches && ev.touches[0].clientY);
            const x = (clientX - rect.left) * (maskCanvas.width / rect.width);
            const y = (clientY - rect.top) * (maskCanvas.height / rect.height);
            
            maskCtx.lineWidth = document.getElementById('brushSize').value;
            
            if (window.isErasing) maskCtx.globalCompositeOperation = 'destination-out';
            else { maskCtx.globalCompositeOperation = 'source-over'; maskCtx.strokeStyle = '#ff0000'; }
            
            maskCtx.beginPath(); maskCtx.moveTo(lastX, lastY); maskCtx.lineTo(x, y); maskCtx.stroke();
            lastX = x; lastY = y; hasMaskStrokes = true;
        };

        const stopDraw = () => { 
            if (isDrawing) { isDrawing = false; window.saveMaskState(); }
        };
        
        window.maskStep = -1; window.saveMaskState();

        maskCanvas.addEventListener('mousedown', startDraw); maskCanvas.addEventListener('mousemove', draw);
        maskCanvas.addEventListener('mouseup', stopDraw); maskCanvas.addEventListener('mouseout', stopDraw);
        maskCanvas.addEventListener('touchstart', startDraw, {passive: false}); maskCanvas.addEventListener('touchmove', draw, {passive: false}); maskCanvas.addEventListener('touchend', stopDraw);

        updateUIForSelector(sel);
        document.getElementById('imgPreviewContainer').scrollIntoView({ behavior: 'smooth', block: 'center' });
    };
    img.src = dataUrl;
}

// --- EXTENSIONES: AUTO-CAPTION ---
async function autoCaptionReference(imageId, btnElement) {
    const imgEl = document.getElementById(imageId);
    if (!imgEl.src || imgEl.src === '') return;
    const originalText = btnElement.innerHTML;
    btnElement.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Analizando...';
    btnElement.disabled = true;
        
    try {
        const canvas = document.createElement('canvas'); const ctx = canvas.getContext('2d');
        let width = imgEl.naturalWidth || imgEl.width || 512; let height = imgEl.naturalHeight || imgEl.height || 512;
        if (width > height) { if (width > 512) { height *= 512 / width; width = 512; } } 
        else { if (height > 512) { width *= 512 / height; height = 512; } }
        canvas.width = width; canvas.height = height; ctx.drawImage(imgEl, 0, 0, width, height);
        let base64Image = canvas.toDataURL('image/jpeg', 0.8).split(',')[1];

        const fd = new FormData(); fd.append('action', 'vision_extract'); fd.append('image', base64Image);
        fd.append('idioma', APP_ENV.lang); fd.append('proposito', 'extension'); 
        const response = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await response.json();

        if (data.error) { SwalDark.fire({ icon: 'error', title: GartyLang.swal_err_title, text: data.error }); return; }
        const descripcion = data.response ? data.response.trim() : '';
        
        if (descripcion === '') {
            SwalDark.fire({ icon: 'warning', title: GartyLang.swal_err_title, text: GartyLang.err_ollama_no_text });
        } else {
            // Detectamos si el Modo Directo está activo
            const modoDirectoToggle = document.getElementById('modoDirectoToggle');
            const isDirectMode = modoDirectoToggle ? modoDirectoToggle.checked : false;
            
            if (isDirectMode) {
                // Si es Modo Directo, lo concatenamos en la caja del Prompt Positivo editable
                const posContent = document.getElementById('posContent');
                if (posContent) {
                    const currentTxt = posContent.innerText.trim();
                    posContent.innerText = currentTxt ? (currentTxt + ', ' + descripcion) : descripcion;
                }
            } else {
                // Si es Modo Normal, lo mandamos a la Idea Inicial tradicional
                const promptCaja = document.getElementById('descripcion');
                if (promptCaja) {
                    const currentTxt = promptCaja.value.trim();
                    promptCaja.value = currentTxt ? (currentTxt + ', ' + descripcion) : descripcion;
                }
            }
        }
        
    } catch (error) { 
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_sys_err_title, text: error.message });
    } finally { 
        btnElement.innerHTML = originalText; btnElement.disabled = false; 
    }
}

// --- EXTENSIONES: TOGGLES Y LECTORES ---
function toggleControlNetUI() {
    const isChecked = document.getElementById('controlNetToggle').checked;
    document.getElementById('controlNetUI').classList.toggle('d-none', !isChecked);
    if (!isChecked) clearControlNet();
}

function clearControlNet() {
    currentCnBase64 = null; document.getElementById('cnInput').value = ""; document.getElementById('cnPreviewContainer').classList.add('d-none');
}

const cnInputElem = document.getElementById('cnInput');
if (cnInputElem) {
    cnInputElem.onchange = () => {
        const file = cnInputElem.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let width = img.width; let height = img.height;
                if (width > height && width > 1024) { height = Math.round(height * (1024 / width)); width = 1024; } 
                else if (height >= width && height > 1024) { width = Math.round(width * (1024 / height)); height = 1024; }
                const canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                currentCnBase64 = canvas.toDataURL('image/jpeg', 0.85);
                document.getElementById('cnPreview').src = currentCnBase64;
                document.getElementById('cnPreviewContainer').classList.remove('d-none');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };
}

function toggleIpAdapterUI() {
    const toggle = document.getElementById('ipAdapterToggle');
    const ui = document.getElementById('ipAdapterUI') || document.getElementById('ipaUI');
    if (toggle && ui) { if (toggle.checked) ui.classList.remove('d-none'); else { ui.classList.add('d-none'); clearIpAdapter(); } }
}

function clearIpAdapter() {
    const input = document.getElementById('ipaInput') || document.getElementById('ipAdapterInput'); if (input) input.value = '';
    const container = document.getElementById('ipaPreviewContainer') || document.getElementById('ipAdapterPreviewContainer'); if (container) container.classList.add('d-none');
    const preview = document.getElementById('ipaPreview') || document.getElementById('ipAdapterPreview'); if (preview) preview.src = '';
    currentIpAdapterBase64 = null;
}

const ipaInputElem = document.getElementById('ipaInput') || document.getElementById('ipAdapterInput');
if (ipaInputElem) {
    ipaInputElem.addEventListener('change', function(e) {
        const file = e.target.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = function(event) {
            const img = new Image();
            img.onload = () => {
                let width = img.width; let height = img.height;
                if (width > height && width > 768) { height = Math.round(height * (768 / width)); width = 768; } 
                else if (height >= width && height > 768) { width = Math.round(width * (768 / height)); height = 768; }
                const canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                currentIpAdapterBase64 = canvas.toDataURL('image/jpeg', 0.85);
                const preview = document.getElementById('ipaPreview') || document.getElementById('ipAdapterPreview'); if (preview) preview.src = currentIpAdapterBase64;
                const container = document.getElementById('ipaPreviewContainer') || document.getElementById('ipAdapterPreviewContainer'); if (container) container.classList.remove('d-none');
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });
}

function toggleReactorUI() {
    const isChecked = document.getElementById('reactorToggle').checked;
    document.getElementById('reactorUI').classList.toggle('d-none', !isChecked);
    if (!isChecked) clearFace();
}

function clearFace() {
    currentFaceBase64 = null; document.getElementById('faceInput').value = ""; document.getElementById('facePreviewContainer').classList.add('d-none');
}

const faceInputElem = document.getElementById('faceInput');
if (faceInputElem) {
    faceInputElem.onchange = () => {
        const file = faceInputElem.files[0]; if (!file) return;
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let width = img.width; let height = img.height;
                if (width > height && width > 512) { height = Math.round(height * (512 / width)); width = 512; } 
                else if (height >= width && height > 512) { width = Math.round(width * (512 / height)); height = 512; }
                const canvas = document.createElement('canvas'); canvas.width = width; canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                currentFaceBase64 = canvas.toDataURL('image/jpeg', 0.85);
                document.getElementById('facePreview').src = currentFaceBase64;
                document.getElementById('facePreviewContainer').classList.remove('d-none');
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };
}

function clearAudio() {
    currentAudioBase64 = null;
    const audioIn = document.getElementById('audioInput'); if(audioIn) audioIn.value = "";
    const badge = document.getElementById('audioBadge'); if(badge) badge.remove();
}

const audioInpElem = document.getElementById('audioInput');
if (audioInpElem) {
    audioInpElem.onchange = () => {
        const file = audioInpElem.files[0]; if (!file) return;
        if (file.type.startsWith('audio/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                currentAudioBase64 = e.target.result;
                const imgPreviewContainer = document.getElementById('imgPreviewContainer');
                imgPreviewContainer.innerHTML += `
                    <div class="badge bg-success p-3 mt-2 mb-2 fs-6 w-100 text-start shadow-sm" id="audioBadge">
                        <i class="bi bi-music-note-beamed fs-4 me-2"></i> ${GartyLang.audio_ready}: ${file.name} 
                        <i class="bi bi-x-circle ms-auto float-end text-light" style="cursor:pointer;" onclick="clearAudio()" title="${GartyLang.audio_remove_title}"></i>
                    </div>`;
                imgPreviewContainer.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            showError(GartyLang.err_invalid_audio); audioInpElem.value = "";
        }
    };
}

function toggleFaceSwapPuro(activo) {
    setTimeout(() => {
        const inputPrompt = document.getElementById('descripcion'); 
        const btnArquitecto = document.getElementById('submitBtn'); 
        const btnAmplify = document.getElementById('amplifyBtn'); 
        const btnSurprise = document.getElementById('surpriseBtn'); 
        const btnDirecto = document.getElementById('gpuDirectBtn'); 
        const resultsArea = document.getElementById('results'); 
        const translateToggle = document.getElementById('translateToggleBlock'); 

        if (activo) {
            if (inputPrompt) { inputPrompt.dataset.oldValue = inputPrompt.value; inputPrompt.value = ''; inputPrompt.style.setProperty('display', 'none', 'important'); }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'none', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'none', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'none', 'important');
            if (translateToggle) translateToggle.classList.add('d-none'); 
            if (resultsArea) resultsArea.classList.add('d-none');
            
            if (btnDirecto) {
                btnDirecto.classList.remove('d-none'); btnDirecto.style.setProperty('display', 'inline-block', 'important');
                btnDirecto.dataset.oldText = btnDirecto.innerHTML;
                btnDirecto.innerHTML = '<i class="bi bi-person-bounding-box"></i> ' + GartyLang.btn_apply_faceswap;
                btnDirecto.className = 'btn flex-grow-1 text-dark fw-bold shadow btn-warning';
            }
        } else {
            if (inputPrompt) {
                const selCurrent = document.getElementById('selector') ? document.getElementById('selector').value : '';
                if (selCurrent !== '[VISION]') inputPrompt.style.setProperty('display', 'block', 'important');
                if (inputPrompt.dataset.oldValue !== undefined) { inputPrompt.value = inputPrompt.dataset.oldValue; delete inputPrompt.dataset.oldValue; }
            }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'inline-block', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'inline-block', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'inline-block', 'important');
            if (btnDirecto) {
                btnDirecto.innerHTML = btnDirecto.dataset.oldText || '<i class="bi bi-lightning-fill"></i> ' + GartyLang.btn_renderizar;
                btnDirecto.className = 'btn btn-gpu flex-grow-1 text-white fw-bold shadow';
                const sel = document.getElementById('selector').value;
                if (['[CHAT]', '[VISION]', '[LLM]'].includes(sel)) { btnDirecto.classList.add('d-none'); if (translateToggle) translateToggle.classList.toggle('d-none', sel !== '[LLM]'); } 
                else { btnDirecto.classList.remove('d-none'); if (translateToggle) translateToggle.classList.remove('d-none'); }
            }
        }
    }, 100);
}

function toggleRembgPuro(activo) {
    setTimeout(() => {
        const inputPrompt = document.getElementById('descripcion'); const btnArquitecto = document.getElementById('submitBtn'); 
        const btnAmplify = document.getElementById('amplifyBtn'); const btnSurprise = document.getElementById('surpriseBtn'); 
        const btnDirecto = document.getElementById('gpuDirectBtn'); const resultsArea = document.getElementById('results'); 
        const translateToggle = document.getElementById('translateToggleBlock'); 

        if (activo) {
            if (inputPrompt) { inputPrompt.dataset.oldValue = inputPrompt.value; inputPrompt.value = ''; inputPrompt.style.setProperty('display', 'none', 'important'); }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'none', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'none', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'none', 'important');
            if (translateToggle) translateToggle.classList.add('d-none'); if (resultsArea) resultsArea.classList.add('d-none');
            if (btnDirecto) {
                btnDirecto.classList.remove('d-none'); btnDirecto.style.setProperty('display', 'inline-block', 'important');
                btnDirecto.dataset.oldText = btnDirecto.innerHTML;
                btnDirecto.innerHTML = '<i class="bi bi-scissors"></i> Recortar Fondo Directo';
                btnDirecto.className = 'btn flex-grow-1 text-light fw-bold shadow btn-success';
            }
        } else {
            if (inputPrompt) {
                const selCurrent = document.getElementById('selector') ? document.getElementById('selector').value : '';
                if (selCurrent !== '[VISION]') inputPrompt.style.setProperty('display', 'block', 'important');
                if (inputPrompt.dataset.oldValue !== undefined) { inputPrompt.value = inputPrompt.dataset.oldValue; delete inputPrompt.dataset.oldValue; }
            }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'inline-block', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'inline-block', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'inline-block', 'important');
            if (btnDirecto) {
                btnDirecto.innerHTML = btnDirecto.dataset.oldText || '<i class="bi bi-lightning-fill"></i> ' + GartyLang.btn_renderizar;
                btnDirecto.className = 'btn btn-gpu flex-grow-1 text-white fw-bold shadow';
                const sel = document.getElementById('selector').value;
                if (['[CHAT]', '[VISION]', '[LLM]'].includes(sel)) { btnDirecto.classList.add('d-none'); if (translateToggle) translateToggle.classList.toggle('d-none', sel !== '[LLM]'); } 
                else { btnDirecto.classList.remove('d-none'); if (translateToggle) translateToggle.classList.remove('d-none'); }
            }
        }
    }, 100);
}

function toggleAdetailerPuro(activo) {
    setTimeout(() => {
        const inputPrompt = document.getElementById('descripcion'); const btnArquitecto = document.getElementById('submitBtn'); 
        const btnAmplify = document.getElementById('amplifyBtn'); const btnSurprise = document.getElementById('surpriseBtn'); 
        const btnDirecto = document.getElementById('gpuDirectBtn'); const resultsArea = document.getElementById('results'); 
        const translateToggle = document.getElementById('translateToggleBlock'); 

        if (activo) {
            if (inputPrompt) { inputPrompt.dataset.oldValue = inputPrompt.value; inputPrompt.value = ''; inputPrompt.style.setProperty('display', 'none', 'important'); }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'none', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'none', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'none', 'important');
            if (translateToggle) translateToggle.classList.add('d-none'); if (resultsArea) resultsArea.classList.add('d-none');
            if (btnDirecto) {
                btnDirecto.classList.remove('d-none'); btnDirecto.style.setProperty('display', 'inline-block', 'important');
                btnDirecto.dataset.oldText = btnDirecto.innerHTML;
                btnDirecto.innerHTML = '<i class="bi bi-person-check-fill"></i> ' + (GartyLang.btn_apply_adetailer || 'Restaurar Rostros Directo');
                btnDirecto.className = 'btn flex-grow-1 text-dark fw-bold shadow btn-info';
            }
        } else {
            if (inputPrompt) {
                const selCurrent = document.getElementById('selector') ? document.getElementById('selector').value : '';
                if (selCurrent !== '[VISION]') inputPrompt.style.setProperty('display', 'block', 'important');
                if (inputPrompt.dataset.oldValue !== undefined) { inputPrompt.value = inputPrompt.dataset.oldValue; delete inputPrompt.dataset.oldValue; }
            }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'inline-block', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'inline-block', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'inline-block', 'important');
            if (btnDirecto) {
                btnDirecto.innerHTML = btnDirecto.dataset.oldText || '<i class="bi bi-lightning-fill"></i> ' + GartyLang.btn_renderizar;
                btnDirecto.className = 'btn btn-gpu flex-grow-1 text-white fw-bold shadow';
                const sel = document.getElementById('selector').value;
                if (['[CHAT]', '[VISION]', '[LLM]'].includes(sel)) { btnDirecto.classList.add('d-none'); if (translateToggle) translateToggle.classList.toggle('d-none', sel !== '[LLM]'); } 
                else { btnDirecto.classList.remove('d-none'); if (translateToggle) translateToggle.classList.remove('d-none'); }
            }
        }
    }, 100);
}

function toggleDDColorPuro(activo) {
    setTimeout(() => {
        const inputPrompt = document.getElementById('descripcion'); const btnArquitecto = document.getElementById('submitBtn'); 
        const btnAmplify = document.getElementById('amplifyBtn'); const btnSurprise = document.getElementById('surpriseBtn'); 
        const btnDirecto = document.getElementById('gpuDirectBtn'); const resultsArea = document.getElementById('results'); 
        const translateToggle = document.getElementById('translateToggleBlock'); 

        // Si activamos colorear, apagamos Rembg por seguridad
        if (activo) {
            const rembg = document.getElementById('pureRembgToggle') || document.getElementById('rembgToggle');
            if (rembg && rembg.checked) { rembg.checked = false; rembg.dispatchEvent(new Event('change')); }
        }

        if (activo) {
            if (inputPrompt) { inputPrompt.dataset.oldValue = inputPrompt.value; inputPrompt.value = ''; inputPrompt.style.setProperty('display', 'none', 'important'); }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'none', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'none', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'none', 'important');
            if (translateToggle) translateToggle.classList.add('d-none'); if (resultsArea) resultsArea.classList.add('d-none');
            if (btnDirecto) {
                btnDirecto.classList.remove('d-none'); btnDirecto.style.setProperty('display', 'inline-block', 'important');
                btnDirecto.dataset.oldText = btnDirecto.innerHTML;
                btnDirecto.innerHTML = '<i class="bi bi-palette-fill"></i> Colorear Directo';
                btnDirecto.className = 'btn flex-grow-1 text-light fw-bold shadow btn-danger';
            }
        } else {
            if (inputPrompt) {
                const selCurrent = document.getElementById('selector') ? document.getElementById('selector').value : '';
                if (selCurrent !== '[VISION]') inputPrompt.style.setProperty('display', 'block', 'important');
                if (inputPrompt.dataset.oldValue !== undefined) { inputPrompt.value = inputPrompt.dataset.oldValue; delete inputPrompt.dataset.oldValue; }
            }
            if (btnArquitecto) btnArquitecto.style.setProperty('display', 'inline-block', 'important');
            if (btnAmplify) btnAmplify.style.setProperty('display', 'inline-block', 'important');
            if (btnSurprise) btnSurprise.style.setProperty('display', 'inline-block', 'important');
            if (btnDirecto) {
                btnDirecto.innerHTML = btnDirecto.dataset.oldText || '<i class="bi bi-lightning-fill"></i> ' + GartyLang.btn_renderizar;
                btnDirecto.className = 'btn btn-gpu flex-grow-1 text-white fw-bold shadow';
                const sel = document.getElementById('selector').value;
                if (['[CHAT]', '[VISION]', '[LLM]'].includes(sel)) { btnDirecto.classList.add('d-none'); if (translateToggle) translateToggle.classList.toggle('d-none', sel !== '[LLM]'); } 
                else { btnDirecto.classList.remove('d-none'); if (translateToggle) translateToggle.classList.remove('d-none'); }
            }
        }
    }, 100);
}

const mainImageInput = document.getElementById('imageInput');
if (mainImageInput) {
    mainImageInput.onchange = () => {
        const file = mainImageInput.files[0]; if (!file) return;
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => { setBaseImageFromDataUrl(e.target.result); };
            reader.readAsDataURL(file);
        } else if (file.type.startsWith('video/')) {
            const videoURL = URL.createObjectURL(file);
            const videoElement = document.createElement('video'); videoElement.src = videoURL; videoElement.muted = true;
            videoElement.onloadedmetadata = () => { videoElement.currentTime = Math.max(0, videoElement.duration - 0.1); };
            videoElement.onseeked = () => {
                const canvas = document.createElement('canvas'); canvas.width = videoElement.videoWidth; canvas.height = videoElement.videoHeight;
                canvas.getContext('2d').drawImage(videoElement, 0, 0, canvas.width, canvas.height);
                currentImageBase64 = canvas.toDataURL('image/png'); currentDocumentText = ""; 
                const imgPreviewContainer = document.getElementById('imgPreviewContainer');
                imgPreviewContainer.innerHTML = `
                <div class="badge bg-warning text-dark p-3 mt-2 mb-2 fs-6 w-100 text-start shadow-sm">
                    <i class="bi bi-film fs-4 me-2"></i> ${GartyLang.msg_frame_extracted} ${file.name} 
                    <i class="bi bi-x-circle ms-auto float-end" style="cursor:pointer;" onclick="if(typeof clearVideoUpload === 'function') clearVideoUpload()" title="${GartyLang.btn_quitar}"></i>
                </div>
                <img src="${currentImageBase64}" class="img-fluid rounded shadow-sm mt-2" style="max-height: 200px; border: 2px solid #ffc107;">`;
                imgPreviewContainer.style.display = 'block'; URL.revokeObjectURL(videoURL);
            };
        } else {
            const imgPreviewContainer = document.getElementById('imgPreviewContainer');
            imgPreviewContainer.innerHTML = `<div class="badge bg-secondary p-3 mb-2 fs-6 w-100 text-start shadow-sm"><i class="bi bi-file-earmark-text fs-4 me-2"></i> ${GartyLang.msg_doc_loaded}: ${file.name}</div>`;
            imgPreviewContainer.style.display = 'block'; currentImageBase64 = null; 
            if(typeof clearVideoUpload === 'function') clearVideoUpload();
            updateUIForSelector(document.getElementById('selector').value);
            
            if (file.type === 'application/pdf') {
                const reader = new FileReader();
                reader.onload = async function() {
                    try {
                        const typedarray = new Uint8Array(this.result);
                        const pdfjsLib = window['pdfjs-dist/build/pdf'];
                        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
                        const pdf = await pdfjsLib.getDocument(typedarray).promise;
                        let text = '';
                        for (let i = 1; i <= pdf.numPages; i++) {
                            const page = await pdf.getPage(i); const content = await page.getTextContent();
                            text += content.items.map(item => item.str).join(' ') + '\n';
                        }
                        currentDocumentText = text;
                    } catch (err) { showError("Error leyendo PDF: " + err.message); }
                };
                reader.readAsArrayBuffer(file);
            } else if (file.name.endsWith('.docx')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    mammoth.extractRawText({arrayBuffer: event.target.result}).then(function(result) { currentDocumentText = result.value; }).catch(function(err) { showError("Error leyendo DOCX."); });
                };
                reader.readAsArrayBuffer(file);
            } else {
                const reader = new FileReader(); reader.onload = function() { currentDocumentText = this.result; }; reader.readAsText(file);
            }
        }
    };
}

// --- BARRA DE PROGRESO ---
let progressInterval = null;
function startProgressBar(estimatedSeconds) {
    const pContainer = document.getElementById('progressContainer'); const pBar = document.getElementById('progressBar'); const pText = document.getElementById('progressPercent');
    if(!pContainer) return;
    pContainer.classList.remove('d-none'); pBar.style.width = '0%'; pText.innerText = '0%';
    let currentPercent = 0; const intervalMs = 200; const increment = 100 / ((estimatedSeconds * 1000) / intervalMs);
    clearInterval(progressInterval);
    progressInterval = setInterval(() => {
        currentPercent += increment; if(currentPercent > 95) currentPercent = 95; 
        pBar.style.width = currentPercent + '%'; pText.innerText = Math.round(currentPercent) + '%';
    }, intervalMs);
}

function stopProgressBar() {
    clearInterval(progressInterval);
    const pBar = document.getElementById('progressBar'); const pText = document.getElementById('progressPercent');
    if(pBar) pBar.style.width = '100%'; if(pText) pText.innerText = '100%';
    setTimeout(() => { const pc = document.getElementById('progressContainer'); if(pc) pc.classList.add('d-none'); if(pBar) pBar.style.width = '0%'; }, 1000);
}

// --- ACTUALIZACIÓN DE UI SEGÚN SELECTOR - APAREIX O NO ---
function updateUIForSelector(sel) {
    const modoDirectoToggle = document.getElementById('modoDirectoToggle');
    const modoDirectoWrapper = document.getElementById('modoDirectoWrapper');
    
    // Ocultar físicamente el interruptor en Visión y Chat
    if (modoDirectoWrapper) {
        modoDirectoWrapper.style.display = (['[CHAT]', '[VISION]'].includes(sel)) ? 'none' : 'block';
    }

    // Apagar el Modo Directo automáticamente si entramos en una categoría no compatible
    if (modoDirectoToggle && modoDirectoToggle.checked && ['[CHAT]', '[VISION]'].includes(sel)) {
        modoDirectoToggle.checked = false;
        if(typeof window.toggleModoIngreso === 'function') {
            window.toggleModoIngreso();
            return; // Detenemos aquí para no pisar la ejecución
        }
    }
    
    const isDirectMode = modoDirectoToggle ? modoDirectoToggle.checked : false;

    const btnUpload = document.getElementById('uploadBtn'); const btnGaleria = document.getElementById('btnCargarGaleria');
    const descInput = document.getElementById('descripcion'); const btnWildcards = document.getElementById('btnWildcards');
    const btnAudio = document.getElementById('audioUploadBtn'); const lblIdea = document.getElementById('lblIdea');

    const oldStyle = document.getElementById('garty-vision-style'); if (oldStyle) oldStyle.remove();

    if (sel === '[VISION]') {
        if (descInput) { descInput.hidden = true; descInput.classList.add('d-none'); descInput.style.setProperty('display', 'none', 'important'); }
        if (btnWildcards) { btnWildcards.hidden = true; btnWildcards.classList.add('d-none'); btnWildcards.style.setProperty('display', 'none', 'important'); }
        if (lblIdea) lblIdea.innerHTML = '<i class="bi bi-eye text-warning"></i> ' + GartyLang.tit_analisis_vis;
        if (btnGaleria) btnGaleria.classList.remove('d-none');
    } else {
        if (descInput) { descInput.hidden = false; descInput.classList.remove('d-none'); descInput.style.removeProperty('display'); }
        if (btnWildcards) {
            if (['[LLM]', '[CHAT]'].includes(sel)) { btnWildcards.hidden = true; btnWildcards.classList.add('d-none'); } 
            else { btnWildcards.hidden = false; btnWildcards.classList.remove('d-none'); btnWildcards.style.removeProperty('display'); }
        }
        if (lblIdea) lblIdea.innerHTML = GartyLang.tit_idea;
        if (['[LLM]', '[CHAT]'].includes(sel)) { if (descInput) descInput.placeholder = (sel === '[CHAT]') ? GartyLang.txt_xat_mensaje : GartyLang.txt_idea; } 
        else { if (descInput) descInput.placeholder = GartyLang.txt_arrast_png; }
        if (btnGaleria) btnGaleria.classList.remove('d-none');
    }

    if (btnAudio) btnAudio.classList.toggle('d-none', sel !== '[VIDEO]');

    if (['[LLM]', '[VISION]', '[CHAT]'].includes(sel)) {
        if (btnUpload) { btnUpload.classList.remove('d-none'); btnUpload.innerHTML = '<i class="bi bi-paperclip"></i> ' + GartyLang.btn_subiranalisis; }
    } else {
        if (btnUpload) {
            if (!isAvanzado) btnUpload.classList.add('d-none');
            else { btnUpload.classList.remove('d-none'); btnUpload.innerHTML = '<i class="bi bi-image"></i> ' + GartyLang.btn_subirbase; }
        }
    }

    const gpuDirectBtn = document.getElementById('gpuDirectBtn'); const llmDirectBtn = document.getElementById('llmDirectBtn');
    const translateToggleBlock = document.getElementById('translateToggleBlock'); const translateLabel = document.querySelector('label[for="autoTranslateToggle"]');
    const autoTranslateToggle = document.getElementById('autoTranslateToggle');
    
    if (gpuDirectBtn) {
        if (['[CHAT]', '[VISION]', '[LLM]'].includes(sel)) { 
            gpuDirectBtn.classList.add('d-none');
            if (sel === '[LLM]') { if (llmDirectBtn) llmDirectBtn.classList.remove('d-none'); } else { if (llmDirectBtn) llmDirectBtn.classList.add('d-none'); }
            if (translateToggleBlock) { translateToggleBlock.classList.remove('d-flex'); translateToggleBlock.classList.add('d-none'); translateToggleBlock.style.setProperty('display', 'none', 'important'); }
            if (autoTranslateToggle) autoTranslateToggle.checked = false; 
        } else if (sel === '[VIDEO]') {
            gpuDirectBtn.classList.remove('d-none'); if (llmDirectBtn) llmDirectBtn.classList.add('d-none');
            if (translateToggleBlock) { translateToggleBlock.classList.remove('d-none'); translateToggleBlock.classList.add('d-flex'); translateToggleBlock.style.setProperty('display', 'flex', 'important'); }
            if (translateLabel) translateLabel.innerHTML = '<i class="bi bi-translate"></i> ' + (GartyLang.ctrl_auto_trad3 || 'Auto-traducir Vídeo');
            gpuDirectBtn.innerHTML = '<i class="bi bi-film"></i> Vídeo Directo';
        } else {
            gpuDirectBtn.classList.remove('d-none'); if (llmDirectBtn) llmDirectBtn.classList.add('d-none');
            if (translateToggleBlock) { translateToggleBlock.classList.remove('d-none'); translateToggleBlock.classList.add('d-flex'); translateToggleBlock.style.setProperty('display', 'flex', 'important'); }
            if (translateLabel) translateLabel.innerHTML = '<i class="bi bi-translate"></i> ' + (GartyLang.ctrl_auto_trad2 || 'Auto-traducir Prompt');
            gpuDirectBtn.innerHTML = '<i class="bi bi-lightning-fill"></i> ' + GartyLang.btn_renderizar;
        }
    }

    const gpuArquitectoBtn = document.getElementById('gpuArquitectoBtn');
    if (gpuArquitectoBtn) {
        if (sel === '[VIDEO]') { gpuArquitectoBtn.innerHTML = '<i class="bi bi-film"></i> ' + GartyLang.btn_render_video; gpuArquitectoBtn.className = 'btn w-100 py-2 text-dark fw-bold shadow btn-warning'; } 
        else { gpuArquitectoBtn.innerHTML = '<i class="bi bi-gpu-card"></i> ' + GartyLang.btn_rendprompt; gpuArquitectoBtn.className = 'btn btn-gpu w-100 py-2 text-white fw-bold shadow'; }
    }

    const llmModelBlock = document.getElementById('llmModelBlock'); if (llmModelBlock) llmModelBlock.style.display = (['[LLM]', '[CHAT]'].includes(sel)) ? 'block' : 'none';
    const modelBlock = document.getElementById('modelBlock'); if (modelBlock) modelBlock.style.display = (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VISION]', '[CHAT]', '[VIDEO]'].includes(sel)) ? 'block' : 'none';
    // --- CONTROL DE VISIBILIDAD DE RESOLUCIONES (Principal y Manual) ---
    const propBlock = document.getElementById('proporcionIndependienteBlock');
    const manualResBlock = document.getElementById('manualResBoxesBlock');
    const mostrarResolucionImagen = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[CHAT]', '[VISION]'].includes(sel);

    if (propBlock) {
        propBlock.style.display = mostrarResolucionImagen ? 'block' : 'none';
    }
    if (manualResBlock) {
        manualResBlock.style.display = mostrarResolucionImagen ? 'block' : 'none';
    }
    
    const estilosContainer = document.getElementById('estilosContainer'); if (estilosContainer) estilosContainer.style.display = (['[LLM]', '[VISION]', '[VIDEO]', '[CHAT]'].includes(sel)) ? 'none' : 'block';
    
    const presetBlock = document.getElementById('presetBlock');
    if (presetBlock) { if (['[LLM]', '[VISION]', '[VIDEO]'].includes(sel) || (sel === '[CHAT]' && !isAvanzado)) presetBlock.style.display = 'none'; else presetBlock.style.display = 'block'; }

    const chatView = document.getElementById('chatView'); if (chatView) chatView.classList.toggle('d-none', sel !== '[CHAT]');
    const chatRoleBlock = document.getElementById('chatRoleBlock'); if (chatRoleBlock) chatRoleBlock.style.display = (sel === '[CHAT]') ? 'block' : 'none';
    const surpriseBtn = document.getElementById('surpriseBtn'); if (surpriseBtn) surpriseBtn.classList.toggle('d-none', sel === '[VISION]');
    
    const loraContainer = document.getElementById('loraContainer'); if (loraContainer) loraContainer.style.display = (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]', '[CHAT]', '[VISION]'].includes(sel)) ? 'block' : 'none';

    // (Aquí siguen los toggles de ControlNet, IpAdapter, Reactor, Adetailer, Denoise...)
    ['controlNet', 'ipAdapter', 'reactor', 'adetailer'].forEach(id => {
        const block = document.getElementById(id + 'Block');
        const toggle = document.getElementById(id + 'Toggle') || document.getElementById(id);
        if (block) {
            if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(sel) && isAvanzado) block.style.display = 'block';
            else { block.style.display = 'none'; if(toggle) toggle.checked = false; }
        }
    });

    const denoiseBlock = document.getElementById('denoiseBlock'); if (denoiseBlock) denoiseBlock.style.display = (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VISION]'].includes(sel)) ? 'block' : 'none';
    const batchBlock = document.getElementById('batchSize') ? document.getElementById('batchBlock') : null; if (batchBlock) batchBlock.style.display = (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(sel)) ? 'block' : 'none';
    
    const advBlock = document.getElementById('advancedSettingsBlock');
    if (advBlock) {
        const framesBlock = document.getElementById('videoFramesBlock'); if (framesBlock) framesBlock.style.display = (sel === '[VIDEO]') ? 'block' : 'none';
        if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]', '[VISION]'].includes(sel) && isAvanzado) {
            advBlock.style.display = 'block'; const cfgLabel = document.getElementById('cfgLabel');
            if (cfgLabel) cfgLabel.innerText = (sel === '[NATURAL_IMAGE]' || sel === '[VISION]') ? "GUIDANCE / CFG" : "CFG SCALE";
        } else advBlock.style.display = 'none';
    }

    const imgPreviewContainer = document.getElementById('imgPreviewContainer');
    const currentImgBase64 = currentImageBase64 || null; 
    const hasImage = imgPreviewContainer && imgPreviewContainer.style.display === 'block' && currentImgBase64 !== null;
    
    const videoOptimizeBlock = document.getElementById('videoOptimizeBlock'); if (videoOptimizeBlock) videoOptimizeBlock.style.display = (sel === '[VIDEO]') ? 'block' : 'none';
    
    const isStaticGraphical = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]'].includes(sel);
    const canEdit = isStaticGraphical && isAvanzado;
    
    const inpaintToolbar = document.getElementById('inpaintToolbar'); const outpaintToolbar = document.getElementById('outpaintToolbar');
    const maskCanvas = document.getElementById('maskCanvas');
    if (inpaintToolbar) inpaintToolbar.style.display = (canEdit && hasImage) ? 'flex' : 'none';
    if (outpaintToolbar) outpaintToolbar.style.display = (canEdit && hasImage) ? 'flex' : 'none';
    if (maskCanvas) maskCanvas.style.display = (canEdit && hasImage) ? 'block' : 'none';
    
    const hiresBlock = document.getElementById('hiresBlock');
    if (hiresBlock) {
        if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(sel) && isAvanzado) hiresBlock.style.display = 'block';
        else { hiresBlock.style.display = 'none'; const hiresToggle = document.getElementById('hiresToggle'); if (hiresToggle) hiresToggle.checked = false; }
    }
    
    const rembgBlock = document.getElementById('rembgBlock');
    if (rembgBlock) {
        if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(sel) && isAvanzado) rembgBlock.style.display = 'block';
        else { rembgBlock.style.display = 'none'; const rembgToggle = document.getElementById('rembgToggle'); if (rembgToggle) rembgToggle.checked = false; }
    }
    
    const ddcolorBlock = document.getElementById('ddcolorBlock');
    if (ddcolorBlock) {
        if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(sel) && isAvanzado) ddcolorBlock.style.display = 'block';
        else { ddcolorBlock.style.display = 'none'; const toggleDDColor = document.getElementById('toggleDDColor'); if (toggleDDColor) { toggleDDColor.checked = false; toggleDDColor.dispatchEvent(new Event('change')); } }
    }

    // --- NUEVO: Visibilidad y reseteo de IC-Light ---
    const icLightBlock = document.getElementById('icLightBlock');
    if (icLightBlock) {
        if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(sel) && isAvanzado) icLightBlock.style.display = 'block';
        else { 
            icLightBlock.style.display = 'none'; 
            const toggleIcLight = document.getElementById('iclight_enabled'); 
            if (toggleIcLight && toggleIcLight.checked) { toggleIcLight.checked = false; if(typeof toggleIcLightUI === 'function') toggleIcLightUI(); } 
        }
    }
    // ------------------------------------------------

    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) { submitBtn.innerText = (sel === '[VISION]') ? GartyLang.btn_desc_imagen : (sel === '[CHAT]' ? GartyLang.btn_envimensaje : GartyLang.btn_arquitecto); }

    // --- ENFORZAR OCULTACIÓN SI ESTAMOS EN MODO DIRECTO ---
    if (isDirectMode) {
        const btnArq = document.getElementById('submitBtn');
        if (btnArq) btnArq.style.setProperty('display', 'none', 'important');
        
        const btnAmp = document.getElementById('amplifyBtn');
        if (btnAmp) btnAmp.style.setProperty('display', 'none', 'important');
        
        const btnSur = document.getElementById('surpriseBtn');
        if (btnSur) btnSur.style.setProperty('display', 'none', 'important');
        
        const contenedorIdea = document.getElementById('contenedorIdea');
        if (contenedorIdea) contenedorIdea.classList.add('d-none');
        
        const btnLlm = document.getElementById('llmDirectBtn');
        const btnGpu = document.getElementById('gpuDirectBtn');

        // Ocultar/Mostrar el botón correcto forzando el !important para que no haya duplicados
        if (sel === '[LLM]') {
            if (btnLlm) { btnLlm.classList.remove('d-none'); btnLlm.style.setProperty('display', 'inline-block', 'important'); }
            if (btnGpu) { btnGpu.classList.add('d-none'); btnGpu.style.setProperty('display', 'none', 'important'); }
        } else {
            if (btnLlm) { btnLlm.classList.add('d-none'); btnLlm.style.setProperty('display', 'none', 'important'); }
            if (btnGpu) { btnGpu.classList.remove('d-none'); btnGpu.style.setProperty('display', 'inline-block', 'important'); }
        }

        // MOSTRAR/OCULTAR CAJA NEGATIVA DINÁMICAMENTE
        const pArea = document.getElementById('promptArea');
        const nArea = document.getElementById('negativeArea');
        const isGraph = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]'].includes(sel);
        
        if (pArea) pArea.classList.remove('d-none');
        if (nArea) {
            if (isGraph) { nArea.classList.remove('d-none'); } 
            else { nArea.classList.add('d-none'); }
        }
    } else {
        // MODO IDEA NORMAL: Quitamos los bloqueos para que CSS actúe de forma natural
        const btnArq = document.getElementById('submitBtn'); if(btnArq) btnArq.style.removeProperty('display');
        const btnAmp = document.getElementById('amplifyBtn'); if(btnAmp) btnAmp.style.removeProperty('display');
        const btnSur = document.getElementById('surpriseBtn'); if(btnSur) btnSur.style.removeProperty('display');
        const btnLlm = document.getElementById('llmDirectBtn'); if(btnLlm) btnLlm.style.removeProperty('display');
        const btnGpu = document.getElementById('gpuDirectBtn'); if(btnGpu) btnGpu.style.removeProperty('display');
        
        const contenedorIdea = document.getElementById('contenedorIdea'); 
        if(contenedorIdea) contenedorIdea.classList.remove('d-none');
    }
    
    // --- REAPLICAR MODO PURO TRAS EL RESETEO AL SUBIR IMÁGENES ---
    setTimeout(() => {
        const ddColorPuro = document.getElementById('pureDDColorToggle');
        if (ddColorPuro && ddColorPuro.checked && typeof toggleDDColorPuro === 'function') toggleDDColorPuro(true);
        
        const rembgPuro = document.getElementById('pureRembgToggle');
        if (rembgPuro && rembgPuro.checked && typeof toggleRembgPuro === 'function') toggleRembgPuro(true);
        
        const faceSwap = document.getElementById('pureFaceSwapToggle');
        if (faceSwap && faceSwap.checked && typeof toggleFaceSwapPuro === 'function') toggleFaceSwapPuro(true);
        
        const aDetailer = document.getElementById('pureAdetailerToggle');
        if (aDetailer && aDetailer.checked && typeof toggleAdetailerPuro === 'function') toggleAdetailerPuro(true);
    }, 150);
}

function clearResultsUI() {
    const isDirectMode = document.getElementById('modoDirectoToggle') && document.getElementById('modoDirectoToggle').checked;

    const results = document.getElementById('results'); 
    if(results && !isDirectMode) results.classList.add('d-none');
    
    const descArea = document.getElementById('descriptionArea'); if(descArea) descArea.classList.add('d-none'); 
    const promptArea = document.getElementById('promptArea'); if(promptArea && !isDirectMode) promptArea.classList.add('d-none');
    const negArea = document.getElementById('negativeArea'); if(negArea && !isDirectMode) negArea.classList.add('d-none');
    const llmAct = document.getElementById('llmActionArea'); if(llmAct) llmAct.classList.add('d-none');
    const imgRes = document.getElementById('imageResult'); if(imgRes) imgRes.innerHTML = ""; 
    const llmRes = document.getElementById('llmResponse'); if(llmRes) llmRes.classList.add('d-none');
    const arqAct = document.getElementById('arquitectoActionArea'); if(arqAct) arqAct.classList.add('d-none');
    
    // Limpiamos contenido
    const posContent = document.getElementById('posContent'); if(posContent) posContent.innerText = "";
    const negContent = document.getElementById('negContent'); if(negContent) negContent.innerText = "";
    
    currentPromptId = 0; lastGeneratedPrompt = { pos: "", neg: "" }; 
    
    // Solo recarga toda la interfaz si NO estamos en modo directo
    if (!isDirectMode) updateUIForSelector(document.getElementById('selector').value);
}

const mainSelector = document.getElementById('selector');
if (mainSelector) {
    mainSelector.addEventListener('change', (e) => {
        document.getElementById('descripcion').value = ""; 
        const imageInput = document.getElementById('imageInput'); if(imageInput) imageInput.value = ""; 
        if(typeof clearAudio === 'function') clearAudio(); 
        document.getElementById('imgPreviewContainer').style.display = 'none'; currentDocumentText = ""; currentImageBase64 = null; 
        clearResultsUI(); updateUIForSelector(e.target.value);
        if(typeof updateModelFilter === 'function') updateModelFilter(e.target.value); 
        if(typeof updateLoraFilter === 'function') updateLoraFilter(e.target.value);
        
        document.querySelectorAll('.lora-l-box').forEach(box => { box.style.display = (e.target.value === '[VIDEO]') ? 'block' : 'none'; });
        
        if (e.target.value === '[CHAT]') { const cajaIdea = document.getElementById('descriptionContent'); if (cajaIdea) cajaIdea.innerText = ''; }
    });
}

// --- PRESETS STYLES Y PROMPTS ---
function applyPresetToPrompts(pos, neg, presetVal) {
    if (!presetVal || typeof window.stylePresets === 'undefined' || !window.stylePresets[presetVal]) { return { pos: pos || "", neg: neg || "" }; }
    const preset = window.stylePresets[presetVal];
    let newPos = (pos || "").trim(); let newNeg = (neg || "").trim();

    let pPos = (preset.param_map && preset.param_map.prompt) ? preset.param_map.prompt : (preset.prompt_replace || preset.prompt || preset.pos || "");
    if (Array.isArray(pPos)) pPos = pPos.join(", "); else pPos = String(pPos);
    if (pPos.trim()) { if (pPos.match(/\{(prompt|value)\}/i)) { newPos = pPos.replace(/\{(prompt|value)\}/gi, newPos); } else { newPos = newPos ? (newPos + ", " + pPos) : pPos; } }

    let pNeg = (preset.param_map && preset.param_map.negativeprompt) ? preset.param_map.negativeprompt : (preset.negative_prompt || preset.negativeprompt || preset.negative || preset.neg || "");
    if (Array.isArray(pNeg)) pNeg = pNeg.join(", "); else pNeg = String(pNeg);
    if (pNeg.trim()) { if (pNeg.match(/\{(prompt|value)\}/i)) { newNeg = pNeg.replace(/\{(prompt|value)\}/gi, newNeg); } else { newNeg = newNeg ? (newNeg + ", " + pNeg) : pNeg; } }

    newPos = newPos.replace(/,\s*,/g, ', ').replace(/^,\s*/, '').replace(/,\s*$/, '').trim();
    newNeg = newNeg.replace(/,\s*,/g, ', ').replace(/^,\s*/, '').replace(/,\s*$/, '').trim();
    return { pos: newPos, neg: newNeg };
}

window.getPromptsWithPresets = function(basePos, baseNeg) {
    const selects = document.querySelectorAll('.preset-selector');
    let currentPos = basePos || ""; let currentNeg = baseNeg || "";
    if (selects.length > 0) {
        Array.from(selects).forEach(s => {
            if (s.value) { const applied = applyPresetToPrompts(currentPos, currentNeg, s.value); currentPos = applied.pos; currentNeg = applied.neg; }
        });
    }
    return { pos: currentPos, neg: currentNeg };
};

// --- MOTOR CORE DE GENERACIÓN ---
function showError(msg) { 
    SwalDark.fire({ icon: 'error', title: GartyLang.avis_atencio, html: msg, confirmButtonText: GartyLang.btn_entendido, confirmButtonColor: '#d33' }); 
}

function appendUIParametersToFormData(fd, forceSingle = false) {
    // 1. AJUSTES AVANZADOS DEL MOTOR
    if (document.getElementById('advancedSettingsBlock') && document.getElementById('advancedSettingsBlock').style.display !== 'none') {
        if(document.getElementById('stepsInput')) fd.append('steps', document.getElementById('stepsInput').value);
        if(document.getElementById('cfgInput')) fd.append('cfg', document.getElementById('cfgInput').value);
        if(document.getElementById('samplerInput')) fd.append('sampler', document.getElementById('samplerInput').value);
        if(document.getElementById('schedulerInput')) fd.append('scheduler', document.getElementById('schedulerInput').value);
        if(document.getElementById('seedInput')) fd.append('seed', document.getElementById('seedInput').value);
        if(document.getElementById('dynThreshToggle')) fd.append('dynamic_thresholding', document.getElementById('dynThreshToggle').checked);
        if(document.getElementById('videoFramesInput')) fd.append('video_frames', document.getElementById('videoFramesInput').value);        
        if(document.getElementById('video_aspect_ratio')) {
            const vW = document.getElementById('vidWidth') ? document.getElementById('vidWidth').value : 832;
            const vH = document.getElementById('vidHeight') ? document.getElementById('vidHeight').value : 480;
            fd.append('video_aspect_ratio', `${vW}x${vH}`);
        }
        if(document.getElementById('videoFpsSelector')) fd.append('video_fps', document.getElementById('videoFpsSelector').value);
        if(document.getElementById('videoFormat')) fd.append('video_format', document.getElementById('videoFormat').value);
    }
    
    // 2. BLOQUE DE LORAS (Ahora se cierra correctamente aquí)
    if (document.getElementById('loraContainer') && document.getElementById('loraContainer').style.display !== 'none') {
        document.querySelectorAll('.lora-row').forEach(row => {
            const lSelector = row.querySelector('.lora-selector'); const lVal = lSelector ? lSelector.value : null;
            if (lVal) { 
                fd.append('lora_names[]', lVal); 
                fd.append('lora_strengths_high[]', row.querySelector('.lora-strength-high') ? row.querySelector('.lora-strength-high').value : 0.8);
                fd.append('lora_strengths_low[]', row.querySelector('.lora-strength-low') ? row.querySelector('.lora-strength-low').value : 0.8);
            }
        });
    } // <-- CORRECCIÓN: Cerramos la llave aquí para liberar el resto de parámetros

    // 3. PARÁMETROS GENERALES Y EXTENSIONES GRÁFICAS
    const wInp = document.getElementById('imgWidth'); const hInp = document.getElementById('imgHeight');
    if (wInp && hInp) { fd.append('width', wInp.value); fd.append('height', hInp.value); }
        
    const denoiseSlider = document.getElementById('denoiseSlider'); if(denoiseSlider) fd.append('denoise', denoiseSlider.value);
    const batchSize = document.getElementById('batchSize'); if(batchSize) fd.append('batch_size', batchSize.value);

    // Hires Fix - UPSCALE
    const hiresToggle = document.getElementById('hiresToggle');
    if (hiresToggle && document.getElementById('hiresBlock').style.display !== 'none') {
        fd.append('hires_fix', hiresToggle.checked);
        if (hiresToggle.checked) {
            fd.append('upscale_model', document.getElementById('upscaleModelSelector').value); 
            fd.append('upscale_factor', document.getElementById('upscaleFactor').value);
            
            // --- NUEVO: Capturar AuraSR ---
            const auraToggle = document.getElementById('aurasrToggle');
            if (auraToggle && auraToggle.checked) fd.append('aurasr_enabled', 'true');
            // ------------------------------
            
            // Detección de Upscale Puro
            const isModoDirecto = document.getElementById('modoDirectoToggle') && document.getElementById('modoDirectoToggle').checked;
            const textoIdea = isModoDirecto 
                ? (document.getElementById('posContent') ? document.getElementById('posContent').innerText.trim() : '') 
                : (document.getElementById('descripcion') ? document.getElementById('descripcion').value.trim() : '');
            
            const hayImagen = (typeof currentImageBase64 !== 'undefined' && currentImageBase64 !== null) || 
                              (typeof compareImageA !== 'undefined' && compareImageA !== null);
            
            if (textoIdea === '' && hayImagen) {
                fd.append('pure_upscale', 'true');
            } else {
                fd.append('pure_upscale', 'false');
            }
        }
    }

    // Remove Background
    const rembgToggle = document.getElementById('rembgToggle');
    if (rembgToggle && document.getElementById('rembgBlock').style.display !== 'none') {
        fd.append('remove_background', rembgToggle.checked);
        const pureRembgToggle = document.getElementById('pureRembgToggle'); if (pureRembgToggle && pureRembgToggle.checked) fd.append('pure_rembg', 'true');
    }
    
    // DDColor (Coloreado Neural)
    const toggleDDColor = document.getElementById('toggleDDColor');
    if (toggleDDColor && document.getElementById('ddcolorBlock') && document.getElementById('ddcolorBlock').style.display !== 'none') {
        fd.append('ddcolor_enabled', toggleDDColor.checked);
        if (toggleDDColor.checked) {
            const ddModel = document.getElementById('ddcolor_model');
            if (ddModel) fd.append('ddcolor_model', ddModel.value);
            
            // NUEVO: Capturar si es modo puro
            const pureDDColorToggle = document.getElementById('pureDDColorToggle');
            if (pureDDColorToggle && pureDDColorToggle.checked) fd.append('pure_ddcolor', 'true');
        }
    }
    
    // --- NUEVO: IC-Light (Relighting Neural) ---
    const toggleIcLight = document.getElementById('iclight_enabled');
    if (toggleIcLight && toggleIcLight.checked && document.getElementById('icLightBlock') && document.getElementById('icLightBlock').style.display !== 'none') {
        fd.append('iclight_enabled', 'true');
        const dirSelect = document.getElementById('iclight_direction');
        const prInput = document.getElementById('iclight_prompt');
        const multSlider = document.getElementById('iclight_multiplier'); // <-- NUEVO: Captura el deslizador
        
        if (dirSelect) fd.append('iclight_direction', dirSelect.value);
        if (prInput && prInput.value.trim() !== '') fd.append('iclight_prompt', prInput.value.trim());
        if (multSlider) fd.append('iclight_multiplier', multSlider.value); // <-- NUEVO: Lo inyecta al FormData
    }
    // -------------------------------------------
    
   // LaMa Remover (Borrado Mágico)
    const toggleLama = document.getElementById('toggleLamaMode');
    if (toggleLama && toggleLama.checked) {
        fd.append('lama_enabled', 'true');
    }

    // IP-Adapter
    const ipAdapterToggle = document.getElementById('ipAdapterToggle');
    if (ipAdapterToggle && ipAdapterToggle.checked && currentIpAdapterBase64) {
        fd.append('ipadapter_enabled', 'true'); 
        fd.append('ipadapter_image', currentIpAdapterBase64.split(',')[1]);
            
        const ipaModel = document.getElementById('ipaModel') ? document.getElementById('ipaModel').value : '';
        const ipaWeightType = document.getElementById('ipaWeightType') ? document.getElementById('ipaWeightType').value : 'linear';
        const ipaNoise = document.getElementById('ipaNoise') ? document.getElementById('ipaNoise').value : '0.0';
        const ipaWeight = document.getElementById('ipaWeight') ? document.getElementById('ipaWeight').value : '0.8';
        const ipaStart = document.getElementById('ipaStart') ? document.getElementById('ipaStart').value : '0.0';
        const ipaEnd = document.getElementById('ipaEnd') ? document.getElementById('ipaEnd').value : '1.0';

        fd.append('ipa_model', ipaModel); 
        fd.append('ipa_weight_type', ipaWeightType);
        fd.append('ipa_noise', ipaNoise); 
        fd.append('ipa_weight', ipaWeight);
        fd.append('ipa_start', ipaStart); 
        fd.append('ipa_end', ipaEnd);
    }

    // FaceSwap / Reactor
    const reactorToggle = document.getElementById('reactorToggle');
    if (reactorToggle && reactorToggle.checked && currentFaceBase64) {
        fd.append('reactor_enabled', 'true'); fd.append('reactor_image', currentFaceBase64.split(',')[1]);
        const pureFaceSwapToggle = document.getElementById('pureFaceSwapToggle'); if (pureFaceSwapToggle && pureFaceSwapToggle.checked) fd.append('pure_faceswap', 'true');
        fd.append('reactor_target_index', document.getElementById('reactorTargetIndex').value); fd.append('reactor_source_index', document.getElementById('reactorSourceIndex').value);
        fd.append('reactor_restore_model', document.getElementById('reactorRestoreModel').value); fd.append('reactor_gender', document.getElementById('reactorGender').value);
        fd.append('reactor_detector', document.getElementById('reactorDetector').value); fd.append('reactor_fidelity', document.getElementById('reactorFidelity').value);
        fd.append('reactor_visibility', document.getElementById('reactorVisibility').value);
    }
        
    // ControlNet
    const controlNetToggle = document.getElementById('controlNetToggle');
    if (controlNetToggle && controlNetToggle.checked && currentCnBase64 && document.getElementById('cnModelSelector').value) {
        fd.append('controlnet_enabled', 'true'); fd.append('controlnet_image', currentCnBase64.split(',')[1]);
        fd.append('controlnet_model', document.getElementById('cnModelSelector').value); fd.append('controlnet_preprocessor', document.getElementById('cnPreprocessor').value);
        fd.append('controlnet_weight', document.getElementById('cnWeight').value); fd.append('controlnet_start', document.getElementById('cnStart').value);
        fd.append('controlnet_end', document.getElementById('cnEnd').value); fd.append('controlnet_mode', document.getElementById('cnMode').value);
    }

    // 4. IMÁGENES BASE / INPAINT / OUTPAINT
    if (document.getElementById('imgPreviewContainer') && document.getElementById('imgPreviewContainer').style.display !== 'none' && currentImageBase64) {
        fd.append('init_image', currentImageBase64.split(',')[1]);
        const extractedMask = extractMaskBase64();
        if (extractedMask) { fd.append('mask_data', extractedMask); }
        
        if (document.getElementById('outTop')) {
            fd.append('outpaint_top', document.getElementById('outTop').value); fd.append('outpaint_bottom', document.getElementById('outBottom').value);
            fd.append('outpaint_left', document.getElementById('outLeft').value); fd.append('outpaint_right', document.getElementById('outRight').value);
        }
        const maskBlur = document.getElementById('maskBlur'); if (maskBlur) fd.append('mask_blur', maskBlur.value);
        const inpaintFill = document.getElementById('inpaintFill'); if (inpaintFill) fd.append('inpaint_fill', inpaintFill.value);
        const inpaintArea = document.getElementById('inpaintArea'); if (inpaintArea) fd.append('inpaint_area', inpaintArea.value);
    }
    
    // 5. AUDIO Y UTILIDADES EXTRA
    if (currentAudioBase64) { fd.append('audio_data', currentAudioBase64.split(',')[1]); }
    
    const adetailerToggle = document.getElementById('adetailer');
    if (adetailerToggle && adetailerToggle.checked && document.getElementById('adetailerBlock').style.display !== 'none') {
        fd.append('adetailer', 'on');
        const adDenoise = document.getElementById('adetailerDenoise'); if (adDenoise) fd.append('adetailer_denoise', adDenoise.value);
        
        // --- AQUÍ VA LA CAPTURA DEL MODO PURO ---
        const pureAdToggle = document.getElementById('pureAdetailerToggle'); 
        if (pureAdToggle && pureAdToggle.checked) fd.append('pure_adetailer', 'true');
    }
        
    if (forceSingle) { fd.set('batch_size', 1); }
    return fd;
}

function showGeneratedPromptsInUI(p, n, selValue) {
    const results = document.getElementById('results'); if (results) results.classList.remove('d-none');
    const alertArq = document.querySelector('#results .alert'); if (alertArq) alertArq.classList.remove('d-none');
    document.getElementById('promptArea').classList.remove('d-none'); document.getElementById('posContent').innerText = p;
    const noise = ["none", "n/a", "null", "empty", ""];
    const toggleContainer = document.getElementById('manualNegativeToggleContainer'); const toggleSwitch = document.getElementById('manualNegativeToggle');

    if (n && n.trim() !== "" && !noise.includes(n.toLowerCase().trim())) {
        document.getElementById('negativeArea').classList.remove('d-none'); document.getElementById('negContent').innerText = n;
        if (toggleContainer) toggleContainer.classList.add('d-none'); if (toggleSwitch) toggleSwitch.checked = true;
    } else {
        document.getElementById('negativeArea').classList.add('d-none'); document.getElementById('negContent').innerText = "";
        if (toggleContainer && ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]'].includes(selValue)) {
            toggleContainer.classList.remove('d-none'); if (toggleSwitch) toggleSwitch.checked = false;
        } else { if (toggleContainer) toggleContainer.classList.add('d-none'); }
    }
    document.getElementById('llmActionArea').classList.toggle('d-none', selValue !== '[LLM]');
    const arqArea = document.getElementById('arquitectoActionArea'); if (arqArea) { arqArea.classList.toggle('d-none', ['[LLM]', '[CHAT]', '[VISION]'].includes(selValue)); }
    document.getElementById('promptArea').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function toggleManualNegative(mostrar) {
    const negArea = document.getElementById('negativeArea'); const negContent = document.getElementById('negContent');
    if (mostrar) { negArea.classList.remove('d-none'); setTimeout(() => negContent.focus(), 100); } 
    else { negArea.classList.add('d-none'); negContent.innerText = ""; }
}

async function amplifyBtnOnclick() {
    const descInput = document.getElementById('descripcion'); const idea = descInput.value.trim();
    if (!idea) { SwalDark.fire({ icon: 'info', title: GartyLang.swal_amp_empty_title, text: GartyLang.swal_amp_empty_desc }); return; }
    
    const amplifyBtn = document.getElementById('amplifyBtn');
    const originalContent = amplifyBtn.innerHTML; amplifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>'; amplifyBtn.disabled = true; 
    const submitBtn = document.getElementById('submitBtn'); if (submitBtn) submitBtn.disabled = true;
    
    const fd = new FormData(); fd.append('action', 'amplificar_prompt'); fd.append('descripcion', idea); fd.append('idioma', APP_ENV.lang.toUpperCase());
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const rawText = await res.text(); 
        try {
            const data = JSON.parse(rawText);
            if (data.prompt_amplificado) {
                descInput.value = data.prompt_amplificado;
                amplifyBtn.classList.replace('btn-info', 'btn-success');
                setTimeout(() => amplifyBtn.classList.replace('btn-success', 'btn-info'), 2000);
            } 
            else if (data.error) { SwalDark.fire({ icon: 'warning', title: GartyLang.swal_amp_vram_title, text: data.error, confirmButtonText: GartyLang.btn_entendido }); } 
            else { SwalDark.fire({ icon: 'question', title: GartyLang.swal_amp_unexp_title, text: GartyLang.swal_amp_unexp_desc + '\n' + JSON.stringify(data) }); }
        } catch (eJson) {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_amp_php_title, text: GartyLang.swal_amp_php_desc + '\n\n' + rawText.substring(0, 200) + '...' });
        }
    } catch (e) { SwalDark.fire({ icon: 'error', title: GartyLang.swal_amp_net_title, text: e.message }); } 
    finally { amplifyBtn.innerHTML = originalContent; amplifyBtn.disabled = false; if (submitBtn) submitBtn.disabled = false; }
}

if (document.getElementById('amplifyBtn')) document.getElementById('amplifyBtn').onclick = amplifyBtnOnclick;

async function visionToPrompt(btnElement) {
    const textContent = document.getElementById('descriptionContent').innerText.trim();
    if (!textContent || textContent === GartyLang.err_desc_fail) { showError(GartyLang.err_no_valid_desc); return; }
    
    const originalHtml = btnElement.innerHTML; 
    btnElement.disabled = true; 
    btnElement.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ${GartyLang.btn_extracting_prompt || 'Extrayendo prompt...'}`;
    
    // 1. Capturamos el modelo gráfico activo en el desplegable de la interfaz
    const modelSelect = document.getElementById('modelSelector');
    const selectedModel = modelSelect ? modelSelect.value : '';
    const lowerModel = selectedModel.toLowerCase();
    
    // 2. Determinamos inteligentemente el selector estructural según el modelo
    let targetSelector = '[SDXL]'; // Fallback por defecto
    
    if (lowerModel.includes('flux') || lowerModel.includes('chroma') || lowerModel.includes('sd3') || lowerModel.includes('z-image') || lowerModel.includes('zimage') || lowerModel.includes('qwen') || lowerModel.includes('krea') || lowerModel.includes('dit')) {
        targetSelector = '[NATURAL_IMAGE]';
    } else if (lowerModel.includes('15') || lowerModel.includes('v1-5') || lowerModel.includes('sd15')) {
        targetSelector = '[SD15]';
    } else if (modelSelect && modelSelect.selectedIndex >= 0) {
        const optCat = modelSelect.options[modelSelect.selectedIndex].getAttribute('data-categoria');
        if (optCat && (optCat === 'natural' || optCat === 'flux' || optCat === '[NATURAL_IMAGE]')) {
            targetSelector = '[NATURAL_IMAGE]';
        } else if (optCat && (optCat === 'sd15' || optCat === '[SD15]')) {
            targetSelector = '[SD15]';
        }
    }

    const fd = new FormData(); 
    fd.append('selector', targetSelector); 
    fd.append('descripcion', textContent);
    if (selectedModel) {
        fd.append('model_path', selectedModel);
    }
    
    // 3. Ejecutamos pasándole la arquitectura real, SIN tocar el botón principal verde (silentMainBtn = true)
    await executeProcess(fd, targetSelector, 2, null, true);
    
    btnElement.innerHTML = originalHtml; 
    btnElement.disabled = false;
}

async function executeProcess(fd, selValue, retries = 2, loadingId = null, silentMainBtn = false) {
    // Escudo: Verificamos la categoría real del selector DOM antes de decidir el texto del botón
    const currentCategory = document.getElementById('selector') ? document.getElementById('selector').value : selValue;
    const resetText = (currentCategory === '[VISION]') ? GartyLang.btn_desc_imagen : (currentCategory === '[CHAT]' ? GartyLang.btn_envimensaje : GartyLang.btn_generarprompt);
    
    if (!loadingId && retries === 2 && !silentMainBtn) { 
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ' + GartyLang.btn_arquipensan; 
    }
    let isRetrying = false; 

    try {
        const response = await fetch('procesar.php', { method: 'POST', body: fd });
        if (!response.ok) throw new Error(GartyLang.err_net_server || "Fallo de red al contactar con el servidor.");

        // --- LECTOR DEL STREAMING SILENCIOSO ---
        const reader = response.body.getReader();
        const decoder = new TextDecoder("utf-8");
        let buffer = "";
        let finalData = null;

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });

            let lines = buffer.split('\n');
            buffer = lines.pop(); // Guardamos el fragmento incompleto

            for (let line of lines) {
                if (line.trim() === '') continue;
                try {
                    const parsedData = JSON.parse(line);
                    
                    if (parsedData.error) {
                        finalData = parsedData; // Capturamos el error
                    } else if (parsedData.status === 'thinking') {
                        continue;
                    } else if (parsedData.choices) {
                        finalData = parsedData;
                    }
                } catch (e) { /* Línea JSON incompleta */ }
            }
        }

        const data = finalData || { error: GartyLang.err_empty_interrupted || "Respuesta vacía o interrumpida." };

        // --- PROCESAMIENTO HABITUAL ---
        if (data.error) {
            if ((data.error.includes("vacía") || data.error.includes("empty") || data.error.includes("Timeout") || data.error.includes("parser")) && retries > 0) {
                console.warn(GartyLang.warn_anomalous_response);
                isRetrying = true;
                if (loadingId) { document.getElementById(loadingId).innerHTML = `<div class="d-flex align-items-center text-warning"><div class="spinner-border spinner-border-sm me-2"></div> <small>${GartyLang.msg_retrying_ia} (${3 - retries}/2)...</small></div>`; } 
                else if (!silentMainBtn) { 
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn) submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ${GartyLang.msg_retrying_conn} (${3 - retries}/2)...`; 
                }
                return executeProcess(fd, selValue, retries - 1, loadingId, silentMainBtn);
            }
            SwalDark.fire({ icon: 'warning', title: GartyLang.swal_sys_warn, text: data.error, confirmButtonColor: '#17a2b8', confirmButtonText: '<i class="bi bi-check-lg"></i> ' + GartyLang.btn_understood_check });
            if (typeof stopProgressBar === 'function') stopProgressBar();
            return; 
        }

        currentPromptId = data.prompt_id || 0;
        let p = ""; let n = "";
        try { const parsed = JSON.parse(data.choices[0].message.content); p = parsed.prompt || ""; n = parsed.negative_prompt || ""; } catch(e) { p = data.choices[0].message.content; }

        if (selValue === '[CHAT]') { 
            if (loadingId) {
                const b = document.getElementById(loadingId); const ts = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                let contentHtml = p;
                if (isAvanzado && p.length > 5 && !p.includes(GartyLang.txt_ai_greeting)) {
                    const safeText = encodeURIComponent(p);
                    contentHtml += `<div class="mt-3 text-end border-top border-secondary pt-2" style="border-color: rgba(255,255,255,0.1) !important;"><button class="btn btn-sm btn-outline-info border-0" onclick="generateImageFromChatBtn(this, '${safeText}')" title="${GartyLang.btn_paint_title}"><i class="bi bi-gpu-card"></i> ${GartyLang.btn_paint_this}</button></div>`;
                }
                b.innerHTML = `${contentHtml}<span class="bubble-meta">${GartyLang.txt_architect} — ${ts}</span>`;
                document.getElementById('chatThreadContainer').scrollTop = document.getElementById('chatThreadContainer').scrollHeight;
            } else { addMessageToUI('ai', p); }
        } else {
            lastGeneratedPrompt.pos = p; lastGeneratedPrompt.neg = n;
            const applied = getPromptsWithPresets(p, n);
            showGeneratedPromptsInUI(applied.pos, applied.neg, selValue);
        }
    } catch (error) { 
        console.error(GartyLang.log_err_conn || "Fallo en la comunicación:", error);
        if (loadingId) {
            document.getElementById(loadingId).innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${GartyLang.txt_error}${error.message}</span><span class="bubble-meta">${GartyLang.txt_system}</span>`;
            document.getElementById('chatThreadContainer').scrollTop = document.getElementById('chatThreadContainer').scrollHeight;
        } else {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_err_conn_title, html: `${GartyLang.swal_err_conn_text}<br><br><span class="text-danger small" style="font-family: monospace;">${error.message}</span>`, confirmButtonColor: '#d33', confirmButtonText: GartyLang.btn_cerrar });
        }
        if (typeof stopProgressBar === 'function') stopProgressBar();
    } finally {
        if (!isRetrying && !silentMainBtn) { 
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = false; 
                submitBtn.innerHTML = resetText; 
            }
        }
    }
}

document.getElementById('promptForm').onsubmit = async (e) => {
    e.preventDefault();
    const selValue = document.getElementById('selector').value;
    const idea = document.getElementById('descripcion').value.trim();
    const presetArray = Array.from(document.querySelectorAll('.preset-selector')).map(s => s.value).filter(v => v !== "");
    const hasPreset = presetArray.length > 0;
    const hasFile = currentImageBase64 !== null || currentDocumentText !== "";
    const isGraphical = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]'].includes(selValue);

    // Comprobamos si es un Upscale Puro (está encendido PERO el campo de idea está vacío)
    const activeUpscaleForm = document.getElementById('hiresToggle') && document.getElementById('hiresToggle').checked;
    const isPureUpscaleForm = activeUpscaleForm && idea === '';

    // --- CORRECCIÓN 1: Inclusión estricta SOLO de Modos Puros ---
    const isPureModeForm = (document.getElementById('pureFaceSwapToggle') && document.getElementById('pureFaceSwapToggle').checked) ||
                           (document.getElementById('pureRembgToggle') && document.getElementById('pureRembgToggle').checked) ||
                           (document.getElementById('pureAdetailerToggle') && document.getElementById('pureAdetailerToggle').checked) ||
                           (document.getElementById('pureDDColorToggle') && document.getElementById('pureDDColorToggle').checked) ||
                           (document.getElementById('toggleLamaMode') && document.getElementById('toggleLamaMode').checked) ||
                           (document.getElementById('iclight_enabled') && document.getElementById('iclight_enabled').checked) ||
                           isPureUpscaleForm; // <-- Ahora sí es 100% inteligente

    // Ahora la validación respeta si hay un modo puro activo para ignorar la idea vacía
    if (!idea && !hasFile && !isPureModeForm) {
        if (isGraphical && hasPreset) {
            clearResultsUI(); lastGeneratedPrompt = { pos: "", neg: "" };
            const applied = getPromptsWithPresets("", "");
            showGeneratedPromptsInUI(applied.pos, applied.neg, selValue);
            return;
        } else { 
            SwalDark.fire({ icon: 'warning', title: GartyLang.avis_idea1, text: GartyLang.avis_idea2, confirmButtonText: GartyLang.btn_entendido, confirmButtonColor: '#f39c12' }); return; 
        }
    }

    // --- NUEVO AVISO SALVAVIDAS PARA UPSCALE PURO ---
    const isUpscaleOn = document.getElementById('hiresToggle') && document.getElementById('hiresToggle').checked;
    const txtIdeaVal = document.getElementById('descripcion') ? document.getElementById('descripcion').value.trim() : '';
    
    // Si hay imagen subida, el Upscale está activo, el texto está vacío y no estamos usando los modos especiales de chat/visión:
    if (hasFile && txtIdeaVal === '' && isUpscaleOn && !['[VISION]', '[CHAT]'].includes(selValue)) {
        SwalDark.fire({
            icon: 'info',
            title: GartyLang.swal_pure_upscale_title || 'Modo Upscale Puro',
            text: GartyLang.swal_pure_upscale_text || 'Para escalar la imagen que has subido, NO necesitas al Arquitecto. Pulsa directamente el botón de "Renderizar" (el del rayo).',
            confirmButtonText: '<i class="bi bi-check2-circle"></i> ' + (GartyLang.btn_entendido || 'Entendido')
        });
        return;
    }
    // ------------------------------------------------

    if (selValue !== '[CHAT]') clearResultsUI(); 
    document.getElementById('submitBtn').disabled = true;

    const fd = new FormData(); fd.append('selector', selValue); fd.append('descripcion', idea);
    const llmSel = document.getElementById('llmModelSelector');
    if (llmSel && llmSel.value && ['[LLM]', '[CHAT]'].includes(selValue)) fd.append('model_path', llmSel.value);
    
    if (selValue === '[CHAT]') {
        const roleSel = document.getElementById('chatRoleSelector');
        let chosenRole = roleSel ? roleSel.value : '';
        if (chosenRole === 'custom') chosenRole = document.getElementById('customRoleInput').value;
        if (chosenRole) fd.append('chat_role', chosenRole);
    }

    if (selValue === '[CHAT]') { 
        const lowerIdea = idea.toLowerCase();
        const isLlmCmd = lowerIdea.startsWith('/imagen ') || lowerIdea.startsWith('/img ');
        const isGpuCmd = lowerIdea.startsWith('/gpu ') || lowerIdea.startsWith('/render ');

        if (isLlmCmd || isGpuCmd) {
            const promptIdea = isGpuCmd ? idea.replace(/^\/(gpu|render)\s+/i, '') : idea.replace(/^\/(imagen|img)\s+/i, '');
            addMessageToUI('user', idea); document.getElementById('descripcion').value = "";
            const thread = document.getElementById('chatThreadContainer'); const loadingId = 'loading-' + Date.now();
            const b = document.createElement('div'); b.className = `chat-bubble bubble-ai`; b.id = loadingId;
            
            if (!isAvanzado) {
                b.innerHTML = `<span class="text-danger"><i class="bi bi-shield-x"></i> ${GartyLang.chat_err_role_images || 'No tienes permisos.'}</span>`;
                thread.appendChild(b); thread.scrollTop = thread.scrollHeight; document.getElementById('submitBtn').disabled = false; return;
            }

            // --- ARREGLO 1: ADIÓS AL UNDEFINED CON FALLBACKS ---
            const msgInicial = isGpuCmd ? (GartyLang.msg_gpu_direct || 'Enviando a GPU...') : (GartyLang.msg_gpu_ai || 'Consultando a la IA...');
            b.innerHTML = `<div class="d-flex align-items-center text-info"><div class="spinner-border spinner-border-sm me-2"></div> <small>${msgInicial}</small></div>`;
            thread.appendChild(b); thread.scrollTop = thread.scrollHeight;

            const processChatImg = async (retries = 2) => {
                try {
                    let finalP = promptIdea; let finalN = ""; let chatPromptId = 0;
                    
                    if (isLlmCmd) {
                        const fdPrompt = new FormData(); 
                        
                        // --- ARREGLO 2: ADIÓS AL [SDXL] HARDCODEADO ---
                        // Lee el modelo gráfico real que tienes seleccionado, o usa NATURAL_IMAGE por defecto
                        const currentCat = document.getElementById('selector') ? document.getElementById('selector').value : '[NATURAL_IMAGE]';
                        const targetCat = ['[CHAT]', '[LLM]', '[VISION]'].includes(currentCat) ? '[NATURAL_IMAGE]' : currentCat;
                        fdPrompt.append('selector', targetCat); 
                        fdPrompt.append('descripcion', promptIdea);
                        
                        // --- ARREGLO DEL STREAMING Y DEL ERROR JSON ---
                        const resPrompt = await fetch('procesar.php', { method: 'POST', body: fdPrompt }); 
                        const reader = resPrompt.body.getReader();
                        const decoder = new TextDecoder("utf-8");
                        let buffer = ""; let dataPrompt = null;

                        while (true) {
                            const { done, value } = await reader.read();
                            if (done) break;
                            buffer += decoder.decode(value, { stream: true });
                            let lines = buffer.split('\n');
                            buffer = lines.pop(); 
                            for (let line of lines) {
                                if (line.trim() === '') continue;
                                try {
                                    const parsedData = JSON.parse(line);
                                    if (parsedData.error) dataPrompt = parsedData;
                                    else if (parsedData.choices) dataPrompt = parsedData;
                                } catch (e) {}
                            }
                        }
                        if (!dataPrompt) dataPrompt = { error: "Respuesta vacía" };

                        if (dataPrompt.error) {
                            if ((dataPrompt.error.includes("vacía") || dataPrompt.error.includes("empty")) && retries > 0) {
                                document.getElementById(loadingId).innerHTML = `<div class="d-flex align-items-center text-warning"><div class="spinner-border spinner-border-sm me-2"></div> <small> ${GartyLang.chat_msg_ia_thinking || 'Pensando...'} (${3 - retries}/2)...</small></div>`;
                                return processChatImg(retries - 1);
                            }
                            throw new Error(dataPrompt.error);
                        }
                        chatPromptId = dataPrompt.prompt_id || 0; 
                        try { const parsed = JSON.parse(dataPrompt.choices[0].message.content); finalP = parsed.prompt || ""; finalN = parsed.negative_prompt || ""; } 
                        catch(e) { finalP = dataPrompt.choices[0].message.content; }
                    }

                    document.getElementById(loadingId).innerHTML = `<b>${GartyLang.chat_msg_prompt_to_gen || 'Prompt generado:'}</b><br><code class="text-light">${finalP}</code><br><br><div class="d-flex align-items-center text-info"><div class="spinner-border spinner-border-sm me-2"></div> <small>${GartyLang.chat_msg_rendering_gpu || 'Enviando a renderizar...'}</small></div>`;
                    thread.scrollTop = thread.scrollHeight;
                    
                    let fdImg = new FormData();
                    const applied = getPromptsWithPresets(finalP, finalN); finalP = applied.pos; finalN = applied.neg;
                    fdImg.append('action', 'generar_imagen'); fdImg.append('prompt', finalP); fdImg.append('negative_prompt', finalN);
                    
                    const selectedModel = document.getElementById('modelSelector') ? document.getElementById('modelSelector').value : "";
                    const modelToUse = (selectedModel && !selectedModel.includes('Ollama') && !selectedModel.includes('lukey')) ? selectedModel : "";
                    fdImg.append('model_path', modelToUse);
                    if (chatPromptId > 0) fdImg.append('historial_id', chatPromptId);
                    
                    fdImg = appendUIParametersToFormData(fdImg, true); 
                    fdImg.append('async_mode', 'true');

                    const resImg = await fetch('procesar.php', { method: 'POST', body: fdImg });
                    const dataImg = await resImg.json();
                    if (dataImg.error) throw new Error(dataImg.error); 
                    
                    if (dataImg.status === 'ticket_issued' && dataImg.prompt_id) {
                        document.getElementById(loadingId).innerHTML = `<b>${GartyLang.chat_msg_prompt_designed || 'Diseñado:'}</b><br><code class="text-light">${finalP}</code><br><br><div class="d-flex align-items-center text-warning"><div class="spinner-grow spinner-grow-sm me-2"></div> <small>${GartyLang.chat_msg_gpu_processing || 'Procesando en GPU'} (${dataImg.prompt_id})...</small></div>`;
                        thread.scrollTop = thread.scrollHeight;

                        const chatRadarInterval = setInterval(async () => {
                            let fdCheck = new FormData(); fdCheck.append('action', 'check_ticket'); fdCheck.append('prompt_id', dataImg.prompt_id); fdCheck.append('historial_id', dataImg.historial_id || chatPromptId);
                            try {
                                const resCheck = await fetch('procesar.php', { method: 'POST', body: fdCheck }); const dataCheck = await resCheck.json();
                                if (dataCheck.status === 'completed') {
                                    clearInterval(chatRadarInterval);
                                    if (dataCheck.images && dataCheck.images.length > 0) {
                                        let html = `<b>${GartyLang.chat_msg_prompt_label || 'Prompt:'}</b> <code class="text-light">${finalP}</code><div class="row g-2 mt-2">`;
                                        dataCheck.images.forEach(img => { html += construirTarjetaImagen(img, dataImg.historial_id || chatPromptId, true); });
                                        html += `</div><span class="bubble-meta">${GartyLang.chat_meta_gpu_engine || 'Motor GPU - '}${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
                                        document.getElementById(loadingId).innerHTML = html;
                                        
                                        if (document.hidden) {
                                            if (typeof tocarCampana === 'function') tocarCampana();
                                            if (typeof avisarAlSistema === 'function') avisarAlSistema(GartyLang.notif_chat_gpu_title || "¡Chat GPU Libre!", GartyLang.notif_chat_gpu_body || "Tu imagen ha terminado.", dataCheck.images[0]);
                                        }
                                    } else {
                                        document.getElementById(loadingId).innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${GartyLang.chat_err_gen_failed || 'Fallo de generación'}</span><span class="bubble-meta">${GartyLang.chat_meta_system || 'Sistema'}</span>`;
                                    }
                                    thread.scrollTop = thread.scrollHeight;
                                }
                            } catch (e) { console.warn(GartyLang.log_chat_radar_cut || 'Radar cortado', e); }
                        }, 3000);
                    } else { throw new Error(GartyLang.err_no_gpu_ticket || 'No hay ticket de GPU'); }
                } catch(e) { 
                    const prefijoErr = GartyLang.chat_err_prefix || "Error: ";
                    document.getElementById(loadingId).innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${prefijoErr}${e.message}</span><span class="bubble-meta">${GartyLang.chat_meta_system || 'Sistema'}</span>`; 
                } finally { document.getElementById('submitBtn').disabled = false; thread.scrollTop = thread.scrollHeight; }
            };
            processChatImg();
            return;
        }

        if (currentDocumentText) {
            fd.append('document_text', currentDocumentText);
            addMessageToUI('user', idea || GartyLang.chat_msg_analyze_doc, null, true);
            document.getElementById('descripcion').value = ""; document.getElementById('imageInput').value = ""; document.getElementById('imgPreviewContainer').style.display = 'none'; currentDocumentText = "";
            const loadingId = 'loading-' + Date.now(); const b = document.createElement('div'); b.className = `chat-bubble bubble-ai`; b.id = loadingId;
            b.innerHTML = `<div class="d-flex align-items-center text-info"><div class="spinner-border spinner-border-sm me-2"></div> <small>${GartyLang.chat_msg_analyzing_doc}</small></div>`;
            document.getElementById('chatThreadContainer').appendChild(b); document.getElementById('chatThreadContainer').scrollTop = document.getElementById('chatThreadContainer').scrollHeight;
            executeProcess(fd, selValue, 2, loadingId); return;
        }
        
        if (currentImageBase64) {
            addMessageToUI('user', idea || GartyLang.chat_msg_analyze_img, currentImageBase64, false);
            fd.append('image_data', currentImageBase64);
            document.getElementById('imageInput').value = ""; document.getElementById('imgPreviewContainer').style.display = 'none'; currentImageBase64 = null; document.getElementById('descripcion').value = "";
            const loadingId = 'loading-' + Date.now(); const b = document.createElement('div'); b.className = `chat-bubble bubble-ai`; b.id = loadingId;
            b.innerHTML = `<div class="d-flex align-items-center text-info"><div class="spinner-border spinner-border-sm me-2"></div> <small>${GartyLang.chat_msg_analyzing_img}</small></div>`;
            document.getElementById('chatThreadContainer').appendChild(b); document.getElementById('chatThreadContainer').scrollTop = document.getElementById('chatThreadContainer').scrollHeight;
            executeProcess(fd, selValue, 2, loadingId); return;
        }

        addMessageToUI('user', idea); document.getElementById('descripcion').value = ""; 
        const loadingId = 'loading-' + Date.now(); const b = document.createElement('div'); b.className = `chat-bubble bubble-ai`; b.id = loadingId;
        b.innerHTML = `<div class="d-flex align-items-center text-info"><div class="spinner-border spinner-border-sm me-2"></div> <small>${GartyLang.chat_msg_thinking}</small></div>`;
        document.getElementById('chatThreadContainer').appendChild(b); document.getElementById('chatThreadContainer').scrollTop = document.getElementById('chatThreadContainer').scrollHeight;
        executeProcess(fd, selValue, 2, loadingId); return;
    }

    if (currentDocumentText) fd.append('document_text', currentDocumentText);

    if (selValue === '[VISION]') {
        fd.append('action', 'vision_extract'); fd.append('proposito', 'exhaustivo');
        if (currentImageBase64) {
            fd.append('image', currentImageBase64.split(',')[1] || currentImageBase64);
            fd.append('idioma', APP_ENV.lang);
            const resetText = GartyLang.btn_desc_imagen;
            
            const fetchVisionWithRetry = async (retriesLeft) => {
                const totalRetries = 5; const currentTry = totalRetries - retriesLeft + 1; const btn = document.getElementById('submitBtn');
                if (currentTry === 1) { btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> ' + GartyLang.msg_loading_vram; } 
                else { btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> ${GartyLang.msg_retrying_num} (${currentTry}/${totalRetries + 1})...`; }

                try {
                    const res = await fetch('procesar.php', { method: 'POST', body: fd });
                    const rawText = await res.text(); let data;
                    try { data = JSON.parse(rawText); } catch (eJson) { throw new Error(GartyLang.err_timeout_invalid); }
                    if (data.error) {
                        const errStr = data.error.toLowerCase();
                        if ((errStr.includes("vacía") || errStr.includes("empty") || errStr.includes("loading") || errStr.includes("timeout") || errStr.includes("tardó")) && retriesLeft > 0) {
                            const waitTime = errStr.includes("loading") ? 4000 : 2000; 
                            await new Promise(resolve => setTimeout(resolve, waitTime));
                            return fetchVisionWithRetry(retriesLeft - 1);
                        }
                        throw new Error(data.error); 
                    }
                    if (data.response !== undefined) {
                        const content = data.response.trim();
                        if (content === "" && retriesLeft > 0) { await new Promise(resolve => setTimeout(resolve, 2000)); return fetchVisionWithRetry(retriesLeft - 1); }
                        document.getElementById('results').classList.remove('d-none');
                        document.getElementById('descriptionArea').classList.remove('d-none');
                        document.getElementById('descriptionContent').innerText = content || GartyLang.err_model_empty;
                        btn.disabled = false; btn.innerText = resetText; updateUIForSelector('[VISION]'); 
                    } else { throw new Error(GartyLang.err_vision_fail); }
                } catch (e) {
                    SwalDark.fire({ icon: 'error', title: GartyLang.swal_analysis_fail_title, text: e.message || GartyLang.err_critical_net, confirmButtonText: '<i class="bi bi-check2-circle"></i> ' + GartyLang.btn_understood });
                    btn.disabled = false; btn.innerText = resetText; updateUIForSelector('[VISION]'); 
                }
            };
            fetchVisionWithRetry(5);
        } else { showError(GartyLang.err_valid_img_vision); document.getElementById('submitBtn').disabled = false; }
    } else { executeProcess(fd, selValue); }
};

// --- LLM EJECUCIÓN NORMAL Y DIRECTA (CON STREAMING) ---
async function runLlm() {
    const resBox = document.getElementById('llmResponse');
    resBox.classList.remove('d-none'); resBox.innerText = GartyLang.msg_synthesizing_draft;
    
    const fd = new FormData(); fd.append('ejecutar_llm', 'true'); fd.append('prompt_final', document.getElementById('posContent').innerText);
    const modelSel = document.getElementById('llmModelSelector'); if (modelSel && modelSel.value) fd.append('llm_model', modelSel.value);
    if (currentPromptId > 0) fd.append('prompt_id', currentPromptId);
    
    // Bandera para avisar a PHP de que queremos la respuesta en tiempo real
    fd.append('stream', 'true'); 
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        if (!res.ok) throw new Error("Error de conexión con el servidor");

        // --- INICIO DE LECTURA STREAMING ---
        const reader = res.body.getReader();
        const decoder = new TextDecoder("utf-8");
        let buffer = "";
        let textoIAResolucion = "";
        resBox.innerText = ""; // Limpiamos para que empiece a escribir letra a letra

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });

            let lines = buffer.split('\n');
            buffer = lines.pop(); // El último fragmento suele estar incompleto, lo guardamos para el siguiente ciclo

            for (let line of lines) {
                if (line.trim() === '') continue;
                try {
                    const data = JSON.parse(line);
                    
                    if (data.error) {
                        SwalDark.fire({ icon: 'error', title: GartyLang.swal_sys_warn_title, text: data.error, confirmButtonText: '<i class="bi bi-check2-circle"></i> ' + GartyLang.btn_understood });
                        if (textoIAResolucion === "") resBox.innerText = "";
                        return;
                    }

                    let chunkText = "";
                    if (data.message && data.message.content) chunkText = data.message.content;
                    else if (data.response !== undefined) chunkText = data.response;

                    if (chunkText) {
                        textoIAResolucion += chunkText;
                        // Pintamos en tiempo real y ocultamos el XML de los modelos abliterated
                        resBox.innerText = textoIAResolucion.replace(/<think>[\s\S]*?<\/think>/gi, '').replace(/<think>[\s\S]*/gi, '\n[Pensando...]\n');
                    }

                    if (data.new_prompt_id) currentPromptId = data.new_prompt_id;
                } catch (e) { /* Paquete incompleto, se procesará en el siguiente ciclo */ }
            }
        }
        
        // Limpieza final
        resBox.innerText = textoIAResolucion.replace(/<think>[\s\S]*?<\/think>\n*/gi, '').trim();

    } catch(e) {
        console.error(GartyLang.log_err_llm_exec, e);
        if (resBox.innerText === "") resBox.innerText = GartyLang.llm_err_network || "Error de red.";
    }
}

async function runLlmDirect() {
    const isModoDirecto = document.getElementById('modoDirectoToggle') && document.getElementById('modoDirectoToggle').checked;
    let ideaInicial = "";
    let finalPrompt = "";
    
    if (isModoDirecto) {
        finalPrompt = document.getElementById('posContent').innerText.trim();
        if (!finalPrompt) { showError(GartyLang.avis_llm1); return; }
        ideaInicial = finalPrompt;
    } else {
        ideaInicial = document.getElementById('descripcion').value.trim();
        if (!ideaInicial) { showError(GartyLang.avis_llm1); return; }
        finalPrompt = ideaInicial;
    }
    
    const btn = document.getElementById('llmDirectBtn'); const originalBtnText = btn.innerHTML;
    btn.disabled = true;
    
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> ${GartyLang.btn_procesllm}`;
    document.getElementById('results').classList.remove('d-none');
    const alertArq = document.querySelector('#results .alert'); if (alertArq) alertArq.classList.add('d-none');
    document.getElementById('descriptionArea').classList.add('d-none'); 
    
    // ARREGLO: No ocultar las cajas si estamos en Modo Directo
    if (!isModoDirecto) {
        document.getElementById('promptArea').classList.add('d-none');
        document.getElementById('negativeArea').classList.add('d-none'); 
    }
    document.getElementById('arquitectoActionArea').classList.add('d-none');
    
    const llmActionArea = document.getElementById('llmActionArea'); llmActionArea.classList.remove('d-none');
    const runLlmBtnNormal = llmActionArea.querySelector('button'); if (runLlmBtnNormal) runLlmBtnNormal.classList.add('d-none');
    const resBox = document.getElementById('llmResponse'); resBox.classList.remove('d-none'); resBox.innerText = GartyLang.llm_msg_typing;
    
    const fd = new FormData(); fd.append('ejecutar_llm', 'true'); fd.append('prompt_final', finalPrompt); fd.append('descripcion_original', ideaInicial);
    const modelSel = document.getElementById('llmModelSelector'); if (modelSel && modelSel.value) fd.append('llm_model', modelSel.value);
    
    // Bandera para avisar a PHP de que queremos la respuesta en tiempo real
    fd.append('stream', 'true'); 
    
    if (currentDocumentText) { fd.append('document_text', currentDocumentText); currentDocumentText = ""; }
    if (currentImageBase64) {
        fd.append('image_data', currentImageBase64); currentImageBase64 = null; 
        document.getElementById('imgPreviewContainer').style.display = 'none'; document.getElementById('imageInput').value = "";
    }
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        if (!res.ok) throw new Error("Error de red");

        // --- INICIO DE LECTURA STREAMING ---
        const reader = res.body.getReader();
        const decoder = new TextDecoder("utf-8");
        let buffer = "";
        let textoIAResolucion = "";
        resBox.innerText = "";

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            buffer += decoder.decode(value, { stream: true });

            let lines = buffer.split('\n');
            buffer = lines.pop();

            for (let line of lines) {
                if (line.trim() === '') continue;
                try {
                    const data = JSON.parse(line);
                    
                    if (data.error) {
                        SwalDark.fire({ icon: 'error', title: GartyLang.swal_llm_err_title, text: data.error, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
                        if (textoIAResolucion === "") resBox.innerText = GartyLang.llm_msg_err_cancel;
                        return; 
                    }
                    
                    let chunkText = "";
                    if (data.message && data.message.content) chunkText = data.message.content;
                    else if (data.response !== undefined) chunkText = data.response;

                    if (chunkText) {
                        textoIAResolucion += chunkText;
                        resBox.innerText = textoIAResolucion.replace(/<think>[\s\S]*?<\/think>/gi, '').replace(/<think>[\s\S]*/gi, '\n[Pensando...]\n');
                    }

                    if (data.new_prompt_id) currentPromptId = data.new_prompt_id;
                } catch (e) { /* Paquete incompleto */ }
            }
        }
        
        resBox.innerText = textoIAResolucion.replace(/<think>[\s\S]*?<\/think>\n*/gi, '').trim();

    } catch(e) {
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_net_err_title, text: `${GartyLang.swal_net_err_text}${e.message}`, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
        if (resBox.innerText === "") resBox.innerText = GartyLang.llm_msg_err_conn;
    } finally { btn.innerHTML = originalBtnText; btn.disabled = false; if (runLlmBtnNormal) runLlmBtnNormal.classList.remove('d-none'); }
}

// --- GPU Y RADAR ---
async function runGpu(mode = 'directo') {
    const resDiv = document.getElementById('imageResult'); const resultsArea = document.getElementById('results'); 
    const autoTranslate = document.getElementById('autoTranslateToggle') ? document.getElementById('autoTranslateToggle').checked : false;
    const originalCategory = document.getElementById('selector').value;
    const ideaInicial = document.getElementById('descripcion').value.trim(); 
    
    let finalPrompt = ""; let finalNegPrompt = "";
    let buttonUsed = mode === 'directo' ? document.getElementById('gpuDirectBtn') : document.getElementById('gpuArquitectoBtn');
    // Comprobamos si es un Upscale Puro (está encendido el Upscale PERO no hay ningún prompt/idea para generar de cero)
    const activeUpscale = document.getElementById('hiresToggle') && document.getElementById('hiresToggle').checked;
    const currentPromptText = document.getElementById('descripcion') ? document.getElementById('descripcion').value.trim() : '';
    const isPureUpscaleActive = activeUpscale && currentPromptText === '';

    // --- CORRECCIÓN DEFINITIVA DE MODOS PUROS EN GPU ---
    const isPureMode = (document.getElementById('pureFaceSwapToggle') && document.getElementById('pureFaceSwapToggle').checked) || 
                       (document.getElementById('pureRembgToggle') && document.getElementById('pureRembgToggle').checked) ||
                       (document.getElementById('pureAdetailerToggle') && document.getElementById('pureAdetailerToggle').checked) ||
                       (document.getElementById('pureDDColorToggle') && document.getElementById('pureDDColorToggle').checked) ||
                       (document.getElementById('toggleLamaMode') && document.getElementById('toggleLamaMode').checked) ||
                       (document.getElementById('iclight_enabled') && document.getElementById('iclight_enabled').checked) ||
                       isPureUpscaleActive; // <-- Ahora SOLO es modo puro si NO hay texto. Si hay texto, te dejará generar de cero!

    const isModoDirecto = document.getElementById('modoDirectoToggle') && document.getElementById('modoDirectoToggle').checked;

    // Si es modo directo estándar (y NO hemos activado el toggle manual de Prompts)
    if (mode === 'directo' && !isModoDirecto) {
        if (!ideaInicial && !isPureMode) { showError(GartyLang.avis_gengpu1); return; }
        buttonUsed.disabled = true;
        
        if (autoTranslate) {
            const originalTextBtn = buttonUsed.innerHTML;
            buttonUsed.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> ${GartyLang.gpu_msg_translating}`;
            try {
                const fdTrad = new FormData(); fdTrad.append('action', 'traducir_rapido'); fdTrad.append('texto', ideaInicial);
                const llmSel = document.getElementById('llmModelSelector'); if (llmSel && llmSel.value) fdTrad.append('llm_model', llmSel.value);
                const resTrad = await fetch('procesar.php', { method: 'POST', body: fdTrad }); const textTrad = await resTrad.text(); 
                try {
                    const dataTrad = JSON.parse(textTrad);
                    if (dataTrad.error_curl || dataTrad.debug_api) console.warn(GartyLang.log_warn_trans_internal, dataTrad);
                    if (dataTrad.traduccion && dataTrad.traduccion.trim() !== '') finalPrompt = dataTrad.traduccion; else finalPrompt = ideaInicial;
                } catch(eJson) { console.error(GartyLang.log_err_trans_invalid, textTrad); finalPrompt = ideaInicial; }
            } catch (e) { console.error(GartyLang.log_err_trans_net, e); finalPrompt = ideaInicial; }
            buttonUsed.innerHTML = originalTextBtn;
        } else { 
            finalPrompt = ideaInicial; 
        }
        
        const applied = getPromptsWithPresets(finalPrompt, ""); 
        finalPrompt = applied.pos; 
        finalNegPrompt = applied.neg;
        
    } else {
        // Ejecución de "Arquitecto" o "Modo Directo Manual" (Leemos de las cajas)
        finalPrompt = document.getElementById('posContent').innerText.trim();
        finalNegPrompt = document.getElementById('negContent') ? document.getElementById('negContent').innerText.trim() : "";
        if (!finalPrompt && !isPureMode) { showError(GartyLang.avis_no_prompt_arq || "Por favor, introduce un prompt."); return; }
        
        // --- SOLUCIÓN: Si estamos en Modo Directo manual, aplicamos los presets a lo introducido en las cajas ---
        if (isModoDirecto) {
            const applied = getPromptsWithPresets(finalPrompt, finalNegPrompt);
            finalPrompt = applied.pos;
            finalNegPrompt = applied.neg;
        }

        buttonUsed.disabled = true;
    }
    
    const isReactorOn = document.getElementById('reactorToggle') && document.getElementById('reactorToggle').checked;
    if (isReactorOn && (!currentFaceBase64 || currentFaceBase64.indexOf(',') === -1)) { SwalDark.fire({icon: 'warning', title: GartyLang.swal_reactor_title, text: GartyLang.swal_reactor_text}); buttonUsed.disabled = false; return; }
    const isIpAdapterOn = document.getElementById('ipAdapterToggle') && document.getElementById('ipAdapterToggle').checked;
    if (isIpAdapterOn && (!currentIpAdapterBase64 || currentIpAdapterBase64.indexOf(',') === -1)) { SwalDark.fire({icon: 'warning', title: GartyLang.swal_ip_title, text: GartyLang.swal_ip_text}); buttonUsed.disabled = false; return; }
    const isControlNetOn = document.getElementById('controlNetToggle') && document.getElementById('controlNetToggle').checked;
    if (isControlNetOn && (!currentCnBase64 || currentCnBase64.indexOf(',') === -1)) { SwalDark.fire({icon: 'warning', title: GartyLang.swal_cn_title, text: GartyLang.swal_cn_text}); buttonUsed.disabled = false; return; }
    if (isPureMode && !currentImageBase64) { SwalDark.fire({icon: 'warning', title: GartyLang.swal_pure_title, text: GartyLang.swal_pure_text}); buttonUsed.disabled = false; return; }

    const originalBtnText = buttonUsed.innerHTML;
    // (Por si acaso falta la variable de idioma, le ponemos fallback)
    buttonUsed.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> ${GartyLang.gpu_sending_spinner || 'Enviando...'}`;
    resDiv.innerHTML = '';
    
    // ARREGLO: Solo ocultamos el bloque si NO estamos en el nuevo Modo Directo
    if (mode === 'directo' && resultsArea && !isModoDirecto) { 
        resultsArea.classList.add('d-none'); 
    }
    
    if (typeof startProgressBar === 'function') startProgressBar(20); 
    
    let fd = new FormData(); fd.append('action', 'generar_imagen'); fd.append('selector', originalCategory);
    fd.append('prompt', finalPrompt); fd.append('negative_prompt', finalNegPrompt); fd.append('descripcion_original', ideaInicial || finalPrompt); 
    fd.append('model_path', document.getElementById('modelSelector') ? document.getElementById('modelSelector').value : "");
    fd.append('async_mode', 'true'); 
    if (currentPromptId > 0) fd.append('historial_id', currentPromptId);
    fd = appendUIParametersToFormData(fd);

    const modoActual = document.getElementById('selector').value;
    const numFrames = parseInt(document.getElementById('videoFramesInput').value) || 33;
    const tieneAudio = typeof currentAudioBase64 !== 'undefined' && currentAudioBase64 !== null;
    
    // --- LECTURA INTELIGENTE DEL MODELO LTX ---
    const modelSelect = document.getElementById('modelSelector');
    const nombreModeloVisible = modelSelect && modelSelect.options[modelSelect.selectedIndex] ? modelSelect.options[modelSelect.selectedIndex].text.toLowerCase() : '';
    const modeloSeleccionado = fd.get('model_path') ? fd.get('model_path').toLowerCase() : '';
    const esLTX = modeloSeleccionado.includes('ltx') || nombreModeloVisible.includes('ltx');

    // --- CONTROL DE VÍDEOS LARGOS AUTOREGRESIVOS (LTX) ---
    if (modoActual === '[VIDEO]' && numFrames > 65 && esLTX) {
        if (tieneAudio) console.log("Detectado vídeo LTX largo con audio...");
        if (typeof lanzarVideoEncadenado === 'function') {
            lanzarVideoEncadenado(fd, numFrames).finally(() => {
                buttonUsed.innerHTML = originalBtnText; buttonUsed.disabled = false;
            });
        }
        return; 
    }

    // === NUEVO: INTERCEPCIÓN PARA SEMILLAS ÚNICAS Y BUCLE INFINITO ===
    const batchVal = document.getElementById('batchSize') ? document.getElementById('batchSize').value : "1";
    const seedVal = document.getElementById('seedInput') ? parseInt(document.getElementById('seedInput').value) : -1;

    // 1. GESTIÓN DEL BUCLE INFINITO ('inf')
    if (batchVal === 'inf') {
        window.bucleInfinitoActivo = true;
        window.configBucleInfinito = { fd: fd, resDiv: resDiv, buttonUsed: buttonUsed, originalCategory: originalCategory };
        
        buttonUsed.innerHTML = `<i class="bi bi-stop-circle-fill"></i> ${GartyLang.btn_stop_inf || 'DETENER BUCLE ∞'}`;
        buttonUsed.classList.replace('btn-gpu', 'btn-danger');
        buttonUsed.classList.replace('btn-primary', 'btn-danger');
        buttonUsed.disabled = false;
        buttonUsed.onclick = window.detenerBucleInfinito;

        // Inyectamos 2 tareas iniciales para calentar motores y tener el buffer lleno
        window.dispararTareaInfinita();
        window.dispararTareaInfinita();
        return;
    }

    // 2. GESTIÓN DE SEMILLAS ÚNICAS PARA LOTES (2 o 4 imágenes)
    if (batchVal === '2' || batchVal === '4') {
        const totalImagenes = parseInt(batchVal);
        for (let i = 0; i < totalImagenes; i++) {
            let fdSingle = new FormData();
            fd.forEach((value, key) => fdSingle.append(key, value));
            fdSingle.set('batch_size', 1); // Forzamos 1 a 1 para que ComfyUI no repita semilla
            
            // Si la semilla era -1 generamos una nueva para cada imagen. Si era fija (ej. 5000), le sumamos i (5000, 5001...)
            let semillaUnica = (seedVal === -1 || isNaN(seedVal)) 
                ? Math.floor(Math.random() * 9007199254740991) 
                : (seedVal + i);
            fdSingle.set('seed', semillaUnica);

            enviarTareaIndividualGpu(fdSingle, resDiv, buttonUsed, currentPromptId, originalCategory, i + 1, totalImagenes);
        }
        return;
    }
    // === FIN DE LA INTERCEPCIÓN (Si es 1 imagen, continúa con el try normal de abajo) ===

    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd }); const data = await res.json();
        if (data.error) {
            if (typeof stopProgressBar === 'function') stopProgressBar(); 
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_gen_cancel_title, html: data.error, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
            buttonUsed.innerHTML = originalBtnText; buttonUsed.disabled = false; return; 
        }
        
        if (data.status === 'ticket_issued' && data.prompt_id) {
            currentPromptId = data.historial_id || currentPromptId; 
            buttonUsed.innerText = GartyLang.btn_procesando;
            localStorage.setItem('garty_tarea_pendiente', JSON.stringify({ prompt_id: data.prompt_id, db_id: currentPromptId, categoria: originalCategory }));
            iniciarRadarGpu(data.prompt_id, resDiv, buttonUsed, currentPromptId, originalCategory); 
        } else {
            if (typeof stopProgressBar === 'function') stopProgressBar();
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_gpu_err_title, text: GartyLang.swal_gpu_err_text, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
            buttonUsed.innerHTML = originalBtnText; buttonUsed.disabled = false; return;
        }
    } catch (e) { 
        if (typeof stopProgressBar === 'function') stopProgressBar();
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_net_arq_title, text: `${GartyLang.swal_net_arq_text}${e.message}`, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
        buttonUsed.innerHTML = originalBtnText; buttonUsed.disabled = false;
    }
}

window.activeRadars = window.activeRadars || {};
window.bucleInfinitoActivo = false;
window.configBucleInfinito = null;

// --- LIMPIEZA Y RESTAURACIÓN UNIVERSAL DE BOTONES ---
window.restaurarBotonesGpu = function() {
    window.bucleInfinitoActivo = false;
    window.configBucleInfinito = null;

    const btnDirecto = document.getElementById('gpuDirectBtn');
    if (btnDirecto) {
        btnDirecto.innerHTML = '<i class="bi bi-lightning-fill"></i> ' + GartyLang.btn_renderizar;
        btnDirecto.classList.remove('btn-danger', 'btn-warning'); // Limpiamos rojo (LaMa) y naranja (IC-Light)
        btnDirecto.classList.add('btn-gpu');
        btnDirecto.disabled = false;
        btnDirecto.onclick = () => runGpu('directo');
    }

    const btnArq = document.getElementById('gpuArquitectoBtn');
    if (btnArq) {
        btnArq.innerHTML = '<i class="bi bi-gpu-card"></i> ' + GartyLang.btn_rendprompt;
        btnArq.classList.remove('btn-danger', 'btn-warning');
        btnArq.classList.add('btn-gpu');
        btnArq.disabled = false;
        btnArq.onclick = () => runGpu('arquitecto');
    }

    const selectorEl = document.getElementById('selector');
    if (selectorEl && typeof updateUIForSelector === 'function') {
        updateUIForSelector(selectorEl.value);
    }

    // --- NUEVO: SI LAMA ESTÁ ACTIVADO, RESTAURAMOS SU BOTÓN ROJO ---
    const isLama = document.getElementById('toggleLamaMode') && document.getElementById('toggleLamaMode').checked;
    if (isLama && typeof toggleLamaUI === 'function') {
        toggleLamaUI(true);
    }

    // --- NUEVO: SI IC-LIGHT ESTÁ ACTIVADO, RESTAURAMOS SU BOTÓN NARANJA ---
    const isIcLight = document.getElementById('iclight_enabled') && document.getElementById('iclight_enabled').checked;
    if (isIcLight && typeof toggleIcLightUI === 'function') {
        toggleIcLightUI();
    }
    // ---------------------------------------------------------------
};

// --- DETENER BUCLE INFINITO (Suave - Deja terminar lo que está en la VRAM) ---
window.detenerBucleInfinito = function() {
    window.restaurarBotonesGpu();
    SwalDark.fire({ icon: 'info', title: GartyLang.swal_loop_stop_title, text: GartyLang.swal_loop_stop_text, confirmButtonText: '<i class="bi bi-check2-circle"></i> ' + GartyLang.btn_entendido });
};

// --- PURGAR TAREA ATASCADA O COLA (En seco - Botón pequeño / Cancelar) ---
window.forzarCancelacionTarea = function() {
    // 1. Matamos los radares locales del navegador
    if (window.activeRadars) {
        Object.values(window.activeRadars).forEach(intervalId => clearInterval(intervalId));
        window.activeRadars = {};
    }
    if (window.currentRadarInterval) clearInterval(window.currentRadarInterval);
    if (typeof stopProgressBar === 'function') stopProgressBar();
    localStorage.removeItem('garty_tarea_pendiente'); 
    
    // 2. ORDEN NUCLEAR AL SERVIDOR: Avisamos a PHP para que vacíe la cola en ComfyUI
    let fdCancel = new FormData();
    fdCancel.append('action', 'cancelar_tarea');
    fetch('procesar.php', { method: 'POST', body: fdCancel }).catch(e => console.warn(GartyLang.log_err_cancel_net || "Purga red:", e));

    // 3. Restauramos la interfaz
    window.restaurarBotonesGpu();

    SwalDark.fire({ icon: 'info', title: GartyLang.swal_kill_title, text: GartyLang.swal_kill_text, confirmButtonText: '<i class="bi bi-check2-circle"></i> ' + GartyLang.btn_entendido });
};

// --- DISPARADOR DE TAREAS INDIVIDUALES (Blindado contra fallos de red) ---
async function enviarTareaIndividualGpu(fd, resDiv, buttonUsed, dbId, originalCategory, numActual, total) {
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd }); 
        const data = await res.json();
        if (data.error) {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_gen_cancel_title, html: data.error });
            if (buttonUsed && Object.keys(window.activeRadars || {}).length === 0) {
                buttonUsed.disabled = false;
                buttonUsed.innerText = GartyLang.btn_generar;
            }
            return; 
        }
        if (data.status === 'ticket_issued' && data.prompt_id) {
            if (buttonUsed && numActual === 1) buttonUsed.innerText = `${GartyLang.btn_procesando} (1/${total})...`;
            iniciarRadarGpu(data.prompt_id, resDiv, buttonUsed, data.historial_id || dbId, originalCategory); 
        }
    } catch (e) { 
        console.error(GartyLang.log_err_single_task, e); 
        if (buttonUsed && Object.keys(window.activeRadars || {}).length === 0) {
            buttonUsed.disabled = false;
            buttonUsed.innerText = GartyLang.btn_generar;
        }
    }
}

window.dispararTareaInfinita = async function() {
    if (!window.bucleInfinitoActivo || !window.configBucleInfinito) return;
    const cfg = window.configBucleInfinito;
    
    let fdSingle = new FormData();
    cfg.fd.forEach((value, key) => fdSingle.append(key, value));
    fdSingle.set('batch_size', 1);
    fdSingle.set('seed', Math.floor(Math.random() * 9007199254740991));
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fdSingle }); 
        const data = await res.json();
        if (data.status === 'ticket_issued' && data.prompt_id) {
            iniciarRadarGpu(data.prompt_id, cfg.resDiv, null, data.historial_id || 0, cfg.originalCategory); 
        }
    } catch (e) { console.error(GartyLang.log_err_inf_loop, e); }
};

function iniciarRadarGpu(promptId, targetDiv, btnElement, dbId, originalCategory) {
    const pText = document.getElementById('progressText');
    if(pText) pText.innerHTML = `<span style="color: #0dcaf0 !important; font-weight: bold;">${GartyLang.radar_msg_rendering} (${promptId})</span>`;

    let intentosRadar = 0; const maxIntentos = 600; 
    
    window.activeRadars = window.activeRadars || {};
    if (window.activeRadars[promptId]) clearInterval(window.activeRadars[promptId]);
    if (window.currentRadarInterval) clearInterval(window.currentRadarInterval);

    window.activeRadars[promptId] = setInterval(async () => {
        intentosRadar++;
        if (intentosRadar > maxIntentos || !window.activeRadars[promptId]) {
            if (window.activeRadars[promptId]) { clearInterval(window.activeRadars[promptId]); delete window.activeRadars[promptId]; }
            if (Object.keys(window.activeRadars).length === 0) {
                if (typeof stopProgressBar === 'function') stopProgressBar();
                localStorage.removeItem('garty_tarea_pendiente');
                if (btnElement && !window.bucleInfinitoActivo) {
                    btnElement.innerHTML = `<i class="bi bi-clock-history"></i> ${GartyLang.radar_btn_timeout}`; btnElement.classList.replace('btn-primary', 'btn-danger');
                    setTimeout(() => { btnElement.innerText = GartyLang.btn_generar; btnElement.classList.replace('btn-danger', 'btn-primary'); btnElement.disabled = false; }, 4000);
                }
            }
            SwalDark.fire({ icon: 'warning', title: GartyLang.swal_radar_stuck_title, text: GartyLang.swal_radar_stuck_text, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
            return;
        }

        let fd = new FormData(); fd.append('action', 'check_ticket'); fd.append('prompt_id', promptId); fd.append('historial_id', dbId || 0);

        try {
            let res = await fetch('procesar.php', { method: 'POST', body: fd }); let data = await res.json();
            if (data.error) {
                if (window.activeRadars[promptId]) { clearInterval(window.activeRadars[promptId]); delete window.activeRadars[promptId]; }
                if (Object.keys(window.activeRadars).length === 0) {
                    if (typeof stopProgressBar === 'function') stopProgressBar();
                    localStorage.removeItem('garty_tarea_pendiente');
                    if (btnElement && !window.bucleInfinitoActivo) {
                        btnElement.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${GartyLang.radar_btn_gpu_fail}`; btnElement.classList.replace('btn-primary', 'btn-danger');
                        setTimeout(() => { btnElement.innerText = GartyLang.btn_generar; btnElement.classList.replace('btn-danger', 'btn-primary'); btnElement.disabled = false; }, 4000);
                    }
                }
                SwalDark.fire({ icon: 'error', title: GartyLang.swal_radar_gpu_err_title, text: data.error, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
                return; 
            }

            if (data.status === 'completed') {
                if (window.activeRadars[promptId]) { clearInterval(window.activeRadars[promptId]); delete window.activeRadars[promptId]; }
                
                // Alimentador infinito: si termina una, reponemos otra
                if (window.bucleInfinitoActivo && typeof window.dispararTareaInfinita === 'function') {
                    window.dispararTareaInfinita();
                }

                if (data.images && data.images.length > 0) {
                    const currentCategory = document.getElementById('selector').value; let htmlElements = '';
                    data.images.forEach(img => { 
                        const isChatMode = (originalCategory === '[CHAT]' || originalCategory === '[LLM]');
                        const isVisionMode = (originalCategory === '[VISION]');
                        htmlElements += construirTarjetaImagen(img, dbId, isChatMode, isVisionMode);
                    });

                    if (currentCategory === originalCategory) {
                        if (targetDiv) targetDiv.innerHTML = `<div class="row g-3">${htmlElements}</div>`;
                        const gpuArea = document.getElementById('gpuActionArea'); if (gpuArea) gpuArea.classList.remove('d-none');
                    } else {
                        let asyncGallery = document.getElementById('asyncGallery');
                        if (!asyncGallery) {
                            asyncGallery = document.createElement('div'); asyncGallery.id = 'asyncGallery';
                            const cardBody = document.querySelector('.card-body'); if (cardBody) cardBody.prepend(asyncGallery);
                        }
                        let galleryHtml = `
                        <div class="alert alert-info border-info shadow-sm p-3 mb-4" style="background-color: #010409;">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-info pb-2">
                                <strong class="text-info"><i class="bi bi-stars"></i> ${GartyLang.radar_async_done}${promptId})</strong>
                                <button type="button" class="btn-close btn-close-white" onclick="this.parentElement.parentElement.remove()"></button>
                            </div>
                            <div class="row g-3">${htmlElements}</div>
                        </div>`;
                        asyncGallery.innerHTML = galleryHtml + asyncGallery.innerHTML;
                    }
                    
                    if (document.hidden) {
                        if (typeof tocarCampana === 'function') tocarCampana();
                        if (typeof avisarAlSistema === 'function') avisarAlSistema(GartyLang.notif_gpu_free_title || "¡GPU Liberada!", GartyLang.notif_gpu_free_text || "Tu imagen ha terminado de renderizarse.", data.images[0]);
                    }

                    let toastContainer = document.getElementById('gpuToastContainer');
                    if (!toastContainer) {
                        toastContainer = document.createElement('div'); toastContainer.id = 'gpuToastContainer';
                        toastContainer.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;';
                        document.body.appendChild(toastContainer);
                    }

                    let imgDataToast = data.images[0]; let toastMediaHtml = '';
                    if (typeof imgDataToast === 'string' && imgDataToast.length < 500 && imgDataToast.includes('.')) {
                        let lowerPath = imgDataToast.toLowerCase();
                        if (lowerPath.endsWith('.mp4') || lowerPath.endsWith('.webm') || lowerPath.endsWith('.mov')) {
                            toastMediaHtml = `<video src="galeria/${imgDataToast}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 15px; border: 2px solid white; background: #000;" muted autoplay loop playsinline></video>`;
                        } else { toastMediaHtml = `<img src="galeria/${imgDataToast}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 15px; border: 2px solid white;">`; }
                    } else {
                        let currentCat = document.getElementById('selector') ? document.getElementById('selector').value : '';
                        let isVideoToast = imgDataToast.startsWith('data:video') || (!imgDataToast.startsWith('data:image') && currentCat === '[VIDEO]');
                        if (isVideoToast) {
                            let src = imgDataToast.startsWith('data:') ? imgDataToast : `data:video/mp4;base64,${imgDataToast}`;
                            toastMediaHtml = `<video src="${src}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 15px; border: 2px solid white; background: #000;" muted autoplay loop playsinline></video>`;
                        } else {
                            let src = imgDataToast.startsWith('data:') ? imgDataToast : `data:image/png;base64,${imgDataToast}`;
                            toastMediaHtml = `<img src="${src}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 15px; border: 2px solid white;">`;
                        }
                    }

                    const toast = document.createElement('div'); toast.className = 'toast show align-items-center text-bg-success border-0 shadow-lg'; toast.style.pointerEvents = 'auto';
                    toast.innerHTML = `<div class="d-flex"><div class="toast-body d-flex align-items-center">${toastMediaHtml}<div><strong class="fs-6">${GartyLang.notif_gpu_free_title}</strong><br><small>${GartyLang.notif_gpu_free_text}</small></div></div><button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button></div>`;
                    toastContainer.appendChild(toast); setTimeout(() => { if(toast.parentElement) toast.remove(); }, 10000);

                    // === CONTROL REAL DE LIBERACIÓN DE BOTÓN ===
                    const pendientes = Object.keys(window.activeRadars).length;
                    if (pendientes === 0) {
                        if (typeof stopProgressBar === 'function') stopProgressBar();
                        localStorage.removeItem('garty_tarea_pendiente');
                        if (btnElement && !window.bucleInfinitoActivo) {
                            btnElement.innerText = GartyLang.radar_btn_completed;
                            setTimeout(() => { btnElement.innerText = GartyLang.btn_generar; btnElement.disabled = false; }, 3000);
                        }
                    } else {
                        // Aún quedan imágenes del lote de 2 o 4: mostramos el progreso y MANTENEMOS BLOQUEADO
                        if (btnElement && !window.bucleInfinitoActivo) {
                            btnElement.innerText = `${GartyLang.btn_procesando} (quedan ${pendientes})...`;
                        }
                    }
                    // ===========================================
                } else {
                    if (btnElement && data.status !== 'processing' && !window.bucleInfinitoActivo) {
                        btnElement.innerHTML = '<i class="bi bi-exclamation-triangle"></i> ' + GartyLang.btn_gpu_free_no_images;
                        btnElement.classList.replace('btn-primary', 'btn-danger');
                        setTimeout(() => { btnElement.innerText = GartyLang.btn_generar; btnElement.classList.replace('btn-danger', 'btn-primary'); btnElement.disabled = false; }, 4000);
                    }
                }
            }
        } catch (e) { 
            console.warn(GartyLang.log_radar_net_crit, e); 
            if (window.activeRadars[promptId]) { clearInterval(window.activeRadars[promptId]); delete window.activeRadars[promptId]; }
            if (Object.keys(window.activeRadars).length === 0) {
                if (typeof stopProgressBar === 'function') stopProgressBar();
                localStorage.removeItem('garty_tarea_pendiente');
                if (btnElement && !window.bucleInfinitoActivo) {
                    btnElement.innerHTML = `<i class="bi bi-wifi-off"></i> ${GartyLang.radar_btn_conn_err}`; btnElement.classList.replace('btn-primary', 'btn-danger');
                    setTimeout(() => { btnElement.innerText = GartyLang.btn_generar; btnElement.classList.replace('btn-danger', 'btn-primary'); btnElement.disabled = false; }, 4000);
                }
            }
            SwalDark.fire(GartyLang.swal_radar_conn_err_title, GartyLang.swal_radar_conn_err_text, 'error');
        }
    }, 3000); 
}

// --- CONSTRUCTOR DE IMÁGENES ---
function construirTarjetaImagen(imgData, dbId = 0, isChat = false, isVision = false) {
    if (!imgData) {
        console.error(GartyLang.log_err_img_null);
        return `<div class="alert alert-danger m-3 shadow"><i class="bi bi-exclamation-triangle-fill"></i> ${GartyLang.err_empty_result_ffmpeg}</div>`;
    }
    if (typeof imgData === 'object') { imgData = imgData.imagen_path || imgData.filename || JSON.stringify(imgData); }

    const prefix = isChat ? 'byGartyChat_' : 'byGarty_';
    const selectorEl = document.getElementById('selector'); const currentCat = selectorEl ? selectorEl.value : '';
    let isVideo = false; let isAnimatedWebp = false; let mediaSrc = ''; let extension = 'png';

    const isFilePath = typeof imgData === 'string' && imgData.length < 500 && imgData.includes('.');
    if (isFilePath) {
        const lowerPath = imgData.toLowerCase();
        isVideo = lowerPath.endsWith('.mp4') || lowerPath.endsWith('.webm') || lowerPath.endsWith('.mov');
        isAnimatedWebp = lowerPath.endsWith('.webp') && currentCat === '[VIDEO]';
        mediaSrc = `galeria/${imgData}`; extension = isVideo ? 'mp4' : (lowerPath.endsWith('.webp') ? 'webp' : 'png');
    } else {
        if (imgData.startsWith('data:video')) { isVideo = true; } 
        else if (imgData.startsWith('data:image')) { isVideo = false; if (imgData.includes('webp') && currentCat === '[VIDEO]') isAnimatedWebp = true; } 
        else {
            const isPNG = typeof imgData === 'string' && imgData.startsWith('iVBORw0KGgo');
            const isJPEG = typeof imgData === 'string' && imgData.startsWith('/9j/');
            const isWebP = typeof imgData === 'string' && imgData.startsWith('UklGR');
            const isGIF = typeof imgData === 'string' && imgData.startsWith('R0lGOD');
            if (isWebP && currentCat === '[VIDEO]') isAnimatedWebp = true;
            else isVideo = !isPNG && !isJPEG && !isWebP && !isGIF; 
        }
        if (isVideo) { mediaSrc = imgData.startsWith('data:') ? imgData : `data:video/mp4;base64,${imgData}`; extension = 'mp4'; } 
        else if (isAnimatedWebp) { mediaSrc = imgData.startsWith('data:') ? imgData : `data:image/webp;base64,${imgData}`; extension = 'webp'; } 
        else { mediaSrc = imgData.startsWith('data:') ? imgData : `data:image/png;base64,${imgData}`; extension = 'png'; }
    }
    
    const mediaTag = isVideo 
        ? `<video src="${mediaSrc}" onclick="if(typeof abrirVisor === 'function') abrirVisor(this.src)" style="cursor: pointer;" class="result-image w-100" muted loop autoplay playsinline onmouseover="this.play()" onmouseout="this.pause()"></video>`
        : `<img src="${mediaSrc}" onclick="if(typeof abrirVisor === 'function') abrirVisor(this.src)" style="cursor: zoom-in;" class="result-image w-100">`;

    const showMergeCheckbox = isVideo || isAnimatedWebp;

    return `
    <div class="${isChat ? 'col-6' : 'col-12 col-md-6'}">
        <div class="img-container shadow m-0 ${!isChat ? 'border border-info rounded' : ''}" style="position: relative;">
            ${mediaTag}
            ${showMergeCheckbox ? `<div style="position: absolute; top: 10px; left: 10px; z-index: 50;"><input type="checkbox" class="form-check-input shadow border border-dark merge-checkbox" style="width: 25px; height: 25px; cursor: pointer; border: 2px solid #0dcaf0;" value="${imgData}" onchange="if(typeof toggleVideoFusion === 'function') toggleVideoFusion(this.value, this.checked)" title="${GartyLang.gal_title_select_merge}"></div>` : ''}
            <a href="javascript:void(0)" onclick="if(typeof togglePublic === 'function') togglePublic(${dbId}, this)" class="btn-pub-img" title="${GartyLang.btn_pub_gallery}"><i class="bi bi-globe"></i></a>
            <a href="javascript:void(0)" onclick="if(typeof toggleFavorito === 'function') toggleFavorito(${dbId}, this)" class="btn-fav-img" title="${GartyLang.btn_fav_img}"><i class="bi bi-heart"></i></a>
            <a href="${mediaSrc}" download="${prefix}${Date.now()}.${extension}" class="btn-fab btn-download-img-fab"><i class="bi bi-download"></i></a>
            ${!isChat ? `
            <div style="position: absolute; bottom: 10px; left: 10px; display: flex; gap: 6px; z-index: 50;">
                ${!isVision ? `
                <a href="javascript:void(0)" onclick="enviarImagenA('${mediaSrc}', 'principal')" class="btn btn-sm btn-danger rounded-circle shadow" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; opacity: 0.9;" title="${GartyLang.btn_edit_inpaint}"><i class="bi bi-brush"></i></a>
                ` : ''}
                ${!isVision && !showMergeCheckbox ? `
                <a href="javascript:void(0)" onclick="enviarImagenA('${mediaSrc}', 'reactor')" class="btn btn-sm btn-warning rounded-circle shadow" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; opacity: 0.9;" title="${GartyLang.btn_use_face}"><i class="bi bi-person-bounding-box text-dark"></i></a>
                <a href="javascript:void(0)" onclick="enviarImagenA('${mediaSrc}', 'ipadapter')" class="btn btn-sm btn-info rounded-circle shadow" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; opacity: 0.9;" title="${GartyLang.btn_use_style}"><i class="bi bi-images text-dark"></i></a>
                ` : ''}
                <a href="javascript:void(0)" onclick="if(typeof prepararComparacion === 'function') prepararComparacion('${mediaSrc}', this)" class="btn btn-sm btn-secondary rounded-circle shadow btn-compare-ab" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; opacity: 0.9;" title="${GartyLang.btn_compare_ab}"><i class="bi bi-symmetry-vertical"></i></a>
            </div>
            ` : ''}
        </div>
    </div>`;
}

async function enviarImagenA(mediaSrc, destino) {
    if (!mediaSrc) return;
    let base64Data = mediaSrc;
    
    // Si la imagen viene del servidor (no es un data URL ya codificado)
    if (mediaSrc.includes('/') && !mediaSrc.startsWith('data:')) {
        try {
            SwalDark.fire({ title: GartyLang.swal_prep_img, toast: true, position: 'top-end', showConfirmButton: false, timer: 1000 });
            const response = await fetch(mediaSrc); 
            const blob = await response.blob();
            
            // --- NUEVO: INTELIGENCIA PARA EXTRAER FRAME DE VÍDEO ---
            if (blob.type.startsWith('video/')) {
                const videoURL = URL.createObjectURL(blob);
                const videoElement = document.createElement('video'); 
                videoElement.src = videoURL; 
                videoElement.muted = true;
                
                base64Data = await new Promise((resolve) => {
                    // Esperamos a que carguen los metadatos y saltamos al final del vídeo
                    videoElement.onloadedmetadata = () => { 
                        videoElement.currentTime = Math.max(0, videoElement.duration - 0.1); 
                    };
                    // Cuando haya saltado al frame, lo pintamos en un canvas y lo exportamos
                    videoElement.onseeked = () => {
                        const canvas = document.createElement('canvas'); 
                        canvas.width = videoElement.videoWidth; 
                        canvas.height = videoElement.videoHeight;
                        canvas.getContext('2d').drawImage(videoElement, 0, 0, canvas.width, canvas.height);
                        resolve(canvas.toDataURL('image/png'));
                        URL.revokeObjectURL(videoURL);
                    };
                });
            } else {
                // Flujo normal para imágenes estáticas (PNG, JPG, WEBP)
                base64Data = await new Promise((resolve) => {
                    const reader = new FileReader(); 
                    reader.onloadend = () => resolve(reader.result); 
                    reader.readAsDataURL(blob);
                });
            }
        } catch(e) {
            console.error(GartyLang.log_err_convert_img, e); 
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_err_title, text: GartyLang.swal_err_read_gal }); 
            return;
        }
    }
    
    // Enrutamiento según el botón que se haya pulsado
    if (destino === 'principal') {
        if (typeof setBaseImageFromDataUrl === 'function') setBaseImageFromDataUrl(base64Data);
    } else if (destino === 'reactor') {
        const preview = document.getElementById('facePreview'); 
        const container = document.getElementById('facePreviewContainer'); 
        const toggle = document.getElementById('reactorToggle');
        if (preview && container && toggle) {
            preview.src = base64Data; 
            container.classList.remove('d-none'); 
            currentFaceBase64 = base64Data; 
            toggle.checked = true;
            if (typeof toggleReactorUI === 'function') toggleReactorUI();
        }
    } else if (destino === 'ipadapter') {
        const preview = document.getElementById('ipaPreview') || document.getElementById('ipAdapterPreview');
        const container = document.getElementById('ipaPreviewContainer') || document.getElementById('ipAdapterPreviewContainer');
        const toggle = document.getElementById('ipAdapterToggle');
        if (preview && container && toggle) {
            preview.src = base64Data; 
            container.classList.remove('d-none'); 
            currentIpAdapterBase64 = base64Data; 
            toggle.checked = true;
            if (typeof toggleIpAdapterUI === 'function') toggleIpAdapterUI();
        }
    }
    // Hacemos scroll arriba para que el usuario vea la imagen cargada
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// --- CHAT INTERACTIVO ---
window.generateImageFromChatBtn = async function(btnElement, encodedText) {
    const promptText = decodeURIComponent(encodedText); const thread = document.getElementById('chatThreadContainer');
    btnElement.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${GartyLang.chat_btn_rendering || 'Renderizando...'}`;
    btnElement.disabled = true;

    const b = document.createElement('div'); b.className = `chat-bubble bubble-ai mt-2`;
    b.innerHTML = `<div class="d-flex align-items-center text-info"><div class="spinner-border spinner-border-sm me-2"></div> <small>${GartyLang.chat_msg_gen_gpu || 'Enviando a GPU...'}</small></div>`;
    thread.appendChild(b); thread.scrollTop = thread.scrollHeight;

    let fd = new FormData(); const applied = getPromptsWithPresets(promptText, "");
    fd.append('action', 'generar_imagen'); 
    
    // El chat a veces no tiene un 'selector' gráfico activo, pasamos a NATURAL_IMAGE por defecto
    const currentSelector = document.getElementById('selector') ? document.getElementById('selector').value : '[NATURAL_IMAGE]';
    const targetSelector = ['[CHAT]', '[LLM]', '[VISION]'].includes(currentSelector) ? '[NATURAL_IMAGE]' : currentSelector;
    
    fd.append('selector', targetSelector); 
    fd.append('prompt', applied.pos);
    if(applied.neg) fd.append('negative_prompt', applied.neg);
    
    const modeloSeleccionado = document.getElementById('modelSelector') ? document.getElementById('modelSelector').value : ""; 
    fd.append('model_path', modeloSeleccionado);
    
    fd.set('batch_size', 1); fd = appendUIParametersToFormData(fd, true);
    
    // ¡AQUÍ ESTABA EL TRUCO! Le decimos a PHP que lo queremos de forma asíncrona
    fd.append('async_mode', 'true'); 

    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd }); const data = await res.json();
        
        if (data.error) {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_sys_notice_title || 'Atención', text: data.error, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
            b.innerHTML = `<span class="text-muted"><i class="bi bi-x-circle"></i> ${GartyLang.chat_msg_gen_canceled || 'Generación cancelada'}</span>`;
            btnElement.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${GartyLang.chat_btn_error || 'Error'}`; thread.scrollTop = thread.scrollHeight; return; 
        }
        
        // --- EL NUEVO SISTEMA ASÍNCRONO CON RADAR ---
        if (data.status === 'ticket_issued' && data.prompt_id) {
            b.innerHTML = `<div class="d-flex align-items-center text-warning"><div class="spinner-grow spinner-grow-sm me-2"></div> <small>${GartyLang.chat_msg_gpu_processing || 'Procesando en GPU'} (${data.prompt_id})...</small></div>`;
            thread.scrollTop = thread.scrollHeight;

            const chatRadarInterval = setInterval(async () => {
                let fdCheck = new FormData(); fdCheck.append('action', 'check_ticket'); fdCheck.append('prompt_id', data.prompt_id); fdCheck.append('historial_id', data.historial_id || 0);
                try {
                    const resCheck = await fetch('procesar.php', { method: 'POST', body: fdCheck }); const dataCheck = await resCheck.json();
                    
                    if (dataCheck.status === 'completed') {
                        clearInterval(chatRadarInterval);
                        if (dataCheck.images && dataCheck.images.length > 0) {
                            let html = '<div class="row g-2 mb-2">';
                            dataCheck.images.forEach(img => { html += construirTarjetaImagen(img, data.historial_id || 0, true); });
                            html += '</div>';
                            b.innerHTML = html + `<span class="bubble-meta">${GartyLang.chat_meta_gpu_engine || 'Motor GPU - '}${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>`;
                            btnElement.innerHTML = `<i class="bi bi-check-circle"></i> ${GartyLang.chat_btn_completed || 'Completado'}`;
                            
                            // Avisos si el usuario está en otra pestaña
                            if (document.hidden) {
                                if (typeof tocarCampana === 'function') tocarCampana();
                                if (typeof avisarAlSistema === 'function') avisarAlSistema(GartyLang.notif_chat_gpu_title || "¡Chat GPU Libre!", GartyLang.notif_chat_gpu_body || "Tu imagen ha terminado.", dataCheck.images[0]);
                            }
                        } else { 
                            SwalDark.fire({ icon: 'warning', title: GartyLang.swal_no_res_title || 'Sin resultados', text: GartyLang.swal_no_res_text || 'No se han generado imágenes.', confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
                            b.innerHTML = `<span class="text-muted"><i class="bi bi-x-circle"></i> ${GartyLang.chat_msg_gen_failed_btn || 'Falló la generación'}</span>`;
                            btnElement.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${GartyLang.chat_btn_no_image || 'Sin imagen'}`;
                        }
                        thread.scrollTop = thread.scrollHeight;
                    }
                } catch (e) { console.warn(GartyLang.log_radar_net_crit || 'Radar cortado', e); }
            }, 3000);
            
        } else {
            throw new Error(GartyLang.err_no_gpu_ticket || "El servidor no emitió el ticket de trabajo.");
        }
    } catch (e) { 
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_net_fail_title || 'Fallo de Red', text: `${GartyLang.swal_net_fail_text || 'Error: '}${e.message}`, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
        b.innerHTML = `<span class="text-muted"><i class="bi bi-wifi-off"></i> ${GartyLang.chat_msg_conn_err || 'Error de conexión'}</span>`;
        btnElement.innerHTML = `<i class="bi bi-arrow-clockwise"></i> ${GartyLang.chat_btn_retry || 'Reintentar'}`;
    }
    thread.scrollTop = thread.scrollHeight;
}

function addMessageToUI(role, text, imgSrc = null, isDoc = false) {
    const b = document.createElement('div'); b.className = `chat-bubble ${role === 'user' ? 'bubble-user' : 'bubble-ai'}`;
    const ts = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    let contentHtml = text;

    if (imgSrc) contentHtml += `<br><img src="${imgSrc}" class="img-fluid rounded mt-2 shadow-sm" style="max-height: 180px;">`;
    else if (isDoc) contentHtml += `<br><div class="badge bg-light text-dark p-2 mt-2"><i class="bi bi-file-earmark-text"></i> ${GartyLang.chat_msg_doc_processed}</div>`;

    if (isAvanzado && role === 'ai' && text.length > 5 && !text.includes(GartyLang.txt_ai_greeting)) {
        let rawText = text.replace(/```json|```/gi, '').trim(); let promptToSend = rawText;
        try { const parsed = JSON.parse(rawText); if (parsed.prompt) promptToSend = parsed.prompt; } catch(e) {}
        const safeText = encodeURIComponent(promptToSend);
        contentHtml += `<div class="mt-3 text-end border-top border-secondary pt-2" style="border-color: rgba(255,255,255,0.1) !important;"><button class="btn btn-sm btn-outline-info border-0" onclick="generateImageFromChatBtn(this, '${safeText}')" title="${GartyLang.chat_btn_render_title}"><i class="bi bi-gpu-card"></i> ${GartyLang.chat_btn_render_this}</button></div>`;
    }

    const roleName = role === 'user' ? GartyLang.chat_meta_you : GartyLang.chat_meta_architect;
    b.innerHTML = `${contentHtml}<span class="bubble-meta">${roleName} — ${ts}</span>`;
    document.getElementById('chatThreadContainer').appendChild(b);
    document.getElementById('chatThreadContainer').scrollTop = document.getElementById('chatThreadContainer').scrollHeight;
}

function resetChat() { 
    document.getElementById('chatThreadContainer').innerHTML = `<div class="chat-bubble bubble-ai">${GartyLang.txt_xat_benv}<span class="bubble-meta">${GartyLang.txt_xat_sist}</span></div>`;
}

// --- ARRANQUE GENERAL AL CARGAR EL DOM ---
window.addEventListener('DOMContentLoaded', async () => { 
    resetChat(); 
    updateUIForSelector(document.getElementById('selector').value); 

    if (typeof stylePresets !== 'undefined') { window.stylePresets = stylePresets; } else { window.stylePresets = {}; }

    let presetOptionsGroupedHtml = '<option value="">' + GartyLang.opt_no_style + '</option>';
    if (Object.keys(window.stylePresets).length > 0) {
        const groups = {};
        for (const [key, data] of Object.entries(window.stylePresets)) {
            let groupName = GartyLang.lbl_general_styles; let styleName = data.title || data.name || key;
            if (key.includes('/')) { const parts = key.split('/'); groupName = parts[0]; styleName = parts.slice(1).join('/'); }
            if (!groups[groupName]) groups[groupName] = [];
            groups[groupName].push({ key: key, name: styleName });
        }
        const sortedGroups = Object.keys(groups).sort();
        for (const groupName of sortedGroups) {
            presetOptionsGroupedHtml += `<optgroup label="${groupName.replace(/_/g, ' ')}">`;
            groups[groupName].sort((a, b) => a.name.localeCompare(b.name)).forEach(item => { presetOptionsGroupedHtml += `<option value="${item.key}">${item.name}</option>`; });
            presetOptionsGroupedHtml += `</optgroup>`;
        }
    }

    window.addPresetRow = function() {
        const wrapper = document.getElementById('presetsWrapper'); if (!wrapper) return;
        const row = document.createElement('div'); row.className = 'd-flex mb-2 preset-row';
        row.innerHTML = `
            <select class="form-select border-info preset-selector me-2">${presetOptionsGroupedHtml}</select>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="this.closest('.preset-row').remove(); recalculatePrompts();" title="${GartyLang.btn_rmv_style}"><i class="bi bi-x"></i></button>`;
        wrapper.appendChild(row);
    };

    const pWrapper = document.getElementById('presetsWrapper');
    if (pWrapper) {
        addPresetRow();
        pWrapper.addEventListener('change', (e) => { if (e.target.classList.contains('preset-selector')) recalculatePrompts(); });
    }

    window.recalculatePrompts = function() {
        if (document.getElementById('promptArea').classList.contains('d-none') || lastGeneratedPrompt.pos === undefined || lastGeneratedPrompt.pos === "") return;
        const applied = getPromptsWithPresets(lastGeneratedPrompt.pos, lastGeneratedPrompt.neg);
        document.getElementById('posContent').innerText = applied.pos;
        if (applied.neg) { document.getElementById('negativeArea').classList.remove('d-none'); document.getElementById('negContent').innerText = applied.neg; } 
        else { document.getElementById('negativeArea').classList.add('d-none'); document.getElementById('negContent').innerText = ""; }
    };
});

// --- NUEVA FUNCIÓN: MODO DIRECTO (PROMPTS MANUALES) ---
window.toggleModoIngreso = function() {
    const modoDirectoToggle = document.getElementById('modoDirectoToggle');
    if (!modoDirectoToggle) return;
    
    const isDirectMode = modoDirectoToggle.checked;
    const selValue = document.getElementById('selector').value;
    const isGraphical = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]'].includes(selValue);

    const contenedorIdea = document.getElementById('contenedorIdea');
    const submitBtn = document.getElementById('submitBtn');
    const amplifyBtn = document.getElementById('amplifyBtn');
    const surpriseBtn = document.getElementById('surpriseBtn');
    const gpuDirectBtn = document.getElementById('gpuDirectBtn');
    const llmDirectBtn = document.getElementById('llmDirectBtn');
    const lblIdea = document.getElementById('lblIdea');
    const translateToggleBlock = document.getElementById('translateToggleBlock');
    
    const resultsArea = document.getElementById('results');
    const promptArea = document.getElementById('promptArea');
    const negativeArea = document.getElementById('negativeArea');
    
    const mainButtonsContainer = document.getElementById('mainButtonsContainer');
    const upperInputBlock = document.getElementById('upperInputBlock');

    if (isDirectMode) {
        if(contenedorIdea) contenedorIdea.classList.add('d-none');
        if(submitBtn) submitBtn.style.setProperty('display', 'none', 'important');
        if(amplifyBtn) amplifyBtn.style.setProperty('display', 'none', 'important');
        if(surpriseBtn) surpriseBtn.style.setProperty('display', 'none', 'important');
        if(translateToggleBlock) translateToggleBlock.style.setProperty('display', 'none', 'important');
        
        if(lblIdea) lblIdea.innerHTML = '<i class="bi bi-input-cursor-text text-warning"></i> ' + (GartyLang.lbl_prompts_directos || 'Prompts Directos');

        if(resultsArea) {
            resultsArea.classList.remove('d-none');
            if (mainButtonsContainer) resultsArea.after(mainButtonsContainer);
        }
        
        // Bloqueo explícito de botones para evitar duplicados
        if (isGraphical) {
            if(gpuDirectBtn) { gpuDirectBtn.classList.remove('d-none'); gpuDirectBtn.style.setProperty('display', 'inline-block', 'important'); }
            if(llmDirectBtn) { llmDirectBtn.classList.add('d-none'); llmDirectBtn.style.setProperty('display', 'none', 'important'); }
        } else if (selValue === '[LLM]') {
            if(llmDirectBtn) { llmDirectBtn.classList.remove('d-none'); llmDirectBtn.style.setProperty('display', 'inline-block', 'important'); }
            if(gpuDirectBtn) { gpuDirectBtn.classList.add('d-none'); gpuDirectBtn.style.setProperty('display', 'none', 'important'); }
        }

        if(promptArea) promptArea.classList.remove('d-none');
        if(negativeArea) {
            if (isGraphical) { negativeArea.classList.remove('d-none'); } 
            else { negativeArea.classList.add('d-none'); }
        }
        
        const posContent = document.getElementById('posContent');
        if(posContent) { posContent.contentEditable = "true"; if(posContent.innerText === "") posContent.setAttribute("placeholder", GartyLang.ph_prompt_pos || "Pega aquí tu Prompt Positivo..."); }
        const negContent = document.getElementById('negContent');
        if(negContent) { negContent.contentEditable = "true"; if(negContent.innerText === "") negContent.setAttribute("placeholder", GartyLang.ph_prompt_neg || "Pega aquí tu Prompt Negativo..."); }
        
        const alertArq = document.querySelector('#results .alert'); if (alertArq) alertArq.classList.add('d-none');
        const arqAct = document.getElementById('arquitectoActionArea'); if(arqAct) arqAct.classList.add('d-none');

    } else {
        if(lblIdea) lblIdea.innerHTML = GartyLang.tit_idea || 'Idea Inicial';
        
        // --- RESTAURAMOS EL TRADUCTOR ---
        // Simplemente quitamos el candado fuerte inline.
        // Toda la lógica de si debe mostrarse o no según el modelo se gestiona abajo en updateUIForSelector
        if (translateToggleBlock) {
            translateToggleBlock.style.removeProperty('display');
        }
        
        // Limpiamos los "important" para que el sistema normal respire
        if(submitBtn) submitBtn.style.removeProperty('display');
        if(amplifyBtn) amplifyBtn.style.removeProperty('display');
        if(surpriseBtn) surpriseBtn.style.removeProperty('display');
        if(gpuDirectBtn) gpuDirectBtn.style.removeProperty('display');
        if(llmDirectBtn) llmDirectBtn.style.removeProperty('display');

        // Devolvemos la botonera al final, debajo de la caja de texto y del control de traducción
        if (mainButtonsContainer) {
            if (translateToggleBlock) {
                translateToggleBlock.after(mainButtonsContainer);
            } else if (contenedorIdea) {
                contenedorIdea.after(mainButtonsContainer);
            }
        }

        clearResultsUI();
        updateUIForSelector(selValue);
    }
};

// --- Sincronización Cajas / Desplegables ---
window.sincRes = function() {
    const s = document.getElementById('aspectRatio').value.split('x');
    if(s.length===2) { document.getElementById('imgWidth').value = s[0]; document.getElementById('imgHeight').value = s[1]; }
};
window.desmarcarProp = function() { document.getElementById('aspectRatio').selectedIndex = -1; };

window.sincResVid = function() {
    const s = document.getElementById('video_aspect_ratio').value.split('x');
    if(s.length===2) { document.getElementById('vidWidth').value = s[0]; document.getElementById('vidHeight').value = s[1]; }
};
window.desmarcarPropVid = function() { document.getElementById('video_aspect_ratio').selectedIndex = -1; };

document.addEventListener('DOMContentLoaded', () => { sincRes(); sincResVid(); });

// --- CONTROL DEL MODO BORRADO MÁGICO (LaMa Remover) ---
function toggleLamaUI(isLama) {
    // 1. Ocultar o mostrar los parámetros exclusivos de Inpaint
    const inpaintParams = document.querySelectorAll('.inpaint-only-params');
    inpaintParams.forEach(el => el.style.display = isLama ? 'none' : '');

    // 2. Transformar el botón principal de renderizado GPU
    const btnRender = document.getElementById('gpuDirectBtn') || document.getElementById('btnRenderGpu');
    if (btnRender) {
        if (isLama) {
            btnRender.dataset.oldClass = btnRender.className;
            btnRender.dataset.oldText = btnRender.innerHTML;
            btnRender.className = 'btn flex-grow-1 text-light fw-bold shadow btn-danger';
            btnRender.innerHTML = '<i class="bi bi-eraser-fill me-2"></i> 🧹 Borrar Selección Directo';
        } else {
            if (btnRender.dataset.oldClass) btnRender.className = btnRender.dataset.oldClass;
            else btnRender.className = 'btn btn-gpu flex-grow-1 text-white fw-bold shadow';
            
            if (btnRender.dataset.oldText) btnRender.innerHTML = btnRender.dataset.oldText;
            else btnRender.innerHTML = '<i class="bi bi-lightning-fill me-2"></i> ' + (GartyLang.btn_renderizar || 'Renderizar');
        }
    }

    // 3. Por seguridad, apagar otros modos puros si se enciende LaMa
    if (isLama) {
        const ddPuro = document.getElementById('pureDDColorToggle');
        if (ddPuro && ddPuro.checked) { ddPuro.checked = false; if(typeof toggleDDColorPuro === 'function') toggleDDColorPuro(false); }
        
        const rembgPuro = document.getElementById('pureRembgToggle') || document.getElementById('rembgToggle');
        if (rembgPuro && rembgPuro.checked) { rembgPuro.checked = false; if(typeof toggleRembgPuro === 'function') toggleRembgPuro(false); }
    }
}

// --- CONTROL DEL MODO ILUMINACIÓN NEURAL (IC-Light) ---
function toggleIcLightUI() {
    const toggle = document.getElementById('iclight_enabled');
    const ui = document.getElementById('icLightUI');
    if (!toggle || !ui) return;

    const isIcLight = toggle.checked;

    if (isIcLight) {
        ui.classList.remove('d-none');
    } else {
        ui.classList.add('d-none');
    }

    // Apagamos otros modos contradictorios para evitar mezclas extrañas en VRAM
    if (isIcLight) {
        const ddPuro = document.getElementById('pureDDColorToggle');
        if (ddPuro && ddPuro.checked) { ddPuro.checked = false; if(typeof toggleDDColorPuro === 'function') toggleDDColorPuro(false); }
        
        const rembgPuro = document.getElementById('pureRembgToggle') || document.getElementById('rembgToggle');
        if (rembgPuro && rembgPuro.checked) { rembgPuro.checked = false; if(typeof toggleRembgPuro === 'function') toggleRembgPuro(false); }

        const toggleLama = document.getElementById('toggleLamaMode');
        if (toggleLama && toggleLama.checked) { toggleLama.checked = false; if(typeof toggleLamaUI === 'function') toggleLamaUI(false); }
    }

    // Transformamos el botón principal de renderizado GPU
    const btnRender = document.getElementById('gpuDirectBtn') || document.getElementById('btnRenderGpu');
    if (btnRender) {
        if (isIcLight) {
            btnRender.dataset.oldClass = btnRender.className;
            btnRender.dataset.oldText = btnRender.innerHTML;
            btnRender.className = 'btn flex-grow-1 text-dark fw-bold shadow btn-warning';
            btnRender.innerHTML = '<i class="bi bi-lightbulb-fill me-2"></i> ' + (GartyLang.btn_apply_iclight || '💡 Iluminar Directo');
        } else {
            if (btnRender.dataset.oldClass) btnRender.className = btnRender.dataset.oldClass;
            else btnRender.className = 'btn btn-gpu flex-grow-1 text-white fw-bold shadow';
            
            if (btnRender.dataset.oldText) btnRender.innerHTML = btnRender.dataset.oldText;
            else btnRender.innerHTML = '<i class="bi bi-lightning-fill me-2"></i> ' + (GartyLang.btn_renderizar || 'Renderizar');
        }
    }
}