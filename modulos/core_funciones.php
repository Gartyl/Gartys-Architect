<?php
// ==============================================================================
// --- CORE FUNCIONES: SEGURIDAD, PARCHES BBDD Y HERRAMIENTAS GLOBALES ---
// ==============================================================================

$user_id = $_SESSION['user_id'];
$user_rol = $_SESSION['rol'] ?? 'free';

// Asignación de Roles Unificada
$is_admin = ($user_rol === 'admin');
$is_pro   = ($user_rol === 'pro' || $user_rol === 'admin'); 
$is_free  = ($user_rol === 'free');

// --- TRAMPA GLOBAL PARA ROLES DE CHAT (FREEMIUM) ---
if (!$is_pro && isset($_POST['chat_role'])) {
    $rol_enviado = trim($_POST['chat_role']);
    $rol_por_defecto = '';
    
    try {
        $stmt = $pdo->prepare("SELECT prompt_texto FROM personalidades_prompts WHERE tipo = 'chat_default' AND idioma = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$lang]);
        $rol_por_defecto = trim((string)$stmt->fetchColumn());
        
        if (empty($rol_por_defecto)) {
            $stmt->execute(['es']);
            $rol_por_defecto = trim((string)$stmt->fetchColumn());
        }

        if ($rol_enviado !== '' && $rol_enviado !== $rol_por_defecto) {
            echo json_encode(['success' => false, 'error' => __('err_pro_role_lock')]);
            exit;
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error de seguridad validando el rol.']);
        exit;
    }
}

// --- HERRAMIENTAS GLOBALES ---
function filter_admin_folders($list) {
    $filtered = [];
    foreach ($list as $item) {
        if (!preg_match('/_C[\/\\\\]/i', $item)) {
            $filtered[] = $item;
        }
    }
    return $filtered;
}

if (!function_exists('obtener_info_modelo')) {
    function obtener_info_modelo($identificador_o_nombre, $pdo, $fallback_motor = 'comfyui') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM modelos_ia WHERE activo = 1 AND (id = ? OR nombre_archivo = ?) LIMIT 1");
            if ($stmt) {
                $stmt->execute([$identificador_o_nombre, $identificador_o_nombre]);
                $modelo_db = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($modelo_db) return $modelo_db; 
            }
        } catch (Throwable $e) {}

        return [
            'nombre_archivo' => $identificador_o_nombre,
            'motor' => $fallback_motor,
            'vae_requerido' => null,
            'nivel_acceso' => 'usuario'
        ];
    }
}

function cargarWorkflowJSON($ruta_archivo, $reemplazos) {
    if (!file_exists($ruta_archivo)) die(json_encode(['error' => __('err_json_not_found') . " " . $ruta_archivo]));
    $json_texto = file_get_contents($ruta_archivo);

    foreach ($reemplazos as $etiqueta => $valor) {
        if (is_string($valor)) {
            $valor_seguro = substr(json_encode($valor), 1, -1);
            $json_texto = str_replace($etiqueta, $valor_seguro, $json_texto);
        } else {
            $json_texto = str_replace($etiqueta, $valor, $json_texto);
        }
    }

    $workflow_decodificado = json_decode($json_texto, true);
    if (json_last_error() !== JSON_ERROR_NONE) die(json_encode(['error' => __('err_json_corrupted') . " " . json_last_error_msg()]));
    return $workflow_decodificado;
}

function process_dynamic_prompts($text, $depth = 0) {
    $wildcards_dir = __DIR__ . '/../wildcards'; // Ajustado porque ahora estamos en /modulos
    if ($depth > 10 || empty($text)) return $text; 

    $text = preg_replace_callback('/\{([^{}]*)\}/', function($matches) {
        $options = explode('|', $matches[1]);
        return trim($options[array_rand($options)]);
    }, $text);

    $text = preg_replace_callback('/__([a-zA-Z0-9_\-]+)__/', function($matches) use ($wildcards_dir) {
        $filename = $matches[1] . '.txt';
        $filepath = $wildcards_dir . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($filepath)) {
            $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!empty($lines)) {
                $valid_lines = array_filter($lines, function($line) { return strpos(trim($line), '#') !== 0; });
                if (!empty($valid_lines)) return trim($valid_lines[array_rand($valid_lines)]);
            }
        }
        return $matches[0];
    }, $text);

    if (preg_match('/\{.*?\}/', $text) || preg_match('/__.*?__/', $text)) $text = process_dynamic_prompts($text, $depth + 1);
    return preg_replace('/\s+/', ' ', $text);
}

// --- AUTO-PARCHES DE BASE DE DATOS ---
try { $pdo->query("SELECT imagen_path FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN imagen_path VARCHAR(255) DEFAULT NULL"); }

try { $pdo->query("SELECT metadata FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN metadata TEXT DEFAULT NULL"); }

try { $pdo->query("SELECT favorito FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN favorito TINYINT(1) DEFAULT 0"); }

try { $pdo->query("SELECT is_public FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN is_public TINYINT(1) DEFAULT 0"); }

try { $pdo->query("SELECT texto_generado FROM historial_prompts LIMIT 1"); } 
catch (Exception $e) { $pdo->exec("ALTER TABLE historial_prompts ADD COLUMN texto_generado TEXT DEFAULT NULL"); }

try { 
    $pdo->exec("CREATE TABLE IF NOT EXISTS modelos_ia (
        id INT AUTO_INCREMENT PRIMARY KEY, nombre_visual VARCHAR(100) NOT NULL, nombre_archivo VARCHAR(150) NOT NULL,
        motor VARCHAR(50) NOT NULL, categoria VARCHAR(50) NOT NULL, vae_requerido VARCHAR(150) DEFAULT NULL,
        config_yaml VARCHAR(150) DEFAULT NULL, nivel_acceso VARCHAR(50) DEFAULT 'usuario', activo TINYINT(1) DEFAULT 1, fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) { error_log(__('log_err_patch_models') . ": " . $e->getMessage()); }

try { 
    $pdo->exec("CREATE TABLE IF NOT EXISTS personalidades_prompts (
        id INT AUTO_INCREMENT PRIMARY KEY, tipo VARCHAR(50) NOT NULL, idioma VARCHAR(10) NOT NULL DEFAULT 'es',
        titulo VARCHAR(100) NOT NULL, prompt_texto TEXT NOT NULL, parametros TEXT DEFAULT NULL, activo TINYINT(1) DEFAULT 1, fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Throwable $e) { error_log(__('log_err_patch_personas') . ": " . $e->getMessage()); }
?>