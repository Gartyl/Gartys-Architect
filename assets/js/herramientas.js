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
        if (['[LLM]', '[VISION]', '[CHAT]'].includes(currentCat)) return;
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
        let offset = 8; let metadata = {};
        try {
            while (offset < view.byteLength) {
                const length = view.getUint32(offset);
                const type = String.fromCharCode(view.getUint8(offset+4), view.getUint8(offset+5), view.getUint8(offset+6), view.getUint8(offset+7));
                if (type === 'tEXt') {
                    const data = new Uint8Array(view.buffer, offset+8, length);
                    const text = new TextDecoder('utf-8').decode(data);
                    const nullIdx = text.indexOf('\0');
                    const keyword = text.substring(0, nullIdx);
                    metadata[keyword] = text.substring(nullIdx + 1);
                }
                offset += 12 + length;
            }
            if (metadata.prompt) {
                const promptData = JSON.parse(metadata.prompt);
                let foundPos = "", foundNeg = "";
                for (const key in promptData) {
                    const node = promptData[key];
                    if (node.class_type === "KSampler" || node.class_type === "KSamplerAdvanced") {
                        if(document.getElementById('stepsInput')) document.getElementById('stepsInput').value = node.inputs.steps || 30;
                        if(document.getElementById('cfgInput')) document.getElementById('cfgInput').value = node.inputs.cfg || 5.0;
                        if(document.getElementById('samplerInput')) document.getElementById('samplerInput').value = node.inputs.sampler_name || "euler";
                        if(document.getElementById('schedulerInput')) document.getElementById('schedulerInput').value = node.inputs.scheduler || "normal";
                        if(document.getElementById('seedInput')) document.getElementById('seedInput').value = node.inputs.seed || node.inputs.noise_seed || -1;
                    }
                    if (node.class_type === "CLIPTextEncode" && node.inputs.text) {
                        if (foundPos === "") foundPos = node.inputs.text;
                        else if (node.inputs.text.length > foundPos.length) { foundNeg = foundPos; foundPos = node.inputs.text; }
                        else { foundNeg = node.inputs.text; }
                    }
                }
                let desc = foundPos;
                if(foundNeg) desc += "\n\nNEGATIVO:\n" + foundNeg;
                document.getElementById('descripcion').value = desc;
                if(typeof savePreferences === 'function') savePreferences();
            } else {
                SwalDark.fire({ icon: 'warning', title: GartyLang.swal_no_meta_title, text: GartyLang.swal_no_meta_text });
            }
        } catch(err) { console.error(err); }
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
        const isVideo = url.toLowerCase().endsWith('.mp4') || url.toLowerCase().endsWith('.webm');
        if (isVideo) {
            const videoElement = document.createElement('video');
            videoElement.src = url; videoElement.muted = true;
            videoElement.onloadedmetadata = () => { videoElement.currentTime = Math.max(0, videoElement.duration - 0.1); };
            videoElement.onseeked = () => {
                const canvas = document.createElement('canvas');
                canvas.width = videoElement.videoWidth; canvas.height = videoElement.videoHeight;
                const ctx = canvas.getContext('2d'); ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
                currentImageBase64 = canvas.toDataURL('image/png'); currentDocumentText = ""; 
                
                const filename = url.split('/').pop();
                const imgPreviewContainer = document.getElementById('imgPreviewContainer');
                if(imgPreviewContainer) {
                    imgPreviewContainer.innerHTML = `
                    <div class="badge bg-warning text-dark p-3 mt-2 mb-2 fs-6 w-100 text-start shadow-sm">
                        <i class="bi bi-film fs-4 me-2"></i> ${GartyLang.gal_frame_extracted} ${filename} 
                        <i class="bi bi-x-circle ms-auto float-end" style="cursor:pointer;" onclick="if(typeof clearVideoUpload === 'function') clearVideoUpload()"></i>
                    </div>
                    <img src="${currentImageBase64}" class="img-fluid rounded shadow-sm mt-2" style="max-height: 200px; border: 2px solid #ffc107;">`;
                    imgPreviewContainer.style.display = 'block';
                }
                const inst = bootstrap.Modal.getInstance(document.getElementById('modalGaleriaReciente'));
                if (inst) inst.hide();
            };
        } else {
            const response = await fetch(url);
            const blob = await response.blob();
            const reader = new FileReader();
            reader.onloadend = function() {
                if (typeof setBaseImageFromDataUrl === 'function') setBaseImageFromDataUrl(reader.result);
                const inst = bootstrap.Modal.getInstance(document.getElementById('modalGaleriaReciente'));
                if (inst) inst.hide();
            }
            reader.readAsDataURL(blob);
        }
    } catch (e) { console.error(GartyLang.log_err_load_gallery, e); }
}

async function cargarDatosImagen(imgId) {
    const fd = new FormData(); fd.append('action', 'get_single_image_data'); fd.append('img_id', imgId);
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

            const posCode = document.getElementById('posContent'); const promptArea = document.getElementById('promptArea');
            if (posCode) posCode.innerText = data.prompt_positivo || "";
            if (promptArea) { promptArea.style.display = 'block'; promptArea.classList.remove('collapse', 'd-none'); }

            const negCode = document.getElementById('negContent'); const negativeArea = document.getElementById('negativeArea');
            if (negCode) negCode.innerText = data.prompt_negativo || "";
            if (negativeArea) { negativeArea.style.display = 'block'; negativeArea.classList.remove('collapse', 'd-none'); }
            
            if (data.metadata) {
                const meta = JSON.parse(data.metadata);
                if (document.getElementById('stepsInput')) document.getElementById('stepsInput').value = meta.Steps || meta.steps || 30;
                if (document.getElementById('cfgInput')) document.getElementById('cfgInput').value = meta['CFG Scale'] || meta.cfg || 5.0;
                if (document.getElementById('seedInput')) document.getElementById('seedInput').value = meta.Seed || meta.seed || -1;
                
                setTimeout(() => {
                    const modelDropdown = document.querySelector('select[name="model_path"]') || document.getElementById('model_path');
                    const savedModel = meta.Model || meta.model || data.model_path || data.modelo_grafico;
                    if (modelDropdown && savedModel) {
                        for (let i = 0; i < modelDropdown.options.length; i++) {
                            if (modelDropdown.options[i].value.includes(savedModel) || savedModel.includes(modelDropdown.options[i].value)) {
                                modelDropdown.selectedIndex = i; break;
                            }
                        }
                    }
                    
                    const lorasGuardados = meta.LoRAs || meta.loras || meta.Loras;
                    const switchLoras = document.getElementById('loraToggle'); 
                    let lorasValidos = [];
                    if (lorasGuardados) {
                        const textoLoras = String(lorasGuardados).trim();
                        if (textoLoras.length > 2) lorasValidos = textoLoras.split(',').filter(lora => lora.match(/(.+?)\s*\(([\d.]+)\)/));
                    }

                    if (switchLoras) {
                        if (lorasValidos.length > 0) {
                            if (!switchLoras.checked) { switchLoras.checked = true; switchLoras.dispatchEvent(new Event('change')); }
                            setTimeout(() => {
                                const btnAñadir = document.getElementById('addLoraBtn') || document.querySelector('button[onclick="addLoraRow()"]');
                                lorasValidos.forEach((loraStr, index) => {
                                    const match = loraStr.match(/(.+?)\s*\(([\d.]+)\)/);
                                    if (match) {
                                        const nombreLora = match[1].trim(); const pesoLora = match[2];
                                        let filas = document.querySelectorAll('.lora-row');
                                        if (index >= filas.length && btnAñadir) { btnAñadir.click(); filas = document.querySelectorAll('.lora-row'); }
                                        const filaActual = filas[index];
                                        if (filaActual) {
                                            const select = filaActual.querySelector('.lora-selector');
                                            if (select) {
                                                let searchLora = nombreLora.toLowerCase().replace('.safetensors', '').trim();
                                                for (let i = 0; i < select.options.length; i++) {
                                                    let optText = select.options[i].text.toLowerCase();
                                                    if (optText.includes(searchLora) || searchLora.includes(optText)) {
                                                        select.selectedIndex = i; select.dispatchEvent(new Event('change')); break;
                                                    }
                                                }
                                            }
                                            if (filaActual.querySelector('.lora-strength-high')) filaActual.querySelector('.lora-strength-high').value = pesoLora;
                                            if (filaActual.querySelector('.lora-strength-low')) filaActual.querySelector('.lora-strength-low').value = pesoLora;
                                        }
                                    }
                                });
                            }, 300);
                        } else {
                            if (switchLoras.checked) { switchLoras.checked = false; switchLoras.dispatchEvent(new Event('change')); }
                        }
                    }
                }, 100);
            }
            if (typeof savePreferences === 'function') savePreferences();
        }
    } catch(e) { SwalDark.fire({ icon: 'error', title: GartyLang.swal_net_err_title, text: GartyLang.swal_rest_err_net }); }
}

// --- BOTÓN SORPRÉNDEME ---
const botonDadoMagico = document.getElementById('surpriseBtn');
if (botonDadoMagico) {
    botonDadoMagico.onclick = async function() {
        let cajaTextoPrincipal = document.getElementById('descripcion');
        if (!cajaTextoPrincipal) return;
        let iconoOriginal = botonDadoMagico.innerHTML;
        botonDadoMagico.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
        botonDadoMagico.disabled = true;

        try {
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

            if (data.success) {
                if (data.prompt !== "") {
                    cajaTextoPrincipal.value = data.prompt;
                    botonDadoMagico.classList.replace('btn-warning', 'btn-success');
                    setTimeout(() => botonDadoMagico.classList.replace('btn-success', 'btn-warning'), 500);
                } else {
                    SwalDark.fire({ icon: 'info', title: GartyLang.avis_dado1, text: GartyLang.avis_dado2 });
                }
            } else if (data.error) {
                SwalDark.fire({ icon: 'warning', title: GartyLang.ollama_in_process, text: data.error, confirmButtonText: GartyLang.btn_entendido });
            }
        } catch (error) { SwalDark.fire({ icon: 'error', title: GartyLang.swal_ollama_err_title, text: GartyLang.swal_ollama_err_text });
        } finally { botonDadoMagico.innerHTML = iconoOriginal; botonDadoMagico.disabled = false; }
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