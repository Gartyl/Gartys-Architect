// ==============================================================================
// --- VISOR.JS: VISOR PAN/ZOOM, COMPARADOR A/B Y ZOOM DE LIENZO ---
// ==============================================================================

// Variables globales de estado
let visorScale = 1, visorPannedX = 0, visorPannedY = 0;
let isPanning = false, startPanX = 0, startPanY = 0;
let compareImageA = null, compareBtnA = null;

// 1. LÓGICA DEL VISOR PRINCIPAL
window.abrirVisor = function(src) {
    const imgVisor = document.getElementById('imagenVisor');
    const visorBg = document.getElementById('visorPantallaCompleta');
    const vid = document.getElementById('videoVisor');
    
    const isVideo = src.includes('video/mp4') || src.toLowerCase().endsWith('.mp4');
    
    if (isVideo) {
        if(imgVisor) imgVisor.style.display = 'none';
        if(vid) {
            vid.style.display = 'block';
            vid.src = src;
        }
    } else {
        if(vid) {
            vid.style.display = 'none';
            vid.pause();
        }
        if(imgVisor) {
            imgVisor.style.display = 'block';
            imgVisor.src = src;
        }
    }
    if(visorBg) visorBg.style.display = 'flex';
};

window.cerrarVisor = function() {
    const imgVisor = document.getElementById('imagenVisor');
    const visorBg = document.getElementById('visorPantallaCompleta');
    const vid = document.getElementById('videoVisor');

    if(visorBg) visorBg.style.display = 'none';
    if(vid) {
        vid.pause();
        vid.src = "";
    }
    
    visorScale = 1; visorPannedX = 0; visorPannedY = 0;
    if(imgVisor) imgVisor.style.transform = `translate(0px, 0px) scale(1)`;
};

// 2. INICIALIZACIÓN DE EVENTOS PAN & ZOOM (Esperamos a que el DOM cargue)
document.addEventListener('DOMContentLoaded', () => {
    const imgVisor = document.getElementById('imagenVisor');
    const visorBg = document.getElementById('visorPantallaCompleta');

    if (visorBg && imgVisor) {
        // Rueda del ratón para Zoom
        visorBg.addEventListener('wheel', (e) => {
            if (visorBg.style.display !== 'flex') return;
            e.preventDefault();
            visorScale += e.deltaY * -0.002;
            visorScale = Math.min(Math.max(0.5, visorScale), 6);
            imgVisor.style.transform = `translate(${visorPannedX}px, ${visorPannedY}px) scale(${visorScale})`;
        }, {passive: false});

        // Clic y Arrastrar para Desplazamiento (Pan)
        imgVisor.addEventListener('mousedown', (e) => {
            isPanning = true; 
            imgVisor.style.cursor = 'grabbing';
            startPanX = e.clientX - visorPannedX;
            startPanY = e.clientY - visorPannedY;
        });
        
        window.addEventListener('mouseup', () => { 
            isPanning = false; 
            if(imgVisor) imgVisor.style.cursor = 'grab'; 
        });
        
        window.addEventListener('mousemove', (e) => {
            if (!isPanning || visorBg.style.display !== 'flex') return;
            visorPannedX = e.clientX - startPanX; 
            visorPannedY = e.clientY - startPanY;
            imgVisor.style.transform = `translate(${visorPannedX}px, ${visorPannedY}px) scale(${visorScale})`;
        });

        // Doble clic para resetear o hacer Zoom 200% rápido
        imgVisor.addEventListener('dblclick', () => {
            visorScale = (visorScale > 1.2) ? 1 : 2; 
            visorPannedX = 0; visorPannedY = 0;
            imgVisor.style.transform = `translate(0px, 0px) scale(${visorScale})`;
        });
        
        // Tecla Escape para cerrar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && visorBg.style.display === 'flex') {
                window.cerrarVisor();
            }
        });
    }
});

// 3. MÓDULO: COMPARADOR A/B
window.prepararComparacion = function(imgSrc, btnElement) {
    if (!compareImageA) {
        compareImageA = imgSrc;
        compareBtnA = btnElement;
        
        btnElement.classList.replace('btn-secondary', 'btn-success');
        btnElement.innerHTML = '<i class="bi bi-check2"></i>';
        
        // Soporte seguro para SwalDark
        const swalTheme = typeof SwalDark !== 'undefined' ? SwalDark : Swal;
        
        // Buscamos la variable correcta en GartyLang, con un fallback de seguridad
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
        
        const imgA = document.getElementById('compareImgA');
        const imgB = document.getElementById('compareImgB');
        
        if(imgA) imgA.src = compareImageA;
        if(imgB) imgB.src = imgSrc;
        
        const slider = document.getElementById('compareSlider');
        if(slider) slider.value = 50;
        window.updateSliderPos(50);
        
        const modalEl = document.getElementById('modalComparador');
        if(modalEl && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
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

// Limpieza automática del comparador al cerrar el modal
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('modalComparador')?.addEventListener('hidden.bs.modal', window.cancelarComparacion);
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