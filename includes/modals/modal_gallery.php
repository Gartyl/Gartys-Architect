<div class="modal fade" id="modalGaleriaReciente" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-info" style="background-color: #161b22; color: #c9d1d9;">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-info"><i class="bi bi-images me-2"></i> <?= __('mod_gal_use_title') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4" style="max-height: 60vh; overflow-y: auto;">
                <div class="row g-2" id="gridGaleriaModal"></div>
            </div>
            <!-- NUEVO: FOOTER CON PAGINACIÓN -->
            <div class="modal-footer border-info d-flex justify-content-between" style="border-color: rgba(13, 202, 240, 0.2) !important;">
                <button class="btn btn-sm btn-outline-info fw-bold" id="btnPrevGaleria" onclick="abrirModalGaleria(currentGalleryPage - 1)"><i class="bi bi-chevron-left"></i> <?= __('gal_btn_prev') ?></button>
                <span class="text-light small fw-bold" id="galeriaPageInfo"><?= __('gal_page_init') ?></span>
                <button class="btn btn-sm btn-outline-info fw-bold" id="btnNextGaleria" onclick="abrirModalGaleria(currentGalleryPage + 1)"><?= __('gal_btn_next') ?> <i class="bi bi-chevron-right"></i></button>
            </div>
        </div>
    </div>
</div>