// ==============================================================================
// --- PROMPTS.JS: GESTOR DE BBDD DE PROMPTS Y WILDCARDS ---
// ==============================================================================

// --- MÓDULO: WILDCARDS (COMODINES) ---
let todosLosWildcards = [];

async function abrirModalWildcards() {
    const modal = new bootstrap.Modal(document.getElementById('modalWildcards'));
    modal.show();
    
    if (todosLosWildcards.length === 0) {
        const fd = new FormData();
        fd.append('action', 'get_wildcards');
        try {
            const res = await fetch('procesar.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.error) {
                SwalDark.fire({
                    icon: 'error',
                    title: GartyLang.swal_err_wildcards_title,
                    text: data.error,
                    confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}`
                });
                document.getElementById('listaWildcards').innerHTML = `<span class="text-muted"><i class="bi bi-x-circle"></i> ${GartyLang.err_load_wildcards}</span>`;
                return;
            }

            if (data.wildcards) {
                todosLosWildcards = data.wildcards;
                renderizarWildcards(todosLosWildcards);
            }
        } catch(e) {
            SwalDark.fire({
                icon: 'error',
                title: GartyLang.swal_net_err_title,
                text: `${GartyLang.swal_err_comm}${e.message}`,
                confirmButtonText: `<i class="bi bi-check2-circle"></i> ${GartyLang.btn_entendido}`
            });
            document.getElementById('listaWildcards').innerHTML = `<span class="text-muted"><i class="bi bi-wifi-off"></i> ${GartyLang.err_conn_wildcards}</span>`;
        }
    } else {
        document.getElementById('buscadorWildcards').value = "";
        renderizarWildcards(todosLosWildcards);
    }
    
    setTimeout(() => document.getElementById('buscadorWildcards').focus(), 500);
}

function renderizarWildcards(lista) {
    const contenedor = document.getElementById('listaWildcards');
    if (lista.length === 0) {
        contenedor.innerHTML = `<span class="text-muted w-100 text-center mt-3"><i class="bi bi-search"></i> ${GartyLang.msg_no_wildcards}</span>`;
        return;
    }
    contenedor.innerHTML = lista.map(w => 
        `<button class="btn btn-sm btn-outline-warning rounded-pill shadow-sm" onclick="insertarWildcard('${w}')"><i class="bi bi-file-text"></i> ${w}</button>`
    ).join('');
}

function filtrarWildcards() {
    const texto = document.getElementById('buscadorWildcards').value.toLowerCase();
    const filtrados = todosLosWildcards.filter(w => w.toLowerCase().includes(texto));
    renderizarWildcards(filtrados);
}

function insertarWildcard(nombre) {
    const cajaIdea = document.getElementById('descripcion');
    const textoInsertar = `__${nombre}__`;
    
    const cursorStart = cajaIdea.selectionStart;
    const textBefore = cajaIdea.value.substring(0, cursorStart);
    const textAfter  = cajaIdea.value.substring(cajaIdea.selectionEnd, cajaIdea.value.length);
    
    const spBefore = (textBefore.length === 0 || textBefore.endsWith(' ')) ? '' : ' ';
    const spAfter = (textAfter.length === 0 || textAfter.startsWith(' ') || textAfter.startsWith(',')) ? '' : ' ';
    
    cajaIdea.value = textBefore + spBefore + textoInsertar + spAfter + textAfter;
    
    const modalEl = document.getElementById('modalWildcards');
    const inst = bootstrap.Modal.getInstance(modalEl);
    if (inst) inst.hide();
    
    cajaIdea.focus();
}

// --- MÓDULO: GESTOR DE PROMPTS BBDD ---
let idPromptEditando = null;

async function cargarTablaPrompts() {
    const tbody = document.getElementById('tablaPromptsBody');
    if(!tbody) return; // Parche de seguridad
    tbody.innerHTML = `<tr><td colspan="7" class="text-info"><span class="spinner-border spinner-border-sm"></span> ${GartyLang.adm_msg_load_prompts}</td></tr>`;
    
    try {
        let fd = new FormData();
        fd.append('action', 'get_prompts_bd');
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        let data = await res.json();
        
        window.promptsDBSistema = data.prompts || []; 
        
        if (data.prompts && data.prompts.length > 0) {
            const etiquetasTipos = {
                'seed_image': '🌱 ' + GartyLang.adm_pr_sd_img,
                'seed_chat': '🌱 ' + GartyLang.adm_pr_sd_chat,
                'seed_video': '🌱 ' + GartyLang.adm_pr_sd_vid,
                'random_prompt': '🎲 ' + GartyLang.adm_pr_rnd_char,
                'chat_personality': '🗣️ ' + GartyLang.adm_pr_chat_pers,
                'chat_default': '🤖 ' + GartyLang.adm_pr_chat_def,
                'sys_prompt_chat': '💬 ' + GartyLang.adm_pr_chat_sys,
                'enhance_prompt': '✨ ' + GartyLang.adm_pr_amp_trad,
                'vision_analyst': '👁️ ' + GartyLang.adm_pr_vis_ana,
                'core_architect': '⚙️ ' + GartyLang.adm_pr_core_arq,
                'estilo_llm': '📝 ' + GartyLang.adm_pr_sty_txt,
                'estilo_sd15': '🎨 ' + GartyLang.adm_pr_sty_sd15,
                'estilo_sdxl': '⚡ ' + GartyLang.adm_pr_sty_sdxl,
                'estilo_flux': '💎 ' + GartyLang.adm_pr_sty_flux,
                'estilo_video': '🎬 ' + GartyLang.adm_pr_sty_vid
            };

            tbody.innerHTML = data.prompts.map(p => {
                let candado = (typeof APP_ENV !== 'undefined' && APP_ENV.userRole === 'free' && p.tipo === 'chat_persona') 
                              ? ` <span class="text-warning small fw-bold" title="${GartyLang.adm_lbl_pro_exc}">🔒 (Pro)</span>` 
                              : '';
                
                let tipoVisual = etiquetasTipos[p.tipo] || p.tipo.toUpperCase();

                return `
                <tr>
                    <td class="text-secondary">${p.id}</td>
                    <td class="fw-bold text-light">${p.titulo}${candado}</td>
                    <td><span class="badge bg-secondary text-light">${tipoVisual}</span></td>
                    <td><span class="badge border border-info text-info">${p.idioma.toUpperCase()}</span></td>
                    <td class="small text-muted">${p.parametros ? '⚙️ ' + GartyLang.lbl_yes : '-'}</td>
                    <td>
                        <div class="form-check form-switch d-flex justify-content-center m-0">
                            <input class="form-check-input border-info" type="checkbox" ${p.activo == 1 ? 'checked' : ''} onchange="cambiarEstadoPrompt(${p.id}, this.checked)">
                        </div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-info shadow-sm me-1" onclick="duplicarRegistro(${p.id}, '${p.titulo}')" title="${GartyLang.btn_duplicar}"><i class="bi bi-copy"></i></button>
                        <button class="btn btn-sm btn-warning me-1" onclick="cargarPromptEnFormulario(${p.id})" title="${GartyLang.btn_editar}"><i class="bi bi-pencil-fill"></i></button>
                        <button class="btn btn-sm btn-outline-danger shadow-sm" onclick="borrarPromptBD(${p.id}, '${p.titulo}')" title="${GartyLang.btn_eliminar}"><i class="bi bi-trash3-fill"></i></button>
                    </td>
                </tr>
                `;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="7" class="text-warning fw-bold py-4">${GartyLang.adm_msg_empty_prompts}</td></tr>`;
        }
    } catch(e) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-danger py-4">${GartyLang.err_conexion}</td></tr>`;
    }
}

function cargarPromptEnFormulario(id) {
    const prompt = window.promptsDBSistema.find(p => p.id == id);
    if (!prompt) return;

    idPromptEditando = id;

    // Blindaje: Comprobamos que cada caja existe antes de inyectar datos
    const elTitulo = document.getElementById('prTitulo');
    if (elTitulo) elTitulo.value = prompt.titulo || '';

    const elTipo = document.getElementById('prTipo');
    if (elTipo) elTipo.value = prompt.tipo || '';

    const elParams = document.getElementById('prParams');
    if (elParams) elParams.value = prompt.parametros || ''; 

    const elTexto = document.getElementById('prTexto');
    if (elTexto) elTexto.value = prompt.prompt_texto || '';

    // --- BLINDAJE ABSOLUTO DEL DESPLEGABLE DE IDIOMA ---
    const selIdioma = document.getElementById('prIdioma');
    if (selIdioma) {
        selIdioma.disabled = false; // Forzamos que se despierte
        let idiomaBD = String(prompt.idioma || 'es').trim().toLowerCase();
        
        // 1. Intento directo normal
        selIdioma.value = idiomaBD;
        
        // 2. Si se ha quedado tonto, buscamos la opción a lo bruto
        if (selIdioma.selectedIndex === -1 || selIdioma.value === "") {
            for (let i = 0; i < selIdioma.options.length; i++) {
                let valorOpcion = selIdioma.options[i].value.trim().toLowerCase();
                let textoOpcion = selIdioma.options[i].text.trim().toLowerCase();
                
                if (valorOpcion === idiomaBD || textoOpcion === idiomaBD || valorOpcion.includes(idiomaBD)) {
                    selIdioma.selectedIndex = i;
                    break;
                }
            }
        }
        
        // 3. ¡EL MARTILLAZO PARA PLUGINS VISUALES!
        // A) Disparo nativo para JS moderno
        selIdioma.dispatchEvent(new Event('change', { bubbles: true }));
        
        // B) Disparo jQuery para Select2 / Bootstrap Select
        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(selIdioma).trigger('change');
            
            // Si por casualidad usa Bootstrap-Select (selectpicker), lo forzamos a redibujarse
            if (typeof window.jQuery.fn.selectpicker !== 'undefined') {
                window.jQuery(selIdioma).selectpicker('refresh');
            }
        }
    }
    // --------------------------------------------------

    const btnGuardar = document.querySelector('button[onclick="guardarPromptBD()"]');
    if (btnGuardar) {
        btnGuardar.innerHTML = '<i class="bi bi-pencil-square"></i> Actualizar Prompt';
        btnGuardar.classList.remove('btn-info', 'text-dark');
        btnGuardar.classList.add('btn-warning', 'text-dark');
    }

    const formEl = document.getElementById('formNuevoPrompt');
    if (formEl) formEl.scrollIntoView({ behavior: 'smooth' });
}

async function guardarPromptBD() {
    const titulo = document.getElementById('prTitulo') ? document.getElementById('prTitulo').value.trim() : '';
    const tipo = document.getElementById('prTipo') ? document.getElementById('prTipo').value : '';
    const parametros = document.getElementById('prParams') ? document.getElementById('prParams').value.trim() : '';
    const texto = document.getElementById('prTexto') ? document.getElementById('prTexto').value.trim() : '';
    
    // Captura segura del idioma
    const selIdioma = document.getElementById('prIdioma');
    const idioma = selIdioma ? selIdioma.value : 'es';

    if(!titulo || !texto) {
        SwalDark.fire({icon: 'error', title: GartyLang.swal_miss_data_title, text: GartyLang.swal_prompt_req_text});
        return;
    }

    let fd = new FormData();
    if (idPromptEditando !== null) {
        fd.append('action', 'update_prompt_bd');
        fd.append('id', idPromptEditando);
    } else {
        fd.append('action', 'save_prompt_bd');
    }

    fd.append('titulo', titulo); 
    fd.append('tipo', tipo);
    fd.append('idioma', idioma); 
    fd.append('parametros', parametros);
    fd.append('prompt_texto', texto);

    try {
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        let data = await res.json();
        
        if(data.success) {
            const formEl = document.getElementById('formNuevoPrompt');
            if (formEl) formEl.reset();
            
            idPromptEditando = null;
            
            const btnGuardar = document.querySelector('button[onclick="guardarPromptBD()"]');
            if (btnGuardar) {
                btnGuardar.innerHTML = `<i class="bi bi-save"></i> ${GartyLang.btn_save_prompt}`;
                btnGuardar.classList.remove('btn-warning');
                btnGuardar.classList.add('btn-info');
            }

            cargarTablaPrompts();
            SwalDark.fire({icon: 'success', title: GartyLang.swal_op_completed, timer: 1500, showConfirmButton: false});
        }
    } catch(e) {
        SwalDark.fire({icon: 'error', title: GartyLang.swal_err_title, text: GartyLang.swal_err_conn_prob});
    }
}

async function borrarPromptBD(id, titulo) {
    const confirm = await SwalDark.fire({ 
        title: GartyLang.swal_del_prompt_title, 
        text: `${GartyLang.swal_del_prompt_text1} ${titulo} ${GartyLang.swal_del_prompt_text2}`, 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#d33', 
        cancelButtonColor: '#6c757d',
        confirmButtonText: GartyLang.btn_siborrar,
        cancelButtonText: GartyLang.btn_cancelar
    });
    
    if (confirm.isConfirmed) {
        let fd = new FormData(); 
        fd.append('action', 'delete_prompt_bd'); 
        fd.append('id', id);
        
        try { 
            let res = await fetch('procesar.php', { method: 'POST', body: fd }); 
            let data = await res.json();
            if(data.success || data.mensaje) {
                cargarTablaPrompts();
                SwalDark.fire({icon: 'success', title: GartyLang.swal_deleted_title, timer: 1000, showConfirmButton: false});
            }
        } catch(e) {
            console.error(GartyLang.log_err_delete, e);
        }
    }
}

async function duplicarRegistro(id, titulo) {
    const confirm = await SwalDark.fire({
        title: GartyLang.swal_dup_title,
        text: `${GartyLang.swal_dup_text1} ${titulo} ${GartyLang.swal_dup_text2}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: GartyLang.btn_si_duplicar,
        cancelButtonText: GartyLang.btn_cancelar
    });
    
    if (confirm.isConfirmed) {
        let fd = new FormData();
        fd.append('action', 'duplicar_prompt');
        fd.append('id', id);

        try {
            let res = await fetch('procesar.php', { method: 'POST', body: fd });
            let data = await res.json();
            if (data.success || data.mensaje) {
                cargarTablaPrompts();
                SwalDark.fire({icon: 'success', title: GartyLang.swal_duplicated, timer: 1000, showConfirmButton: false});
            }    
        } catch(e) {
            console.error(GartyLang.log_err_duplicate, e);
        }
    }
}

async function cambiarEstadoPrompt(id, estado) {
    let fd = new FormData(); 
    fd.append('action', 'toggle_prompt_bd'); 
    fd.append('id', id); 
    fd.append('estado', estado ? 1 : 0);
    
    try {
        await fetch('procesar.php', { method: 'POST', body: fd });
    } catch(e) {
        console.error(GartyLang.log_err_toggle_state, e);
    }
}