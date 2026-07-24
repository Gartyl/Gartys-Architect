<?php require_once 'includes/core/init.php'; ?>

<!-- ======================================================= -->
<!-- Versión 260 - v. 1.0.0 -->
<!-- byGarty - R.A.G. -->
<!-- ======================================================= -->

<!-- ======================================================= -->
<!-- INIT -->
<!-- ======================================================= -->

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <?php include 'includes/partials/head.php'; ?>
</head>

<body id="dropZoneBody">

<!-- ======================================================= -->
<!-- MSG ADMIN -->
<!-- ======================================================= -->
<?php if (!empty($mensajes_pendientes)): ?>
    <?php include 'includes/modals/modal_msgadmin.php'; ?>
<?php endif; ?>

<!-- ======================================================= -->
<!-- ABRIR GALERÍA -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_gallery.php'; ?>

<!-- ======================================================= -->
<!-- NAVBAR - HEADER -->
<!-- ======================================================= -->
<?php include 'includes/partials/navbar.php'; ?>

<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="app-card shadow-lg" id="mainConsoleCard">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0 text-light"><?= __('tit_terminal') ?></h4>
                    </div>
                    
                    <!-- NUEVO: MONITOR DE COLA GPU -->
                    <div id="queueMonitor" class="d-none" title="Trabajos activos en la VRAM">
                        <span class="badge border border-warning text-warning py-2 px-3 shadow-sm" style="background-color: rgba(255, 193, 7, 0.1); font-size: 0.85rem;">
                            <span class="spinner-grow spinner-grow-sm me-2" role="status" style="width: 0.7rem; height: 0.7rem;"></span>
                            <?= __('tit_gpu_ocupada') ?> (<span id="queueCount">0</span>)
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">
                
                    <!-- ======================================================= -->
                    <!-- PANEL CHAT -->
                    <!-- ======================================================= -->    
                    <?php include 'includes/components/panel_chat.php'; ?>

                    <form id="promptForm">
                        
                        <!-- ======================================================= -->
                        <!-- 1. BLOQUE PRINCIPAL (Configuración Core) -->
                        <!-- ======================================================= -->
                        <?php include 'includes/components/panel_principal.php'; ?>
                        
                        <!-- ======================================================= -->
                        <!-- 2. ROL DE CHAT -->
                        <!-- ======================================================= -->
                        <?php include 'includes/components/panel_chatrol.php'; ?>
                        
                        <!-- ======================================================= -->
                        <!-- 3. HERRAMIENTAS GRATUITAS (Core del Usuario) -->
                        <!-- ======================================================= -->
                        <?php include 'includes/components/panel_motor.php'; ?>
                        <?php include 'includes/components/panel_lora.php'; ?>
                        <?php include 'includes/components/panel_presets.php'; ?>

                        <!-- ======================================================= -->
                        <!-- 4. CONTENEDOR PADRE: HERRAMIENTAS PRO -->
                        <!-- ======================================================= -->
                        <div class="param-group shadow-sm border-secondary mb-3" style="border-color: rgba(255, 193, 7, 0.4) !important; background: rgba(255, 193, 7, 0.05);">
                            <div class="d-flex justify-content-between align-items-center" style="cursor: pointer;" 
                                 onclick="document.getElementById('proToolsContainer').classList.toggle('d-none'); document.getElementById('proChevron').classList.toggle('bi-chevron-up'); document.getElementById('proChevron').classList.toggle('bi-chevron-down');">
                                <label class="small text-warning fw-bold mb-0" style="cursor: pointer;">
                                    <i class="bi bi-stars me-1"></i> <?= __('tit_herramientas_pro') ?? 'Herramientas Avanzadas PRO' ?> 
                                    <?= !$is_pro ? '🔒 (Pro)' : '' ?>
                                    <span id="proActiveIndicator" class="pro-active-dot d-none" title="Módulos Pro Activos"></span>
                                </label>
                                <div class="text-warning">
                                    <i id="proChevron" class="bi bi-chevron-down fw-bold"></i>
                                </div>
                            </div>
                            
                            <div id="proToolsContainer" class="d-none mt-3 pt-3 border-top border-secondary" style="border-color: rgba(255, 193, 7, 0.2) !important;">
                                
                                <!-- Extensiones Pro -->
                                <?php include 'includes/components/panel_controlnet.php'; ?>
                                <?php include 'includes/components/panel_ipadapter.php'; ?>
                                <?php include 'includes/components/panel_reactor.php'; ?>
                                <?php include 'includes/components/panel_adetailer.php'; ?>
                                <?php include 'includes/components/panel_upscaler.php'; ?>
                                <?php include 'includes/components/panel_rembg.php'; ?>     
                                <?php include 'includes/components/panel_ddcolor.php'; ?>
                                <?php include 'includes/components/panel_iclight.php'; ?>
                                <?php include 'includes/components/panel_audio.php'; ?>

                            </div>
                        </div>

                        <!-- ======================================================= -->
                        <!-- 5. LIENZO Y EDICIÓN AVANZADA (Inpaint / Outpaint / LaMa) -->
                        <!-- ======================================================= -->
                        <div id="imgPreviewContainer" style="display: none;">
                            <?php include 'includes/components/panel_inpaint.php'; ?>
                        </div>
                        
                        <!-- ======================================================= -->
                        <!-- 6. BOTONES DE CARGA Y WILDCARDS -->
                        <!-- ======================================================= -->
                        <?php include 'includes/components/panel_control.php'; ?>
                        
                    </form>
                    
                    <!-- ======================================================= -->
                    <!-- 7. ZONA DE RESULTADOS DEL ARQUITECTO IA -->
                    <!-- ======================================================= -->
                    <?php include 'includes/components/panel_architect.php'; ?>

                    <!-- ======================================================= -->
                    <!-- 8. BARRA DE PROGRESO Y CONTENEDOR DE IMÁGENES AL FINAL -->
                    <!-- ======================================================= -->
                    <?php include 'includes/components/panel_result.php'; ?>
                    
                </div> <!-- /card-body -->
            </div> <!-- /app-card -->
        </div> <!-- /col-lg-8 -->
    </div> <!-- /row -->
</div> <!-- /container -->

<!-- ======================================================= -->
<!-- MODALES -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_compare.php'; ?>
<?php include 'includes/modals/modal_viewer.php'; ?>
<?php include 'includes/modals/modal_wildcards.php'; ?>
<?php include 'includes/modals/modal_gestmodel.php'; ?>
<?php include 'includes/modals/modal_videomerge.php'; ?>
<?php include 'includes/modals/modal_support.php'; ?>
<?php include 'includes/modals/modal_license.php'; ?>

<?php if ($necesita_onboarding): ?>
    <?php include 'includes/modals/modal_onboarding.php'; ?>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- ======================================================= -->
<!-- 10. SCRIPTS -->
<!-- ======================================================= -->
<script>
    const APP_ENV = {
        userRole: '<?= $user_rol ?>',
        isAvanzado: true,
        isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
        lang: '<?= strtolower($lang) ?>'
    };

    const GartyLang = <?= json_encode($diccionario, JSON_UNESCAPED_UNICODE) ?>;
    const currentUserRole = APP_ENV.userRole; 

    // --- NUEVO: MONITOR VISUAL DE MÓDULOS PRO ACTIVOS ---
    document.addEventListener('DOMContentLoaded', () => {
        const proSwitches = [
            'toggleControlNet', 'toggleIPAdapter', 'reactorToggle', 
            'adetailerToggle', 'hiresToggle', 'rembgToggle', 
            'toggleDDColor', 'iclightToggle'
        ];
        
        function updateProIndicator() {
            const indicator = document.getElementById('proActiveIndicator');
            const anyActive = proSwitches.some(id => {
                const el = document.getElementById(id);
                return el && el.checked;
            });
            
            if (indicator) {
                indicator.classList.toggle('d-none', !anyActive);
            }
        }

        // Asignamos el listener a todos los switches
        proSwitches.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', updateProIndicator);
        });
        
        // Llamada inicial para chequear estado (ej: si se carga un preset)
        setTimeout(updateProIndicator, 500);
    });
</script>

<script src="assets/js/core.js?v=<?php echo filemtime('assets/js/core.js'); ?>"></script>
<script src="assets/js/visor.js?v=<?php echo filemtime('assets/js/visor.js'); ?>"></script>
<script src="assets/js/prompts.js?v=<?php echo filemtime('assets/js/prompts.js'); ?>"></script>
<script src="assets/js/modelos.js?v=<?php echo filemtime('assets/js/modelos.js'); ?>"></script>
<script src="assets/js/video.js?v=<?php echo filemtime('assets/js/video.js'); ?>"></script>
<script src="assets/js/herramientas.js?v=<?php echo filemtime('assets/js/herramientas.js'); ?>"></script>
<script src="assets/js/audio.js?v=<?php echo filemtime('assets/js/audio.js'); ?>"></script>
<script src="assets/js/presets_personales.js?v=<?php echo filemtime('assets/js/presets_personales.js'); ?>"></script> 
<script src="assets/js/motor.js?v=<?php echo filemtime('assets/js/motor.js'); ?>"></script>

</body>
</html>