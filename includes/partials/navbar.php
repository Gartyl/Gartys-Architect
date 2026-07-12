<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" class="me-2 text-primary">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
            <span class="fw-bold text-white">Garty's <span class="text-info">Architect</span></span>
        </a>
        
        <button class="navbar-toggler border-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#navbarGarty" aria-controls="navbarGarty" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarGarty">
            <div class="ms-auto d-flex flex-column flex-lg-row align-items-center gap-3 mt-3 mt-lg-0 pb-3 pb-lg-0">
                
                <div class="logo-garty me-lg-3 d-none d-lg-block">byGarty</div>
                
                <a class="nav-link fw-bold text-info" href="galeria.php"><i class="bi bi-globe"></i> <?= __('menu_galeria') ?></a>
                <a class="nav-link fw-bold text-light" href="historial.php"><i class="bi bi-clock-history"></i> <?= __('menu_historial') ?></a>
                <a class="nav-link fw-bold text-success" href="#" onclick="abrirGestorModelos()"><i class="bi bi-database-fill-gear"></i> <?= __('menu_modelos') ?></a>
                
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] !== 'pro' && $_SESSION['rol'] !== 'admin'): ?>
                    <button type="button" class="btn btn-sm btn-outline-warning fw-bold ms-lg-1" data-bs-toggle="modal" data-bs-target="#modalLicencia">
                        <i class="bi bi-star-fill text-warning"></i> <?= __('btn_activar_pro') ?? 'Activar Pro' ?>
                    </button>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2 ms-lg-1">
                        <span class="badge bg-warning text-dark" style="font-size: 0.85em;"><i class="bi bi-star-fill"></i> PRO</span>
                        <button type="button" class="btn btn-sm btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalSoportePro" title="<?= __('tit_soporte_vip') ?? 'Asistencia Técnica Exclusiva' ?>">
                            <i class="bi bi-headset"></i> <?= __('btn_soporte_vip') ?? 'Soporte VIP' ?>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (defined('APP_MODE') && APP_MODE === 'servidor'): ?>
                    
                    <?php if (isset($is_admin) && $is_admin): ?>
                        <a class="nav-link fw-bold text-warning" href="admin_panel.php"><i class="bi bi-shield-lock"></i> <?= __('menu_panel') ?></a>
                    <?php endif; ?>

                    <div class="dropdown ms-lg-2">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle fw-bold border-0 text-light" type="button" id="menuUsuario" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle text-primary"></i> <?= htmlspecialchars((empty($_SESSION['nick']) || $_SESSION['nick'] === 'Usuario') ? __('menu_user') : $_SESSION['nick']) ?>   
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary shadow-lg text-center text-lg-start" aria-labelledby="menuUsuario">
                            <li>
                                <a class="dropdown-item text-light fw-bold" href="perfil.php">
                                    <i class="bi bi-key-fill text-info me-2"></i> <?= __('menu_perfil') ?>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider border-secondary"></li>
                            <li>
                                <a class="dropdown-item text-danger fw-bold" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> <?= __('menu_salir') ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                <?php endif; ?>
                
                <div class="dropdown d-inline-block ms-lg-2 ps-lg-3">
                    <button class="btn btn-sm btn-outline-secondary border-0 text-light" type="button" data-bs-toggle="dropdown" title="<?= __('tit_cambiar_idioma') ?? 'Cambiar Idioma / Language' ?>">
                        <i class="bi bi-translate fs-5 text-info"></i>
                    </button>
                    
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow border-secondary text-center text-lg-start" style="background-color: #161b22; min-width: 120px;">
                        <li><h6 class="dropdown-header text-info"><?= __('tit_idioma') ?></h6></li>
                        
                        <?php
                        $lang_dir = __DIR__ . '/../../lang/';
                        
                        $json_path = $lang_dir . 'idiomas_meta.json';
                        $nombres_json = [];
                        if (file_exists($json_path)) {
                            $nombres_json = json_decode(file_get_contents($json_path), true) ?? [];
                        }

                        if (is_dir($lang_dir)) {
                            $archivos_lang = glob($lang_dir . '*.php');
                                                                                                        
                            $nombres_base = [
                                'es' => '🇪🇸 Español',
                                'en' => '🇬🇧 English',
                                'ca' => '<img src="assets/img/ca.svg" alt="CAT" style="width: 20px; height: 20px; margin-left:-2px; margin-bottom: 1px; border-radius: 2px;">Català',
                                'fr' => '🇫🇷 Français',
                                'it' => '🇮🇹 Italiano',
                                'de' => '🇩🇪 Deutsch',
                                'pt' => '🇵🇹 Português'
                            ];

                            $nombres_visuales = array_merge($nombres_base, $nombres_json);

                            foreach ($archivos_lang as $archivo) {
                                $codigo_iso = basename($archivo, '.php');
                                $nombre_mostrar = $nombres_visuales[$codigo_iso] ?? strtoupper($codigo_iso);
                                                                
                                $activo_class = (isset($lang) && $lang === $codigo_iso) ? 'fw-bold text-info' : '';
                                echo '<li><a class="dropdown-item small ' . $activo_class . '" href="?lang=' . $codigo_iso . '">' . $nombre_mostrar . '</a></li>';
                            }
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- ============================================================================== -->
<!-- --- BANNER DE ACTUALIZACIÓN DEL SISTEMA (Oculto hasta detección por JS) --- -->
<!-- ============================================================================== -->
<div id="contenedorActualizacion" style="display: none;" class="bg-warning text-dark border-bottom border-dark py-2 px-3 shadow-sm">
    <div class="container d-flex flex-column flex-sm-row align-items-center justify-content-between gap-2 text-center text-sm-start">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-rocket-takeoff-fill fs-5"></i>
            <span id="textoNuevaVersion" class="fw-bold small"></span>
        </div>
        
        <div class="d-flex align-items-center gap-2">
            <div id="progresoActualizacion" style="display: none;" class="small fw-bold text-dark me-2">
                <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                <?= __('msg_updating_wait') ?? 'Actualizando, no cierres la ventana...' ?>
            </div>
            <button id="btnActualizarSistema" onclick="ejecutarActualizacion()" class="btn btn-sm btn-dark fw-bold px-3 shadow-sm text-nowrap">
                ⚡ <?= __('btn_update_now') ?? 'Actualizar Ahora' ?>
            </button>
        </div>
    </div>
</div>