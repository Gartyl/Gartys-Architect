<?php
/**
 * galeria.php - Showcase Público (v5) LOCAL/SERVIDOR.
 */
require_once __DIR__ . '/includes/core/init.php';

// Recuperamos la ID del usuario de la sesión
$user_id = $_SESSION['user_id'];

// Obtener todas las imágenes públicas
try {
    $stmt = $pdo->query("
        SELECT h.id, h.prompt_positivo, h.prompt_negativo, h.imagen_path, h.modelo, h.user_id, u.nick 
        FROM historial_prompts h
        JOIN usuarios u ON h.user_id = u.id
        WHERE h.is_public = 1 AND h.imagen_path IS NOT NULL
        ORDER BY h.fecha_hora DESC
    ");
    $imagenes_publicas = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error al cargar la galería.");
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('tit_galeria') ?> - Garty's Architect</title>
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=<?php echo time(); ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const GartyLang = {
            avis_portap: <?= json_encode(__('avis_portap')) ?>,
            swal_gpu_free: <?= json_encode(__('swal_gpu_free')) ?>,
            swal_gpu_saved: <?= json_encode(__('swal_gpu_saved')) ?>,
            swal_gpu_async_done: <?= json_encode(__('swal_gpu_async_done')) ?>,
            tit_gpu_ready: <?= json_encode(__('tit_gpu_ready')) ?>,
            swal_remove_gal_tit: <?= json_encode(__('swal_remove_gal_tit')) ?>,
            swal_remove_gal_txt: <?= json_encode(__('swal_remove_gal_txt')) ?>,
            btn_yes_hide: <?= json_encode(__('btn_yes_hide')) ?>,
            btn_cancelar: <?= json_encode(__('btn_cancelar')) ?>,
            swal_img_removed: <?= json_encode(__('swal_img_removed')) ?>,
            err_hide_img: <?= json_encode(__('err_hide_img')) ?>,
            err_toggle_public: <?= json_encode(__('err_toggle_public')) ?>
        };
    </script>
	
<div id="visorPantallaCompleta" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0,0,0,0.92); z-index: 999999; justify-content: center; align-items: center; overflow: hidden; backdrop-filter: blur(5px);">
    <button onclick="cerrarVisor()" class="btn btn-dark border border-secondary shadow-lg position-absolute top-0 end-0 m-4" style="z-index: 1000000; border-radius: 50%; width: 50px; height: 50px; opacity: 0.8;"><i class="bi bi-x-lg"></i></button>
    <div class="position-absolute bottom-0 start-50 translate-middle-x mb-4 text-white p-2 rounded bg-dark border border-secondary" style="opacity: 0.7; z-index: 1000000; pointer-events: none;">
        <i class="bi bi-mouse3 me-1"></i> <?= __('txt_ayuda_visor') ?? 'Rueda para Zoom | Clic y Arrastrar | Doble clic = 100%' ?>
    </div>
    
    <img id="imagenVisor" src="" style="max-width: 95%; max-height: 95vh; object-fit: contain; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.9); transition: transform 0.1s ease-out; cursor: grab; transform-origin: center center;" draggable="false">
    
    <video id="videoVisor" src="" style="max-width: 95%; max-height: 95vh; object-fit: contain; border-radius: 4px; box-shadow: 0 10px 40px rgba(0,0,0,0.9); display: none;" controls loop autoplay></video>
</div>

<?php include __DIR__ . '/includes/modals/modal_compare.php'; ?>

    <script src="assets/js/visor.js" defer></script>
    <script src="assets/js/scripts.js" defer></script>
</head>
<body class="bg-darker">

<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="me-2 text-info"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
             <span class="fw-bold text-white">Garty's <span class="text-info">Architect</span></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="logo-garty me-3">byGarty</div>
            <a class="nav-link fw-bold text-light" href="historial.php"><i class="bi bi-clock-history"></i> <?= __('menu_historial') ?></a>
            <a href="index.php" class="btn btn-outline-light btn-sm fw-bold"><?= __('btn_volvergenerador') ?></a>
        </div>
    </div>
</nav>

<div class="container py-4">
    <div class="text-center mb-5">
        <h2 class="fw-bold"><?= __('tit_galeria') ?></h2>
        <p><?= __('txt_galeria') ?></p>
    </div>

    <?php if (empty($imagenes_publicas)): ?>
        <div class="text-center py-5 opacity-50">
            <i class="bi bi-images fs-1 mb-3 d-block"></i>
            <p><?= __('txt_galer_vc') ?></p>
        </div>
    <?php else: ?>
        <div class="masonry-grid">
            <?php foreach ($imagenes_publicas as $img): ?>
                <div class="masonry-item">
                    <?php if (strtolower(pathinfo($img['imagen_path'], PATHINFO_EXTENSION)) === 'mp4'): ?>
                        <video src="galeria/<?php echo htmlspecialchars($img['imagen_path']); ?>" onclick="abrirVisor(this.src)" style="cursor: pointer; width: 100%; display: block;" muted loop onmouseover="this.play()" onmouseout="this.pause()"></video>
                    <?php else: ?>
                        <img src="galeria/<?php echo htmlspecialchars($img['imagen_path']); ?>" onclick="abrirVisor(this.src)" style="cursor: zoom-in;" alt="Arte Generativo">
                    <?php endif; ?>
                    
                    <div class="author-badge"><i class="bi bi-person-fill me-1"></i><?php echo htmlspecialchars($img['nick']); ?></div>
                    <div class="model-badge"><?php echo htmlspecialchars(basename($img['modelo'])); ?></div>
                    
                    <a href="galeria/<?php echo htmlspecialchars($img['imagen_path']); ?>" download class="btn-fab btn-download-img-fab" title="<?= __('btn_descargar') ?>">
                        <i class="bi bi-download"></i>
                    </a>

                    <div class="cluster-btns-fab">
                        <a href="index.php?reutilizar=<?php echo $img['id']; ?>" class="btn-fab btn-reutilizar-fab" title="<?= __('btn_reutilizar') ?>">
                            <i class="bi bi-magic"></i>
                        </a>
                        
                        <?php if ($user_id === $img['user_id'] || $is_admin): ?>
                            <button class="btn-fab btn-remove-fab" onclick="quitarGaleria(<?php echo $img['id']; ?>, this)" title="<?= __('btn_retirar') ?>">
                                <i class="bi bi-eye-slash-fill"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>