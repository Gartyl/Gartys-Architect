<div id="visorPantallaCompleta" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0,0,0,0.92); z-index: 999999; justify-content: center; align-items: center; overflow: hidden; backdrop-filter: blur(5px);">
    <button onclick="cerrarVisor()" class="btn btn-dark border border-secondary shadow-lg position-absolute top-0 end-0 m-4" style="z-index: 1000000; border-radius: 50%; width: 50px; height: 50px; opacity: 0.8;"><i class="bi bi-x-lg"></i></button>
    <div class="position-absolute bottom-0 start-50 translate-middle-x mb-4 text-white p-2 rounded bg-dark border border-secondary" style="opacity: 0.7; z-index: 1000000; pointer-events: none;">
        <i class="bi bi-mouse3 me-1"></i> <?= __('vis_zoom_hint') ?>
    </div>
    <img id="imagenVisor" src="" style="max-width: 95%; max-height: 95vh; object-fit: contain; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.9); transition: transform 0.1s ease-out; cursor: grab; transform-origin: center center;" draggable="false">
    <video id="videoVisor" src="" style="max-width: 95%; max-height: 95vh; object-fit: contain; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.9); display: none;" controls loop autoplay></video>
</div>