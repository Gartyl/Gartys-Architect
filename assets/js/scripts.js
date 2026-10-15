// ==============================================================================
// --- SCRIPTS.JS: CORE DE INTERACTIVIDAD PARA HISTORIAL Y GALERÍA ---
// ==============================================================================

// 1. Configuración del Tema Oscuro de SweetAlert2
const SwalDark = Swal.mixin({
    background: '#161b22',
    color: '#c9d1d9',
    confirmButtonColor: '#238636',
    cancelButtonColor: '#d33',
    customClass: { popup: 'border border-secondary shadow-lg rounded-4' }
});

// Helper seguro para obtener textos traducidos con fallback en inglés/español
function _lang(key, fallback = '') {
    return (typeof GartyLang !== 'undefined' && GartyLang[key]) ? GartyLang[key] : fallback;
}

// 2. Función de Copiado al Portapapeles (Universal)
window.copyTextEx = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    
    const text = el.innerText;
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
    
    Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 }).fire({
        icon: 'success', 
        title: _lang('avis_portap', 'Copiado al portapapeles')
    });
};

// 3. Gestión de Visibilidad Pública (Galería)
window.togglePublic = function(promptId, btn) {
    if (!promptId) return;
    const isActive = btn.classList.contains('active');
    const newState = isActive ? 0 : 1;
    
    if (newState) btn.classList.add('active');
    else btn.classList.remove('active');
    
    const fd = new FormData();
    fd.append('action', 'toggle_public');
    fd.append('prompt_id', promptId);
    fd.append('estado', newState);
    
    fetch('procesar.php', { method: 'POST', body: fd })
    .catch(() => alert(_lang('err_toggle_public', 'Error al cambiar estado público.')));
};

// 4. Quitar de la Galería con Animación (Específico de vista Galería)
window.quitarGaleria = async function(promptId, btn) {
    const result = await SwalDark.fire({
        title: _lang('swal_remove_gal_tit', '¿Retirar de la galería?'),
        text: _lang('swal_remove_gal_txt', 'La imagen desaparecerá del escaparate público.'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#495057',
        confirmButtonText: '<i class="bi bi-eye-slash-fill"></i> ' + _lang('btn_yes_hide', 'Sí, ocultar'),
        cancelButtonText: _lang('btn_cancelar', 'Cancelar')
    });

    if (!result.isConfirmed) return;
    
    const fd = new FormData();
    fd.append('action', 'toggle_public');
    fd.append('prompt_id', promptId);
    fd.append('estado', 0);
    
    try {
        const res = await fetch('procesar.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            const item = btn.closest('.masonry-item');
            if (item) {
                item.style.transition = 'all 0.3s ease';
                item.style.transform = 'scale(0.9)';
                item.style.opacity = '0';
            }
            setTimeout(async () => {
                if (item) item.remove();
                await window.sincronizarPaginaHistorial();
            }, 300);

            SwalDark.fire({
                toast: true, position: 'top-end', icon: 'success', 
                title: _lang('swal_img_removed', 'Imagen retirada'), showConfirmButton: false, timer: 2000
            });
        }
    } catch(e) {
        SwalDark.fire({ icon: 'error', title: 'Error', text: _lang('err_hide_img', 'Error al ocultar la imagen.') });
    }
};

// 5. Gestión de Favoritos
window.toggleFavorito = function(promptId, btn) {
    if (!promptId) return;
    const isActive = btn.classList.contains('active');
    const newState = isActive ? 0 : 1;
    
    if (newState) {
        btn.classList.add('active');
        btn.innerHTML = '<i class="bi bi-heart-fill"></i>';
    } else {
        btn.classList.remove('active');
        btn.innerHTML = '<i class="bi bi-heart"></i>';
    }
    
    const fd = new FormData();
    fd.append('action', 'toggle_favorito');
    fd.append('prompt_id', promptId);
    fd.append('estado', newState);
    
    fetch('procesar.php', { method: 'POST', body: fd }).catch(() => {});
};

// 6. Eliminación de Registros Individuales
window.borrarRegistro = async function(id) {
    const result = await SwalDark.fire({
        title: _lang('avis_borrareg1', '¿Eliminar registro?'),
        text: _lang('avis_borrareg2', 'Esta acción es irreversible.'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#495057',
        confirmButtonText: '<i class="bi bi-trash"></i> ' + _lang('btn_siborrar', 'Sí, borrar'),
        cancelButtonText: _lang('btn_cancelar', 'Cancelar')
    });

    if (result.isConfirmed) {
        const row = document.getElementById(`prompt-row-${id}`);
        const fd = new FormData();
        fd.append('action', 'eliminar_prompt');
        fd.append('prompt_id', id);
        
        try {
            const res = await fetch('procesar.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                if (row) {
                    row.style.transition = 'all 0.25s ease';
                    row.style.opacity = '0';
                    row.style.transform = 'scale(0.95)';
                }
                setTimeout(async () => {
                    if (row) row.remove();
                    await window.sincronizarPaginaHistorial();
                }, 250);
            }
        } catch (e) { console.error("Error al borrar", e); }
    }
};

// 7. Eliminación de Hilos / Lotes Completos
window.borrarHilo = async function(ids, cardId) {
    const result = await SwalDark.fire({
        title: _lang('avis_borralot1', '¿Borrar lote?'),
        text: _lang('avis_borralot2', 'Vas a eliminar') + ' ' + ids.length + ' ' + _lang('avis_borralot3', 'registros.'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#495057',
        confirmButtonText: '<i class="bi bi-trash3-fill"></i> ' + _lang('btn_borralote', 'Borrar lote'),
        cancelButtonText: _lang('btn_cancelar', 'Cancelar')
    });

    if (result.isConfirmed) {
        const card = document.getElementById(cardId);
        
        SwalDark.fire({
            title: _lang('avis_borrando', 'Borrando...'),
            text: _lang('avis_borrantxt', 'Limpiando la base de datos.'),
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        for (const id of ids) {
            const fd = new FormData();
            fd.append('action', 'eliminar_prompt');
            fd.append('prompt_id', id);
            await fetch('procesar.php', { method: 'POST', body: fd });
        }
        
        Swal.close();
        if (card) {
            card.style.transition = 'all 0.25s ease';
            card.style.opacity = '0';
            card.style.transform = 'translateX(20px)';
        }
        setTimeout(async () => {
            if (card) card.remove();
            await window.sincronizarPaginaHistorial();
        }, 250);
    }
};

// 7.5. Sincronización silenciosa de paginación (Universal: Historial y Galería)
window.sincronizarPaginaHistorial = async function() {
    const dynamicContainer = document.getElementById('historialDynamicContent') 
                          || document.querySelector('.col-lg-12') 
                          || document.querySelector('.container.py-4');
    if (!dynamicContainer) return;

    try {
        const scrollActual = window.scrollY;
        const selectorTarjetas = '.card-historial[id], .masonry-item';
        const idsPrevios = new Set(
            Array.from(dynamicContainer.querySelectorAll('.card-historial[id], .masonry-item[id]')).map(el => el.id)
        );

        // Añadimos marca de tiempo en cabecera para evitar que Firefox cachee el GET
        const res = await fetch(window.location.href, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'Cache-Control': 'no-cache, no-store, must-revalidate', 'Pragma': 'no-cache' }
        });
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // 1. Actualizamos el contador superior (si está fuera del contenedor dinámico)
        const nuevoContador = doc.getElementById('historialTotalCount');
        const actualContador = document.getElementById('historialTotalCount');
        if (nuevoContador && actualContador) {
            actualContador.innerHTML = nuevoContador.innerHTML;
        }

        // 2. Localizamos el contenedor equivalente en el HTML recién descargado
        const nuevoContenedor = doc.getElementById('historialDynamicContent') 
                             || doc.querySelector('.col-lg-12') 
                             || doc.querySelector('.container.py-4');
        if (!nuevoContenedor) return;

        // Filtramos el formulario de búsqueda (.filter-bar) para contar solo tarjetas reales
        const nuevasTarjetas = Array.from(nuevoContenedor.querySelectorAll(selectorTarjetas))
                                    .filter(el => !el.classList.contains('filter-bar'));

        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = parseInt(urlParams.get('page') || '1', 10);

        // Si hemos vaciado por completo una página superior a la 1, retrocedemos a la anterior
        if (nuevasTarjetas.length === 0 && currentPage > 1) {
            urlParams.set('page', currentPage - 1);
            window.location.search = urlParams.toString();
            return;
        }

        // 3. Reemplazamos el contenido y fijamos el scroll exacto en Firefox
        dynamicContainer.innerHTML = nuevoContenedor.innerHTML;
        window.scrollTo(0, scrollActual);

        // 4. Animamos suavemente las tarjetas nuevas que han subido desde la página siguiente
        dynamicContainer.querySelectorAll('.card-historial[id], .masonry-item[id]').forEach(card => {
            if (idsPrevios.size > 0 && !idsPrevios.has(card.id)) {
                card.style.opacity = '0';
                card.style.transform = 'translateY(12px)';
                requestAnimationFrame(() => {
                    card.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                });
            }
        });
    } catch (e) {
        console.warn(_lang('log_warn_sync_pag'), e);
    }
};

// 8. Radar Asíncrono de Cola Global (Soporte Multi-Ticket)
document.addEventListener('DOMContentLoaded', () => {
    const leerCola = () => {
        try {
            const raw = localStorage.getItem('garty_tarea_pendiente');
            if (!raw) return [];
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : (parsed && parsed.prompt_id ? [parsed] : []);
        } catch (e) { return []; }
    };

    const quitarDeCola = (promptId) => {
        const restantes = leerCola().filter(t => t.prompt_id !== promptId);
        if (restantes.length > 0) {
            localStorage.setItem('garty_tarea_pendiente', JSON.stringify(restantes));
        } else {
            localStorage.removeItem('garty_tarea_pendiente');
        }
        return restantes.length;
    };

    if (leerCola().length > 0) {
        const enConsulta = new Set();

        const radarGlobal = setInterval(async () => {
            const colaActual = leerCola();
            if (colaActual.length === 0) {
                clearInterval(radarGlobal);
                return;
            }

            for (const tarea of colaActual) {
                if (!tarea.prompt_id || enConsulta.has(tarea.prompt_id)) continue;
                enConsulta.add(tarea.prompt_id);

                const fd = new FormData();
                fd.append('action', 'check_ticket');
                fd.append('prompt_id', tarea.prompt_id);
                fd.append('historial_id', tarea.db_id || 0);

                try {
                    const res = await fetch('procesar.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.error) {
                        quitarDeCola(tarea.prompt_id);
                    } else if (data.status === 'completed') {
                        const quedan = quitarDeCola(tarea.prompt_id);

                        // 1. Sonido de campana
                        try {
                            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                            const osc = audioCtx.createOscillator();
                            const gain = audioCtx.createGain();
                            osc.connect(gain); gain.connect(audioCtx.destination);
                            osc.type = 'sine'; osc.frequency.setValueAtTime(880, audioCtx.currentTime);
                            gain.gain.setValueAtTime(0.2, audioCtx.currentTime);
                            gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.6);
                            osc.start(audioCtx.currentTime); osc.stop(audioCtx.currentTime + 0.6);
                        } catch (e) {}

                        // 2. Aviso visual SweetAlert
                        if (typeof Swal !== 'undefined') {
                            SwalDark.fire({
                                toast: true, position: 'top-end', icon: 'success',
                                title: _lang('swal_gpu_free', '¡GPU Libre!'),
                                text: _lang('swal_gpu_saved', 'Generación guardada.'),
                                showConfirmButton: false, timer: 4500
                            });
                        }

                        // 3. Notificación nativa de sistema operativo
                        if ("Notification" in window && Notification.permission === "granted") {
                            const iconUrl = (data.filenames && data.filenames[0])
                                ? new URL('galeria/' + data.filenames[0], window.location.href).href : '';

                            const osNoti = new Notification(_lang('tit_gpu_ready', '¡GPU LISTA!'), {
                                body: _lang('swal_gpu_async_done', 'El trabajo asíncrono ha terminado.'),
                                icon: iconUrl, silent: true
                            });
                            osNoti.onclick = () => { window.focus(); osNoti.close(); };
                        }

                        if (quedan === 0) {
                            document.title = _lang('tit_gpu_ready', '¡GPU LISTA!');
                        }

                        // 4. Si estamos viendo historial.php, inyectamos la nueva tarjeta suavemente sin pulsar F5
                        if (typeof window.sincronizarPaginaHistorial === 'function') {
                            await window.sincronizarPaginaHistorial();
                        }
                    }
                } catch (e) {
                } finally {
                    enConsulta.delete(tarea.prompt_id);
                }
            }
        }, 3000);
    }
});
