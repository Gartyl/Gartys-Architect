// ==============================================================================
// --- VISOR.JS: VISOR PAN/ZOOM, COMPARADOR A/B (IMAGEN + VÍDEO) Y LIENZO ---
// ==============================================================================

// Variables globales de estado
let visorScale = 1, visorPannedX = 0, visorPannedY = 0;
let isPanning = false, startPanX = 0, startPanY = 0;
let compareImageA = null, compareBtnA = null;

// Variables para el Pan/Zoom del Comparador A/B
let compScale = 1, compPanX = 0, compPanY = 0;
let isCompPanning = false, startCompX = 0, startCompY = 0;

// Helper universal para detectar si una ruta o base64 es un vídeo
function esRutaVideo(src) {
    if (!src || typeof src !== 'string') return false;
    const clean = src.split('?')[0].toLowerCase();
    return src.startsWith('data:video') || clean.endsWith('.mp4') || clean.endsWith('.webm') || clean.endsWith('.mov');
}

// 1. LÓGICA DEL VISOR PRINCIPAL A PANTALLA COMPLETA
window.abrirVisor = function(src) {
    const imgVisor = document.getElementById('imagenVisor');
    const visorBg = document.getElementById('visorPantallaCompleta');
    const vid = document.getElementById('videoVisor');
    
    if (esRutaVideo(src)) {
        if (imgVisor) imgVisor.style.display = 'none';
        if (vid) {
            vid.style.display = 'block';
            vid.src = src;
            vid.play().catch(() => {});
        }
    } else {
        if (vid) {
            vid.style.display = 'none';
            vid.pause();
            vid.src = "";
        }
        if (imgVisor) {
            imgVisor.style.display = 'block';
            imgVisor.src = src;
        }
    }
    if (visorBg) visorBg.style.display = 'flex';
};

window.cerrarVisor = function() {
    const imgVisor = document.getElementById('imagenVisor');
    const visorBg = document.getElementById('visorPantallaCompleta');
    const vid = document.getElementById('videoVisor');

    if (visorBg) visorBg.style.display = 'none';
    if (vid) {
        vid.pause();
        vid.src = "";
    }
    
    visorScale = 1; visorPannedX = 0; visorPannedY = 0;
    if (imgVisor) imgVisor.style.transform = `translate(0px, 0px) scale(1)`;
};

// 2. INICIALIZACIÓN DE EVENTOS PAN & ZOOM DEL VISOR PRINCIPAL
document.addEventListener('DOMContentLoaded', () => {
    const imgVisor = document.getElementById('imagenVisor');
    const visorBg = document.getElementById('visorPantallaCompleta');

    if (visorBg && imgVisor) {
        visorBg.addEventListener('wheel', (e) => {
            if (visorBg.style.display !== 'flex') return;
            e.preventDefault();
            visorScale += e.deltaY * -0.002;
            visorScale = Math.min(Math.max(0.5, visorScale), 6);
            imgVisor.style.transform = `translate(${visorPannedX}px, ${visorPannedY}px) scale(${visorScale})`;
        }, {passive: false});

        imgVisor.addEventListener('mousedown', (e) => {
            isPanning = true; 
            imgVisor.style.cursor = 'grabbing';
            startPanX = e.clientX - visorPannedX;
            startPanY = e.clientY - visorPannedY;
        });
        
        window.addEventListener('mouseup', () => { 
            isPanning = false; 
            if (imgVisor) imgVisor.style.cursor = 'grab'; 
        });
        
        window.addEventListener('mousemove', (e) => {
            if (!isPanning || visorBg.style.display !== 'flex') return;
            visorPannedX = e.clientX - startPanX; 
            visorPannedY = e.clientY - startPanY;
            imgVisor.style.transform = `translate(${visorPannedX}px, ${visorPannedY}px) scale(${visorScale})`;
        });

        imgVisor.addEventListener('dblclick', () => {
            visorScale = (visorScale > 1.2) ? 1 : 2; 
            visorPannedX = 0; visorPannedY = 0;
            imgVisor.style.transform = `translate(0px, 0px) scale(${visorScale})`;
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && visorBg.style.display === 'flex') {
                window.cerrarVisor();
            }
        });
    }
});

// 3. MÓDULO: COMPARADOR A/B (INTERCAMBIO NATIVO IMG <-> VIDEO MANTENIENDO ID Y LAYOUT)
function prepararElementoComparador(id, src) {
    let el = document.getElementById(id);
    if (!el) return null;

    // Guardamos el estilo original exacto de modal_compare.php la primera vez
    if (el.dataset.baseStyle === undefined) {
        el.dataset.baseStyle = el.getAttribute('style') || '';
    }

    const isVid = esRutaVideo(src);
    const neededTag = isVid ? 'VIDEO' : 'IMG';

    if (el.tagName !== neededTag) {
        const nuevo = document.createElement(neededTag);
        nuevo.id = el.id;
        nuevo.className = el.className;
        nuevo.setAttribute('style', el.dataset.baseStyle);
        nuevo.dataset.baseStyle = el.dataset.baseStyle;
        nuevo.draggable = false;
        el.replaceWith(nuevo);
        el = nuevo;
    } else {
        el.setAttribute('style', el.dataset.baseStyle);
    }

    if (isVid) {
        el.muted = true;
        el.loop = true;
        el.autoplay = true;
        el.playsInline = true;
        el.src = src;
        el.load();
    } else {
        el.src = src;
    }

    return el;
}

window.prepararComparacion = function(imgSrc, btnElement) {
    if (!compareImageA) {
        compareImageA = imgSrc;
        compareBtnA = btnElement;
        
        btnElement.classList.replace('btn-secondary', 'btn-success');
        btnElement.innerHTML = '<i class="bi bi-check2"></i>';
        
        const swalTheme = typeof SwalDark !== 'undefined' ? SwalDark : Swal;
        const textoAviso = (typeof GartyLang !== 'undefined') 
            ? (GartyLang.swal_img_a_mem || GartyLang.swal_compare_a_mem || 'Imagen A memorizada') 
            : 'Imagen A memorizada';

        swalTheme.fire({
            toast: true, 
            position: 'top-end', 
            showConfirmButton: false, 
            timer: 3000, 
            icon: 'info', 
            title: textoAviso
        });
    } else {
        if (compareImageA === imgSrc) {
            window.cancelarComparacion();
            return;
        }
        
        const elA = prepararElementoComparador('compareImgA', compareImageA);
        const elB = prepararElementoComparador('compareImgB', imgSrc);
        
        // Reset de Pan & Zoom del comparador
        compScale = 1; compPanX = 0; compPanY = 0;
        if (elA) elA.style.transform = `translate(0px, 0px) scale(1)`;
        if (elB) elB.style.transform = `translate(0px, 0px) scale(1)`;
        
        const slider = document.getElementById('compareSlider');
        if (slider) slider.value = 50;
        window.updateSliderPos(50);
        
        const modalEl = document.getElementById('modalComparador');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();

            // Arrancamos y sincronizamos los vídeos una vez que el modal ya es visible en el DOM
            setTimeout(() => {
                if (elA && elA.tagName === 'VIDEO') { elA.currentTime = 0; elA.play().catch(() => {}); }
                if (elB && elB.tagName === 'VIDEO') { elB.currentTime = 0; elB.play().catch(() => {}); }
            }, 150);
        }
        
        window.cancelarComparacion();
    }
};

window.cancelarComparacion = function() {
    if (compareBtnA) {
        compareBtnA.classList.replace('btn-success', 'btn-secondary');
        compareBtnA.innerHTML = '<i class="bi bi-symmetry-vertical"></i>';
    }
    compareImageA = null;
    compareBtnA = null;
};

window.updateSliderPos = function(percent) {
    const imgA = document.getElementById('compareImgA');
    const line = document.getElementById('compareLine');
    if (imgA) imgA.style.clipPath = `polygon(0 0, ${percent}% 0, ${percent}% 100%, 0 100%)`;
    if (line) line.style.left = `${percent}%`;
};

// Eventos de limpieza y PAN/ZOOM para el modal comparador
document.addEventListener('DOMContentLoaded', () => {
    const modalComp = document.getElementById('modalComparador');
    if (modalComp) {
        modalComp.addEventListener('hidden.bs.modal', () => {
            window.cancelarComparacion();
            ['compareImgA', 'compareImgB'].forEach(id => {
                const el = document.getElementById(id);
                if (el && el.tagName === 'VIDEO') {
                    el.pause();
                    el.removeAttribute('src');
                }
            });
        });

        // Rueda del ratón para Zoom simultáneo
        modalComp.addEventListener('wheel', (e) => {
            const imgA = document.getElementById('compareImgA');
            const imgB = document.getElementById('compareImgB');
            if (!imgA || !imgB) return;
            e.preventDefault();
            compScale += e.deltaY * -0.002;
            compScale = Math.min(Math.max(0.5, compScale), 6);
            imgA.style.transform = `translate(${compPanX}px, ${compPanY}px) scale(${compScale})`;
            imgB.style.transform = `translate(${compPanX}px, ${compPanY}px) scale(${compScale})`;
        }, {passive: false});

        // Clic y Arrastrar para Desplazamiento (Pan)
        modalComp.addEventListener('mousedown', (e) => {
            if (e.target.id === 'compareSlider') return;
            e.preventDefault();
            isCompPanning = true;
            startCompX = e.clientX - compPanX;
            startCompY = e.clientY - compPanY;
            modalComp.style.cursor = 'grabbing';
        });

        window.addEventListener('mouseup', () => {
            isCompPanning = false;
            if (modalComp) modalComp.style.cursor = 'default';
        });

        window.addEventListener('mousemove', (e) => {
            if (!isCompPanning) return;
            const imgA = document.getElementById('compareImgA');
            const imgB = document.getElementById('compareImgB');
            if (!imgA || !imgB) return;
            compPanX = e.clientX - startCompX;
            compPanY = e.clientY - startCompY;
            imgA.style.transform = `translate(${compPanX}px, ${compPanY}px) scale(${compScale})`;
            imgB.style.transform = `translate(${compPanX}px, ${compPanY}px) scale(${compScale})`;
        });

        // Doble clic para resetear la vista rápidamente
        modalComp.addEventListener('dblclick', () => {
            compScale = 1; compPanX = 0; compPanY = 0;
            const imgA = document.getElementById('compareImgA');
            const imgB = document.getElementById('compareImgB');
            if (imgA) imgA.style.transform = `translate(0px, 0px) scale(1)`;
            if (imgB) imgB.style.transform = `translate(0px, 0px) scale(1)`;
        });
    }
});

// 4. LÓGICA DE ZOOM PARA EL LIENZO DE INPAINT (PINTURA)
window.escalaLienzoActual = 100;

window.zoomLienzo = function(incremento) {
    window.escalaLienzoActual += incremento;
    if (window.escalaLienzoActual < 50) window.escalaLienzoActual = 50;
    if (window.escalaLienzoActual > 500) window.escalaLienzoActual = 500;
    
    const lienzo = document.getElementById('lienzoZoom');
    if (lienzo) lienzo.style.width = window.escalaLienzoActual + '%';
    if (typeof window.updateCursor === 'function') window.updateCursor();
};

window.resetZoomLienzo = function() {
    window.escalaLienzoActual = 100;
    const lienzo = document.getElementById('lienzoZoom');
    if (lienzo) lienzo.style.width = '100%';
    if (typeof window.updateCursor === 'function') window.updateCursor();
};