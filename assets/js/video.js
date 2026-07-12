// ==============================================================================
// --- VIDEO.JS: MESA DE MEZCLAS, FUSIÓN Y AUTOREGRESIVO LTX/WAN ---
// ==============================================================================

let listaVideosFusion = [];
let currentVideoUploadBase64 = null;

// --- MOTOR AUTOREGRESIVO JS (EL DIRECTOR DE ORQUESTA DE FRAMES) ---
async function lanzarVideoEncadenado(fdOriginal, totalFramesPeticion) {
    const framesPorTramo = 65; 
    const totalTramos = Math.ceil(totalFramesPeticion / framesPorTramo);
    let tramosNombres = [];
    
    let audioDataOriginal = fdOriginal.get('audio_data');
    fdOriginal.delete('audio_data'); 
    
    // FIX HISTORIAL: Borramos el ID anterior para forzar una fila nueva en la BD (Evita mezclar Wan y LTX)
    fdOriginal.delete('historial_id');
    if (typeof currentPromptId !== 'undefined') currentPromptId = 0; 
    
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const visorInfo = document.getElementById('imageResult'); 

    // --- NUEVO: Función radar interna para no bloquear el servidor por Timeout ---
    const pollTicket = async (promptId, dbId) => {
        return new Promise((resolve, reject) => {
            let attempts = 0;
            const interval = setInterval(async () => {
                attempts++;
                // Límite de 15 minutos (300 * 3s)
                if (attempts > 300) { clearInterval(interval); reject(new Error(GartyLang.err_timeout_invalid || "Timeout esperando a ComfyUI")); }
                
                let fdCheck = new FormData(); 
                fdCheck.append('action', 'check_ticket'); 
                fdCheck.append('prompt_id', promptId); 
                fdCheck.append('historial_id', dbId);
                try {
                    let resC = await fetch('procesar.php', { method: 'POST', body: fdCheck });
                    let dataC = await resC.json();
                    if (dataC.error) { 
                        clearInterval(interval); reject(new Error(dataC.error)); 
                    } else if (dataC.status === 'completed') { 
                        clearInterval(interval); resolve(dataC); 
                    }
                } catch(e) {
                    // Si hay un micro-corte de red temporal, lo ignoramos y el radar seguirá preguntando en el próximo ciclo
                    console.warn("Fallo leve de red al consultar ticket, reintentando...", e);
                }
            }, 3000);
        });
    };

    try {
        if (progressContainer) progressContainer.style.display = 'block';
        if (visorInfo) visorInfo.innerHTML = '';
        if (progressBar) {
            progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-success';
            progressBar.style.cssText = ''; 
        }

        if (isNaN(totalTramos) || totalTramos <= 0) {
            throw new Error(GartyLang.err_invalid_frames);
        }

        for (let i = 0; i < totalTramos; i++) {
            let fdTramo = new FormData();
            for (let pair of fdOriginal.entries()) {
                fdTramo.append(pair[0], pair[1]);
            }
            
            fdTramo.set('video_frames', framesPorTramo);
            fdTramo.set('is_tramo', 'true');
            // CLAVE DEL ARREGLO: Modo asíncrono para evitar el Timeout y el error JSON.parse
            fdTramo.set('async_mode', 'true'); 
            
            if (i === 0) {
                if (typeof currentImageBase64 !== 'undefined' && currentImageBase64) {
                    let cleanImg = currentImageBase64.includes(',') ? currentImageBase64.split(',')[1] : currentImageBase64;
                    fdTramo.set('init_image', cleanImg);
                }
            } else {
                fdTramo.delete('init_image');
                fdTramo.set('previous_video', tramosNombres[i - 1]);
            }
            
            if (progressText) progressText.innerHTML = `<i class="bi bi-gear-wide-connected spin-icon"></i> ${GartyLang.msg_proc_tramo1} ${i + 1} ${GartyLang.msg_proc_tramo2} ${totalTramos}...`;
            let porcentaje = Math.round(((i) / totalTramos) * 100);
            if (progressBar) {
                progressBar.style.width = porcentaje + '%';
                progressBar.innerText = porcentaje + '%';
            }

            let res = await fetch('procesar.php', { method: 'POST', body: fdTramo });
            let data = await res.json();

            // En lugar de esperar a que termine, recogemos el ticket e iniciamos el radar para este tramo
            if (data.status === 'ticket_issued' && data.prompt_id) {
                let finalData = await pollTicket(data.prompt_id, data.historial_id || 0);
                if (finalData.filenames && finalData.filenames.length > 0) {
                    tramosNombres.push(finalData.filenames[0]);
                } else {
                    throw new Error(GartyLang.err_server_tramo + (i + 1));
                }
            } else if (data.status === 'completed' && data.images && data.filenames) {
                // Por si el servidor responde 'completed' directamente (muy rápido)
                tramosNombres.push(data.filenames[0]);
            } else {
                throw new Error(data.error || (GartyLang.err_server_tramo + (i + 1)));
            }
        }

        if (progressBar) {
            progressBar.className = 'progress-bar progress-bar-striped progress-bar-animated bg-info';
            progressBar.style.width = '100%';
            progressBar.innerText = GartyLang.msg_assemble_bar;
        }
        if (progressText) progressText.innerHTML = `<i class="bi bi-gear-fill spin-icon"></i> ${GartyLang.msg_assemble_text}`;
        
        let fdConcat = new FormData();
        fdConcat.append('action', 'concatenar_videos');
        fdConcat.append('videos_array', JSON.stringify(tramosNombres));
        
        // --- CAPTURA DE FPS DEL DESPLEGABLE ---
        const fpsElegidos = document.getElementById('videoFpsSelector') ? document.getElementById('videoFpsSelector').value : 16;
        fdConcat.append('video_fps', fpsElegidos);
        
        if (audioDataOriginal) {
            let cleanAudio = (typeof audioDataOriginal === 'string' && audioDataOriginal.includes(',')) 
                             ? audioDataOriginal.split(',')[1] : audioDataOriginal;
            fdConcat.append('audio_data', cleanAudio);
        }
        
        let resConcat = await fetch('procesar.php', { method: 'POST', body: fdConcat });
        let dataConcat = await resConcat.json();
        
        if (dataConcat.status === 'completed') {
            if (visorInfo && typeof construirTarjetaImagen === 'function') {
                visorInfo.innerHTML = construirTarjetaImagen(dataConcat.images[0]);
            }
            if (progressContainer) progressContainer.style.display = 'none';
            if (typeof stopProgressBar === 'function') stopProgressBar();
        } else {
            throw new Error(dataConcat.error || GartyLang.err_concat_ffmpeg);
        }
        
    } catch (err) {
        console.error(GartyLang.log_err_concat_engine, err);
        if (progressText) progressText.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ${GartyLang.swal_err_title}: ${err.message}</span>`;
        if (progressBar) {
            progressBar.className = 'progress-bar progress-bar-striped bg-danger';
            progressBar.style.cssText = ''; 
        }
        if (typeof stopProgressBar === 'function') stopProgressBar();
        // Lanzamos el error para que el .finally() del motor.js lo atrape y desbloquee el botón
        throw err; 
    }
}

function clearVideoUpload() {
    currentVideoUploadBase64 = null;
    document.getElementById('imageInput').value = "";
    document.getElementById('imgPreviewContainer').style.display = 'none';
    document.getElementById('imgPreviewContainer').innerHTML = "";
}

// 1. Marca/Desmarca vídeos de la galería
function toggleVideoFusion(nombreArchivo, estaMarcado) {
    let nombreLimpio = nombreArchivo.includes('/') ? nombreArchivo.split('/').pop() : nombreArchivo;
    
    if (estaMarcado) {
        listaVideosFusion.push(nombreLimpio);
    } else {
        listaVideosFusion = listaVideosFusion.filter(v => v !== nombreLimpio);
    }
    actualizarBarraFusion();
}

// 2. Sube un vídeo de tu disco duro directamente a la lista de fusión
async function subirExternoParaFusion(input) {
    if (!input.files || input.files.length === 0) return;
    
    let fd = new FormData();
    fd.append('action', 'subir_video_externo');
    fd.append('video_file', input.files[0]);
    
    document.getElementById('textoContadorFusion').innerHTML = `<i class="bi bi-arrow-repeat spin-icon"></i> ${GartyLang.msg_uploading}`;
    
    try {
        let res = await fetch('procesar.php', { method: 'POST', body: fd });
        let data = await res.json();
        if (data.status === 'completed') {
            listaVideosFusion.push(data.filename);
            actualizarBarraFusion();
            SwalDark.fire({toast: true, position: 'top-end', icon: 'success', title: GartyLang.swal_vid_added, showConfirmButton: false, timer: 2500});
        } else {
            SwalDark.fire({icon: 'error', title: GartyLang.swal_err_upload_title, text: data.error});
        }
    } catch (e) {
        SwalDark.fire({icon: 'error', title: GartyLang.swal_err_upload_vid});
    }
    input.value = ""; 
}

// 3. Muestra u oculta la barra según si hay vídeos
function actualizarBarraFusion() {
    const barra = document.getElementById('barraFusionFlotante');
    const contador = document.getElementById('textoContadorFusion');
    
    if (listaVideosFusion.length > 0) {
        barra.style.display = 'flex';
        contador.innerText = listaVideosFusion.length + (listaVideosFusion.length === 1 ? ' ' + GartyLang.msg_vid_queue_sing : ' ' + GartyLang.msg_vid_queue_plur);
    } else {
        barra.style.display = 'none';
    }
}

// 4. El botón mágico que manda la orden a FFMPEG
async function ejecutarMesaDeMezclas() {
    if (listaVideosFusion.length < 2) {
        SwalDark.fire({icon: 'warning', title: GartyLang.swal_warn_vid_title, text: GartyLang.swal_warn_vid_req});
        return;
    }

    const btn = document.getElementById('btnEjecutarFusion');
    btn.innerHTML = `<i class="bi bi-gear-fill spin-icon"></i> ${GartyLang.btn_merging}`;
    btn.disabled = true;

    let fdConcat = new FormData();
    fdConcat.append('action', 'concatenar_videos');
    fdConcat.append('videos_array', JSON.stringify(listaVideosFusion));
    fdConcat.append('borrar_origenes', 'false'); // ¡EL SEGURO ANTI-BORRADO EN ACCIÓN!

    try {
        let res = await fetch('procesar.php', { method: 'POST', body: fdConcat });
        let data = await res.json();
        
        if (data.status === 'completed') {
            listaVideosFusion = [];
            actualizarBarraFusion();
            
            document.querySelectorAll('.merge-checkbox').forEach(cb => cb.checked = false);
            
            const visorInfo = document.getElementById('imageResult');
            if (visorInfo && typeof construirTarjetaImagen === 'function') {
                visorInfo.innerHTML = construirTarjetaImagen(data.images[0]);
            }
            SwalDark.fire({icon: 'success', title: GartyLang.swal_merge_done_title, text: GartyLang.swal_merge_done_text});
        } else {
            SwalDark.fire({icon: 'error', title: GartyLang.swal_merge_fail_title, text: data.error});
        }
    } catch (err) {
        SwalDark.fire({icon: 'error', title: GartyLang.swal_comm_err_title, text: err.message});
    }

    btn.innerHTML = `<i class="bi bi-film"></i> ${GartyLang.btn_join_vid}`;
    btn.disabled = false;
}

window.recalcularImagenVideo = function() {
    if (window.rawUploadedDataUrl && document.getElementById('selector').value === '[VIDEO]') {
        if (typeof setBaseImageFromDataUrl === 'function') setBaseImageFromDataUrl(window.rawUploadedDataUrl);
    }
};

window.recalcularTiempoVideo = function() {
    const fps = parseInt(document.getElementById('videoFpsSelector').value) || 16;
    const framesTotales = parseInt(document.getElementById('videoFramesInput').value) || 33;
    const segundos = (framesTotales / fps).toFixed(1);
    const label = document.getElementById('videoTimeLabel');
    if (label) label.innerText = '(~' + segundos + 's)';
};