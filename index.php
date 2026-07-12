<?php require_once 'includes/core/init.php'; ?>

<!-- ======================================================= -->
<!-- Versión 260 - v. 1.0.0 - R1 -->
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
						<!--small class="text-muted">Operativo v172 (Selección LLM)</small-->
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
						<!-- 3. AJUSTES DE MOTOR (Elevado para mayor accesibilidad) -->
						<!-- ======================================================= -->
						<?php include 'includes/components/panel_motor.php'; ?>

						<!-- ======================================================= -->
						<!-- 4. CARGA DE LORA -->
						<!-- ======================================================= -->
						<?php include 'includes/components/panel_lora.php'; ?>

                        <!-- ======================================================= -->
						<!-- 5. EXTENSIONES DE EDICIÓN -->
						<!-- ======================================================= -->
						
                        <!-- 5.1. CONTROLNET STUDIO -->
							<?php include 'includes/components/panel_controlnet.php'; ?>

                        <!-- 5.2. IP-ADAPTER -->
							<?php include 'includes/components/panel_ipadapter.php'; ?>

                        <!-- 5.3. REACTOR -->
							<?php include 'includes/components/panel_reactor.php'; ?>
							
						<!-- 5.4. AFTER DETAILER -->
							<?php include 'includes/components/panel_adetailer.php'; ?>
                        
                        <!-- 5.5. ESTILOS Y PRESETS -->
							<?php include 'includes/components/panel_presets.php'; ?>
						
						<!-- 5.6. UPSCALER -->
							<?php include 'includes/components/panel_upscaler.php'; ?>
                      
                        <!-- 5.7. ELIMINAR FONDO -->
							<?php include 'includes/components/panel_rembg.php'; ?>	
						
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
<!-- MODAL COMPARADOR A/B -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_compare.php'; ?>

<!-- ======================================================= -->
<!-- VISOR DE IMAGEN PROFESIONAL (PAN & ZOOM) -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_viewer.php'; ?>

<!-- ======================================================= -->
<!-- MODAL DE WILDCARDS -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_wildcards.php'; ?>

<!-- ======================================================= -->
<!-- MODAL GESTOR MODELOS -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_gestmodel.php'; ?>

<!-- ======================================================= -->
<!-- BARRA FUSIÓN DE VIDEOS -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_videomerge.php'; ?>

<!-- ======================================================= -->
<!-- MODAL SOPORTE -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_support.php'; ?>

<!-- ======================================================= -->
<!-- MODAL LICENCIA -->
<!-- ======================================================= -->
<?php include 'includes/modals/modal_license.php'; ?>

<!-- ======================================================= -->
<!-- NECESITA ONBOARDING -->
<!-- ======================================================= -->
<?php if ($necesita_onboarding): ?>
	<?php include 'includes/modals/modal_onboarding.php'; ?>
<?php endif; ?> <!-- ¡IMPORTANTE! Este es el cierre del Onboarding. El modal va debajo. -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- ======================================================= -->
<!-- 10. SCRIPTS -->
<!-- ======================================================= -->
<script>
    // Variables globales de entorno
    const APP_ENV = {
        userRole: '<?= $user_rol ?>',
        isAvanzado: true,
        isAdmin: <?= $is_admin ? 'true' : 'false' ?>,
        lang: '<?= strtolower($lang) ?>'
    };

    // Esto pasa el 100% de tus traducciones a JS en una sola línea
    const GartyLang = <?= json_encode($diccionario, JSON_UNESCAPED_UNICODE) ?>;
    const currentUserRole = APP_ENV.userRole; // Alias rápido para compatibilidad
</script>

<!-- Carga de módulos troceados nativos JS -->

<script src="assets/js/core.js?v=<?php echo filemtime('assets/js/core.js'); ?>"></script>
<script src="assets/js/visor.js?v=<?php echo filemtime('assets/js/visor.js'); ?>"></script>
<script src="assets/js/prompts.js?v=<?php echo filemtime('assets/js/prompts.js'); ?>"></script>
<script src="assets/js/modelos.js?v=<?php echo filemtime('assets/js/modelos.js'); ?>"></script>
<script src="assets/js/video.js?v=<?php echo filemtime('assets/js/video.js'); ?>"></script>
<script src="assets/js/herramientas.js?v=<?php echo filemtime('assets/js/herramientas.js'); ?>"></script>
<script src="assets/js/presets_personales.js?v=<?php echo filemtime('assets/js/presets_personales.js'); ?>"></script> 
<script src="assets/js/motor.js?v=<?php echo filemtime('assets/js/motor.js'); ?>"></script>

</body>
</html>
