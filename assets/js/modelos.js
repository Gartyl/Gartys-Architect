// ==============================================================================
// --- MODELOS.JS: GESTIÓN DE MODELOS, OLLAMA, LORAS, CIVITAI E IDIOMAS ---
// ==============================================================================

window.misModelosOllama = []; 
window.modelosDBSistema = []; 
let allCheckpoints = [];
let allUnets = [];
let allControlNets = [];
let allUpscalers = [];
let loadedLoras = []; 
let filteredLoras = []; 

// --- MÓDULO: OLLAMA ---
async function cargarModelosOllama() {
    try {
        let fd = new FormData();
        fd.append('action', 'get_ollama_models');
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.error) {
            SwalDark.fire({
                icon: 'error',
                title: GartyLang.swal_ollama_err_title,
                text: data.error,
                confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}`
            });
            return; 
        }
        
        if (data.models && data.models.length > 0) {
            window.misModelosOllama = data.models;
            inyectarModelosOllama();
        } else {
            console.warn(GartyLang.log_ollama_warn_empty);
        }
    } catch (e) { 
        console.error(GartyLang.log_ollama_err_conn, e); 
    }
}

function inyectarModelosOllama() {
    const llmSel = document.getElementById('llmModelSelector'); 
    if (!llmSel) return;

    const modeloPrevio = llmSel.value;
    const selectorEl = document.getElementById('selector');
    const selActual = selectorEl ? selectorEl.value : '';
    const catDB = (selActual === '[VISION]') ? 'vision' : 'chat';
    
    const llmsDB = window.modelosDBSistema ? window.modelosDBSistema.filter(m => m.categoria === catDB && m.motor === 'ollama') : [];

    if (llmsDB.length > 0) {
        llmSel.innerHTML = '';
        llmsDB.forEach((m, index) => {
            const opt = document.createElement('option');
            opt.value = m.id; 
            opt.innerText = m.nombre_visual;
            if (modeloPrevio && modeloPrevio == m.id) opt.selected = true;
            else if (!modeloPrevio && index === 0) opt.selected = true;
            llmSel.appendChild(opt);
        });
    } else {
        // --- CIRUGÍA: FALLBACK DE OLLAMA ELIMINADO ---
        llmSel.innerHTML = '<option value="" disabled selected>⚠️ ' + (GartyLang.opt_no_compat_models || 'Registra un modelo en Administración') + '</option>';
    }
}

// --- MÓDULO: SELECCIÓN Y FILTRADO DE MODELOS GRÁFICOS ---
window.autoSelectModelByTag = function(tag) {
    const sel = document.getElementById('modelSelector');
    if (!sel) return;

    let keyword = '';
    switch(tag) {
        case '[SD15]': keyword = 'sd15'; break;
        case '[SDXL]': 
        case '[VISION]': keyword = 'sdxl'; break;
        case '[NATURAL_IMAGE]': keyword = 'flux'; break;
        case '[VIDEO]': keyword = 'video'; break; 
        default: return; 
    }

    for (let i = 0; i < sel.options.length; i++) {
        let optionText = sel.options[i].text.toLowerCase();
        let optionValue = sel.options[i].value.toLowerCase();
        if (optionText.includes(keyword) || optionValue.includes(keyword)) {
            sel.selectedIndex = i;
            sel.dispatchEvent(new Event('change')); 
            break; 
        }
    }
};

function getFilteredItems(itemsList, category) {
    const graphCategories = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VISION]', '[CHAT]', '[VIDEO]'];
    if (!graphCategories.includes(category)) return [];

    let filtered = [];
    if (category === '[SD15]') {
        filtered = itemsList.filter(m => m.toLowerCase().includes('sd15') || m.toLowerCase().includes('v15'));
    } else if (category === '[SDXL]') { 
        filtered = itemsList.filter(m => {
            const low = m.toLowerCase();
            return low.includes('sdxl') || low.includes('illustrious') || low.includes('zavy') || low.includes('sdxl_c') || low.includes('xl');
        });
    } else if (category === '[NATURAL_IMAGE]') {
        filtered = itemsList.filter(m => {
            const low = m.toLowerCase();
            // AÑADIDO: krea2 y krea-2 a la lista de arquitecturas naturales permitidas
            const isNatural = low.includes('flux') || low.includes('sd35') || low.includes('sd3.5') || low.includes('sd3_5') || low.includes('z-image') || low.includes('zimage') || low.includes('z_image') || low.includes('qwen') || low.includes('krea2') || low.includes('krea-2') || low.includes('hunyuan') || low.includes('hidream');
            const isNotSdxl = !low.includes('sdxl') && !low.includes('sdxl_c');
            return isNatural && isNotSdxl;
        });
    } else if (category === '[VIDEO]') {
        filtered = itemsList.filter(m => {
            const low = m.toLowerCase();
            return low.includes('video') || low.includes('wan') || low.includes('ltx') || low.includes('qwen');
        });
    } else if (category === '[VISION]' || category === '[CHAT]') {
        filtered = itemsList;
    } else {
        filtered = itemsList;
    }

    if (typeof APP_ENV.isAvanzado !== 'undefined' && typeof APP_ENV.isAdmin !== 'undefined') {
        if (APP_ENV.isAvanzado && !APP_ENV.isAdmin) {
            filtered = filtered.filter(m => !/_c[\/\\]/i.test(m));
        }
    }
    return filtered;
}

async function loadModelsAndLoras() {
    if (APP_ENV.isAvanzado) {
        try {
            const fdModels = new FormData();
            fdModels.append('action', 'get_checkpoints');
            const resModels = await fetch('procesar.php', { method: 'POST', body: fdModels });
            const textModels = await resModels.text();
            
            try {
                const dataModels = JSON.parse(textModels);
                allCheckpoints = dataModels.checkpoints || [];
                allUnets = dataModels.unets || [];
                allControlNets = dataModels.controlnets || [];
                allUpscalers = dataModels.upscalers || [];
            } catch(err) { }
            
            updateModelFilter(document.getElementById('selector').value);
            
            const upsel = document.getElementById('upscaleModelSelector');
            if(upsel) {
                upsel.innerHTML = '<option value="">' + GartyLang.opt_select_model + '</option>';
                if(allUpscalers.length > 0) {
                    allUpscalers.forEach(m => {
                        const opt = document.createElement('option'); opt.value = m; opt.textContent = m.split(/[\\/]/).pop();
                        upsel.appendChild(opt);
                    });
                } else {
                    const popularUpscalers = ['4x-UltraSharp.pth', 'RealESRGAN_x4plus.pth', '4x_NMKD-Siax_200k.pth', '8x_NMKD-Superscale_150000_G.pth'];
                    popularUpscalers.forEach(m => {
                        const opt = document.createElement('option'); opt.value = m; opt.textContent = m; upsel.appendChild(opt);
                    });
                }
            }
        } catch(e) {}

        try {
            const fdLoras = new FormData();
            fdLoras.append('action', 'get_loras');
            const resLoras = await fetch('procesar.php', { method: 'POST', body: fdLoras });
            const dataLoras = await resLoras.json();
            if (dataLoras.loras && dataLoras.loras.length > 0) {
                loadedLoras = dataLoras.loras;
                updateLoraFilter(document.getElementById('selector').value); 
            }
        } catch(e) {}
    }
}

function updateModelFilter(category) {
    const modelSel = document.getElementById('modelSelector');
    const modelBlock = document.getElementById('modelBlock');
    if (!modelSel || !modelBlock) return;
    
    const graphCategories = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VISION]', '[CHAT]', '[VIDEO]'];
    if (!graphCategories.includes(category)) { modelBlock.style.display = "none"; return; }
    
    modelBlock.style.display = "block";
    modelSel.innerHTML = "";

    const mapaCategorias = { '[SD15]': 'sd15', '[SDXL]': 'sdxl', '[NATURAL_IMAGE]': 'flux', '[VIDEO]': 'video' };
    const catDB = mapaCategorias[category] || '';
    
    const modelosFiltradosDB = window.modelosDBSistema ? window.modelosDBSistema.filter(m => {
        if (category === '[VISION]' || category === '[CHAT]') return m.motor === 'comfyui' && m.categoria !== 'video';
        return m.categoria === catDB && m.motor === 'comfyui';
    }) : [];

    if (modelosFiltradosDB.length > 0) {
        modelosFiltradosDB.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id; 
            if (m.categoria) opt.dataset.categoria = m.categoria;
            
            let esPro = (m.nivel_acceso === 'avanzado' || m.nivel_acceso === 'pro');
            if (typeof currentUserRole !== 'undefined' && currentUserRole === 'free' && esPro) {
                opt.disabled = true;
                opt.textContent = m.nombre_visual + ' 🔒 (Pro)';
            } else {
                opt.textContent = m.nombre_visual;
            }
            modelSel.appendChild(opt);
        });
    } else {
        // --- CIRUGÍA: FALLBACK ELIMINADO ---
        // Si no hay modelos en la Base de Datos para esta categoría, forzamos a que lo registren.
        modelSel.innerHTML = '<option value="" disabled selected>⚠️ ' + (GartyLang.opt_no_compat_models || 'Registra un modelo en Administración') + '</option>';
    }

    const cnSel = document.getElementById('cnModelSelector');
    if(cnSel) {
        if(allControlNets.length > 0) {
            cnSel.innerHTML = '<option value="">' + GartyLang.opt_select_model + '</option>';
            let filteredCn = allControlNets; 
            if (APP_ENV.isAvanzado) {
                filteredCn = filteredCn.filter(m => !/_c[\/\\]/i.test(m));
            }
            filteredCn.forEach(m => {
                const opt = document.createElement('option'); opt.value = m; opt.textContent = m.split(/[\\/]/).pop();
                cnSel.appendChild(opt);
            });
        } else {
            cnSel.innerHTML = '<option value="">' + GartyLang.opt_empty_cn_folder + '</option>';
        }
    }
    if (typeof syncLorasWithSelectedModel === 'function') syncLorasWithSelectedModel();
}

function updateLoraFilter(category) {
    const graphCategories = ['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VISION]', '[CHAT]', '[VIDEO]'];
    
    // --- NUEVO: CERROJO INTELIGENTE DE MODELOS ---
    const modelSel = document.getElementById('modelSelector');
    let modeloSeleccionado = "";
    let esModeloValido = true;

    if (modelSel && modelSel.selectedIndex !== -1) {
        let opcion = modelSel.options[modelSel.selectedIndex];
        // Comprobamos si el modelo está desactivado (es de un Free) o es el texto de "Sin modelos"
        if (opcion.disabled || opcion.value === "" || opcion.text.includes('⚠️')) {
            esModeloValido = false;
        } else {
            // TRUCO: Juntamos el nombre del menú y el nombre del archivo
            modeloSeleccionado = (opcion.text + " " + opcion.value).toLowerCase();
        }
    } else {
        esModeloValido = false;
    }
    // ---------------------------------------------

    if (!graphCategories.includes(category) || !esModeloValido) { 
        // Si no hay categoría o NO hay modelo válido, vaciamos los LoRAs
        filteredLoras = []; 
    } else { 
        let tempLoras = [];
        
        // --- LA MAGIA REAL: DETERMINAR LA ARQUITECTURA ---
        // Ya no nos fiamos a ciegas de la "categoría" del menú principal (Chat/Visión).
        // Nos fijamos en qué modelo físico está seleccionado en el desplegable "Modelo Gráfico".
        
        let targetArch = category; 
        
        // Si estamos en Chat o Visión, miramos qué modelo gráfico está puesto para inferir la arquitectura
        if (category === '[VISION]' || category === '[CHAT]') {
             if (modeloSeleccionado.includes('sd15') || modeloSeleccionado.includes('v15')) targetArch = '[SD15]';
             else if (modeloSeleccionado.includes('sdxl') || modeloSeleccionado.includes('xl')) targetArch = '[SDXL]';
             else if (modeloSeleccionado.includes('video') || modeloSeleccionado.includes('wan') || modeloSeleccionado.includes('ltx')) targetArch = '[VIDEO]';
             else targetArch = '[NATURAL_IMAGE]'; // Fallback a los gordos (Flux/Chroma/etc)
        }

        // 2. Filtramos la lista maestra de LoRAs (loadedLoras) según la arquitectura detectada
        if (targetArch === '[SD15]') {
            tempLoras = loadedLoras.filter(m => m.toLowerCase().includes('sd15') || m.toLowerCase().includes('v15'));
        } else if (targetArch === '[SDXL]') {
            tempLoras = loadedLoras.filter(m => m.toLowerCase().includes('sdxl') || m.toLowerCase().includes('xl'));
        } else if (targetArch === '[NATURAL_IMAGE]') {
            // === FILTRO AISLADO POR ARQUITECTURAS ===
            if (modeloSeleccionado.includes('chroma') && !modeloSeleccionado.includes('zavy')) {
                tempLoras = loadedLoras.filter(m => m.toLowerCase().includes('chroma\\') || m.toLowerCase().includes('chroma/'));
            } else if (modeloSeleccionado.includes('flux')) {
                tempLoras = loadedLoras.filter(m => {
                    let low = m.toLowerCase();
                    return low.includes('flux\\') || low.includes('flux/') || low.startsWith('f1_') || low.startsWith('f2_');
                });
            } else if (modeloSeleccionado.includes('z-image') || modeloSeleccionado.includes('zimage') || modeloSeleccionado.includes('z_image')) {
                tempLoras = loadedLoras.filter(m => m.toLowerCase().includes('zimage'));
            } else if (modeloSeleccionado.includes('qwen')) {
                tempLoras = loadedLoras.filter(m => m.toLowerCase().includes('qwen'));
            } else if (modeloSeleccionado.includes('krea2') || modeloSeleccionado.includes('krea-2') || modeloSeleccionado.includes('krea 2')) {
                tempLoras = loadedLoras.filter(m => {
                    let low = m.toLowerCase();
                    return low.includes('krea2') || low.includes('krea-2');
                });
            } else if (modeloSeleccionado.includes('sd3') || modeloSeleccionado.includes('3.5')) {
                tempLoras = loadedLoras.filter(m => {
                    let low = m.toLowerCase();
                    return low.includes('sd3') || low.includes('3.5');
                });
			// =========================================================================
            // --- NUEVO: FILTRO AISLADO PARA CARPETAS HUNYUAN Y HIDREAM ---
            // =========================================================================
            } else if (modeloSeleccionado.includes('hunyuan') && !modeloSeleccionado.includes('video')) {
                tempLoras = loadedLoras.filter(m => {
					let low = m.toLowerCase();
					return low.includes('hunyuan');
				});
            } else if (modeloSeleccionado.includes('hidream')) {
                tempLoras = loadedLoras.filter(m => {
					let low = m.toLowerCase();
					return low.includes('hidream');
				});
            // =========================================================================
            } else {
                tempLoras = loadedLoras.filter(m => {
                    const low = m.toLowerCase();
                    return low.includes('flux') || low.includes('sd35') || low.includes('sd3.5') || low.includes('sd3_5') || low.includes('zimage') || low.includes('z_image') || low.includes('z-image') || low.includes('qwen') || low.includes('krea2') || low.includes('krea-2') || low.includes('hunyuan') || low.includes('hidream');      
                });
            }
        } else if (targetArch === '[VIDEO]') {
            tempLoras = loadedLoras.filter(m => m.toLowerCase().includes('wan') || m.toLowerCase().includes('ltx'));
        } else {
            tempLoras = [...loadedLoras];
        }

        // 3. Filtrado de Admin (Ocultamos las carpetas _C a los que no son admin)
        if (APP_ENV.isAvanzado && !APP_ENV.isAdmin) {
            filteredLoras = tempLoras.filter(m => !/_c[\/\\]/i.test(m));
        } else {
            filteredLoras = tempLoras;
        }
    }

    // 4. Actualizamos visualmente todos los desplegables de LoRAs de la interfaz
    const selects = document.querySelectorAll('.lora-selector');
    if (!selects.length) return;

    selects.forEach(select => {
        const currentVal = select.value;
        select.innerHTML = '<option value="">' + (GartyLang.opt_no_lora || 'Sin LoRA') + '</option>';
        filteredLoras.forEach(lora => {
            const opt = document.createElement('option');
            opt.value = lora;
            // Mostramos solo el nombre limpio del archivo sin la ruta de la carpeta
            opt.textContent = lora.split(/[\\/]/).pop();
            select.appendChild(opt);
        });
        
        // Si el valor que tenías seleccionado antes sigue existiendo, lo mantenemos. 
        if (currentVal && filteredLoras.includes(currentVal)) { 
            select.value = currentVal; 
        } else {
            select.value = "";
        }
    });
}

function addLoraRow() {
    const wrapper = document.getElementById('lorasWrapper');
    if (!wrapper) return;
    const row = document.createElement('div');
    row.className = 'row mb-2 lora-row';
    let optionsHtml = '<option value="">' + GartyLang.opt_no_lora + '</option>';
    filteredLoras.forEach(lora => { 
        const cleanName = lora.split(/[\\/]/).pop();
        optionsHtml += `<option value="${lora}">${cleanName}</option>`; 
    });
    row.innerHTML = `
        <div class="col-6 pe-1 d-flex">
            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 me-1" onclick="this.closest('.lora-row').remove()" title="${GartyLang.lora_btn_rmv}"><i class="bi bi-x"></i></button>
            <select class="form-select lora-selector pref-track">${optionsHtml}</select>
        </div>
        <div class="col-3 px-1">
            <div class="input-group input-group-sm" title="${GartyLang.lora_title_h}">
                <span class="input-group-text bg-dark border-secondary text-warning fw-bold">H</span>
				<input type="number" class="form-control lora-strength-high pref-track" value="0.8" min="-5.0" max="5.0" step="0.05">
            </div>
        </div>
        <div class="col-3 ps-1 lora-l-box">
            <div class="input-group input-group-sm" title="${GartyLang.lora_title_l}">
                <span class="input-group-text bg-dark border-secondary text-info fw-bold">L</span>
				<input type="number" class="form-control lora-strength-low pref-track" value="0.8" min="-5.0" max="5.0" step="0.05">
            </div>
        </div>
        <div class="col-12 mt-1">
            <span class="badge bg-secondary text-info border border-info trigger-badge text-wrap text-break" style="display:none; cursor:pointer; text-align: left;" title="${GartyLang.lora_title_add}"></span>
        </div>
    `;
    wrapper.appendChild(row);
    
    document.querySelectorAll('.lora-l-box').forEach(box => {
        box.style.display = (document.getElementById('selector').value === '[VIDEO]') ? 'block' : 'none';
    });
    
    let selectElement = row.querySelector('select');
    let triggerBadge = row.querySelector('.trigger-badge');
    let promptBox = document.getElementById('descripcionInput') || document.querySelector('textarea'); 

    selectElement.addEventListener('change', function() {
        let loraName = this.value;
        triggerBadge.style.display = 'none';

        if(loraName) {
            let fd = new FormData();
            fd.append('action', 'get_lora_trigger');
            fd.append('lora_name', loraName);

            fetch('procesar.php', { method: 'POST', body: fd })
            .then(response => response.text()) 
            .then(text => {
                try {
                    let data = JSON.parse(text);
                    if(data.triggers && data.triggers.trim() !== '') {
                        triggerBadge.innerText = "✨ " + GartyLang.lora_lbl_triggers + ": " + data.triggers;
                        triggerBadge.style.display = 'inline-block';
                        
                        triggerBadge.onclick = function() {
                            if(promptBox) {
                                promptBox.value += (promptBox.value ? ', ' : '') + data.triggers;
                            } else {
                                navigator.clipboard.writeText(data.triggers);
                                SwalDark.fire({toast: true, position: 'top-end', icon: 'success', title: GartyLang.lora_msg_trig_copied, text: data.triggers, showConfirmButton: false, timer: 3000});
                            }
                        };
                    }
                } catch(e) { console.error(GartyLang.err_server_route, text); }
            }).catch(err => console.log(GartyLang.err_conexion_log, err));
        }
    });
}

function syncLorasWithSelectedModel() {
    const modelSel = document.getElementById('modelSelector');
    const selectorGeneral = document.getElementById('selector');
    if (!modelSel || modelSel.selectedIndex === -1) return;
    
    const optionSeleccionada = modelSel.options[modelSel.selectedIndex];
    const categoriaM = optionSeleccionada.dataset.categoria; 
    let categoriaDestino = '';

    if (categoriaM) {
        let mapaCategorias = { 'sd15': '[SD15]', 'sdxl': '[SDXL]', 'flux': '[NATURAL_IMAGE]', 'video': '[VIDEO]' };
        categoriaDestino = mapaCategorias[categoriaM.toLowerCase()];
    }

    if (categoriaDestino) {
        if (typeof updateLoraFilter === 'function') updateLoraFilter(categoriaDestino);
    } else if (selectorGeneral) {
        const catActual = selectorGeneral.value;
        if (['[SD15]', '[SDXL]', '[NATURAL_IMAGE]', '[VIDEO]'].includes(catActual)) {
            if (typeof updateLoraFilter === 'function') updateLoraFilter(catActual);
        }
    }
}

// --- MÓDULO: TABLAS ADMINISTRADOR ---
function abrirGestorModelos() {
    const modal = new bootstrap.Modal(document.getElementById('modalGestorModelos'));
    modal.show();
    cargarTablaModelos();
}

async function cargarTablaModelos() {
    const tbody = document.getElementById('tablaModelosBody');
    tbody.innerHTML = `<tr><td colspan="8" class="text-info"><span class="spinner-border spinner-border-sm"></span> ${GartyLang.msg_query_engines}</td></tr>`;
    
    try {
        let fd = new FormData();
        fd.append('action', 'get_modelos_bd');
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        let data = await res.json();
        
        if (data.modelos && data.modelos.length > 0) {
            tbody.innerHTML = data.modelos.map(m => {
                let candado = (typeof currentUserRole !== 'undefined' && currentUserRole === 'free' && (m.nivel_acceso === 'avanzado' || m.nivel_acceso === 'pro')) 
                              ? ` <span class="text-warning small fw-bold" title="${GartyLang.msg_pro_exclusive}">🔒 (Pro)</span>` 
                              : '';
                let textoNivel = (m.nivel_acceso === 'avanzado') ? GartyLang.adm_lvl_adv : GartyLang.adm_lvl_user;

                return `
                <tr>
                    <td class="text-secondary">${m.id}</td>
                    <td class="fw-bold text-light">${m.nombre_visual}${candado}</td>
                    <td class="text-muted small"><code>${m.nombre_archivo}</code></td>
                    <td><span class="badge ${m.motor === 'ollama' ? 'bg-info text-dark' : 'bg-primary'}"><i class="bi bi-cpu-fill"></i> ${m.motor.toUpperCase()}</span></td>
                    <td><span class="badge bg-secondary text-light">${m.categoria.toUpperCase()}</span></td>
                    <td><span class="badge ${m.nivel_acceso === 'avanzado' ? 'bg-warning text-dark' : 'border border-secondary text-secondary'}"><i class="bi ${m.nivel_acceso === 'avanzado' ? 'bi-star-fill' : 'bi-person'}"></i> ${textoNivel.toUpperCase()}</span></td>
                    <td>
                        <div class="form-check form-switch d-flex justify-content-center m-0">
                            <input class="form-check-input border-success" type="checkbox" ${m.activo == 1 ? 'checked' : ''} onchange="cambiarEstadoModelo(${m.id}, this.checked)">
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-danger shadow-sm" onclick="borrarModeloBD(${m.id}, '${m.nombre_visual}')" title="${GartyLang.btn_eliminar}"><i class="bi bi-trash3-fill"></i></button>
                    </td>
                </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="8" class="text-warning fw-bold py-4"><i class="bi bi-exclamation-triangle"></i> ${GartyLang.msg_db_models_empty}</td></tr>`;
        }
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-danger py-4">${GartyLang.msg_err_conn_proc}</td></tr>`;
    }
}

async function guardarModeloBD() {
    const nombre = document.getElementById('modNombre').value.trim();
    const archivo = document.getElementById('modArchivo').value.trim();
    const motor = document.getElementById('modMotor').value;
    const cat = document.getElementById('modCat').value;
    const nivel = document.getElementById('modNivel') ? document.getElementById('modNivel').value : 'usuario';

    if(!nombre || !archivo) {
        SwalDark.fire({icon: 'error', title: GartyLang.swal_miss_data_title, text: GartyLang.swal_miss_data_text});
        return;
    }

    let fd = new FormData();
    fd.append('action', 'save_modelo_bd');
    fd.append('nombre_visual', nombre);
    fd.append('nombre_archivo', archivo);
    fd.append('motor', motor);
    fd.append('categoria', cat);
    fd.append('nivel_acceso', nivel); 

   try {
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        let data = await res.json();
        if(data.success) {
            document.getElementById('formNuevoModelo').reset();
            cargarTablaModelos();
            SwalDark.fire({icon: 'success', title: GartyLang.swal_mod_inst_title, text: GartyLang.swal_mod_inst_text, timer: 2000, showConfirmButton: false});
        } else {
            throw new Error(data.error || GartyLang.err_db_unknown);
        }
    } catch(e) {
        SwalDark.fire({icon: 'error', title: GartyLang.swal_err_save_title, text: e.message});
    }
}

async function borrarModeloBD(id, nombre) {
    const confirm = await SwalDark.fire({
        title: GartyLang.swal_uninstall_title,
        text: `${GartyLang.swal_uninstall_text1} ${nombre} ${GartyLang.swal_uninstall_text2}`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: GartyLang.btn_siborrar, cancelButtonText: GartyLang.btn_cancelar
    });

    if (confirm.isConfirmed) {
        let fd = new FormData(); fd.append('action', 'delete_modelo_bd'); fd.append('id', id);
        try {
            await fetch('procesar.php', { method: 'POST', body: fd });
            cargarTablaModelos();
            SwalDark.fire({icon: 'success', title: GartyLang.swal_deleted_title, timer: 1500, showConfirmButton: false});
        } catch(e) {}
    }
}

async function cambiarEstadoModelo(id, estado) {
    let fd = new FormData(); fd.append('action', 'toggle_modelo_bd'); fd.append('id', id); fd.append('estado', estado ? 1 : 0);
    try { await fetch('procesar.php', { method: 'POST', body: fd }); } catch(e) {}
}

async function descargarModelosDisponibles() {
    try {
        let fd = new FormData(); fd.append('action', 'get_active_models');
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        window.modelosDBSistema = (await res.json()).modelos || [];
        updateModelFilter(document.getElementById('selector').value);
        inyectarModelosOllama();
    } catch(e) { console.error(GartyLang.log_err_load_idx, e); }
}

// --- DESCARGADOR CIVITAI ---
async function abrirDescargadorCivitai() {
    const { value: formValues } = await SwalDark.fire({
        target: document.getElementById('modalGestorModelos'),
        title: `<i class="bi bi-cloud-arrow-down-fill text-primary"></i> ${GartyLang.swal_dl_model_title}`,
        html: `
            <div class="text-start mt-3">
                <label class="small text-secondary fw-bold">1. ${GartyLang.swal_dl_lbl_1}</label>
                <input id="swal-url" class="form-control bg-dark text-light border-primary mb-3" placeholder="${GartyLang.swal_dl_ph_1}">
                <label class="small text-secondary fw-bold">2. ${GartyLang.swal_dl_lbl_2}</label>
                <input id="swal-nombre" class="form-control bg-dark text-light border-secondary mb-3" placeholder="${GartyLang.swal_dl_ph_2}">
                <label class="small text-secondary fw-bold">3. ${GartyLang.swal_dl_lbl_3}</label>
                <select id="swal-categoria" class="form-select bg-dark text-light border-secondary">
                    <optgroup label="${GartyLang.swal_dl_opt_base}">
                        <option value="ckpt_sd15">${GartyLang.swal_dl_opt_sd15}</option>
                        <option value="ckpt_sdxl">${GartyLang.swal_dl_opt_sdxl}</option>
                        <option value="unet_flux">${GartyLang.swal_dl_opt_flux}</option>
                        <option value="unet_video">${GartyLang.swal_dl_opt_vid}</option>
                    </optgroup>
                    <optgroup label="${GartyLang.swal_dl_opt_loras}">
                        <option value="lora_sd15">${GartyLang.swal_dl_lora_sd15}</option>
                        <option value="lora_sdxl">${GartyLang.swal_dl_lora_sdxl}</option>
                        <option value="lora_flux">${GartyLang.swal_dl_lora_flux}</option>
                        <option value="lora_video">${GartyLang.swal_dl_lora_vid}</option>
                    </optgroup>
                </select>
                <small class="text-info mt-2 d-block"><i class="bi bi-info-circle"></i> ${GartyLang.swal_dl_info}</small>
            </div>
        `,
        focusConfirm: false, showCancelButton: true, confirmButtonText: `<i class="bi bi-download"></i> ${GartyLang.btn_start_dl}`, cancelButtonText: GartyLang.btn_cancelar,
        preConfirm: () => {
            const url = document.getElementById('swal-url').value.trim();
            const nombre = document.getElementById('swal-nombre').value.trim();
            const categoria = document.getElementById('swal-categoria').value;
            if (!url || !nombre) { Swal.showValidationMessage(GartyLang.swal_val_req); return false; }
            if (!url.startsWith('http')) { Swal.showValidationMessage(GartyLang.swal_val_http); return false; }
            return { url, nombre, categoria };
        }
    });

    if (formValues) iniciarDescargaPHP(formValues.url, formValues.nombre, formValues.categoria);
}

async function iniciarDescargaPHP(url, nombre, categoria) {
    const fd = new FormData(); fd.append('action', 'descargar_civitai'); fd.append('url', url); fd.append('nombre_visual', nombre); fd.append('categoria', categoria);
    SwalDark.fire({
        title: GartyLang.swal_dl_prog_title,
        html: `
            <div class="text-light mb-2">${GartyLang.swal_dl_prog_text1} <b>${nombre}</b>...</div>
            <div class="progress" style="height: 25px; background-color: #0d1117;">
                <div id="barra-progreso" class="progress-bar progress-bar-striped progress-bar-animated bg-success fw-bold" style="width: 0%">0%</div>
            </div>
            <small class="text-secondary mt-2 d-block">${GartyLang.swal_dl_prog_text2}</small>
        `,
        showConfirmButton: false, allowOutsideClick: false
    });

    const monitorProgreso = setInterval(async () => {
        try {
            let res = await fetch('procesar.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'action=leer_progreso_descarga' });
            let data = await res.json();
            let barra = document.getElementById('barra-progreso');
            if (barra) {
                barra.style.width = data.porcentaje + '%'; barra.innerText = data.porcentaje + '%';
                if (data.porcentaje > 5) { barra.classList.remove('bg-success'); barra.classList.add('bg-primary'); }
            }
        } catch (e) { console.warn("Fallo temporal leyendo progreso"); }
    }, 1500);

    try {
        const respuestaDescarga = await fetch('procesar.php', { method: 'POST', body: fd });
        const dataDescarga = await respuestaDescarga.json();
        clearInterval(monitorProgreso); 

        if (dataDescarga.success) {
            SwalDark.fire({ icon: 'success', title: GartyLang.swal_dl_done_title, text: `${nombre} ${GartyLang.swal_dl_done_text}`, confirmButtonText: GartyLang.btn_genial }).then(() => { location.reload(); });
        } else {
            SwalDark.fire({ icon: 'error', title: GartyLang.swal_fail_title, text: dataDescarga.error });
        }
    } catch (e) {
        clearInterval(monitorProgreso);
        SwalDark.fire({ icon: 'error', title: GartyLang.swal_crit_err_title, text: GartyLang.swal_crit_err_text });
    }
}

// --- GESTOR DE IDIOMAS ADMIN ---
async function cargarIdiomasAdmin() {
    const tbody = document.getElementById('tablaIdiomasBody');
    if (!tbody) return;

    let fd = new FormData(); fd.append('action', 'admin_get_idiomas');
    try {
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        let data = await res.json();

        if (data.success && data.idiomas.length > 0) {
            // 1. Nuestro diccionario base
            let nombresVisuales = {
                'es': '🇪🇸 Español (Castellano)', 'en': '🇬🇧 English (International)', 'ca': '<img src="assets/img/ca.svg" alt="CAT" style="width: 20px; height: 20px; border-radius: 2px;"> Català',
                'fr': '🇫🇷 Français', 'it': '🇮🇹 Italiano', 'de': '🇩🇪 Deutsch', 'pt': '🇵🇹 Português'
            };

            // 2. Fusionamos con el diccionario dinámico del JSON que nos manda PHP
            if (data.nombres_meta) {
                nombresVisuales = { ...nombresVisuales, ...data.nombres_meta };
            }

            tbody.innerHTML = data.idiomas.map(lang => {
                const descripcion = nombresVisuales[lang] || GartyLang.lang_custom;
                return `
                    <tr class="border-bottom border-secondary" style="border-color: rgba(255,255,255,0.04) !important;">
                        <td class="fw-bold text-warning" style="padding-left: 15px; font-family: monospace; font-size: 0.95rem;">${lang.toUpperCase()}</td>
                        <td class="text-light small">${descripcion}</td>
                        <td style="text-align: end; padding-right: 15px;">
                            <button type="button" class="btn btn-sm btn-outline-info fw-bold py-1 px-3 shadow-sm" onclick="editarIdioma('${lang}')"><i class="bi bi-pencil-square me-1"></i> ${GartyLang.btn_translate_edit}</button>
                        </td>
                    </tr>`;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="text-warning text-center py-4">${GartyLang.err_no_lang_files}</td></tr>`;
        }
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center py-4"><i class="bi bi-exclamation-octagon me-2"></i> ${GartyLang.err_comm_server}</td></tr>`;
    }
}

async function crearNuevoIdioma() {
    const { value: formValues } = await SwalDark.fire({
        target: document.getElementById('modalGestorModelos'), 
        title: GartyLang.swal_new_lang_title, 
        html: `
            <div class="mb-3 text-start">
                <label class="form-label text-secondary small fw-bold mb-1">${GartyLang.swal_new_lang_text || 'Código ISO (2 letras):'}</label>
                <input id="swal-lang-code" class="swal2-input m-0 w-100" maxlength="2" autocapitalize="off" placeholder="${GartyLang.swal_new_lang_ph}">
            </div>
            <div class="text-start">
                <label class="form-label text-secondary small fw-bold mb-1">Nombre en el menú (Opcional):</label>
                <input id="swal-lang-name" class="swal2-input m-0 w-100" placeholder="Ej: 🇷🇺 Ruso">
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true, 
        confirmButtonText: GartyLang.btn_continuar, 
        cancelButtonText: GartyLang.btn_cancelar,
        preConfirm: () => {
            const code = document.getElementById('swal-lang-code').value.toLowerCase().trim();
            const name = document.getElementById('swal-lang-name').value.trim();
            if (!code || code.length !== 2) {
                Swal.showValidationMessage(GartyLang.swal_new_lang_val);
                return false;
            }
            return { code: code, name: name };
        }
    });

    if (formValues) {
        // Le pasamos también el nombre a la función editarIdioma
        editarIdioma(formValues.code, true, formValues.name);
    }
}

async function editarIdioma(langCode, isNew = false, langName = '') {
    let contenidoCode = "<" + `?php\nreturn [\n    '${GartyLang.tpl_key}' => '${GartyLang.tpl_translation}',\n];\n`;
    let currentLangName = langName;

    if (!isNew) {
        let fd = new FormData(); fd.append('action', 'admin_leer_idioma'); fd.append('lang_code', langCode);
        try {
            let res = await fetch('procesar.php', { method: 'POST', body: fd });
            let data = await res.json();
            if (data.success) {
                contenidoCode = data.contenido;
                // Si el PHP nos devuelve el nombre actual, lo guardamos para mostrarlo
                if (data.lang_name) currentLangName = data.lang_name;
            }
            else { SwalDark.fire(GartyLang.swal_err_title, data.error, 'error'); return; }
        } catch(e) { SwalDark.fire(GartyLang.swal_err_title, GartyLang.swal_err_read_file, 'error'); return; }
    } else {
        let fdEs = new FormData(); fdEs.append('action', 'admin_leer_idioma'); fdEs.append('lang_code', 'es');
        let resEs = await fetch('procesar.php', { method: 'POST', body: fdEs });
        let dataEs = await resEs.json();
        if (dataEs.success) contenidoCode = dataEs.contenido;
    }

    const safeContent = contenidoCode.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    
    const { value: formValues, isConfirmed } = await SwalDark.fire({
        target: document.getElementById('modalGestorModelos'), 
        title: `<i class="bi bi-pencil-square text-info"></i> ${GartyLang.swal_edit_lang_title} ${langCode.toUpperCase()}.php`,
        html: `
            <div class="text-start mb-3">
                <label class="form-label text-secondary small fw-bold mb-1">Nombre público en el menú (Opcional):</label>
                <input id="swal-edit-lang-name" class="form-control bg-dark text-light border-secondary" placeholder="Ej: 🇷🇺 Ruso" value="${currentLangName}">
            </div>
            <textarea id="swal-lang-editor" class="form-control bg-dark text-warning border-secondary" style="height: 350px; font-family: monospace; font-size: 0.85rem; white-space: pre;" spellcheck="false">${safeContent}</textarea>
        `,
        width: '800px', showCancelButton: true, confirmButtonText: `<i class="bi bi-floppy"></i> ${GartyLang.btn_save_file}`, cancelButtonText: GartyLang.btn_cancelar,
        preConfirm: () => { 
            return {
                textEdited: document.getElementById('swal-lang-editor').value,
                nameEdited: document.getElementById('swal-edit-lang-name').value.trim()
            };
        }
    });

    if (isConfirmed && formValues && formValues.textEdited) {
        let fdSave = new FormData(); 
        fdSave.append('action', 'admin_guardar_idioma'); 
        fdSave.append('lang_code', langCode); 
        fdSave.append('contenido', formValues.textEdited);
        
        // Enviamos el nombre editado al servidor
        if (formValues.nameEdited !== '') {
            fdSave.append('lang_name', formValues.nameEdited);
        }

        try {
            let res = await fetch('procesar.php', { method: 'POST', body: fdSave });
            let data = await res.json();
            if (data.success) {
                SwalDark.fire({ toast: true, position: 'top-end', icon: 'success', title: GartyLang.swal_lang_saved, showConfirmButton: false, timer: 2000 });
                cargarIdiomasAdmin(); 
            } else { SwalDark.fire(GartyLang.swal_err_save_title, data.error, 'error'); }
        } catch (e) { SwalDark.fire(GartyLang.swal_err_title, GartyLang.swal_err_conn_save, 'error'); }
    }
}

// --- MÓDULO: SUGERENCIAS INTELIGENTES DE MOTOR ---
function sugerirAjustesMotor() {
    const modelSel = document.getElementById('modelSelector');
    if (!modelSel || modelSel.selectedIndex === -1) return;

    // Leemos el nombre del modelo seleccionado
    const opcion = modelSel.options[modelSel.selectedIndex].text.toLowerCase();

    // Capturamos las cajas (Ajusta los IDs de steps y cfg si en tu HTML se llaman diferente)
    const stepsInput = document.getElementById('stepsInput') || document.querySelector('input[name="steps"]');
    const cfgInput = document.getElementById('cfgInput') || document.querySelector('input[name="cfg"]');
    const samplerInput = document.getElementById('samplerInput');
    const schedulerInput = document.getElementById('schedulerInput');

    // 1. Valores base por defecto (Ej: para SDXL clásico o genéricos)
    let newSteps = 30;
    let newCfg = 5.0;
    let newSampler = 'euler_ancestral';
    let newScheduler = 'beta';

    // 2. Replicamos tu árbol de decisiones de api_gpu.php
    if (opcion.includes('turbo') || opcion.includes('schnell')) {
        newSteps = 6;
        newCfg = 1.5;
        newSampler = 'euler';
        newScheduler = 'simple';
    } else if (opcion.includes('krea2') || opcion.includes('krea-2') || opcion.includes('krea 2')) {
        newSteps = 8;
        newCfg = 1.0;
        newSampler = 'euler';
        newScheduler = 'simple';
    } else if (opcion.includes('qwen')) {
        newSteps = 20;
        newCfg = 1.0;
        newSampler = 'euler';
        newScheduler = 'simple';
    } else if (opcion.includes('chroma')) {
        newSteps = 10; 
        newCfg = 1.0;
        newSampler = 'euler';
        newScheduler = 'simple';
    } else if (opcion.includes('flux') || opcion.includes('sd35') || opcion.includes('sd3.5') || opcion.includes('z-image') || opcion.includes('zimage') || opcion.includes('z_image')) {
        newSteps = 25;
        newCfg = 4.0;
        newSampler = 'euler';
        newScheduler = 'simple';
    }

    // 3. Aplicamos los valores sugeridos a las cajas
    if (stepsInput) stepsInput.value = newSteps;
    if (cfgInput) cfgInput.value = newCfg;
    if (samplerInput) samplerInput.value = newSampler;
    if (schedulerInput) schedulerInput.value = newScheduler;

    // 4. Efecto visual: un parpadeo para que el usuario sepa que la IA ha ajustado los valores
    const inputs = [stepsInput, cfgInput, samplerInput, schedulerInput];
    inputs.forEach(input => {
        if (input) {
            input.classList.add('border-info', 'text-info'); // Se iluminarán en azul/info
            setTimeout(() => input.classList.remove('border-info', 'text-info'), 800);
        }
    });
}

// INICIALIZADORES AL CARGAR
document.addEventListener('DOMContentLoaded', () => {
    descargarModelosDisponibles();
    cargarModelosOllama();
    loadModelsAndLoras(); 
    const modelSel = document.getElementById('modelSelector');
    
    if (modelSel) {
        modelSel.addEventListener('change', syncLorasWithSelectedModel);
        modelSel.addEventListener('change', sugerirAjustesMotor); // <--- NUEVO: Llama a la sugerencia al cambiar
    }
});