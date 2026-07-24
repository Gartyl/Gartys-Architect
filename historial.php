<?php
/**
 * historial.php - Versión v70 (Versión Local / Servidor).
 */
require_once __DIR__ . '/includes/core/init.php';

// Recuperamos la ID del usuario de la sesión
$user_id = $_SESSION['user_id'];

// --- AUTO-PARCHES DE BASE DE DATOS (Evita cuelgues si las columnas no existen) ---
try { $pdo->query("SELECT favorito FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN favorito TINYINT(1) DEFAULT 0"); }

try { $pdo->query("SELECT anotacion_admin FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN anotacion_admin TEXT DEFAULT NULL"); }

try { $pdo->query("SELECT is_public FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN is_public TINYINT(1) DEFAULT 0"); }

// --- OBTENER CATEGORÍAS/MODELOS DISPONIBLES PARA EL FILTRO ---
try {
    $stmt_mod = $pdo->prepare("SELECT DISTINCT modelo FROM historial_prompts WHERE user_id = ? ORDER BY modelo");
    $stmt_mod->execute([$user_id]);
    $modelos_disponibles = $stmt_mod->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $modelos_disponibles = [];
}

// --- CAPTURAR PARÁMETROS DE FILTRO ---
$search = $_GET['search'] ?? '';
$modelo_filtro = $_GET['modelo'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$fav_only = isset($_GET['fav_only']) ? 1 : 0;

// --- CONSTRUIR CONSULTA SQL DINÁMICA ---
$query = "SELECT id, modelo, descripcion_original, prompt_positivo, prompt_negativo, fecha_hora, imagen_path, metadata, favorito, anotacion_admin, is_public, texto_generado FROM historial_prompts WHERE user_id = ?";
$params = [$user_id];

if ($fav_only) {
    $query .= " AND favorito = 1";
}
if (!empty($modelo_filtro)) {
    $query .= " AND modelo = ?";
    $params[] = $modelo_filtro;
}
if (!empty($date_from)) {
    $query .= " AND DATE(fecha_hora) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $query .= " AND DATE(fecha_hora) <= ?";
    $params[] = $date_to;
}
if (!empty($search)) {
    $query .= " AND (descripcion_original LIKE ? OR prompt_positivo LIKE ? OR prompt_negativo LIKE ?)";
    $like_search = "%$search%";
    $params[] = $like_search;
    $params[] = $like_search;
    $params[] = $like_search;
}

$query .= " ORDER BY fecha_hora DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $all_prompts = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al consultar el historial: " . $e->getMessage());
}

function groupHistoryItems($prompts) {
    $grouped = [];
    $currentThread = null;
    $currentPromptGroup = null;

    foreach ($prompts as $p) {
        $timestamp = strtotime($p['fecha_hora']);

        if ($p['modelo'] === '[CHAT]') {
            if ($currentPromptGroup) {
                $grouped[] = $currentPromptGroup;
                $currentPromptGroup = null;
            }

            if ($currentThread && $timestamp >= $currentThread['last_time'] - 3600) {
                array_unshift($currentThread['messages'], $p);
                $currentThread['last_time'] = $timestamp;
            } else {
                if ($currentThread) $grouped[] = $currentThread;
                $currentThread = [
                    'type' => 'chat_thread',
                    'modelo' => '[CHAT]',
                    'fecha_hora' => $p['fecha_hora'],
                    'last_time' => $timestamp,
                    'messages' => [$p]
                ];
            }
        } else {
            if ($currentThread) {
                $grouped[] = $currentThread;
                $currentThread = null;
            }

            if ($currentPromptGroup && 
                $p['descripcion_original'] === $currentPromptGroup['descripcion_original'] && 
                $timestamp >= $currentPromptGroup['last_time'] - 7200) {
                
                array_unshift($currentPromptGroup['items'], $p);
                $currentPromptGroup['last_time'] = $timestamp;
            } else {
                if ($currentPromptGroup) $grouped[] = $currentPromptGroup;
                $currentPromptGroup = [
                    'type' => 'prompt_group',
                    'modelo' => $p['modelo'],
                    'descripcion_original' => $p['descripcion_original'],
                    'fecha_hora' => $p['fecha_hora'],
                    'last_time' => $timestamp,
                    'items' => [$p]
                ];
            }
        }
    }
    
    if ($currentThread) $grouped[] = $currentThread;
    if ($currentPromptGroup) $grouped[] = $currentPromptGroup;
    
    return $grouped;
}

$items = groupHistoryItems($all_prompts);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('tit_historial') ?> - Garty's Architect</title>
    
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=<?php echo time(); ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const GartyLang = {
            avis_portap: <?= json_encode(__('avis_portap')) ?>,
            avis_borrareg1: <?= json_encode(__('avis_borrareg1')) ?>,
            avis_borrareg2: <?= json_encode(__('avis_borrareg2')) ?>,
            btn_siborrar: <?= json_encode(__('btn_siborrar')) ?>,
            btn_cancelar: <?= json_encode(__('btn_cancelar')) ?>,
            avis_borralot1: <?= json_encode(__('avis_borralot1')) ?>,
            avis_borralot2: <?= json_encode(__('avis_borralot2')) ?>,
            avis_borralot3: <?= json_encode(__('avis_borralot3')) ?>,
            btn_borralote: <?= json_encode(__('btn_borralote')) ?>,
            avis_borrando: <?= json_encode(__('avis_borrando')) ?>,
            avis_borrantxt: <?= json_encode(__('avis_borrantxt')) ?>,
            swal_img_a_mem: <?= json_encode(__('swal_img_a_mem')) ?>,
            swal_gpu_free: <?= json_encode(__('swal_gpu_free')) ?>,
            swal_gpu_saved: <?= json_encode(__('swal_gpu_saved')) ?>,
            swal_gpu_async_done: <?= json_encode(__('swal_gpu_async_done')) ?>,
            tit_gpu_ready: <?= json_encode(__('tit_gpu_ready')) ?>,
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
<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="me-2 text-primary"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path></svg>
             <span class="fw-bold text-white">Garty's <span class="text-info">Architect</span></span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="logo-garty me-3">byGarty</div>
            <a class="nav-link fw-bold text-info" href="galeria.php"><i class="bi bi-globe"></i> <?= __('btn_vergaleria') ?></a>
            <a href="index.php" class="btn btn-outline-light btn-sm fw-bold"><?= __('btn_volvergenerador') ?></a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h3 class="text-light fw-bold mb-4"><?= __('tit_historial') ?></h3>

            <form method="GET" action="historial.php" class="card card-historial p-3 mb-4 shadow-sm filter-bar">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small text-secondary fw-bold mb-1"><i class="bi bi-search"></i> <?= __('tit_hist_bus') ?></label>
                        <input type="text" name="search" class="form-control form-control-sm bg-dark text-light" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?= __('tit_hist_ph_bus') ?? 'Palabra clave...' ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-secondary fw-bold mb-1"><i class="bi bi-tags"></i> <?= __('tit_hist_cat') ?></label>
                        <select name="modelo" class="form-select form-select-sm bg-dark text-light">
                            <option value=""><?= __('sel_all') ?></option>
                            <?php foreach($modelos_disponibles as $m): ?>
                                <option value="<?php echo htmlspecialchars($m); ?>" <?php if($modelo_filtro === $m) echo 'selected'; ?>><?php echo htmlspecialchars($m); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-secondary fw-bold mb-1"><i class="bi bi-calendar-event"></i> <?= __('tit_hist_de') ?></label>
                        <input type="date" name="date_from" class="form-control form-control-sm bg-dark text-light" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-secondary fw-bold mb-1"><i class="bi bi-calendar-event"></i> <?= __('tit_hist_hasta') ?></label>
                        <input type="date" name="date_to" class="form-control form-control-sm bg-dark text-light" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-center gap-2 filter-actions">
                        <div class="form-check form-switch mt-1 me-2 d-flex align-items-center">
                            <input class="form-check-input me-2 mt-0" type="checkbox" name="fav_only" id="fav_only" value="1" <?php if($fav_only) echo 'checked'; ?> style="cursor:pointer;">
                            <label class="form-check-label small fw-bold <?php echo $fav_only ? 'text-danger' : 'text-secondary'; ?>" for="fav_only" style="cursor:pointer;">
                                <i class="bi bi-heart-fill"></i> <?= __('lbl_favs') ?>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary flex-grow-1 fw-bold"><i class="bi bi-funnel-fill"></i> <?= __('btn_filtrar') ?></button>
                        <a href="historial.php" class="btn btn-sm btn-outline-secondary" title="<?= __('btn_clear_filters') ?>"><i class="bi bi-x-lg"></i></a>
                    </div>
                </div>
            </form>

            <?php if (empty($items)): ?>
                <div class="text-center py-5 opacity-50">
                    <i class="bi bi-search fs-1 mb-3 d-block"></i>
                    <p><?= __('txt_filt_neg') ?></p>
                </div>
            <?php else: ?>
                <?php 
                $lastDate = "";
                foreach ($items as $item): 
                    $dateStr = date('d/m/Y', strtotime($item['fecha_hora']));
                    if ($dateStr !== $lastDate):
                        echo "<div class='group-header'><i class='bi bi-calendar3 me-2'></i> $dateStr</div>";
                        $lastDate = $dateStr;
                    endif;

                    // --- HILOS DE CHAT ---
                    if ($item['type'] === 'chat_thread'): 
                        $threadIds = array_column($item['messages'], 'id');
                        $threadIdsJson = json_encode($threadIds);
                    ?>
                        <div class="card card-historial card-thread p-4 shadow-sm" id="thread-card-<?php echo $item['messages'][0]['id']; ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <span class="model-badge badge-chat"><i class="bi bi-chat-dots-fill me-1"></i> <?= __('lbl_chat_thread') ?></span>
                                    <span class="date-text text-muted ms-2"><?php echo count($item['messages']); ?> <?= __('txt_mensajes') ?></span>
                                </div>
                                <button class="btn-icon-gray btn-icon-danger" title="<?= __('btn_del_conv') ?>" onclick="borrarHilo(<?php echo $threadIdsJson; ?>, 'thread-card-<?php echo $item['messages'][0]['id']; ?>')">
                                    <i class="bi bi-trash3-fill"></i>
                                </button>
                            </div>
                            <?php foreach ($item['messages'] as $msg): ?>
                                <div class="thread-entry position-relative" id="prompt-row-<?php echo $msg['id']; ?>">
                                    
                                    <?php if(!empty($msg['anotacion_admin'])): ?>
                                        <div class="alert alert-warning py-2 px-3 small fw-bold mb-3 border-warning" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= __('lbl_mod_note') ?><?php echo nl2br(htmlspecialchars($msg['anotacion_admin'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <span class="chat-label label-user"><?= __('lbl_user_date') ?> (<?php echo date('d/m/Y H:i', strtotime($msg['fecha_hora'])); ?>):</span>
                                            <p class="text-light mb-3"><?php echo nl2br(htmlspecialchars($msg['descripcion_original'])); ?></p>
                                            <span class="chat-label label-ai"><?= __('lbl_architect_resp') ?></span>
                                            
                                            <?php if (!empty($msg['metadata']) || !empty($msg['imagen_path'])): ?>
                                                <div class="row mt-2">
                                                    <?php if (!empty($msg['imagen_path'])): ?>
                                                    <div class="col-md-4 mb-3 mb-md-0">
                                                        <div class="img-container" style="position: relative;">
															<?php 
															$ext = strtolower(pathinfo($msg['imagen_path'], PATHINFO_EXTENSION));
															if ($ext === 'mp4' || $ext === 'webm' || $ext === 'mov'): ?>
																<video src="galeria/<?php echo htmlspecialchars($msg['imagen_path']); ?>" onclick="abrirVisor(this.src)" style="cursor: pointer;" class="img-fluid rounded border border-secondary shadow-sm w-100" muted loop onmouseover="this.play()" onmouseout="this.pause()"></video>
															<?php elseif ($ext === 'wav' || $ext === 'mp3' || $ext === 'flac'): ?>
																<div class="d-flex flex-column align-items-center justify-content-center p-4 bg-dark w-100 rounded border border-secondary" style="min-height: 180px;">
																	<i class="bi bi-music-note-beamed fs-1 text-info mb-3"></i>
																	<audio src="galeria/<?php echo htmlspecialchars($msg['imagen_path']); ?>" controls class="w-100 shadow-sm"></audio>
																</div>
															<?php else: ?>
																<img src="galeria/<?php echo htmlspecialchars($msg['imagen_path']); ?>" onclick="abrirVisor(this.src)" style="cursor: zoom-in;" class="img-fluid rounded border border-secondary shadow-sm w-100">
															<?php endif; ?>
                                                            
                                                            <a href="javascript:void(0)" onclick="togglePublic(<?php echo $msg['id']; ?>, this)" class="btn-pub-img <?php echo ($msg['is_public'] ? 'active' : ''); ?>" title="<?= __('btn_pub_gal') ?>">
                                                                <i class="bi bi-globe"></i>
                                                            </a>
                                                            <a href="javascript:void(0)" onclick="toggleFavorito(<?php echo $msg['id']; ?>, this)" class="btn-fav-img <?php echo ($msg['favorito'] ? 'active' : ''); ?>" title="<?= __('btn_fav') ?>">
                                                                <i class="bi <?php echo ($msg['favorito'] ? 'bi-heart-fill' : 'bi-heart'); ?>"></i>
                                                            </a>

                                                            <a href="galeria/<?php echo htmlspecialchars($msg['imagen_path']); ?>" download class="btn-fab btn-download-img-fab" title="<?= __('btn_descargar') ?>">
                                                                <i class="bi bi-download"></i>
                                                            </a>

                                                            <div class="cluster-btns-fab">
                                                                <a href="index.php?reutilizar=<?php echo $msg['id']; ?>" class="btn-fab btn-reutilizar-fab" title="<?= __('btn_reutilizar') ?>">
                                                                    <i class="bi bi-magic"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="prepararComparacion('galeria/<?php echo htmlspecialchars($msg['imagen_path']); ?>', this)" class="btn-fab btn-compare-fab" title="<?= __('tit_compare') ?>">
                                                                    <i class="bi bi-symmetry-vertical"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-8">
                                                    <?php else: ?>
                                                    <div class="col-12">
                                                    <?php endif; ?>
                                                    
                                                        <div class="swarm-viewer h-100">
                                                            <div class="swarm-row">
                                                                <span class="swarm-label"><?= __('lbl_prompt') ?></span>
                                                                <span class="swarm-value" id="copy-pos-<?php echo $msg['id']; ?>"><?php echo htmlspecialchars($msg['prompt_positivo']); ?></span>
                                                                <button class="swarm-btn-copy" onclick="copyTextEx('copy-pos-<?php echo $msg['id']; ?>')"><i class="bi bi-copy"></i></button>
                                                            </div>
                                                            <?php if(!empty($msg['prompt_negativo'])): ?>
                                                            <div class="swarm-row">
                                                                <span class="swarm-label"><?= __('lbl_neg_prompt') ?></span>
                                                                <span class="swarm-value" id="copy-neg-<?php echo $msg['id']; ?>"><?php echo htmlspecialchars($msg['prompt_negativo']); ?></span>
                                                                <button class="swarm-btn-copy" onclick="copyTextEx('copy-neg-<?php echo $msg['id']; ?>')"><i class="bi bi-copy"></i></button>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($msg['metadata'])): 
                                                                $meta = json_decode($msg['metadata'], true);
                                                                if ($meta): 
                                                                    foreach($meta as $key => $val): ?>
                                                                    <div class="swarm-row">
                                                                        <span class="swarm-label"><?php echo htmlspecialchars($key); ?>:</span>
                                                                        <span class="swarm-value"><?php echo htmlspecialchars($val); ?></span>
                                                                    </div>
                                                            <?php endforeach; endif; endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="prompt-container mt-2">
                                                    <button class="btn-icon-gray position-absolute top-0 end-0 m-2" onclick="copyTextEx('copy-<?php echo $msg['id']; ?>')"><i class="bi bi-copy"></i></button>
                                                    <code style="color: #8b949e; font-size: 0.85rem; font-family: 'Consolas', monospace; white-space: pre-wrap;" id="copy-<?php echo $msg['id']; ?>"><?php echo nl2br(htmlspecialchars($msg['prompt_positivo'])); ?></code>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($msg['texto_generado'])): ?>
                                                <div class="mt-3 p-3 bg-dark border border-secondary rounded text-light" style="white-space: pre-wrap; font-size: 0.95rem; border-color: rgba(255,255,255,0.1) !important;">
                                                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary pb-2" style="border-color: rgba(255,255,255,0.1) !important;">
                                                        <h6 class="text-info fw-bold mb-0"><i class="bi bi-file-text"></i> <?= __('tit_txt_gen') ?>:</h6>
                                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="copyTextEx('texto-gen-<?php echo $msg['id']; ?>')"><i class="bi bi-copy"></i> <?= __('btn_copy_text') ?></button>
                                                    </div>
                                                    <div id="texto-gen-<?php echo $msg['id']; ?>"><?php echo htmlspecialchars($msg['texto_generado']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                        </div>
                                        <button class="btn-icon-gray ms-3 text-danger border-danger mt-3" title="<?= __('btn_del_var') ?>" onclick="borrarRegistro(<?php echo $msg['id']; ?>)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    <?php 
                    // --- GRUPOS DE PROMPTS (Variaciones) ---
                    elseif ($item['type'] === 'prompt_group'): 
                        $groupIds = array_column($item['items'], 'id');
                        $groupIdsJson = json_encode($groupIds);
                        $isSingle = count($item['items']) === 1;
                    ?>
                        <div class="card card-historial p-4 shadow-sm" id="group-card-<?php echo $item['items'][0]['id']; ?>">
                            
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-3" style="border-color: rgba(255,255,255,0.1) !important;">
                                <div>
                                    <span class="model-badge"><?php echo htmlspecialchars($item['modelo']); ?></span>
                                    <span class="date-text ms-2"><?php echo date('d/m/Y H:i', strtotime($item['fecha_hora'])); ?></span>
                                    <?php if (!$isSingle): ?>
                                        <span class="badge bg-secondary ms-2"><?php echo count($item['items']); ?> <?= __('txt_reg_filt') ?></span>
                                    <?php endif; ?>
                                </div>
                                <button class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1" title="<?= __('btn_del_gen') ?>" onclick="borrarHilo(<?php echo $groupIdsJson; ?>, 'group-card-<?php echo $item['items'][0]['id']; ?>')">
                                    <i class="bi bi-trash3-fill"></i> <span class="d-none d-sm-inline"><?= __('btn_borralote') ?></span>
                                </button>
                            </div>
                            
                            <?php if (!$isSingle): ?>
                                <h5 class="text-light mb-4"><?php echo nl2br(htmlspecialchars($item['descripcion_original'])); ?></h5>
                            <?php else: ?>
                                <h6 class="text-light fw-bold mb-3"><?php echo nl2br(htmlspecialchars($item['descripcion_original'])); ?></h6>
                            <?php endif; ?>
                            
                            <?php foreach ($item['items'] as $index => $subItem): ?>
                                <div class="<?php echo (!$isSingle && $index < count($item['items']) - 1) ? 'mb-4 border-bottom border-secondary pb-4' : ''; ?>" id="prompt-row-<?php echo $subItem['id']; ?>" style="border-color: rgba(255,255,255,0.1) !important;">
                                    
                                    <?php if(!empty($subItem['anotacion_admin'])): ?>
                                        <div class="alert alert-warning py-2 px-3 small fw-bold mb-3 border-warning" style="background-color: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= __('lbl_mod_note') ?><?php echo nl2br(htmlspecialchars($subItem['anotacion_admin'])); ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            
                                            <?php if (!$isSingle): ?>
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="chat-label label-ai mb-0"><i class="bi bi-diagram-2 me-1"></i> <?= __('tit_hist_var') ?></span>
                                                    <span class="date-text ms-2">(<?php echo date('H:i:s', strtotime($subItem['fecha_hora'])); ?>)</span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($subItem['metadata']) || !empty($subItem['imagen_path'])): ?>
                                                <div class="row mt-2">
                                                    <?php if (!empty($subItem['imagen_path'])): ?>
                                                    <div class="col-md-4 mb-3 mb-md-0">
                                                        <div class="img-container" style="position: relative;">
															<?php 
															$ext = strtolower(pathinfo($subItem['imagen_path'], PATHINFO_EXTENSION));
															if ($ext === 'mp4' || $ext === 'webm' || $ext === 'mov'): ?>
																<video src="galeria/<?php echo htmlspecialchars($subItem['imagen_path']); ?>" onclick="abrirVisor(this.src)" style="cursor: pointer;" class="img-fluid rounded border border-secondary shadow-sm w-100" muted loop onmouseover="this.play()" onmouseout="this.pause()"></video>
															<?php elseif ($ext === 'wav' || $ext === 'mp3' || $ext === 'flac'): ?>
																<div class="d-flex flex-column align-items-center justify-content-center p-4 bg-dark w-100 rounded border border-secondary" style="min-height: 180px;">
																	<i class="bi bi-music-note-beamed fs-1 text-info mb-3"></i>
																	<audio src="galeria/<?php echo htmlspecialchars($subItem['imagen_path']); ?>" controls class="w-100 shadow-sm"></audio>
																</div>
															<?php else: ?>
																<img src="galeria/<?php echo htmlspecialchars($subItem['imagen_path']); ?>" onclick="abrirVisor(this.src)" style="cursor: zoom-in;" class="img-fluid rounded border border-secondary shadow-sm w-100">
															<?php endif; ?>
                                                            
                                                            <a href="javascript:void(0)" onclick="togglePublic(<?php echo $subItem['id']; ?>, this)" class="btn-pub-img <?php echo ($subItem['is_public'] ? 'active' : ''); ?>" title="<?= __('btn_pub_gal') ?>">
                                                                <i class="bi bi-globe"></i>
                                                            </a>
                                                            <a href="javascript:void(0)" onclick="toggleFavorito(<?php echo $subItem['id']; ?>, this)" class="btn-fav-img <?php echo ($subItem['favorito'] ? 'active' : ''); ?>" title="<?= __('btn_fav') ?>">
                                                                <i class="bi <?php echo ($subItem['favorito'] ? 'bi-heart-fill' : 'bi-heart'); ?>"></i>
                                                            </a>

                                                            <a href="galeria/<?php echo htmlspecialchars($subItem['imagen_path']); ?>" download class="btn-fab btn-download-img-fab" title="<?= __('btn_descargar') ?>">
                                                                <i class="bi bi-download"></i>
                                                            </a>

                                                            <div class="cluster-btns-fab">
                                                                <a href="index.php?reutilizar=<?php echo $subItem['id']; ?>" class="btn-fab btn-reutilizar-fab" title="<?= __('btn_reutilizar') ?>">
                                                                    <i class="bi bi-magic"></i>
                                                                </a>
                                                                <a href="javascript:void(0)" onclick="prepararComparacion('galeria/<?php echo htmlspecialchars($subItem['imagen_path']); ?>', this)" class="btn-fab btn-compare-fab" title="<?= __('tit_compare') ?>">
                                                                    <i class="bi bi-symmetry-vertical"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-8">
                                                    <?php else: ?>
                                                    <div class="col-12">
                                                    <?php endif; ?>
                                                    
                                                        <div class="swarm-viewer h-100">
                                                            <div class="swarm-row">
                                                                <span class="swarm-label"><?= __('lbl_prompt') ?></span>
                                                                <span class="swarm-value" id="copy-pos-<?php echo $subItem['id']; ?>"><?php echo htmlspecialchars($subItem['prompt_positivo']); ?></span>
                                                                <button class="swarm-btn-copy" onclick="copyTextEx('copy-pos-<?php echo $subItem['id']; ?>')"><i class="bi bi-copy"></i></button>
                                                            </div>
                                                            <?php if(!empty($subItem['prompt_negativo'])): ?>
                                                            <div class="swarm-row">
                                                                <span class="swarm-label"><?= __('lbl_neg_prompt') ?></span>
                                                                <span class="swarm-value" id="copy-neg-<?php echo $subItem['id']; ?>"><?php echo htmlspecialchars($subItem['prompt_negativo']); ?></span>
                                                                <button class="swarm-btn-copy" onclick="copyTextEx('copy-neg-<?php echo $subItem['id']; ?>')"><i class="bi bi-copy"></i></button>
                                                            </div>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($subItem['metadata'])): 
                                                                $meta = json_decode($subItem['metadata'], true);
                                                                if ($meta): 
                                                                    foreach($meta as $key => $val): ?>
                                                                    <div class="swarm-row">
                                                                        <span class="swarm-label"><?php echo htmlspecialchars($key); ?>:</span>
                                                                        <span class="swarm-value"><?php echo htmlspecialchars($val); ?></span>
                                                                    </div>
                                                            <?php endforeach; endif; endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="prompt-container mt-0">
                                                    <button class="btn-icon-gray position-absolute top-0 end-0 m-2" onclick="copyTextEx('copy-<?php echo $subItem['id']; ?>')"><i class="bi bi-copy"></i></button>
                                                    <code id="copy-<?php echo $subItem['id']; ?>"><?php echo nl2br(htmlspecialchars($subItem['prompt_positivo'])); ?></code>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($subItem['texto_generado'])): ?>
                                                <div class="mt-3 p-3 bg-dark border border-secondary rounded text-light" style="white-space: pre-wrap; font-size: 0.95rem; border-color: rgba(255,255,255,0.1) !important;">
                                                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom border-secondary pb-2" style="border-color: rgba(255,255,255,0.1) !important;">
                                                        <h6 class="text-info fw-bold mb-0"><i class="bi bi-file-text"></i> <?= __('tit_txt_gen') ?>:</h6>
                                                        <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="copyTextEx('texto-gen-<?php echo $subItem['id']; ?>')"><i class="bi bi-copy"></i> <?= __('btn_copy_text') ?></button>
                                                    </div>
                                                    <div id="texto-gen-<?php echo $subItem['id']; ?>"><?php echo htmlspecialchars($subItem['texto_generado']); ?></div>
                                                </div>
                                            <?php endif; ?>
                                            
                                        </div>
                                        <?php if (!$isSingle): ?>
                                        <button class="btn-icon-gray ms-3 text-danger border-danger mt-1" title="<?= __('btn_del_var') ?>" onclick="borrarRegistro(<?php echo $subItem['id']; ?>)">
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>