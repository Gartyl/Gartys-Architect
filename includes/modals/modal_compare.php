<div class="modal fade" id="modalComparador" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content" style="background-color: #161b22; color: #c9d1d9; border: 1px solid #58a6ff;">
            <div class="modal-header border-0 bg-dark">
                <h5 class="modal-title fw-bold text-info"><i class="bi bi-symmetry-vertical me-2"></i> <?= __('tit_comparador') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="cancelarComparacion()"></button>
            </div>
            <div class="modal-body p-0 position-relative" style="overflow: hidden; min-height: 50vh; display: flex; justify-content: center; align-items: center; background: #010409;">
                
                <div id="compareContainer" style="position: relative; max-width: 100%; display: inline-block;">
                    <img id="compareImgB" src="" style="display: block; max-width: 100%; height: auto; max-height: 80vh;">
                    
                    <img id="compareImgA" src="" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; object-position: left; clip-path: polygon(0 0, 50% 0, 50% 100%, 0 100%);">
                    
                    <input type="range" id="compareSlider" min="0" max="100" value="50" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: ew-resize; z-index: 10;" oninput="updateSliderPos(this.value)">
                    
                    <div id="compareLine" style="position: absolute; top: 0; bottom: 0; left: 50%; width: 4px; background: white; transform: translateX(-50%); pointer-events: none; z-index: 5; box-shadow: 0 0 15px rgba(0,0,0,0.8);">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 40px; height: 40px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: black; font-size: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.5);">
                            <i class="bi bi-arrows-expand"></i>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-secondary d-flex justify-content-between" style="border-color: rgba(255,255,255,0.1) !important;">
                <span class="small fw-bold text-white-50"><span class="badge bg-primary me-1">A</span> <?= __('comp_first_sel') ?></span>
                <span class="small fw-bold text-white-50"><?= __('comp_second_sel') ?> <span class="badge bg-success ms-1">B</span></span>
            </div>
        </div>
    </div>
</div>