<div class="modal fade" id="modalEmbeddings" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-danger" style="background-color: #161b22; color: #c9d1d9;">
            <div class="modal-header border-0 bg-dark">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-gem me-2"></i> <?= __('emb_title') ?? 'Librería de Embeddings' ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <input type="text" id="buscadorEmbeddings" class="form-control bg-dark text-light border-danger mb-3 shadow-sm" placeholder="<?= __('emb_search') ?? 'Buscar por nombre o carpeta...' ?>" onkeyup="filtrarEmbeddings()">
                
                <!-- Contenedor donde se dibujarán las carpetas y los botones -->
                <div id="listaEmbeddings" class="d-flex flex-column gap-3">
                    <div class="text-center w-100 text-danger"><span class="spinner-border spinner-border-sm"></span> <?= __('emb_loading') ?? 'Cargando embeddings...' ?></div>
                </div>
            </div>
        </div>
    </div>
</div>