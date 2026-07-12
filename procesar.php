<?php
// ==============================================================================
// --- PROCESAR.PHP: ENRUTADOR PRINCIPAL (FRONT CONTROLLER) ---
// ==============================================================================
ini_set('max_execution_time', '0'); 
set_time_limit(0); 
ini_set('memory_limit', '2048M');
error_reporting(0); 

session_start();
require_once 'db.php';
require_once 'config.php';

// --- SISTEMA DE IDIOMAS ---
if (isset($_POST['idioma']) && in_array(strtolower($_POST['idioma']), ['es', 'en', 'ca'])) {
    $lang = strtolower($_POST['idioma']);
} elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['es', 'en', 'ca'])) {
    $lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['user_lang']) && in_array($_COOKIE['user_lang'], ['es', 'en', 'ca'])) {
    $lang = $_COOKIE['user_lang'];
} else {
    $lang = 'es';
}

$diccionario = [];
$ruta_idioma = __DIR__ . "/lang/{$lang}.php";
if (file_exists($ruta_idioma)) {
    $diccionario = include $ruta_idioma;
}

if (!function_exists('__')) {
    function __($clave) {
        global $diccionario;
        return isset($diccionario[$clave]) ? $diccionario[$clave] : $clave;
    }
}

// Salida JSON limpia
while (ob_get_level()) ob_end_clean();
ob_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => __('err_session_expired')]);
    exit();
}

try {
    // ¡LA VACUNA CONTRA EL ERROR 502!
    session_write_close(); 

    // CARGAMOS FUNCIONES GLOBALES
    require_once __DIR__ . '/modulos/core_funciones.php';

    // IDENTIFICAMOS LA ACCIÓN
    $action = $_POST['action'] ?? '';

    // Excepción: Ejecución directa del LLM
    if (isset($_POST['ejecutar_llm']) && $_POST['ejecutar_llm'] === 'true') {
        require_once __DIR__ . '/modulos/api_llm.php';
        exit();
    }

    // ==========================================================================
    // --- ENRUTADOR (SWITCH) ---
    // ==========================================================================
    switch ($action) {
        // --- GPU Y COMFYUI ---
        case 'generar_imagen':
            require_once __DIR__ . '/modulos/api_gpu.php';
            break;
        case 'angel_guardia':
        case 'check_ticket':
        case 'check_queue':
            require_once __DIR__ . '/modulos/api_radar.php';
            break;
        case 'get_checkpoints':
        case 'get_loras':
        case 'get_lora_trigger':
            require_once __DIR__ . '/modulos/api_nodos.php';
            break;

        // --- LLM Y VISIÓN ---
        case 'vision_extract':
        case 'describir_imagen':
            require_once __DIR__ . '/modulos/api_vision.php';
            break;
        case 'amplificar_prompt':
        case 'traducir_rapido':
        case 'generar_prompt_sorpresa':
        case 'get_wildcards':
        case 'get_ollama_models':
            require_once __DIR__ . '/modulos/api_utilidades.php';
            break;

        // --- GALERÍA Y VÍDEO ---
        case 'toggle_public':
        case 'toggle_favorito':
        case 'get_recent_images':
        case 'eliminar_prompt':
        case 'get_single_image_data':
        case 'concatenar_videos':
        case 'subir_video_externo':
		    require_once __DIR__ . '/modulos/api_galeria.php';
            break;

        // --- ADMINISTRACIÓN Y ONBOARDING ---
        case 'activar_licencia':
        case 'descargar_civitai':
        case 'leer_progreso_descarga':
        case 'ping_servicios':
        case 'instalar_basico':
        case 'get_modelos_bd':
        case 'get_active_models':
        case 'save_modelo_bd':
        case 'delete_modelo_bd':
        case 'toggle_modelo_bd':
        case 'duplicar_prompt':
        case 'get_prompts_bd':
        case 'save_prompt_bd':
        case 'update_prompt_bd':
        case 'delete_prompt_bd':
        case 'toggle_prompt_bd':
        case 'admin_get_idiomas':
        case 'admin_leer_idioma':
        case 'admin_guardar_idioma':
            require_once __DIR__ . '/modulos/api_admin.php';
            break;

        default:
            // Si no hay 'action', pero hay 'selector', es el Arquitecto generando un prompt JSON
            if (isset($_POST['selector'])) {
                require_once __DIR__ . '/modulos/api_llm.php';
            } else {
                echo json_encode(['error' => 'Acción no válida o no definida en el enrutador.']);
            }
            break;
    }

} catch (Exception $e) { 
    echo json_encode(['error' => __('err_backend_general') . ' ' . $e->getMessage()]); 
}
?>