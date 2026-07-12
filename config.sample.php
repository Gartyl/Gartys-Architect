<?php
/**
 * config.php - Versión v80 (Local).
 */

// Leer la versión directamente del fichero version.json local (se actualiza automáticamente con cada ZIP)
$version_meta = @json_decode(file_get_contents(__DIR__ . '/version.json'), true);
define('APP_VERSION', $version_meta['version'] ?? '1.0.0');

// --- INTERRUPTOR MAESTRO (local) ---
define('APP_MODE', 'local'); 

// --- CONFIGURACIÓN PARA LLM ---
define('LLM_IP', '127.0.0.1'); 
define('LLM_PORT', '11434'); 

// --- CONFIGURACIÓN PARA COMFYUI (Imágenes) ---
define('API_KEY', '');
define('COMFY_HOST', '127.0.0.1');
define('COMFY_PORT', '8188');
define('COMFY_URL', 'http://' . COMFY_HOST . ':' . COMFY_PORT);

// RUTA FÍSICA A LOS MODELOS DE COMFYUI (Para descargas directas)
// El usuario final solo tendrá que cambiar esta ruta a donde tenga su ComfyUI

define('COMFY_MODELS_DIR', 'C:/ComfyUI/models');

// --- MODELOS LLM ---
define('LLM_EXECUTION_MODE', 'local'); 
?>