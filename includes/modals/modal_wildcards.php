<div class="modal fade" id="modalWildcards" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-warning" style="background-color: #161b22; color: #c9d1d9;">
            <div class="modal-header border-0 bg-dark">
                <h5 class="modal-title fw-bold text-warning"><i class="bi bi-suit-spade-fill me-2"></i> <?= __('wild_title') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="text" id="buscadorWildcards" class="form-control bg-dark text-light border-warning mb-3 shadow-sm" placeholder="<?= __('wild_search') ?>" onkeyup="filtrarWildcards()">
                <div id="listaWildcards" class="d-flex flex-wrap gap-2">
                    <div class="text-center w-100 text-warning"><span class="spinner-border spinner-border-sm"></span> <?= __('wild_loading') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>