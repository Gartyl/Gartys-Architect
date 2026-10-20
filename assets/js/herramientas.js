// ==============================================================================
// --- HERRAMIENTAS.JS: UTILIDADES, GALERÍA, PRESETS Y UX SECUNDARIA ---
// ==============================================================================

// --- MÓDULO LECTOR DE METADATOS PNG (DRAG & DROP) ---
const dropZone = document.getElementById('dropZoneBody');
const consoleCard = document.getElementById('mainConsoleCard');

if (dropZone && consoleCard) {
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); e.stopPropagation(); consoleCard.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', (e) => { e.preventDefault(); e.stopPropagation(); consoleCard.classList.remove('dragover'); });
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault(); e.stopPropagation(); consoleCard.classList.remove('dragover');
        const currentCat = document.getElementById('selector').value;
        if (['[LLM]', '[CHAT]'].includes(currentCat)) return;
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const file = e.dataTransfer.files[0];
            if (file.type === "image/png") extractComfyUIPNG(file);
        }
    });
}

function extractComfyUIPNG(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const view = new DataView(e.target.result);
        let offset = 8;
        let metadata = {};

        try {
            while (offset < view.byteLength) {
                const length = view.getUint32(offset);
                const type = String.fromCharCode(
                    view.getUint8(offset + 4),
                    view.getUint8(offset + 5),
                    view.getUint8(offset + 6),
                    view.getUint8(offset + 7)
                );

                if (type === 'tEXt' || type === 'iTXt') {
                    const data = new Uint8Array(view.buffer, offset + 8, length);
                    const text = new TextDecoder('utf-8').decode(data);
                    const nullIdx = text.indexOf('\0');
                    if (nullIdx !== -1) {
                        const keyword = text.substring(0, nullIdx);
                        let rawVal = text.substring(nullIdx + 1);
                        if (type === 'iTXt') rawVal = rawVal.replace(/^\x00+/, '');
                        metadata[keyword] = rawVal;
                    }
                }
                offset += 12 + length;
            }

            let foundPos = "";
            let foundNeg = "";
            let foundModel = "";
            let foundLoras = [];
            let extractedParams = {};
            let extracted = false;

            // =========================================================================
            // RUTA A: METADATOS NATIVOS DE COMFYUI (JSON)
            // =========================================================================
            if (metadata.prompt) {
                const promptData = JSON.parse(metadata.prompt);

                const resolvePromptNode = (link, isNegative = false, depth = 0) => {
                    if (!link || !Array.isArray(link) || depth > 8) return "";
                    const node = promptData[String(link[0])];
                    if (!node || !node.inputs) return "";

                    if (isNegative && typeof node.inputs.negative_prompt === 'string') return node.inputs.negative_prompt;
                    if (typeof node.inputs.text === 'string') return node.inputs.text;
                    if (typeof node.inputs.prompt === 'string') return node.inputs.prompt;
                    if (typeof node.inputs.value === 'string') return node.inputs.value;
                    if (typeof node.inputs.speech === 'string') return node.inputs.speech;

                    if (Array.isArray(node.inputs.text)) return resolvePromptNode(node.inputs.text, isNegative, depth + 1);
                    if (Array.isArray(node.inputs.prompt)) return resolvePromptNode(node.inputs.prompt, isNegative, depth + 1);

                    if (Array.isArray(node.inputs.conditioning)) return resolvePromptNode(node.inputs.conditioning, isNegative, depth + 1);
                    if (!isNegative && Array.isArray(node.inputs.positive)) return resolvePromptNode(node.inputs.positive, false, depth + 1);
                    if (isNegative && Array.isArray(node.inputs.negative)) return resolvePromptNode(node.inputs.negative, true, depth + 1);

                    return "";
                };

                for (const key in promptData) {
                    const node = promptData[key];
                    if (!node || !node.inputs) continue;

                    // 1. Detectar Modelo Base (Checkpoint / UNET / GGUF)
                    if (["CheckpointLoaderSimple", "UNETLoader", "UnetLoaderGGUF"].includes(node.class_type)) {
                        const mName = node.inputs.ckpt_name || node.inputs.unet_name;
                        if (mName && !foundModel) foundModel = mName;
                    }

                    // 2. Detectar LoRAs
                    if (["LoraLoader", "LoraLoaderModelOnly"].includes(node.class_type) && node.inputs.lora_name) {
                        const lName = node.inputs.lora_name;
                        const lWeight = node.inputs.strength_model ?? 0.8;
                        if (lWeight !== 0) {
                            foundLoras.push({ name: lName, high: lWeight, low: lWeight });
                        }
                    }

                    // 3. Detectar KSampler y Prompts
                    if (node.class_type === "KSampler" || node.class_type === "KSamplerAdvanced") {
                        if (node.inputs.steps) extractedParams.steps = node.inputs.steps;
                        if (node.inputs.cfg !== undefined) extractedParams.cfg = node.inputs.cfg;
                        if (node.inputs.sampler_name) extractedParams.sampler = node.inputs.sampler_name;
                        if (node.inputs.scheduler) extractedParams.scheduler = node.inputs.scheduler;
                        const seedVal = node.inputs.seed ?? node.inputs.noise_seed;
                        if (seedVal !== undefined) extractedParams.seed = seedVal;

                        if (!foundPos && node.inputs.positive) foundPos = resolvePromptNode(node.inputs.positive, false);
                        if (!foundNeg && node.inputs.negative) foundNeg = resolvePromptNode(node.inputs.negative, true);
                    }

                    // 4. Detectar Resolución
                    if (["EmptyLatentImage", "EmptySD3LatentImage", "WanImageToVideo"].includes(node.class_type)) {
                        if (typeof node.inputs.width === 'number' && typeof node.inputs.height === 'number') {
                            extractedParams.width = node.inputs.width;
                            extractedParams.height = node.inputs.height;
                        }
                    }

                    // 5. Detectar Flux Guidance y Clip Skip
                    if (node.class_type === "FluxGuidance" && node.inputs.guidance) {
                        extractedParams.guidance = node.inputs.guidance;
                    }
                    if (node.class_type === "CLIPSetLastLayer" && node.inputs.stop_at_clip_layer) {
                        extractedParams.clipSkip = Math.abs(parseInt(node.inputs.stop_at_clip_layer));
                    }
                }

                if (!foundPos) {
                    for (const key in promptData) {
                        const node = promptData[key];
                        if (node && node.inputs && typeof node.inputs.text === 'string' && node.inputs.text.trim() !== '') {
                            if (!foundPos) foundPos = node.inputs.text;
                            else if (!foundNeg) foundNeg = node.inputs.text;
                        }
                    }
                }
                extracted = true;
            }
            // =========================================================================
            // RUTA B: METADATOS DE CIVITAI / FORGE / AUTOMATIC1111 ('parameters')
            // =========================================================================
            else if (metadata.parameters) {
                const rawParams = metadata.parameters.trim();
                const negSplit = rawParams.split(/\nNegative prompt:\s*/i);
                let beforeNeg = negSplit[0] || "";
                let afterNeg = negSplit[1] || "";

                if (negSplit.length > 1) {
                    foundPos = beforeNeg.trim();
                    const stepsSplit = afterNeg.split(/\n(?=Steps:\s*\d+)/i);
                    foundNeg = (stepsSplit[0] || "").trim();
                    afterNeg = stepsSplit.slice(1).join("\n");
                } else {
                    const stepsSplit = beforeNeg.split(/\n(?=Steps:\s*\d+)/i);
                    foundPos = (stepsSplit[0] || "").trim();
                    afterNeg = stepsSplit.slice(1).join("\n");
                }

                // Extraer etiquetas <lora:nombre:peso> del prompt de Civitai/A1111 y limpiarlas del texto
                const loraRegex = /<lora:([^:>]+):([\d.-]+)(?::[\d.-]+)?>/gi;
                let loraMatch;
                while ((loraMatch = loraRegex.exec(foundPos)) !== null) {
                    foundLoras.push({ name: loraMatch[1], high: parseFloat(loraMatch[2]), low: parseFloat(loraMatch[2]) });
                }
                foundPos = foundPos.replace(loraRegex, '').replace(/\s+,/g, ',').replace(/,\s*,/g, ',').trim();

                const getParam = (regex) => {
                    const m = afterNeg.match(regex);
                    return m ? m[1].trim() : null;
                };

                foundModel = getParam(/Model:\s*([^,\n]+)/i) || "";
                extractedParams.steps = getParam(/Steps:\s*(\d+)/i);
                extractedParams.cfg = getParam(/CFG scale:\s*([\d.]+)/i);
                extractedParams.seed = getParam(/Seed:\s*(\d+)/i);
                extractedParams.clipSkip = getParam(/Clip skip:\s*(\d+)/i);

                const size = getParam(/Size:\s*(\d+x\d+)/i);
                if (size) {
                    const [w, h] = size.split('x').map(Number);
                    if (w && h) { extractedParams.width = w; extractedParams.height = h; }
                }

                const samplerRaw = getParam(/Sampler:\s*([^,\n]+)/i);
                const schedRaw = getParam(/Schedule type:\s*([^,\n]+)/i);

                if (samplerRaw) {
                    const sLow = samplerRaw.toLowerCase();
                    if (sLow.includes('euler a')) extractedParams.sampler = 'euler_ancestral';
                    else if (sLow.includes('dpm++ 2m sde')) extractedParams.sampler = 'dpmpp_2m_sde_gpu';
                    else if (sLow.includes('dpm++ 3m sde')) extractedParams.sampler = 'dpmpp_3m_sde_gpu';
                    else if (sLow.includes('dpm++ 2m')) extractedParams.sampler = 'dpmpp_2m';
                    else if (sLow.includes('euler')) extractedParams.sampler = 'euler';
                    else if (sLow.includes('ddim')) extractedParams.sampler = 'ddim';
                    else if (sLow.includes('lcm')) extractedParams.sampler = 'lcm';
                    if (sLow.includes('karras')) extractedParams.scheduler = 'karras';
                }

                if (schedRaw) {
                    const scLow = schedRaw.toLowerCase();
                    if (scLow.includes('karras')) extractedParams.scheduler = 'karras';
                    else if (scLow.includes('exponential')) extractedParams.scheduler = 'exponential';
                    else if (scLow.includes('sgm') || scLow.includes('uniform')) extractedParams.scheduler = 'sgm_uniform';
                    else if (scLow.includes('simple')) extractedParams.scheduler = 'simple';
                    else if (scLow.includes('beta')) extractedParams.scheduler = 'beta';
                    else if (scLow.includes('normal') || scLow.includes('automatic')) extractedParams.scheduler = 'normal';
                }

                extracted = true;
            }

            // =========================================================================
            // VOLCADO LIMPIO A LA INTERFAZ
            // =========================================================================
            if (extracted && (foundPos || foundNeg)) {
                if (foundNeg === "low quality, blurry, text, watermark" || foundNeg === "N/A (Audio Standalone)") {
                    foundNeg = "";
                }

                const aplicarParametrosExtraidos = () => {
                    if (extractedParams.steps && document.getElementById('stepsInput')) document.getElementById('stepsInput').value = extractedParams.steps;
                    if (extractedParams.cfg !== undefined && extractedParams.cfg !== null && document.getElementById('cfgInput')) document.getElementById('cfgInput').value = extractedParams.cfg;
                    if (extractedParams.sampler && document.getElementById('samplerInput')) document.getElementById('samplerInput').value = extractedParams.sampler;
                    if (extractedParams.scheduler && document.getElementById('schedulerInput')) document.getElementById('schedulerInput').value = extractedParams.scheduler;
                    if (extractedParams.seed !== undefined && extractedParams.seed !== null && document.getElementById('seedInput')) document.getElementById('seedInput').value = extractedParams.seed;
                    if (extractedParams.guidance && document.getElementById('fluxGuidanceInput')) document.getElementById('fluxGuidanceInput').value = extractedParams.guidance;
                    if (extractedParams.clipSkip && document.getElementById('clipSkipInput')) document.getElementById('clipSkipInput').value = extractedParams.clipSkip;
                    if (extractedParams.width && extractedParams.height) {
                        if (document.getElementById('imgWidth')) document.getElementById('imgWidth').value = extractedParams.width;
                        if (document.getElementById('imgHeight')) document.getElementById('imgHeight').value = extractedParams.height;
                        if (typeof window.desmarcarProp === 'function') window.desmarcarProp();
                    }
                };

                if (typeof window.aplicarModeloYLoras === 'function') {
                    window.aplicarModeloYLoras(foundModel, foundLoras, aplicarParametrosExtraidos).then(() => {
                        const descEl = document.getElementById('descripcion');
                        if (descEl) descEl.value = foundPos;

                        const selVal = document.getElementById('selector') ? document.getElementById('selector').value : '[SDXL]';
                        if (typeof lastGeneratedPrompt !== 'undefined') {
                            lastGeneratedPrompt.pos = foundPos;
                            lastGeneratedPrompt.neg = foundNeg;
                        }
                        if (typeof showGeneratedPromptsInUI === 'function') {
                            showGeneratedPromptsInUI(foundPos, foundNeg, selVal);
                        }
                        if (typeof savePreferences === 'function') savePreferences();
                    });
                } else {
                    aplicarParametrosExtraidos();
                }

                SwalDark.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: GartyLang.swal_xray_ok_title,
                    text: GartyLang.swal_xray_ok_text,
                    showConfirmButton: false,
                    timer: 2800
                });
            } else {
                SwalDark.fire({ icon: 'warning', title: GartyLang.swal_no_meta_title, text: GartyLang.swal_no_meta_text });
            }
        } catch (err) {
            console.error(err);
            SwalDark.fire({ icon: 'warning', title: GartyLang.swal_no_meta_title, text: GartyLang.swal_no_meta_text });
        }
    };
    reader.readAsArrayBuffer(file);
}

// --- MÓDULO DICTADO POR VOZ ---
document.addEventListener("DOMContentLoaded", function() {
    const micBtn = document.getElementById('micBtn');
    const descripcionInput = document.getElementById('descripcion');
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        recognition.lang = APP_ENV.lang; // 100% puro JS
        recognition.continuous = false; recognition.interimResults = false;

        recognition.onstart = function() {
            micBtn.classList.replace('btn-tool', 'btn-danger');
            micBtn.innerHTML = '<i class="bi bi-mic-mute-fill"></i>';
            descripcionInput.placeholder = GartyLang.mic_001;
        };
        recognition.onresult = function(event) {
            const transcript = event.results[0][0].transcript;
            descripcionInput.value = descripcionInput.value + (descripcionInput.value ? ' ' : '') + transcript;
        };
        recognition.onerror = function(event) { 
            micBtn.classList.replace('btn-danger', 'btn-tool');
            micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
            descripcionInput.placeholder = GartyLang.txt_idea;
            if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                SwalDark.fire({ icon: 'info', title: GartyLang.mic_002, text: GartyLang.mic_003, confirmButtonText: GartyLang.btn_entendido });
            } else { console.warn(GartyLang.mic_004 + ": ", event.error); }
        };
        recognition.onend = function() {
            micBtn.classList.replace('btn-danger', 'btn-tool');
            micBtn.innerHTML = '<i class="bi bi-mic-fill"></i>';
            descripcionInput.placeholder = GartyLang.txt_idea;
        };
        if(micBtn) micBtn.onclick = () => { recognition.start(); };
    } else {
        if (micBtn) micBtn.style.display = 'none';
    }
});

// --- MÓDULO DE PREFERENCIAS UI ---
function savePreferences() {
    const sel = document.getElementById('selector').value;
    const prefs = JSON.parse(localStorage.getItem('garty_prefs') || '{}');
    if (!prefs[sel]) prefs[sel] = {};
    document.querySelectorAll('.pref-track').forEach(el => {
        if (!el.id || el.id === 'descripcion') return; 
        prefs[sel][el.id] = (el.type === 'checkbox') ? el.checked : el.value;
    });
    localStorage.setItem('garty_prefs', JSON.stringify(prefs));
}

function loadPreferences(sel) {
    const prefs = JSON.parse(localStorage.getItem('garty_prefs') || '{}');
    if (prefs[sel]) {
        Object.keys(prefs[sel]).forEach(key => {
            if (key === 'descripcion') return; 
            const el = document.getElementById(key);
            if (el) {
                if (el.type === 'checkbox') {
                    el.checked = prefs[sel][key];
                    if(el.onchange) el.onchange(); 
                } else {
                    el.value = prefs[sel][key];
                    if (el.tagName === 'SELECT' && !el.value && el.options.length > 0) el.selectedIndex = 0;
                }
            }
        });
    }
}

document.addEventListener('change', function(e) {
    if(e.target && e.target.classList.contains('pref-track')) {
        if(e.target.id !== 'selector') savePreferences();
    }
});

// --- FAVORITOS Y PUBLICACIÓN ---
window.toggleFavorito = async function(promptId, btn) {
    if(!promptId || promptId === 0) return;
    const newState = btn.classList.contains('active') ? 0 : 1;
    if (newState) { btn.classList.add('active'); btn.innerHTML = '<i class="bi bi-heart-fill"></i>'; } 
    else { btn.classList.remove('active'); btn.innerHTML = '<i class="bi bi-heart"></i>'; }
    const fd = new FormData(); fd.append('action', 'toggle_favorito'); fd.append('prompt_id', promptId); fd.append('estado', newState);
    try { await fetch('procesar.php', { method: 'POST', body: fd }); } catch(e) {}
};

window.togglePublic = async function(promptId, btn) {
    if(!promptId || promptId === 0) return;
    const newState = btn.classList.contains('active') ? 0 : 1;
    if (newState) btn.classList.add('active'); else btn.classList.remove('active');
    const fd = new FormData(); fd.append('action', 'toggle_public'); fd.append('prompt_id', promptId); fd.append('estado', newState);
    try { await fetch('procesar.php', { method: 'POST', body: fd }); } catch(e) {}
};

// --- GALERÍA RECIENTE (CON PAGINACIÓN) ---
let currentGalleryPage = 1;

async function abrirModalGaleria(page = 1) {
    currentGalleryPage = page;
    const modalEl = document.getElementById('modalGaleriaReciente');
    const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    inst.show();
    
    const grid = document.getElementById('gridGaleriaModal');
    grid.innerHTML = `<div class="text-center w-100 text-info"><span class="spinner-border spinner-border-sm"></span> ${GartyLang.gal_msg_loading}</div>`;
    
    const fd = new FormData(); 
    fd.append('action', 'get_recent_images'); fd.append('page', page); 
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.error) {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_gal_err_title, text: data.error, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
            grid.innerHTML = `<div class="text-center w-100 text-muted">${GartyLang.gal_msg_err_load}</div>`;
            return;
        }
        
        if (data.images && data.images.length > 0) {
            grid.innerHTML = data.images.map(img => {
                const lowerPath = img.imagen_path.toLowerCase();
                const isMp4Webm = lowerPath.endsWith('.mp4') || lowerPath.endsWith('.webm');
                const isAudio = lowerPath.endsWith('.wav') || lowerPath.endsWith('.mp3') || lowerPath.endsWith('.flac') || lowerPath.endsWith('.ogg');
                const isWebp = lowerPath.endsWith('.webp');
                let mediaContent = '';

                if (isMp4Webm) {
                    mediaContent = `
                        <div class="d-flex flex-column align-items-center justify-content-center bg-dark text-info border border-secondary rounded shadow-sm position-relative overflow-hidden" style="height:100px; width:100%;">
                            <input type="checkbox" class="form-check-input merge-checkbox position-absolute shadow" style="top: 5px; left: 5px; width: 22px; height: 22px; z-index: 20; cursor: pointer; border: 2px solid #0dcaf0;" value="${img.imagen_path}" onchange="if(typeof toggleVideoFusion === 'function') toggleVideoFusion(this.value, this.checked)">
                            <span class="badge bg-secondary position-absolute top-0 end-0 m-1" style="cursor:pointer; z-index: 10;" onclick="event.stopPropagation(); if(typeof abrirVisor === 'function') abrirVisor('galeria/${img.imagen_path}')"><i class="bi bi-play-fill fs-6"></i></span>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center" style="cursor:pointer;" onclick="usarImagenDeGaleria('galeria/${img.imagen_path}')">
                                <i class="bi bi-camera-reels fs-2 mb-1"></i>
                                <small class="text-white text-truncate w-100 px-2 text-center" style="font-size: 0.65rem;">${img.imagen_path}</small>
                            </div>
                        </div>`;
                } else if (isAudio) {
                    // NUEVO: Tarjeta específica para audio con checkbox de fusión
                    mediaContent = `
                        <div class="d-flex flex-column align-items-center justify-content-center bg-dark text-info border border-secondary rounded shadow-sm position-relative overflow-hidden" style="height:100px; width:100%;">
                            <input type="checkbox" class="form-check-input merge-checkbox position-absolute shadow" style="top: 5px; left: 5px; width: 22px; height: 22px; z-index: 20; cursor: pointer; border: 2px solid #0dcaf0;" value="${img.imagen_path}" onchange="if(typeof toggleVideoFusion === 'function') toggleVideoFusion(this.value, this.checked)">
                            <span class="badge bg-secondary position-absolute top-0 end-0 m-1" style="cursor:pointer; z-index: 10;" onclick="event.stopPropagation(); window.open('galeria/${img.imagen_path}', '_blank')"><i class="bi bi-play-fill fs-6"></i></span>
                            <div class="w-100 h-100 d-flex flex-column align-items-center justify-content-center" style="cursor:pointer;" onclick="usarImagenDeGaleria('galeria/${img.imagen_path}')">
                                <i class="bi bi-music-note-beamed fs-2 mb-1"></i>
                                <small class="text-white text-truncate w-100 px-2 text-center" style="font-size: 0.65rem;">${img.imagen_path}</small>
                            </div>
                        </div>`;
                } else {
                    const checkboxHtml = isWebp ? `<input type="checkbox" class="form-check-input merge-checkbox position-absolute shadow" style="top: 5px; left: 5px; width: 22px; height: 22px; z-index: 20; cursor: pointer; border: 2px solid #0dcaf0;" value="${img.imagen_path}" onchange="if(typeof toggleVideoFusion === 'function') toggleVideoFusion(this.value, this.checked)">` : '';
                    mediaContent = `
                        <div class="position-relative" style="height:100px; width:100%;">
                            ${checkboxHtml}
                            <img src="galeria/${img.imagen_path}" class="img-fluid rounded border border-secondary shadow-sm" style="cursor:pointer; height:100px; width:100%; object-fit:cover;" onclick="usarImagenDeGaleria('galeria/${img.imagen_path}')">
                        </div>`;
                }
                return `<div class="col-4 col-md-3 mb-2">${mediaContent}</div>`;
            }).join('');
            
            document.getElementById('galeriaPageInfo').innerText = `${GartyLang.gal_msg_page} ${data.current_page} ${GartyLang.gal_msg_of} ${data.total_pages}`;
            document.getElementById('btnPrevGaleria').disabled = (data.current_page <= 1);
            document.getElementById('btnNextGaleria').disabled = (data.current_page >= data.total_pages);
        } else { 
            grid.innerHTML = `<div class="text-center w-100 text-muted">${GartyLang.gal_msg_empty}</div>`; 
        }
    } catch(e) {
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_net_err_title, text: `${GartyLang.swal_gal_err_net}${e.message}` });
        grid.innerHTML = `<div class="text-center w-100 text-muted">${GartyLang.gal_msg_err_conn}</div>`;
    }
}

async function usarImagenDeGaleria(url) {
    try {
        const lowerUrl = url.toLowerCase();
        // Separamos el audio. Las imágenes y vídeos irán juntos por la ruta principal.
        const isAudio = lowerUrl.endsWith('.wav') || lowerUrl.endsWith('.mp3') || lowerUrl.endsWith('.flac') || lowerUrl.endsWith('.ogg');
        
        if (isAudio) {
            const response = await fetch(url);
            const blob = await response.blob();
            const reader = new FileReader();
            reader.onloadend = function() {
                window.currentAudioBase64 = reader.result;
                const filename = url.split('/').pop();
                
                const oldBadge = document.getElementById('audioBadge');
                if(oldBadge) oldBadge.remove();
                
                const imgPreviewContainer = document.getElementById('imgPreviewContainer');
                if(imgPreviewContainer) {
                    // 🌟 FIX: Inserción segura para audio desde galería
                    imgPreviewContainer.insertAdjacentHTML('afterbegin', `
                    <div class="badge bg-success p-3 mt-2 mb-2 fs-6 w-100 text-start shadow-sm" id="audioBadge">
                        <i class="bi bi-music-note-beamed fs-4 me-2"></i> ${typeof GartyLang !== 'undefined' ? GartyLang.audio_ready || 'Audio Listo' : 'Audio Listo'}: ${filename} 
                        <i class="bi bi-x-circle ms-auto float-end text-light" style="cursor:pointer;" onclick="if(typeof clearAudio === 'function') clearAudio()" title="Quitar"></i>
                    </div>`);
                    imgPreviewContainer.style.display = 'block';
                }
                const inst = bootstrap.Modal.getInstance(document.getElementById('modalGaleriaReciente'));
                if (inst) inst.hide();
            };
            reader.readAsDataURL(blob);
        } else {
            // 🌟 LA RUTA UNIFICADA PARA VÍDEOS E IMÁGENES 🌟
            if (typeof SwalDark !== 'undefined') {
                SwalDark.fire({ title: typeof GartyLang !== 'undefined' && GartyLang.swal_prep_img ? GartyLang.swal_prep_img : 'Preparando...', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            }
            
            const response = await fetch(url);
            const blob = await response.blob();
            
            // Escudo MIME: Corrige el fallo de los servidores locales que envían octet-stream
            let mimeType = blob.type;
            if (!mimeType || mimeType === 'application/octet-stream') {
                if (lowerUrl.endsWith('.mp4')) mimeType = 'video/mp4';
                else if (lowerUrl.endsWith('.webm')) mimeType = 'video/webm';
                else if (lowerUrl.endsWith('.png')) mimeType = 'image/png';
                else if (lowerUrl.endsWith('.jpg') || lowerUrl.endsWith('.jpeg')) mimeType = 'image/jpeg';
                else if (lowerUrl.endsWith('.webp')) mimeType = 'image/webp';
            }

            const filename = url.split('/').pop() || 'media_galeria';
            const destino = window.destinoGaleriaModal || 'principal';

            if (destino === 'principal') {
                // Inyectamos el blob en el input físico. Esto dispara el evento nativo 'change'
                // garantizando que no se rompa la estructura del HTML.
                const dataTransfer = new DataTransfer();
                const fileObj = new File([blob], filename, { type: mimeType });
                dataTransfer.items.add(fileObj);
                
                const mainInput = document.getElementById('imageInput');
                if (mainInput) {
                    mainInput.files = dataTransfer.files;
                    mainInput.dispatchEvent(new Event('change')); 
                }
            } else {
                // Ruta para sub-herramientas (IP-Adapter, Reactor, ControlNet)
                const reader = new FileReader();
                reader.onloadend = function() {
                    if (typeof enviarImagenA === 'function') {
                        enviarImagenA(reader.result, destino);
                    }
                };
                reader.readAsDataURL(blob);
            }

            // Restaurar estado del modal
            window.destinoGaleriaModal = 'principal';
            const inst = bootstrap.Modal.getInstance(document.getElementById('modalGaleriaReciente'));
            if (inst) inst.hide();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    } catch (e) { 
        console.error(typeof GartyLang !== 'undefined' ? GartyLang.log_err_load_gallery : 'Error', e); 
    }
}

async function cargarDatosImagen(imgId) {
    const fd = new FormData();
    fd.append('action', 'get_single_image_data');
    fd.append('img_id', imgId);

    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.error) {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_rest_err_title, text: data.error, confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}` });
            return;
        }
        if (data && data.modelo) {
            let modeloFinal = String(data.modelo).toUpperCase().trim();
            if (!modeloFinal.startsWith('[')) modeloFinal = '[' + modeloFinal + ']';
            const selectorEl = document.getElementById('selector');
            if (selectorEl) selectorEl.value = modeloFinal;

            if (typeof updateUIForSelector === 'function') updateUIForSelector(modeloFinal);
            if (APP_ENV.isAvanzado) {
                if (typeof updateModelFilter === 'function') updateModelFilter(modeloFinal);
                if (typeof updateLoraFilter === 'function') updateLoraFilter(modeloFinal);
            }

            const cajaPrincipal = document.getElementById('descripcion');
            if (cajaPrincipal) cajaPrincipal.value = data.descripcion_original || "";

            const resultsDiv = document.getElementById('results');
            if (resultsDiv) { resultsDiv.style.display = 'block'; resultsDiv.classList.remove('d-none', 'collapse'); }

            const posCode = document.getElementById('posContent');
            const promptArea = document.getElementById('promptArea');
            if (posCode) posCode.innerText = data.prompt_positivo || "";
            if (promptArea) { promptArea.style.display = 'block'; promptArea.classList.remove('collapse', 'd-none'); }

            const negCode = document.getElementById('negContent');
            const negativeArea = document.getElementById('negativeArea');
            if (negCode) negCode.innerText = data.prompt_negativo || "";
            if (negativeArea) {
                if (data.prompt_negativo && data.prompt_negativo.trim() !== "") {
                    negativeArea.style.display = 'block';
                    negativeArea.classList.remove('collapse', 'd-none');
                }
            }

            if (typeof lastGeneratedPrompt !== 'undefined') {
                lastGeneratedPrompt.pos = data.prompt_positivo || "";
                lastGeneratedPrompt.neg = data.prompt_negativo || "";
            }

            if (data.metadata) {
                const meta = JSON.parse(data.metadata);
                const savedModel = meta.Model || meta.model || data.model_path || data.modelo_grafico || "";

                // Parseador de todos los formatos de LoRA guardados en metadata
                let parsedLoras = [];
                const lorasGuardados = meta.LoRAs || meta.loras || meta.Loras;
                if (lorasGuardados && typeof lorasGuardados === 'string') {
                    lorasGuardados.split(',').forEach(part => {
                        const s = part.trim();
                        // Caso 1: Wan Dual "loraH (0.8) + loraL (0.6)"
                        let mDual = s.match(/^(.+?)\s*\(([\d.-]+)\)\s*\+\s*(.+?)\s*\(([\d.-]+)\)$/);
                        if (mDual) {
                            parsedLoras.push({ name: mDual[1].trim(), high: parseFloat(mDual[2]), low: parseFloat(mDual[4]) });
                            return;
                        }
                        // Caso 2: Wan High/Low "lora (High: 0.8 / Low: 0.6)"
                        let mHL = s.match(/^(.+?)\s*\(High:\s*([\d.-]+)\s*\/\s*Low:\s*([\d.-]+)\)$/i);
                        if (mHL) {
                            parsedLoras.push({ name: mHL[1].trim(), high: parseFloat(mHL[2]), low: parseFloat(mHL[3]) });
                            return;
                        }
                        // Caso 3: Estándar "lora_name (0.8)"
                        let mStd = s.match(/^(.+?)\s*\(([\d.-]+)\)$/);
                        if (mStd) {
                            parsedLoras.push({ name: mStd[1].trim(), high: parseFloat(mStd[2]), low: parseFloat(mStd[2]) });
                        }
                    });
                }

                // Función que aplica los parámetros exactos de la imagen tras seleccionar el modelo
                const aplicarAjustesHistorial = () => {
                    if (document.getElementById('stepsInput')) document.getElementById('stepsInput').value = meta.Steps || meta.steps || 30;
                    if (document.getElementById('cfgInput')) document.getElementById('cfgInput').value = meta['CFG Scale'] ?? meta.cfg ?? 5.0;
                    if (document.getElementById('seedInput')) document.getElementById('seedInput').value = meta.Seed || meta.seed || -1;

                    if (meta.Sampler) {
                        const mSamp = String(meta.Sampler).match(/^([^\s(]+)(?:\s*\(([^)]+)\))?/);
                        if (mSamp) {
                            if (mSamp[1] && document.getElementById('samplerInput')) document.getElementById('samplerInput').value = mSamp[1].toLowerCase();
                            if (mSamp[2] && document.getElementById('schedulerInput')) document.getElementById('schedulerInput').value = mSamp[2].toLowerCase();
                        }
                    }

                    if (meta.Resolution && String(meta.Resolution).includes('x')) {
                        const [w, h] = String(meta.Resolution).split('x').map(Number);
                        if (w && h) {
                            if (modeloFinal === '[VIDEO]') {
                                if (document.getElementById('vidWidth')) document.getElementById('vidWidth').value = w;
                                if (document.getElementById('vidHeight')) document.getElementById('vidHeight').value = h;
                                if (typeof window.desmarcarPropVid === 'function') window.desmarcarPropVid();
                            } else {
                                if (document.getElementById('imgWidth')) document.getElementById('imgWidth').value = w;
                                if (document.getElementById('imgHeight')) document.getElementById('imgHeight').value = h;
                                if (typeof window.desmarcarProp === 'function') window.desmarcarProp();
                            }
                        }
                    }

                    if (meta['Guidance (Flux)'] && document.getElementById('fluxGuidanceInput')) {
                        document.getElementById('fluxGuidanceInput').value = meta['Guidance (Flux)'];
                    }
                    if (meta['CLIP Skip'] && document.getElementById('clipSkipInput')) {
                        document.getElementById('clipSkipInput').value = meta['CLIP Skip'];
                    }
                    if (meta['Flow Shift'] && document.getElementById('shiftInput')) {
                        document.getElementById('shiftInput').value = meta['Flow Shift'];
                    }
                    const denVal = meta['Denoise (I2I)'] ?? meta.Denoise;
                    if (denVal !== undefined) {
                        if (document.getElementById('globalDenoiseSlider')) document.getElementById('globalDenoiseSlider').value = denVal;
                        if (document.getElementById('denoiseSlider')) document.getElementById('denoiseSlider').value = denVal;
                        if (document.getElementById('denoiseVal')) document.getElementById('denoiseVal').innerText = denVal;
                    }
                };

                if (typeof window.aplicarModeloYLoras === 'function') {
                    await window.aplicarModeloYLoras(savedModel, parsedLoras, aplicarAjustesHistorial);
                } else {
                    aplicarAjustesHistorial();
                }
            }
            if (typeof savePreferences === 'function') savePreferences();
        }
    } catch(e) {
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_net_err_title, text: GartyLang.swal_rest_err_net });
    }
}

// --- BOTÓN SORPRÉNDEME Y MODO DADO FIJO ---
window.dadoFijoActivo = false;

window.tirarDadoSilencioso = async function() {
    let formData = new FormData();
    formData.append('action', 'generar_prompt_sorpresa');
    formData.append('idioma', APP_ENV.lang.toUpperCase());

    let selectorPrincipal = document.getElementById('selector');
    let modoActual = 'Imagen';
    if (selectorPrincipal) {
        let valor = selectorPrincipal.value.toUpperCase();
        if (valor.includes('VIDEO')) modoActual = 'Video';
        else if (valor.includes('CHAT') || valor.includes('TEXT')) modoActual = 'Chat';
    }
    formData.append('contexto', modoActual);

    let response = await fetch('procesar.php', { method: 'POST', body: formData });
    let data = await response.json();
    if (data && data.success && data.prompt) {
        const caja = document.getElementById('descripcion');
        if (caja) caja.value = data.prompt;
        return data.prompt.trim();
    }
    return '';
};

window.obtenerPromptDadoProcesado = async function() {
    const rawPrompt = await window.tirarDadoSilencioso();
    if (!rawPrompt) return null;

    let finalPos = rawPrompt;
    let finalNeg = "";

    const autoTranslate = document.getElementById('autoTranslateToggle') ? document.getElementById('autoTranslateToggle').checked : false;
    if (autoTranslate) {
        try {
            const fdTrad = new FormData();
            fdTrad.append('action', 'traducir_rapido');
            fdTrad.append('texto', rawPrompt);
            const llmSel = document.getElementById('llmModelSelector');
            if (llmSel && llmSel.value) fdTrad.append('llm_model', llmSel.value);

            const resTrad = await fetch('procesar.php', { method: 'POST', body: fdTrad });
            const dataTrad = await resTrad.json();
            if (dataTrad && dataTrad.traduccion && dataTrad.traduccion.trim() !== '') {
                finalPos = dataTrad.traduccion.trim();
            }
        } catch (e) {
            console.warn("Aviso traduciendo tirada de dado:", e);
        }
    }

    if (typeof window.getPromptsWithPresets === 'function') {
        const applied = window.getPromptsWithPresets(finalPos, "");
        finalPos = applied.pos;
        finalNeg = applied.neg;
    }

    return { pos: finalPos, neg: finalNeg, orig: rawPrompt };
};

const botonDadoMagico = document.getElementById('surpriseBtn');
if (botonDadoMagico) {
    // Clic derecho: Activar / Desactivar Modo Dado Fijo para Lotes y Bucle Infinito
    botonDadoMagico.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        window.dadoFijoActivo = !window.dadoFijoActivo;

        if (!botonDadoMagico.dataset.baseHtml) {
            botonDadoMagico.dataset.baseHtml = botonDadoMagico.innerHTML;
        }

        if (window.dadoFijoActivo) {
            botonDadoMagico.classList.remove('btn-warning');
            botonDadoMagico.classList.add('btn-info', 'border', 'border-light');
            botonDadoMagico.innerHTML = botonDadoMagico.dataset.baseHtml + ' <i class="bi bi-lock-fill" style="font-size: 0.75em;"></i>';
            
            const txtDadoOn = (typeof GartyLang !== 'undefined' && GartyLang.msg_dado_fijo_on) 
                ? GartyLang.msg_dado_fijo_on 
                : '🎲 Dado Fijo ACTIVADO (Clic derecho para quitar)';

            SwalDark.fire({
                toast: true, position: 'top-end', icon: 'info',
                title: txtDadoOn,
                showConfirmButton: false, timer: 2000
            });
        } else {
            botonDadoMagico.classList.remove('btn-info', 'border', 'border-light');
            botonDadoMagico.classList.add('btn-warning');
            botonDadoMagico.innerHTML = botonDadoMagico.dataset.baseHtml;

            const txtDadoOff = (typeof GartyLang !== 'undefined' && GartyLang.msg_dado_fijo_off) 
                ? GartyLang.msg_dado_fijo_off 
                : '🎲 Dado Fijo DESACTIVADO';

            SwalDark.fire({
                toast: true, position: 'top-end', icon: 'info',
                title: txtDadoOff,
                showConfirmButton: false, timer: 1500
            });
        }
    });

    // Clic izquierdo: Tirada única tradicional
    botonDadoMagico.onclick = async function() {
        let cajaTextoPrincipal = document.getElementById('descripcion');
        if (!cajaTextoPrincipal) return;
        let iconoOriginal = botonDadoMagico.innerHTML;
        botonDadoMagico.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        botonDadoMagico.disabled = true;

        try {
            let promptSorpresa = await window.tirarDadoSilencioso();
            if (promptSorpresa !== "") {
                const claseBase = window.dadoFijoActivo ? 'btn-info' : 'btn-warning';
                botonDadoMagico.classList.replace(claseBase, 'btn-success');
                setTimeout(() => botonDadoMagico.classList.replace('btn-success', claseBase), 500);
            } else {
                SwalDark.fire({ icon: 'info', title: GartyLang.avis_dado1, text: GartyLang.avis_dado2 });
            }
        } catch (error) {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_ollama_err_title, text: GartyLang.swal_ollama_err_text });
        } finally {
            botonDadoMagico.innerHTML = iconoOriginal;
            botonDadoMagico.disabled = false;
        }
    };
}

// --- BOTÓN LIMPIAR RECARGA ---
const clearBtnEl = document.getElementById('clearBtn');
if(clearBtnEl) {
    clearBtnEl.addEventListener('click', function(event) {
        event.preventDefault();
        try {
            let selector = document.getElementById('selector');
            if (selector) sessionStorage.setItem('selectorGuardado', selector.value);
        } catch (error) { console.error(GartyLang.log_err_selector, error); }
        window.location.href = window.location.pathname;
    });
}

// --- MONITOR DE COLA GPU ---
function startQueueMonitor() {
    setInterval(async () => {
        try {
            const fd = new FormData(); fd.append('action', 'check_queue');
            const res = await fetch('procesar.php', { method: 'POST', body: fd });
            const data = await res.json();
            const monitor = document.getElementById('queueMonitor');
            const countSpan = document.getElementById('queueCount');
            if (data.error) { if (monitor) monitor.classList.add('d-none'); return; }
            if (data.status === 'ok' && data.total > 0) {
                if (countSpan) countSpan.innerText = data.total;
                if (monitor) monitor.classList.remove('d-none');
            } else { if (monitor) monitor.classList.add('d-none'); }
        } catch (e) {}
    }, 3000); 
}

// --- GESTIÓN TABLAS Y SOPORTE ---
function filtrarTablaAdmin(tbodyId, columnaFiltro, textoFiltro) {
    const tbody = document.getElementById(tbodyId);
    const filas = tbody.getElementsByTagName('tr');
    const filtroUpper = textoFiltro.toUpperCase();
    for (let i = 0; i < filas.length; i++) {
        if (filas[i].cells.length === 1) continue; 
        const celda = filas[i].getElementsByTagName('td')[columnaFiltro];
        if (celda) {
            const textoCelda = celda.textContent || celda.innerText;
            filas[i].style.display = (filtroUpper === "" || textoCelda.toUpperCase().indexOf(filtroUpper) > -1) ? "" : "none";
        }
    }
}

async function enviarTicketSoporte() {
    const email = document.getElementById('supEmail').value.trim();
    const tipo = document.getElementById('supTipo').value;
    const mensaje = document.getElementById('supMensaje').value.trim();
    const btn = document.getElementById('btnEnviarSoporte');

    if (!email || !tipo || !mensaje) {
        SwalDark.fire({ icon: 'warning', title: GartyLang.swal_sup_falta_tit, text: GartyLang.swal_sup_falta_txt });
        return;
    }
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> ' + GartyLang.btn_sup_enviando;
    btn.disabled = true;

    try {
        const response = await fetch('https://formspree.io/f/maqzobbq', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email: email, tipo_consulta: tipo, mensaje: mensaje, app_version: 'Arquitecto IA byGarty' })
        });
        if (response.ok) {
            SwalDark.fire({ icon: 'success', title: GartyLang.swal_sup_ok_tit, text: GartyLang.swal_sup_ok_txt, confirmButtonText: GartyLang.swal_sup_ok_btn });
            document.getElementById('formSoportePro').reset();
            const inst = bootstrap.Modal.getInstance(document.getElementById('modalSoportePro'));
            if (inst) inst.hide();
        } else { throw new Error('Fallo en el servicio web.'); }
    } catch (error) { SwalDark.fire({ icon: 'error', title: GartyLang.swal_sup_err_tit, text: GartyLang.swal_sup_err_txt });
    } finally { btn.innerHTML = originalText; btn.disabled = false; }
}

function validarLicencia() {
    const btn = document.getElementById('btnValidarLicencia');
    const input = document.getElementById('inputLicenseKey');
    const msgDiv = document.getElementById('licenciaMensaje');
    const key = input.value.trim();

    if (!key) { msgDiv.innerHTML = `<div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> ${GartyLang.err_valid_key}</div>`; return; }

    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${GartyLang.mod_lic_validating}`;
    msgDiv.innerHTML = '';

    let formData = new FormData(); formData.append('action', 'activar_licencia'); formData.append('license_key', key);

    fetch('procesar.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            msgDiv.innerHTML = `<div class="alert alert-success py-2"><i class="bi bi-check-circle-fill"></i> ${data.mensaje}</div>`;
            btn.innerHTML = `<i class="bi bi-check2-all"></i> ${GartyLang.mod_lic_activated}`;
            btn.classList.replace('btn-warning', 'btn-success');
            setTimeout(() => { window.location.reload(); }, 2000);
        } else {
            msgDiv.innerHTML = `<div class="alert alert-danger py-2"><i class="bi bi-x-circle-fill"></i> ${data.error}</div>`;
            btn.disabled = false; btn.innerHTML = textoOriginal;
        }
    })
    .catch(error => {
        msgDiv.innerHTML = `<div class="alert alert-danger py-2"><i class="bi bi-wifi-off"></i> ${GartyLang.err_conn_local}</div>`;
        btn.disabled = false; btn.innerHTML = textoOriginal;
    });
}

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => { new bootstrap.Tooltip(el, { trigger: 'manual' }); });
});

function showFeedback(btn) {
    const tooltip = bootstrap.Tooltip.getInstance(btn);
    btn.setAttribute("data-bs-original-title", "¡Copiado!"); tooltip.show();
    const originalIcon = btn.innerHTML; btn.innerHTML = '<i class="bi bi-check"></i>';
    setTimeout(() => { tooltip.hide(); btn.setAttribute("data-bs-original-title", "Copiar"); btn.innerHTML = originalIcon; }, 1500);
}

function copyText(id, btn) {
    const text = document.getElementById(id).innerText;
    function fallbackCopy() {
        const textArea = document.createElement("textarea"); textArea.value = text;
        document.body.appendChild(textArea); textArea.select(); document.execCommand("copy"); document.body.removeChild(textArea);
    }
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => showFeedback(btn)).catch(() => { fallbackCopy(); showFeedback(btn); });
    } else { fallbackCopy(); showFeedback(btn); }
}