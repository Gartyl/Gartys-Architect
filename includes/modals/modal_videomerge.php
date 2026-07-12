<div id="barraFusionFlotante" class="bg-dark text-white p-3 rounded shadow border border-info" style="display: none; position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 9999; align-items: center; gap: 15px;">
    <span id="textoContadorFusion" class="fw-bold"><?= __('msg_0_vid_sel') ?? '0 vídeos seleccionados' ?></span>
    
    <button class="btn btn-sm btn-outline-light" onclick="document.getElementById('inputFusionExterno').click()" title="<?= __('btn_add_ext_vid_title') ?? 'Añadir vídeo de tu PC al empalme' ?>">
        <i class="bi bi-upload"></i> <?= __('btn_add_ext') ?? 'Añadir Externo' ?>
    </button>
    <input type="file" id="inputFusionExterno" accept="video/mp4,video/webm" style="display: none;" onchange="subirExternoParaFusion(this)">
    
    <button id="btnEjecutarFusion" class="btn btn-sm btn-info fw-bold" onclick="ejecutarMesaDeMezclas()">
        <i class="bi bi-film"></i> <?= __('btn_join_vid') ?? '¡Unir Vídeos!' ?>
    </button>
</div>