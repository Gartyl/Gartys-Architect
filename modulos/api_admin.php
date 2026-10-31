<?php
// --- DEFINICIONES DE SEGURIDAD (Y TIPADO PARA VSC / INTELEPHENSE) ---
// Si llamamos directamente al archivo o el editor lo analiza aislado, aseguramos las variables base:
if (!isset($action)) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
}
if (!isset($is_admin)) {
    $is_admin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
}
if (!isset($is_pro)) {
    $is_pro = $is_admin || (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['pro', 'avanzado']));
}

// Y por si acaso no estuviera cargada la configuración local (para poder leer APP_VERSION):
if (!defined('APP_VERSION')) {
    require_once __DIR__ . '/../config.php';
}

// Salvavidas por si api_admin.php es llamado directamente vía AJAX sin el motor de idiomas
if (!function_exists('__')) {
    function __($key) {
        return null;
    }
}

// ==============================================================================
// --- HELPER: CÁLCULO AUTOMÁTICO DE HASH AUTOV2 (CIVITAI) PARA MODELOS ---
// ==============================================================================
if (!function_exists('calcularHashAutoV2Modelo')) {
    function calcularHashAutoV2Modelo($nombre_archivo, $motor = 'comfyui') {
        if (empty($nombre_archivo) || strtolower(trim($motor)) !== 'comfyui') {
            return null;
        }

        $ruta_cfg = defined('COMFY_MODELS_DIR') ? COMFY_MODELS_DIR : (defined('COMFY_MODEL_PATH') ? COMFY_MODEL_PATH : 'C:/ComfyUI/models');
        if (empty($ruta_cfg)) return null;

        $rel = ltrim(str_replace('\\', '/', trim($nombre_archivo)), '/');
        $solo_nombre = basename($rel);
        $base = rtrim(str_replace('\\', '/', $ruta_cfg), '/');
        $padre = dirname($base);

        $raices = array_unique([$base, $padre]);
        $subcarpetas = ['', 'checkpoints', 'unet', 'diffusion_models', 'loras'];

        $ruta_fisica = null;
        foreach ($raices as $r) {
            foreach ($subcarpetas as $sub) {
                $prefijo = $sub === '' ? $r : ($r . '/' . $sub);
                
                if (@file_exists($prefijo . '/' . $rel) && @is_file($prefijo . '/' . $rel)) {
                    $ruta_fisica = $prefijo . '/' . $rel;
                    break 2;
                }
                if (@file_exists($prefijo . '/' . $solo_nombre) && @is_file($prefijo . '/' . $solo_nombre)) {
                    $ruta_fisica = $prefijo . '/' . $solo_nombre;
                    break 2;
                }
            }
        }

        if (!$ruta_fisica) return null;

        @set_time_limit(180);
        $full_sha256 = @hash_file('sha256', $ruta_fisica);
        return $full_sha256 ? strtolower(substr($full_sha256, 0, 10)) : null;
    }
}
// ==============================================================================
// --- MÓDULO ADMIN: GESTIÓN BBDD, DESCARGAS, LICENCIA E IDIOMAS ---
// ==============================================================================

if ($action === 'activar_licencia') {
    $license_key = trim($_POST['license_key'] ?? '');
    $product_id_oficial = '1143620'; 

    if (empty($license_key)) {
        echo json_encode(['success' => false, 'error' => __('err_missing_license') ?? 'Por favor, introduce una clave válida.']);
        exit();
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.lemonsqueezy.com/v1/licenses/validate",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['license_key' => $license_key]),
        CURLOPT_HTTPHEADER => ["Accept: application/json"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    if ($err) {
        echo json_encode(['success' => false, 'error' => __('err_license_server_conn') ?? 'Error de conexión con el servidor de licencias.']);
        exit();
    }

    $data = json_decode($response, true);

    if (isset($data['valid']) && $data['valid'] === true) {
        $id_producto_recibido = isset($data['meta']['product_id']) ? (string)$data['meta']['product_id'] : '';
        if ($id_producto_recibido === $product_id_oficial) {
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET rol = 'pro', license_key = ? WHERE id = ?");
                $user_id_local = $_SESSION['user_id'] ?? 1; 
                $stmt->execute([$license_key, $user_id_local]);
                $_SESSION['rol'] = 'pro';
                echo json_encode(['success' => true, 'mensaje' => __('msg_license_activated') ?? '¡Licencia activada con éxito! Bienvenido a Garty\'s Architect Pro.']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => __('err_license_db_save') ?? 'Error guardando la licencia en la BD.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => __('err_invalid_product_license') ?? 'Esta licencia pertenece a otro producto.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $data['error'] ?? 'La clave no es válida o ha expirado.']);
    }
    exit();
}

if ($action === 'descargar_civitai') {
    if (!$is_admin) die(json_encode(['error' => __('err_unauthorized')]));

    $url = trim($_POST['url'] ?? '');
    $nombre_visual = trim($_POST['nombre_visual'] ?? '');
    $categoria_dropdown = trim($_POST['categoria'] ?? ''); 

    if (empty($url) || empty($nombre_visual) || empty($categoria_dropdown)) {
        echo json_encode(['error' => __('err_missing_data')]);
        exit();
    }

    $base_dir = defined('COMFY_MODELS_DIR') ? rtrim(COMFY_MODELS_DIR, '/\\') : 'C:/ComfyUI/models';
    
    $rutas_destino = [
        'ckpt_sdxl'  => $base_dir . '/checkpoints/',
        'ckpt_sd15'  => $base_dir . '/checkpoints/',
        'unet_flux'  => $base_dir . '/unet/',
        'unet_video' => $base_dir . '/unet/',
        'lora_sdxl'  => $base_dir . '/loras/',
        'lora_flux'  => $base_dir . '/loras/',
        'lora_sd15'  => $base_dir . '/loras/',
        'lora_video' => $base_dir . '/loras/'
    ];

    $carpeta_destino = $rutas_destino[$categoria_dropdown] ?? $base_dir . '/checkpoints/';
    if (!is_dir($carpeta_destino)) { @mkdir($carpeta_destino, 0777, true); }

    $nombre_archivo_seguro = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $nombre_visual) . '.safetensors';
    $ruta_completa = rtrim($carpeta_destino, '/') . '/' . $nombre_archivo_seguro;

    $progreso_file = __DIR__ . '/../progreso_descarga.txt';
    file_put_contents($progreso_file, "0");

    $fp = fopen($ruta_completa, 'w+');
    if (!$fp) { echo json_encode(['error' => __('err_file_creation') . ' ' . $carpeta_destino]); exit(); }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_NOPROGRESS, false); 
    
    $headers = ['Authorization: Bearer ' . API_KEY, 'Accept: */*'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_REFERER, 'https://civitai.com/');
    
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $download_size, $downloaded, $upload_size, $uploaded) use ($progreso_file) {
        if ($download_size > 0) {
            $porcentaje = round(($downloaded / $download_size) * 100);
            if ($porcentaje % 5 === 0) { file_put_contents($progreso_file, $porcentaje); }
        }
    });

    $success = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error_curl = curl_error($ch);
    fclose($fp);

    if (!$success || $http_code !== 200) {
        @unlink($ruta_completa);
        echo json_encode(['error' => __('err_http') . " $http_code. " . __('err_download_fail') . ": $error_curl"]);
        exit();
    }

    @unlink($progreso_file);

    if ($success) {
        $db_motor = 'comfyui';
        $db_cat = (strpos($categoria_dropdown, 'lora') !== false) ? 'LoRA' : 
                 ((strpos($categoria_dropdown, 'video') !== false) ? 'Video' : 'Checkpoints');

        if ($db_cat === 'LoRA') {
            echo json_encode(['success' => true, 'mensaje' => __('msg_lora_downloaded')]);
            exit();
        }

        try {
            $civitai_hash_dl = calcularHashAutoV2Modelo($nombre_para_bd, 'comfyui');
			$stmt = $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso, es_unbundled, civitai_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
			$stmt->execute([$nombre_visual, $nombre_para_bd, $db_motor, $db_cat, 'usuario', $es_unbundled_auto, $civitai_hash_dl]);
			
            $subcarpeta = (strpos($categoria_dropdown, 'unet') !== false) ? 'unet/' : '';
            $nombre_para_bd = $subcarpeta . $nombre_archivo_seguro;
            
            // AUTOMATIZACIÓN: Si en el selector de Civitai elegiste 'unet', la base de datos lo registra como desmembrado.
            $es_unbundled_auto = (strpos($categoria_dropdown, 'unet') !== false) ? 1 : 0;
            
            $stmt->execute([$nombre_visual, $nombre_para_bd, $db_motor, $db_cat, 'usuario', $es_unbundled_auto]);
            echo json_encode(['success' => true, 'mensaje' => __('msg_model_downloaded')]);
        } catch (Exception $e) {
            echo json_encode(['error' => __('err_db_register') . ' ' . $e->getMessage()]);
        }
    }
    exit();
}

if ($action === 'ping_servicios') {
    $ch_ollama = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/tags");
    curl_setopt($ch_ollama, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ollama, CURLOPT_TIMEOUT, 2);
    $res_ollama = curl_exec($ch_ollama);
    $http_ollama = curl_getinfo($ch_ollama, CURLINFO_HTTP_CODE);
    //curl_close($ch_ollama);
    $ollama_ok = ($res_ollama !== false && $http_ollama === 200);

    $ch_comfy = curl_init(COMFY_URL . "/system_stats");
    curl_setopt($ch_comfy, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_comfy, CURLOPT_TIMEOUT, 2);
    $res_comfy = curl_exec($ch_comfy);
    $http_comfy = curl_getinfo($ch_comfy, CURLINFO_HTTP_CODE);
    //curl_close($ch_comfy);
    $comfy_ok = ($res_comfy !== false && $http_comfy === 200);

    echo json_encode(['ollama' => $ollama_ok, 'comfy' => $comfy_ok]);
    exit();
}

if ($action === 'instalar_basico') {
    // Evitamos que PHP corte la descarga por tiempo o bloquee la sesión
    session_write_close();
    @set_time_limit(0);

    $tipo = $_POST['tipo'] ?? '';
    if ($tipo === 'llama3') {
        // 1. Descargamos Llama 3 (con stream: false para respuesta limpia)
        $data = ["name" => "llama3:latest", "stream" => false];
        $ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/pull");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1200);
        $res = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($ch);

        // 2. Descargamos también el motor ligero de Embeddings (nomic-embed-text) para la búsqueda semántica
        $ch_emb = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/pull");
        curl_setopt($ch_emb, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_emb, CURLOPT_POST, true);
        curl_setopt($ch_emb, CURLOPT_POSTFIELDS, json_encode(["name" => "nomic-embed-text:latest", "stream" => false]));
        curl_setopt($ch_emb, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch_emb, CURLOPT_TIMEOUT, 300);
        curl_exec($ch_emb);
        //curl_close($ch_emb);

        if ($http_code === 200) {
            // Evitamos duplicados si el usuario pulsa dos veces
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM modelos_ia WHERE nombre_archivo = 'llama3:latest' AND motor = 'ollama'");
            $stmt_chk->execute();
            if ($stmt_chk->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso, activo) VALUES (?, ?, ?, ?, ?, 1)")
                    ->execute(['Cerebro Llama 3', 'llama3:latest', 'ollama', 'SYS_LLM', 'usuario']);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => __('err_ollama_download')]);
        }
        exit();
    }

    if ($tipo === 'sdxl') {
        // Usamos espejo directo de HuggingFace como respaldo si no hay API_KEY de Civitai configurada aún
        $tiene_api_key = defined('API_KEY') && strlen(trim(API_KEY)) > 5;
        $url_descarga = $tiene_api_key 
            ? "https://civitai.com/api/download/models/782002" 
            : "https://huggingface.co/RunDiffusion/Juggernaut-X-v10/resolve/main/Juggernaut-X-RunDiffusion-NSFW.safetensors";

        $base_dir = defined('COMFY_MODELS_DIR') ? rtrim(COMFY_MODELS_DIR, '/\\') : 'C:/ComfyUI/models';
        $ckpt_dir = $base_dir . '/checkpoints';
        if (!is_dir($ckpt_dir)) { @mkdir($ckpt_dir, 0777, true); }

        $ruta_destino = $ckpt_dir . '/Juggernaut_X_SDXL.safetensors';
        $fp = @fopen($ruta_destino, 'w+');
        if (!$fp) {
            echo json_encode(['error' => (__('err_file_creation') ?? 'No se pudo escribir en: ') . $ckpt_dir]);
            exit();
        }

        $ch = curl_init($url_descarga);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        if ($tiene_api_key) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . API_KEY]);
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //curl_close($ch);
        fclose($fp);

        // Verificamos que sea HTTP 200 y que el archivo pese al menos 100MB (evita guardar páginas HTML de error)
        if ($success && $http_code === 200 && file_exists($ruta_destino) && filesize($ruta_destino) > 104857600) {
            $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM modelos_ia WHERE nombre_archivo = 'Juggernaut_X_SDXL.safetensors'");
            $stmt_chk->execute();
            if ($stmt_chk->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso, activo) VALUES (?, ?, ?, ?, ?, 1)")
                    ->execute(['Juggernaut X (SDXL)', 'Juggernaut_X_SDXL.safetensors', 'comfyui', 'Checkpoints', 'usuario']);
            }
            echo json_encode(['success' => true]);
        } else {
            @unlink($ruta_destino);
            echo json_encode(['error' => __('err_civitai_download') . " (HTTP $http_code)"]);
        }
        exit();
    }
}

if ($action === 'leer_progreso_descarga') {
    $progreso_file = __DIR__ . '/../progreso_descarga.txt';
    $porcentaje = file_exists($progreso_file) ? file_get_contents($progreso_file) : "0";
    echo json_encode(['porcentaje' => $porcentaje]);
    exit();
}

if ($action === 'get_modelos_bd') {
    try { echo json_encode(['modelos' => $pdo->query("SELECT * FROM modelos_ia ORDER BY motor, categoria, nombre_visual")->fetchAll(PDO::FETCH_ASSOC)]); } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'get_active_models') {
    try {
        if ($is_admin) $stmt = $pdo->query("SELECT id, nombre_visual, nombre_archivo, motor, categoria, default_steps, default_cfg, default_sampler, default_scheduler, default_denoise, keep_alive, default_negative, limites_frames FROM modelos_ia WHERE activo = 1 ORDER BY nombre_visual ASC");
        elseif ($is_pro) $stmt = $pdo->query("SELECT id, nombre_visual, nombre_archivo, motor, categoria, default_steps, default_cfg, default_sampler, default_scheduler, default_denoise, keep_alive, default_negative, limites_frames FROM modelos_ia WHERE activo = 1 AND nivel_acceso IN ('usuario', 'avanzado') ORDER BY nombre_visual ASC");
        else $stmt = $pdo->query("SELECT id, nombre_visual, nombre_archivo, motor, categoria, default_steps, default_cfg, default_sampler, default_scheduler, default_denoise, keep_alive, default_negative, limites_frames FROM modelos_ia WHERE activo = 1 AND nivel_acceso = 'usuario' ORDER BY nombre_visual ASC");
        echo json_encode(['modelos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'save_modelo_bd') {
    try {
        // Aseguramos que la columna civitai_hash exista en la tabla
/*        try { $pdo->query("SELECT civitai_hash FROM modelos_ia LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE modelos_ia ADD COLUMN civitai_hash VARCHAR(20) DEFAULT NULL"); }
		try { $pdo->query("SELECT limites_frames FROM modelos_ia LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE modelos_ia ADD COLUMN limites_frames VARCHAR(50) DEFAULT NULL"); }
*/
        $es_unbundled = isset($_POST['es_unbundled']) ? intval($_POST['es_unbundled']) : 0;
        
        // El usuario manda. Si viene vacío, guardamos explícitamente NULL.
        $d_steps = (isset($_POST['default_steps']) && is_numeric($_POST['default_steps'])) ? intval($_POST['default_steps']) : null;
        $d_cfg = (isset($_POST['default_cfg']) && is_numeric($_POST['default_cfg'])) ? floatval($_POST['default_cfg']) : null;
        $d_sampler = !empty($_POST['default_sampler']) ? $_POST['default_sampler'] : null;
        $d_scheduler = !empty($_POST['default_scheduler']) ? $_POST['default_scheduler'] : null;
        $d_denoise = (isset($_POST['default_denoise']) && $_POST['default_denoise'] !== '') ? floatval($_POST['default_denoise']) : null;
        $keep_alive = (isset($_POST['keep_alive']) && trim($_POST['keep_alive']) !== '') ? trim($_POST['keep_alive']) : null;
        
        $tags_uso = $_POST['tags_uso'] ?? ''; 
        $reglas_arquitecto = $_POST['reglas_arquitecto'] ?? '';
        $d_negative = $_POST['default_negative'] ?? '';
        
        // NUEVAS VARIABLES DE ARQUITECTURA
        $vae_requerido = (isset($_POST['vae_requerido']) && trim($_POST['vae_requerido']) !== '') ? trim($_POST['vae_requerido']) : null;
        $text_encoders = (isset($_POST['text_encoders']) && trim($_POST['text_encoders']) !== '') ? trim($_POST['text_encoders']) : null;
		
		// 👇 AÑADE ESTA LÍNEA AQUÍ 👇
        $limites_frames = (isset($_POST['limites_frames']) && trim($_POST['limites_frames']) !== '') ? trim($_POST['limites_frames']) : null;

        // 🌟 CÁLCULO AUTOMÁTICO DEL HASH AUTOV2 PARA CIVITAI
		// 🌟 HASH CIVITAI: Si el usuario lo puso a mano, mandamos el suyo; si está vacío, lo calculamos
		$hash_manual = isset($_POST['civitai_hash']) ? strtolower(preg_replace('/[^a-fA-F0-9]/', '', $_POST['civitai_hash'])) : '';
		if (!empty($hash_manual)) {
		  $civitai_hash = substr($hash_manual, 0, 10);
		} else {
          $civitai_hash = calcularHashAutoV2Modelo($_POST['nombre_archivo'] ?? '', $_POST['motor'] ?? 'comfyui');
		}
		
		$pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso, es_unbundled, default_steps, default_cfg, default_sampler, default_scheduler, default_denoise, keep_alive, tags_uso, reglas_arquitecto, default_negative, vae_requerido, text_encoders, civitai_hash, limites_frames) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
			->execute([
				$_POST['nombre_visual'], 
				$_POST['nombre_archivo'], 
				$_POST['motor'], 
				$_POST['categoria'], 
				$_POST['nivel_acceso'], 
				$es_unbundled, 
				$d_steps, 
				$d_cfg, 
				$d_sampler, 
				$d_scheduler, 
				$d_denoise, 
				$keep_alive, 
				$tags_uso, 
				$reglas_arquitecto, 
				$d_negative, 
				$vae_requerido, 
				$text_encoders, 
				$civitai_hash, 
				$limites_frames
			]);		
	    echo json_encode(['success' => true, 'civitai_hash' => $civitai_hash]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'update_modelo_bd') {
    try {
        //try { $pdo->query("SELECT civitai_hash FROM modelos_ia LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE modelos_ia ADD COLUMN civitai_hash VARCHAR(20) DEFAULT NULL"); }

        $id_mod = intval($_POST['id'] ?? 0);
        $es_unbundled = isset($_POST['es_unbundled']) ? intval($_POST['es_unbundled']) : 0;
        
        $d_steps = (isset($_POST['default_steps']) && is_numeric($_POST['default_steps'])) ? intval($_POST['default_steps']) : null;
        $d_cfg = (isset($_POST['default_cfg']) && is_numeric($_POST['default_cfg'])) ? floatval($_POST['default_cfg']) : null;
        $d_sampler = !empty($_POST['default_sampler']) ? $_POST['default_sampler'] : null;
        $d_scheduler = !empty($_POST['default_scheduler']) ? $_POST['default_scheduler'] : null;
        $d_denoise = (isset($_POST['default_denoise']) && $_POST['default_denoise'] !== '') ? floatval($_POST['default_denoise']) : null;
        $keep_alive = (isset($_POST['keep_alive']) && trim($_POST['keep_alive']) !== '') ? trim($_POST['keep_alive']) : null;

        $tags_uso = $_POST['tags_uso'] ?? '';
        $reglas_arquitecto = $_POST['reglas_arquitecto'] ?? '';
        $d_negative = $_POST['default_negative'] ?? '';
        
        // NUEVAS VARIABLES DE ARQUITECTURA
        $vae_requerido = (isset($_POST['vae_requerido']) && trim($_POST['vae_requerido']) !== '') ? trim($_POST['vae_requerido']) : null;
        $text_encoders = (isset($_POST['text_encoders']) && trim($_POST['text_encoders']) !== '') ? trim($_POST['text_encoders']) : null;
		
		// 👇 AÑADE ESTA LÍNEA AQUÍ 👇
        $limites_frames = (isset($_POST['limites_frames']) && trim($_POST['limites_frames']) !== '') ? trim($_POST['limites_frames']) : null;

        // 🌟 HASH CIVITAI: Prioridad al valor manual; si se deja vacío, calcula automáticamente del disco
        $archivo_nuevo = trim($_POST['nombre_archivo'] ?? '');
        $hash_manual = isset($_POST['civitai_hash']) ? strtolower(preg_replace('/[^a-fA-F0-9]/', '', $_POST['civitai_hash'])) : '';

        if (!empty($hash_manual)) {
            $civitai_hash = substr($hash_manual, 0, 10);
        } else {
            $civitai_hash = calcularHashAutoV2Modelo($archivo_nuevo, $_POST['motor'] ?? 'comfyui');
        }

        $pdo->prepare("UPDATE modelos_ia SET nombre_visual = ?, nombre_archivo = ?, motor = ?, categoria = ?, nivel_acceso = ?, es_unbundled = ?, default_steps = ?, default_cfg = ?, default_sampler = ?, default_scheduler = ?, default_denoise = ?, keep_alive = ?, tags_uso = ?, reglas_arquitecto = ?, default_negative = ?, vae_requerido = ?, text_encoders = ?, civitai_hash = ?, limites_frames = ? WHERE id = ?")
			->execute([
				$_POST['nombre_visual'], 
				$archivo_nuevo, 
				$_POST['motor'], 
				$_POST['categoria'], 
				$_POST['nivel_acceso'], 
				$es_unbundled, 
				$d_steps, 
				$d_cfg, 
				$d_sampler, 
				$d_scheduler, 
				$d_denoise, 
				$keep_alive, 
				$tags_uso, 
				$reglas_arquitecto, 
				$d_negative, 
				$vae_requerido, 
				$text_encoders, 
				$civitai_hash, 
				$limites_frames, 
				$id_mod
			]);	
        echo json_encode(['success' => true, 'civitai_hash' => $civitai_hash]);
    } catch (Exception $e) { 
        echo json_encode(['error' => $e->getMessage()]); 
    }
    exit();
}

if ($action === 'delete_modelo_bd') {
    try {
        $id = intval($_POST['id'] ?? 0);
        $borrar_fisico = isset($_POST['borrar_fisico']) && $_POST['borrar_fisico'] === '1';

        if ($id > 0) {
            // 1. Si el usuario marcó borrar del disco, lo hacemos ANTES de borrar el registro
            if ($borrar_fisico) {
                // ⚠️ IMPORTANTE: Añadimos 'motor' a la consulta SQL para saber a quién llamar
                $stmt = $pdo->prepare("SELECT nombre_archivo, motor FROM modelos_ia WHERE id = ?");
                $stmt->execute([$id]);
                $modelo = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($modelo && !empty($modelo['nombre_archivo'])) {
                    
                    // --- A) BORRADO FÍSICO PARA OLLAMA (Vía API) ---
                    if ($modelo['motor'] === 'ollama') {
                        $ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/delete");
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                        // Ollama requiere que le enviemos el nombre del modelo en JSON
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["name" => $modelo['nombre_archivo']]));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Le damos 30s por si tiene que borrar 20GB de disco
                        curl_exec($ch);
                        //curl_close($ch);
                    } 
                    // --- B) BORRADO FÍSICO PARA COMFYUI (Papelera de Windows) ---
                    else {
                        $base_dir = defined('COMFY_MODELS_DIR') ? rtrim(COMFY_MODELS_DIR, '/\\') : 'C:/ComfyUI/models';
                        $nombre_archivo = ltrim($modelo['nombre_archivo'], '/\\');
                        
                        // Escaneamos las rutas probables
                        $posibles_rutas = [
                            $base_dir . DIRECTORY_SEPARATOR . 'checkpoints' . DIRECTORY_SEPARATOR . $nombre_archivo,
                            $base_dir . DIRECTORY_SEPARATOR . 'unet' . DIRECTORY_SEPARATOR . $nombre_archivo,
                            $base_dir . DIRECTORY_SEPARATOR . 'loras' . DIRECTORY_SEPARATOR . $nombre_archivo,
                            $base_dir . DIRECTORY_SEPARATOR . $nombre_archivo
                        ];

                        foreach ($posibles_rutas as $ruta) {
                            if (file_exists($ruta)) {
                                // MAGIA: Enviamos a la Papelera de Windows vía PowerShell
                                $ruta_win = str_replace('/', '\\', $ruta);
                                $ruta_ps = str_replace("'", "''", $ruta_win); 
                                
                                $ps_cmd = "powershell.exe -NoProfile -Command \"Add-Type -AssemblyName Microsoft.VisualBasic; [Microsoft.VisualBasic.FileIO.FileSystem]::DeleteFile('{$ruta_ps}', 'OnlyErrorDialogs', 'SendToRecycleBin')\"";
                                exec($ps_cmd);
                                
                                // Paracaídas de emergencia por si PowerShell falla
                                if (file_exists($ruta)) {
                                    @unlink($ruta); 
                                }
                                break;
                            }
                        }
                    }
                }
            }

            // 2. Borrado estándar de la Base de Datos
            $pdo->prepare("DELETE FROM modelos_ia WHERE id = ?")->execute([$id]); 
            echo json_encode(['success' => true]); 
        } else {
            echo json_encode(['error' => 'ID de modelo no válido.']);
        }
    } catch (Exception $e) { 
        echo json_encode(['error' => $e->getMessage()]); 
    }
    exit();
}

if ($action === 'toggle_modelo_bd') {
    try { $pdo->prepare("UPDATE modelos_ia SET activo = ? WHERE id = ?")->execute([$_POST['estado'], $_POST['id']]); echo json_encode(['success' => true]); } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'duplicar_prompt') {
    try {
        $pdo->prepare("INSERT INTO personalidades_prompts (titulo, tipo, idioma, parametros, prompt_texto, activo) SELECT CONCAT(titulo, ' (Copia)'), tipo, idioma, parametros, prompt_texto, 0 FROM personalidades_prompts WHERE id = ?")->execute([$_POST['id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'get_prompts_bd') {
    try { echo json_encode(['prompts' => $pdo->query("SELECT id, tipo, idioma, titulo, activo, parametros, prompt_texto FROM personalidades_prompts ORDER BY tipo, idioma, titulo")->fetchAll(PDO::FETCH_ASSOC)]); } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'save_prompt_bd') {
    // --- FRENO DE SEGURIDAD PARA USUARIOS FREE ---
    if (!$is_pro && in_array($_POST['tipo'], ['seed_video', 'chat_personality', 'estilo_flux', 'estilo_video'])) {
        echo json_encode(['error' => __('err_pro_only') ?? 'Esta función es exclusiva para la versión Pro.']);
        exit();
    }
    // ---------------------------------------------
    
    try {
        try { $pdo->query("SELECT parametros FROM personalidades_prompts LIMIT 1"); } catch (Exception $e) { $pdo->exec("ALTER TABLE personalidades_prompts ADD COLUMN parametros TEXT DEFAULT NULL AFTER idioma"); }
        $pdo->prepare("INSERT INTO personalidades_prompts (titulo, tipo, idioma, parametros, prompt_texto, activo) VALUES (?, ?, ?, ?, ?, 1)")->execute([$_POST['titulo'], $_POST['tipo'], $_POST['idioma'], $_POST['parametros'], $_POST['prompt_texto']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'update_prompt_bd') {
    // --- FRENO DE SEGURIDAD PARA USUARIOS FREE ---
    if (!$is_pro && in_array($_POST['tipo'], ['seed_video', 'chat_personality', 'estilo_flux', 'estilo_video'])) {
        echo json_encode(['error' => __('err_pro_only') ?? 'Esta función es exclusiva para la versión Pro.']);
        exit();
    }
    // ---------------------------------------------
    
    try {
        $pdo->prepare("UPDATE personalidades_prompts SET tipo = ?, idioma = ?, titulo = ?, parametros = ?, prompt_texto = ? WHERE id = ?")->execute([$_POST['tipo'], $_POST['idioma'], $_POST['titulo'], $_POST['parametros'], $_POST['prompt_texto'], $_POST['id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'delete_prompt_bd') {
    $pdo->prepare("DELETE FROM personalidades_prompts WHERE id = ?")->execute([$_POST['id']]); echo json_encode(['success' => true]); exit();
}

if ($action === 'toggle_prompt_bd') {
    $pdo->prepare("UPDATE personalidades_prompts SET activo = ? WHERE id = ?")->execute([$_POST['estado'], $_POST['id']]); echo json_encode(['success' => true]); exit();
}

if ($action === 'admin_get_idiomas') {
    $lang_dir = __DIR__ . '/../lang/';
    $idiomas = [];
    if (is_dir($lang_dir)) {
        $archivos = glob($lang_dir . '*.php');
        foreach ($archivos as $archivo) { $idiomas[] = basename($archivo, '.php'); }
    }
    $nombres_meta = []; $json_path = $lang_dir . 'idiomas_meta.json';
    if (file_exists($json_path)) { $nombres_meta = json_decode(file_get_contents($json_path), true) ?? []; }
    echo json_encode(['success' => true, 'idiomas' => $idiomas, 'nombres_meta' => $nombres_meta]);
    exit();
}

if ($action === 'admin_leer_idioma') {
    if (!$is_admin) { echo json_encode(['error' => __('err_admin_only_lang')]); exit(); }
    $lang_code = preg_replace('/[^a-zA-Z]/', '', $_POST['lang_code']);
    $archivo = __DIR__ . "/../lang/{$lang_code}.php";
    if (file_exists($archivo)) {
        $contenido = file_get_contents($archivo);
        $nombre_actual = ''; $json_path = __DIR__ . '/../lang/idiomas_meta.json';
        if (file_exists($json_path)) {
            $meta = json_decode(file_get_contents($json_path), true);
            if (isset($meta[$lang_code])) { $nombre_actual = $meta[$lang_code]; }
        }
        echo json_encode(['success' => true, 'contenido' => $contenido, 'lang_name' => $nombre_actual]);
    } else { echo json_encode(['error' => __('err_read_lang_file')]); }
    exit();
}

if ($action === 'admin_guardar_idioma') {
    if (!$is_admin) { echo json_encode(['error' => __('err_admin_only_lang')]); exit(); }
    $lang_code = preg_replace('/[^a-zA-Z]/', '', $_POST['lang_code']);
    $contenido = $_POST['contenido'];
    $archivo = __DIR__ . "/../lang/{$lang_code}.php";
    if (strpos(trim($contenido), '<?php') !== 0) { echo json_encode(['error' => __('err_php_tag_missing')]); exit(); }

    if (file_put_contents($archivo, $contenido) !== false) {
        $nombre_legible = trim($_POST['lang_name'] ?? '');
        if (!empty($nombre_legible)) {
            $json_path = __DIR__ . '/../lang/idiomas_meta.json';
            $idiomas_meta = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];
            if (!is_array($idiomas_meta)) $idiomas_meta = []; 
            $idiomas_meta[strtolower($lang_code)] = $nombre_legible;
            file_put_contents($json_path, json_encode($idiomas_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        echo json_encode(['success' => true]);
    } else { echo json_encode(['error' => __('err_write_lang_file')]); }
    exit();
}

// ==============================================================================
// --- ACCIÓN: COMPROBAR ACTUALIZACIONES (CHIVATO SILENCIOSO) ---
// ==============================================================================
if ($action === 'check_update') {
    @ini_set('display_errors', 0);
    ob_start();
    header('Content-Type: application/json');
    
    // 1. URL "raw" de tu archivo version.json en GitHub (rama main)
    $url_remote_meta = "https://raw.githubusercontent.com/Gartyl/Gartys-Architect/main/version.json";
    
    // 2. Consultamos con un timeout corto (3 segundos)
    $ch = curl_init($url_remote_meta);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Gartys-Architect-Updater)');
    $remote_data = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_status === 200 && !empty($remote_data)) {
        $meta = json_decode($remote_data, true);
        
        if ($meta && isset($meta['version'])) {
            // Leemos la versión local priorizando version.json si existe, con fallback a APP_VERSION
            $local_ver_raw = defined('APP_VERSION') ? APP_VERSION : '1.0.0';
            $local_json_path = __DIR__ . '/../version.json';
            if (file_exists($local_json_path)) {
                $local_meta = json_decode(@file_get_contents($local_json_path), true);
                if (!empty($local_meta['version'])) {
                    $local_ver_raw = $local_meta['version'];
                }
            }

            $local_ver_clean  = preg_replace('/[^0-9.]/', '', $local_ver_raw);
            $remote_ver_clean = preg_replace('/[^0-9.]/', '', $meta['version']);
            
            $hay_actualizacion = version_compare($local_ver_clean, $remote_ver_clean, '<');
            
            ob_clean();
            echo json_encode([
                'update_available' => $hay_actualizacion,
                'current_version'  => $local_ver_raw,
                'remote_version'   => $meta['version'],
                'changelog'        => $meta['changelog'] ?? '',
                'zip_url'          => $meta['zip_url'] ?? ''
            ]);
            exit();
        }
    }
    
    ob_clean();
    echo json_encode(['update_available' => false]);
    exit();
}

// ==============================================================================
// --- ACCIÓN: DESCARGAR E INSTALAR ACTUALIZACIÓN AUTOMÁTICA (ZIP) ---
// ==============================================================================
if ($action === 'instalar_actualizacion_zip') {
    @ini_set('display_errors', 0);
    @ini_set('memory_limit', '512M');
    ob_start();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    @set_time_limit(0);
    header('Content-Type: application/json');
    
    try {
        if (isset($is_admin) && !$is_admin && defined('APP_MODE') && APP_MODE === 'servidor') {
            $err_unauth = (function_exists('__') ? __('err_unauthorized') : null) ?: 'No tienes permisos para realizar esta acción.';
            ob_clean();
            echo json_encode(['error' => $err_unauth]);
            exit();
        }
        
        // Para actualizaciones de código en caliente, el ZIP ligero de la rama main es siempre el más seguro y rápido
        $url_zip = trim($_POST['zip_url'] ?? '');
        if (empty($url_zip)) {
            $url_zip = "https://github.com/Gartyl/Gartys-Architect/archive/refs/heads/main.zip";
        }
        
        $archivo_temporal = __DIR__ . '/../temp_update.zip';
        $ruta_destino = realpath(__DIR__ . '/../');
        
        // 1. DESCARGAMOS EL ARCHIVO ZIP DESDE GITHUB
        $fp = @fopen($archivo_temporal, 'w+');
        if (!$fp) {
            $err_write = (function_exists('__') ? __('err_temp_file_write') : null) ?: 'No se tiene permiso de escritura para crear el archivo temporal en el servidor.';
            ob_clean();
            echo json_encode(['error' => $err_write]);
            exit();
        }
        
        $ch = curl_init($url_zip);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Gartys-Architect-Updater)');
        $success_dl = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        fclose($fp);
        
        if (!$success_dl || $http_status !== 200 || !file_exists($archivo_temporal) || filesize($archivo_temporal) < 1000) {
            @unlink($archivo_temporal);
            $err_dl = (function_exists('__') ? __('err_download_update') : null) ?: 'No se pudo descargar el paquete de actualización.';
            ob_clean();
            echo json_encode(['error' => $err_dl . " (HTTP $http_status $curl_err)"]);
            exit();
        }

        // --- LISTA NEGRA: Archivos y carpetas INTOCABLES (Usuario + Binarios FrankenPHP) ---
        $protegidos = [
            'config.php',
            'database.sqlite',
            'ia_prompts.sqlite',
            'ia_prompts.db'
        ];

        $esRutaProtegida = function($ruta_rel) use ($protegidos) {
            $ruta_rel = ltrim(str_replace('\\', '/', $ruta_rel), '/');
            $nombre_archivo = basename($ruta_rel);
            $ext = strtolower(pathinfo($nombre_archivo, PATHINFO_EXTENSION));

           // Jamás tocar binarios en ejecución (.exe / .dll), bases de datos locales ni archivos de configuración
			if (in_array($ext, ['exe', 'dll', 'sqlite', 'sqlite3', 'db']) || in_array($nombre_archivo, $protegidos)) {
				return true;
			}
            if (strpos($ruta_rel, 'data/') === 0 || 
                strpos($ruta_rel, 'galeria/') === 0 || 
                strpos($ruta_rel, 'voces/') === 0 || 
                strpos($ruta_rel, 'system/') === 0 ||
                strpos($ruta_rel, '.git/') === 0) {
                return true;
            }
            return false;
        };

        // Helper para construir el mensaje de éxito sin riesgo de Fatal Error en __() o sprintf()
        $construirMensajeExito = function($cantidad) {
            $plantilla = function_exists('__') ? __('msg_update_success_count') : null;
            if (empty($plantilla) || $plantilla === 'msg_update_success_count' || strpos($plantilla, '%') === false) {
                $plantilla = "¡Actualización completada! (%s archivos actualizados correctamente).";
            }
            return @sprintf($plantilla, $cantidad);
        };

        $archivos_actualizados = 0;

        // 2A. MÉTODO 1: ZipArchive nativo de PHP (si está compilado en PHP)
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($archivo_temporal) === TRUE) {
                
                // Detectamos si el ZIP viene todo dentro de una carpeta raíz (ej. "Gartys-Architect-main/")
                $tiene_carpeta_raiz = true;
                $primera_carpeta = null;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = str_replace('\\', '/', $zip->getNameIndex($i));
                    $partes = explode('/', $entry, 2);
                    if (count($partes) < 2) {
                        $tiene_carpeta_raiz = false;
                        break;
                    }
                    if ($primera_carpeta === null) {
                        $primera_carpeta = $partes[0];
                    } elseif ($primera_carpeta !== $partes[0]) {
                        $tiene_carpeta_raiz = false;
                        break;
                    }
                }

                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $nombre_en_zip = str_replace('\\', '/', $zip->getNameIndex($i));
                    
                    if ($tiene_carpeta_raiz) {
                        $partes = explode('/', $nombre_en_zip, 2);
                        if (count($partes) < 2 || empty($partes[1])) continue;
                        $ruta_relativa = $partes[1];
                    } else {
                        $ruta_relativa = $nombre_en_zip;
                    }
                    
                    if (substr($ruta_relativa, -1) === '/') {
                        if (!$esRutaProtegida($ruta_relativa) && !is_dir($ruta_destino . '/' . $ruta_relativa)) {
                            @mkdir($ruta_destino . '/' . $ruta_relativa, 0777, true);
                        }
                        continue;
                    }
                    
                    if ($esRutaProtegida($ruta_relativa)) continue;
                    
                    $contenido = $zip->getFromIndex($i);
                    if ($contenido !== false) {
                        $archivo_final = $ruta_destino . '/' . $ruta_relativa;
                        
                        // Solo sobrescribimos si el archivo no existe o su contenido ha cambiado realmente
                        if (file_exists($archivo_final) && @md5_file($archivo_final) === md5($contenido)) {
                            continue;
                        }

                        $directorio_padre = dirname($archivo_final);
                        if (!is_dir($directorio_padre)) {
                            @mkdir($directorio_padre, 0777, true);
                        }
                        if (@file_put_contents($archivo_final, $contenido) !== false) {
                            $archivos_actualizados++;
                        }
                    }
                }
                $zip->close();
                @unlink($archivo_temporal);

                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'mensaje' => $construirMensajeExito($archivos_actualizados)
                ]);
                exit();
            }
        }

        // 2B. MÉTODO 2 (FALLBACK BLINDADO PARA FRANKENPHP SIN EXTENSIÓN ZIP): Usamos tar nativo de Windows
        $temp_extract_dir = sys_get_temp_dir() . '/garty_upd_' . uniqid();
        @mkdir($temp_extract_dir, 0777, true);

        $cmd = "tar -xf " . escapeshellarg($archivo_temporal) . " -C " . escapeshellarg($temp_extract_dir) . " 2>&1";
        @shell_exec($cmd);
        @unlink($archivo_temporal);

        // Comprobamos si hay una única subcarpeta raíz dentro de la extracción (ej. Gartys-Architect-main)
        $items_extraidos = array_diff(@scandir($temp_extract_dir) ?: [], ['.', '..']);
        $origen_base = $temp_extract_dir;
        if (count($items_extraidos) === 1) {
            $unico_item = reset($items_extraidos);
            if (is_dir($temp_extract_dir . '/' . $unico_item)) {
                $origen_base = $temp_extract_dir . '/' . $unico_item;
            }
        }

        if (is_dir($origen_base) && count(array_diff(@scandir($origen_base) ?: [], ['.', '..'])) > 0) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($origen_base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $subPath = str_replace('\\', '/', substr($item->getPathname(), strlen($origen_base) + 1));
                if ($esRutaProtegida($subPath)) continue;

                $targetPath = $ruta_destino . '/' . $subPath;
                if ($item->isDir()) {
                    if (!is_dir($targetPath)) @mkdir($targetPath, 0777, true);
                } else {
                    // Solo copiamos si el archivo no existe o su contenido ha cambiado realmente
                    if (file_exists($targetPath) && @md5_file($targetPath) === @md5_file($item->getPathname())) {
                        continue;
                    }

                    $parentDir = dirname($targetPath);
                    if (!is_dir($parentDir)) @mkdir($parentDir, 0777, true);
                    if (@copy($item->getPathname(), $targetPath)) {
                        $archivos_actualizados++;
                    }
                }
            }

            // Limpieza de carpeta temporal
            $cleanup = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($temp_extract_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($cleanup as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($temp_extract_dir);

            ob_clean();
            echo json_encode([
                'success' => true, 
                'mensaje' => $construirMensajeExito($archivos_actualizados)
            ]);
            exit();
        }

        $err_unzip = (function_exists('__') ? __('err_unzip_fail') : null) ?: 'Error al abrir o descomprimir el archivo ZIP descargado.';
        ob_clean();
        echo json_encode(['error' => $err_unzip]);
        exit();

    } catch (\Throwable $e) {
        if (isset($archivo_temporal) && file_exists($archivo_temporal)) {
            @unlink($archivo_temporal);
        }
        ob_clean();
        echo json_encode(['error' => 'Error interno al actualizar: ' . $e->getMessage() . ' (Línea ' . $e->getLine() . ')']);
        exit();
    }
}
?>