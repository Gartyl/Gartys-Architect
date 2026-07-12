// ==============================================================================
// --- CORE.JS: CIMIENTOS, UTILIDADES GLOBALES Y UX PREMIUM ---
// ==============================================================================

// 1. CONFIGURACIÓN GLOBAL DE ALERTAS (Tema Oscuro para Arquitecto IA)
const SwalDark = Swal.mixin({
    background: '#161b22',
    color: '#c9d1d9',
    confirmButtonColor: '#238636',
    cancelButtonColor: '#d33',
    customClass: { popup: 'border border-secondary shadow-lg rounded-4' }
});

// 2. RECUPERACIÓN DE ESTADO (Carga inicial)
window.addEventListener('load', () => {
    // Miramos si hay un selector guardado en la memoria
    let selectorGuardado = sessionStorage.getItem('selectorGuardado');
    
    if (selectorGuardado) {
        let miSelector = document.getElementById('selector');
        if (miSelector) {
            miSelector.value = selectorGuardado;
            // "Pellizcamos" para que la página reaccione y cargue los paneles
            miSelector.dispatchEvent(new Event('change'));
        }
        sessionStorage.removeItem('selectorGuardado');
    }
});

// 3. MOTOR DE SONIDO Y PERMISOS (Blindado para navegadores estrictos)
let audioCtx = null;

function inicializarEntornoAvanzado() {
    if ("Notification" in window && Notification.permission === "default") {
        Notification.requestPermission().then(permission => {
            if (permission !== 'granted') console.warn("Permiso de notificaciones denegado.");
        });
    }
    
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain); gain.connect(audioCtx.destination);
        gain.gain.value = 0; // Volumen cero absoluto
        osc.start(0); osc.stop(0.01);
    } else if (audioCtx.state === 'suspended') {
        audioCtx.resume();
    }
}

// Desbloqueo inicial del audio con interacción del usuario
document.getElementById('submitBtn')?.addEventListener('click', inicializarEntornoAvanzado);
document.getElementById('gpuBtn')?.addEventListener('click', inicializarEntornoAvanzado);
document.addEventListener('click', function unlockOnce() {
    inicializarEntornoAvanzado();
    document.removeEventListener('click', unlockOnce);
});

function tocarCampana() {
    try {
        if (!audioCtx) return;
        if (audioCtx.state === 'suspended') audioCtx.resume();
        
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain); gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, audioCtx.currentTime); // Nota La (A5)
        gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);
        osc.start(audioCtx.currentTime); osc.stop(audioCtx.currentTime + 0.6);
    } catch(e) { console.warn("Audio falló", e); }
}

// 4. DISPARADOR DE AVISOS (Push Nativo + Parpadeo de Pestaña)
let originalTitle = document.title;
let titleBlinkInterval = null;

function avisarAlSistema(titulo, mensaje, imagenData) {
    if ("Notification" in window && Notification.permission === "granted") {
        let iconUrl = '';
        if (imagenData) {
            iconUrl = (imagenData.length < 500 && imagenData.includes('.')) 
                ? new URL('galeria/' + imagenData, window.location.href).href 
                : 'data:image/png;base64,' + imagenData;
        }
                      
        let miNotificacion = new Notification(titulo, { body: mensaje, icon: iconUrl, silent: true });
        miNotificacion.onclick = function() {
            window.focus();
            miNotificacion.close();
        };
    }
    
    // --- LÓGICA DE PESTAÑA CORREGIDA ---
    if (document.hidden) {
        if (titleBlinkInterval) clearInterval(titleBlinkInterval);
        let isBlinking = false;
        
        // Usamos el 'titulo' que recibe la función (el mismo de la notificación)
        // Añadimos un texto "salvavidas" por si llegara vacío
        let textoParpadeo = titulo || "¡Tarea terminada!"; 

        titleBlinkInterval = setInterval(() => {
            // Alternamos entre el título original ("Garty's Architect") y el texto
            document.title = isBlinking ? textoParpadeo : originalTitle;
            isBlinking = !isBlinking;
        }, 1000);
    }
}

document.addEventListener('visibilitychange', () => {
    if (!document.hidden && titleBlinkInterval) {
        clearInterval(titleBlinkInterval);
        titleBlinkInterval = null; // Limpiamos el intervalo
        document.title = originalTitle; // Restauramos el título original siempre
    }
});

// 5. ATAJOS DE TECLADO GLOBALES
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const visor = document.getElementById('visorPantallaCompleta');
        if (visor && visor.style.display === 'flex') {
            if (typeof cerrarVisor === 'function') cerrarVisor();
        }
        if (typeof SwalDark !== 'undefined') SwalDark.close();
    }
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        const gpuBtn = document.getElementById('gpuBtn');
        const submitBtn = document.getElementById('submitBtn');
        if (gpuBtn && !gpuBtn.classList.contains('d-none') && !gpuBtn.disabled) {
            gpuBtn.click();
        } else if (submitBtn && !submitBtn.disabled) {
            submitBtn.click();
        }
    }
});

// ==============================================================================
// --- MÓDULO: CARGAR DATOS DESDE GALERÍA E HISTORIAL (RESTAURACIÓN TOTAL) ---
// ==============================================================================
async function cargarDatosImagen(imgId) {
    const fd = new FormData(); 
    fd.append('action', 'get_single_image_data'); // Usamos tu acción original
    fd.append('img_id', imgId);
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        // --- NUESTRO ESCUDO SWALDARK ---
        if (data.error || !data) {
            SwalDark.fire({
                icon: 'error',
                title: typeof GartyLang !== 'undefined' ? GartyLang.swal_rest_err_title : 'Error de Restauración',
                text: data.error || 'Datos vacíos',
                confirmButtonText: `<i class="bi bi-check2-circle"></i> ${typeof GartyLang !== 'undefined' ? GartyLang.btn_entendido : 'Entendido'}`
            });
            return; 
        }
        // --- FIN DEL ESCUDO ---
        
        if (data.modelo) {
            let modeloFinal = String(data.modelo).toUpperCase().trim();
            if (!modeloFinal.startsWith('[')) modeloFinal = '[' + modeloFinal + ']';
            
            const selectorEl = document.getElementById('selector');
            if (selectorEl) {
                selectorEl.value = modeloFinal;
                selectorEl.dispatchEvent(new Event('change')); // Fuerza a la portada a cambiar de modo
            }
            
            if (typeof updateUIForSelector === 'function') updateUIForSelector(modeloFinal);
            if (typeof isAvanzado !== 'undefined' && isAvanzado) {
                if (typeof updateModelFilter === 'function') updateModelFilter(modeloFinal);
                if (typeof updateLoraFilter === 'function') updateLoraFilter(modeloFinal);
            }
            
            // --- INICIO CIRUGÍA DE BUZONES ---
            
            // 1. La Idea Inicial limpia
            const cajaPrincipal = document.getElementById('descripcion');
            if (cajaPrincipal) cajaPrincipal.value = data.descripcion_original || ""; 

            // 2. ABRIR EL CONTENEDOR PADRE PRINCIPAL
            const resultsDiv = document.getElementById('results');
            if (resultsDiv) {
                resultsDiv.style.display = 'block';
                resultsDiv.classList.remove('d-none', 'collapse');
            }

            // 3. El Prompt Positivo y forzar visibilidad
            const posCode = document.getElementById('posContent');
            const promptArea = document.getElementById('promptArea');
            if (posCode) {
                // Compatible tanto si es <textarea> como si es <code>
                if(posCode.tagName === 'TEXTAREA' || posCode.tagName === 'INPUT') posCode.value = data.prompt_positivo || "";
                else posCode.innerText = data.prompt_positivo || "";
            }
            if (promptArea) {
                promptArea.style.display = 'block';
                promptArea.classList.remove('collapse', 'd-none');
            }

            // 4. El Prompt Negativo y forzar visibilidad
            const negCode = document.getElementById('negContent');
            const negativeArea = document.getElementById('negativeArea');
            if (negCode) {
                if(negCode.tagName === 'TEXTAREA' || negCode.tagName === 'INPUT') negCode.value = data.prompt_negativo || "";
                else negCode.innerText = data.prompt_negativo || "";
            }
            if (negativeArea) {
                negativeArea.style.display = 'block';
                negativeArea.classList.remove('collapse', 'd-none');
            }
            // --- FIN CIRUGÍA DE BUZONES ---

            try {
                if (data.metadata) {
                    const meta = typeof data.metadata === 'string' ? JSON.parse(data.metadata) : data.metadata;
                    
                    // 1. Restaurar Parámetros Numéricos
                    if (document.getElementById('stepsInput')) document.getElementById('stepsInput').value = meta.Steps || meta.steps || 30;
                    if (document.getElementById('cfgInput')) document.getElementById('cfgInput').value = meta['CFG Scale'] || meta.cfg || 5.0;
                    if (document.getElementById('seedInput')) document.getElementById('seedInput').value = meta.Seed || meta.seed || -1;
                    
                    // 2. RESTAURAR EL MODELO GRÁFICO ESPECÍFICO
                    setTimeout(() => {
                        const modelDropdown = document.querySelector('select[name="model_path"]') || document.getElementById('model_path');
                        const savedModel = meta.Model || meta.model || data.model_path || data.modelo_grafico;
                        
                        if (modelDropdown && savedModel) {
                            for (let i = 0; i < modelDropdown.options.length; i++) {
                                if (modelDropdown.options[i].value.includes(savedModel) || savedModel.includes(modelDropdown.options[i].value)) {
                                    modelDropdown.selectedIndex = i;
                                    break;
                                }
                            }
                        }
                        
                        // 3. RESTAURACIÓN MÁGICA DE LORAS (CON ESCÁNER ESTRICTO)
                        const lorasGuardados = meta.LoRAs || meta.loras || meta.Loras;
                        const switchLoras = document.getElementById('loraToggle'); 
                        
                        let lorasValidos = [];
                        if (lorasGuardados) {
                            const textoLoras = String(lorasGuardados).trim();
                            if (textoLoras.length > 2) {
                                lorasValidos = textoLoras.split(',').filter(lora => lora.match(/(.+?)\s*\(([\d.]+)\)/));
                            }
                        }

                        if (switchLoras) {
                            if (lorasValidos.length > 0) {
                                if (!switchLoras.checked) {
                                    switchLoras.checked = true;
                                    switchLoras.dispatchEvent(new Event('change'));
                                }

                                setTimeout(() => {
                                    const btnAñadir = document.getElementById('addLoraBtn') || document.querySelector('.btn-add-lora') || document.querySelector('button[onclick="addLoraRow()"]');

                                    lorasValidos.forEach((loraStr, index) => {
                                        const match = loraStr.match(/(.+?)\s*\(([\d.]+)\)/);
                                        if (match) {
                                            const nombreLora = match[1].trim();
                                            const pesoLora = match[2];

                                            let filas = document.querySelectorAll('.lora-row');
                                            
                                            if (index >= filas.length && btnAñadir) {
                                                btnAñadir.click();
                                                filas = document.querySelectorAll('.lora-row'); 
                                            }

                                            const filaActual = filas[index];
                                            if (filaActual) {
                                                const select = filaActual.querySelector('.lora-selector');
                                                if (select) {
                                                    let searchLora = nombreLora.toLowerCase().replace('.safetensors', '').trim();
                                                    for (let i = 0; i < select.options.length; i++) {
                                                        let optText = select.options[i].text.toLowerCase();
                                                        if (optText.includes(searchLora) || searchLora.includes(optText)) {
                                                            select.selectedIndex = i;
                                                            select.dispatchEvent(new Event('change')); 
                                                            break;
                                                        }
                                                    }
                                                }

                                                const inputH = filaActual.querySelector('.lora-strength-high');
                                                const inputL = filaActual.querySelector('.lora-strength-low');
                                                const inputUnico = filaActual.querySelector('.lora-strength'); 
                                                
                                                if (inputH) inputH.value = pesoLora;
                                                if (inputL) inputL.value = pesoLora;
                                                if (inputUnico) inputUnico.value = pesoLora;
                                            }
                                        }
                                    });
                                }, 300);
                            } else {
                                if (switchLoras.checked) {
                                    switchLoras.checked = false;
                                    switchLoras.dispatchEvent(new Event('change'));
                                }
                            }
                        }
                    }, 100);
                }
            } catch(e) { console.error(typeof GartyLang !== 'undefined' ? GartyLang.log_err_meta_parse : 'Error metadata:', e); }

            if (typeof savePreferences === 'function') savePreferences();
            
            const modalEl = document.getElementById('modalGaleriaReciente');
            if (modalEl) {
                const inst = bootstrap.Modal.getInstance(modalEl);
                if (inst) inst.hide();
            }

            // Notificación visual de éxito
            if (typeof SwalDark !== 'undefined') {
                SwalDark.fire({
                    toast: true, position: 'top-end', icon: 'success', 
                    title: typeof GartyLang !== 'undefined' ? GartyLang.swal_params_loaded : 'Parámetros cargados', 
                    showConfirmButton: false, timer: 2000
                });
            }
        }
    } catch(e) { 
        // --- SI FALLA LA RED O EL SERVIDOR ---
        SwalDark.fire({
            icon: 'error',
            title: typeof GartyLang !== 'undefined' ? GartyLang.swal_net_err_title : 'Fallo de Red',
            text: typeof GartyLang !== 'undefined' ? GartyLang.swal_rest_err_net : 'Error cargando la imagen.',
            confirmButtonText: `<i class="bi bi-check2-circle"></i> ${typeof GartyLang !== 'undefined' ? GartyLang.btn_entendido : 'Entendido'}`
        });
    }
}

// Auto-cargar si venimos desde galeria o historial
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const idParaReutilizar = urlParams.get('reutilizar');
    
    if (idParaReutilizar) { 
        window.history.replaceState({}, document.title, window.location.pathname);
        setTimeout(() => { cargarDatosImagen(idParaReutilizar); }, 500);
    }
});

// Botón de limpiar UI (Mantenido intacto)
document.getElementById('clearBtn')?.addEventListener('click', function(event) {
    event.preventDefault();
    try {
        let selector = document.getElementById('selector');
        if (selector) {
            sessionStorage.setItem('selectorGuardado', selector.value);
        } else {
            console.warn(typeof GartyLang !== 'undefined' ? GartyLang.log_warn_selector : 'Aviso: No se encontró selector');
        }
    } catch (error) {
        console.error(typeof GartyLang !== 'undefined' ? GartyLang.log_err_selector : 'Error guardar selector:', error);
    }
    window.location.href = window.location.pathname;
});

// ==============================================================================
// --- 6. MONITOR GLOBAL DE COLA GPU (RADAR DE VRAM) ---
// ==============================================================================
function iniciarMonitorCola() {
    // Consulta la cola cada 5 segundos
    setInterval(async () => {
        try {
            const fd = new FormData();
            fd.append('action', 'check_queue');
            
            const res = await fetch('procesar.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            const monitorDiv = document.getElementById('queueMonitor');
            const countSpan = document.getElementById('queueCount');
            
            if (monitorDiv && countSpan) {
                // AQUÍ LA CLAVE: Buscamos 'data.total' que es lo que devuelve tu PHP
                const trabajosPendientes = data.total || 0;
                
                if (trabajosPendientes > 0) {
                    countSpan.innerText = trabajosPendientes;
                    monitorDiv.classList.remove('d-none');
                } else {
                    monitorDiv.classList.add('d-none');
                }
            }
        } catch (e) {
            // Silencioso en caso de fallo de red
        }
    }, 5000); 
}

// Arrancar en cuanto el DOM esté listo
document.addEventListener('DOMContentLoaded', iniciarMonitorCola);

// ==============================================================================
// --- 7. SISTEMA DE ACTUALIZACIONES AUTOMÁTICAS (INTEGRADO EN NAVBAR) ---
// ==============================================================================
function comprobarActualizaciones() {
    fetch('modulos/api_admin.php?action=check_update')
        .then(response => response.json())
        .then(data => {
            if (data && data.update_available) {
                const contenedor = document.getElementById('contenedorActualizacion');
                const texto = document.getElementById('textoNuevaVersion');
                const btn = document.getElementById('btnActualizarSistema');
                
                if (contenedor && texto && btn) {
                    contenedor.style.display = 'block';
                    // Al estar en el navbar, mostramos un texto compacto
                    texto.innerText = `🚀 v${data.remote_version} disponible`;
                    btn.dataset.zipUrl = data.zip_url || '';
                }
            }
        })
        .catch(err => console.log("Comprobación de versión omitida (offline o error de red)."));
}

function ejecutarActualizacion() {
    const btn = document.getElementById('btnActualizarSistema');
    const progreso = document.getElementById('progresoActualizacion');
    const zipUrl = btn ? (btn.dataset.zipUrl || '') : '';
    
    // Textos multi-idioma con fallback en castellano
    const txtTitulo = typeof GartyLang !== 'undefined' ? GartyLang.swal_upd_confirm_title : '¿Actualizar Sistema?';
    const txtTexto = typeof GartyLang !== 'undefined' ? GartyLang.swal_upd_confirm_text : 'Se descargará e instalará la última versión. Tu configuración y modelos guardados no se verán afectados.';
    const txtBtnSi = typeof GartyLang !== 'undefined' ? GartyLang.swal_upd_btn_yes : '⚡ Sí, actualizar';
    const txtBtnNo = typeof GartyLang !== 'undefined' ? GartyLang.swal_upd_btn_no : 'Cancelar';

    // Usamos tu SwalDark para una experiencia UX Premium
    SwalDark.fire({
        title: txtTitulo,
        text: txtTexto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: txtBtnSi,
        cancelButtonText: txtBtnNo
    }).then((result) => {
        if (result.isConfirmed) {
            if (btn) { btn.disabled = true; btn.style.opacity = '0.5'; }
            if (progreso) progreso.style.display = 'inline-block';
            
            const formData = new FormData();
            formData.append('action', 'instalar_actualizacion_zip');
            formData.append('zip_url', zipUrl);
            
            fetch('modulos/api_admin.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        SwalDark.fire({
                            icon: 'success',
                            title: typeof GartyLang !== 'undefined' ? GartyLang.swal_upd_success_title : '¡Actualizado!',
                            text: data.mensaje,
                            timer: 3000,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        SwalDark.fire({
                            icon: 'error',
                            title: 'Error de Actualización',
                            text: data.error || 'No se pudo completar la actualización.'
                        });
                        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
                        if (progreso) progreso.style.display = 'none';
                    }
                })
                .catch(err => {
                    SwalDark.fire({
                        icon: 'error',
                        title: typeof GartyLang !== 'undefined' ? GartyLang.swal_net_err_title : 'Error de Red',
                        text: 'Fallo crítico de comunicación con el servidor al intentar actualizar.'
                    });
                    if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
                    if (progreso) progreso.style.display = 'none';
                });
        }
    });
}

// Arrancamos el chivato de forma silenciosa al cargar cualquier página
document.addEventListener('DOMContentLoaded', () => {
    // Le damos un pequeño retraso (1 segundo) para dar prioridad a la carga gráfica visual de la página
    setTimeout(comprobarActualizaciones, 1000);
});