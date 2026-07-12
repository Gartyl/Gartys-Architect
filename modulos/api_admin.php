<?php
// --- DEFINICIÓN DE SEGURIDAD PARA $action ---
// Si llamamos directamente al archivo, recogemos la acción por GET o POST para evitar errores.
if (!isset($action)) {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
}

// Y por si acaso no estuviera cargada la configuración local (para poder leer APP_VERSION):
if (!defined('APP_VERSION')) {
    require_once __DIR__ . '/../config.php';
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
            $stmt = $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso) VALUES (?, ?, ?, ?, ?)");
            $subcarpeta = (strpos($categoria_dropdown, 'unet') !== false) ? 'unet/' : '';
            $nombre_para_bd = $subcarpeta . $nombre_archivo_seguro;
            $stmt->execute([$nombre_visual, $nombre_para_bd, $db_motor, $db_cat, 'usuario']);
            echo json_encode(['success' => true, 'mensaje' => __('msg_model_downloaded')]);
        } catch (Exception $e) {
            echo json_encode(['error' => __('err_db_register') . ' ' . $e->getMessage()]);
        }
    }
    exit();
}

if ($action === 'ping_servicios') {
    $ch_ollama = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/tags");
    curl_setopt($ch_ollama, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_ollama, CURLOPT_TIMEOUT, 2);
    $res_ollama = curl_exec($ch_ollama); $ollama_ok = ($res_ollama !== false);

    $ch_comfy = curl_init(COMFY_URL . "/system_stats");
    curl_setopt($ch_comfy, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch_comfy, CURLOPT_TIMEOUT, 2);
    $res_comfy = curl_exec($ch_comfy); $comfy_ok = ($res_comfy !== false);

    echo json_encode(['ollama' => $ollama_ok, 'comfy' => $comfy_ok]);
    exit();
}

if ($action === 'instalar_basico') {
    $tipo = $_POST['tipo'] ?? '';
    if ($tipo === 'llama3') {
        $data = ["name" => "llama3"];
        $ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/pull");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        $res = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code === 200) {
            $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso) VALUES (?, ?, ?, ?, ?)")->execute(['Cerebro Llama 3', 'llama3:latest', 'ollama', 'SYS_LLM', 'usuario']);
            echo json_encode(['success' => true]);
        } else { echo json_encode(['error' => __('err_ollama_download')]); }
        exit();
    }
    if ($tipo === 'sdxl') {
        $url_civitai = "https://civitai.com/api/download/models/782002";
        $base_dir = defined('COMFY_MODELS_DIR') ? rtrim(COMFY_MODELS_DIR, '/\\') : 'C:/ComfyUI/models';
        $ruta_destino = $base_dir . '/checkpoints/Juggernaut_X_SDXL.safetensors';
        $fp = fopen($ruta_destino, 'w+');
        $ch = curl_init($url_civitai); curl_setopt($ch, CURLOPT_FILE, $fp); curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . API_KEY]); curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
        $success = curl_exec($ch); curl_close($ch); fclose($fp);
        if ($success) {
            $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso) VALUES (?, ?, ?, ?, ?)")->execute(['Juggernaut X (SDXL)', 'Juggernaut_X_SDXL.safetensors', 'comfyui', 'Checkpoints', 'usuario']);
            echo json_encode(['success' => true]);
        } else { @unlink($ruta_destino); echo json_encode(['error' => __('err_civitai_download')]); }
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
        if ($is_admin) $stmt = $pdo->query("SELECT id, nombre_visual, motor, categoria FROM modelos_ia WHERE activo = 1 ORDER BY nombre_visual ASC");
        elseif ($is_pro) $stmt = $pdo->query("SELECT id, nombre_visual, motor, categoria FROM modelos_ia WHERE activo = 1 AND nivel_acceso IN ('usuario', 'avanzado') ORDER BY nombre_visual ASC");
        else $stmt = $pdo->query("SELECT id, nombre_visual, motor, categoria FROM modelos_ia WHERE activo = 1 AND nivel_acceso = 'usuario' ORDER BY nombre_visual ASC");
        echo json_encode(['modelos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'save_modelo_bd') {
    try {
        $pdo->prepare("INSERT INTO modelos_ia (nombre_visual, nombre_archivo, motor, categoria, nivel_acceso) VALUES (?, ?, ?, ?, ?)")->execute([$_POST['nombre_visual'], $_POST['nombre_archivo'], $_POST['motor'], $_POST['categoria'], $_POST['nivel_acceso']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit();
}

if ($action === 'delete_modelo_bd') {
    try { $pdo->prepare("DELETE FROM modelos_ia WHERE id = ?")->execute([$_POST['id']]); echo json_encode(['success' => true]); } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
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
        $pdo->prepare("INSERT INTO personalidades_prompts (titulo, tipo, idioma, parametros, prompt_texto) VALUES (?, ?, ?, ?, ?)")->execute([$_POST['titulo'], $_POST['tipo'], $_POST['idioma'], $_POST['parametros'], $_POST['prompt_texto']]);
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
    header('Content-Type: application/json');
    
    // 1. URL "raw" de tu archivo version.json en GitHub (rama main o master)
    // Cambia 'Gartyl/Gartys-Architect' si el nombre del repositorio o rama es diferente
	$url_remote_meta = "https://raw.githubusercontent.com/Gartyl/Gartys-Architect/main/version.json";
    
    // 2. Consultamos con un timeout muy corto (3 segundos) para no frenar la app en entornos offline
    $ch = curl_init($url_remote_meta);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $remote_data = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_status === 200 && !empty($remote_data)) {
        $meta = json_decode($remote_data, true);
        
        if ($meta && isset($meta['version'])) {
            // Limpiamos los textos como "- R1" para quedarnos con una versión numérica comparable (ej: "1.0.0")
            $local_ver_clean = preg_replace('/[^0-9.]/', '', APP_VERSION);
            $remote_ver_clean = preg_replace('/[^0-9.]/', '', $meta['version']);
            
            // Evaluamos si la versión de internet es superior a la de config.sample.php / config.php
            $hay_actualizacion = version_compare($local_ver_clean, $remote_ver_clean, '<');
            
            echo json_encode([
                'update_available' => $hay_actualizacion,
                'current_version'  => APP_VERSION,
                'remote_version'   => $meta['version'],
                'changelog'        => $meta['changelog'] ?? '',
                'zip_url'          => $meta['zip_url'] ?? ''
            ]);
            exit();
        }
    }
    
    // Si no hay conexión o falla la lectura, devolvemos false silenciosamente
    echo json_encode(['update_available' => false]);
    exit();
}

// ==============================================================================
// --- ACCIÓN: DESCARGAR E INSTALAR ACTUALIZACIÓN AUTOMÁTICA (ZIP) ---
// ==============================================================================
if ($action === 'instalar_actualizacion_zip') {
    header('Content-Type: application/json');
    
    // Comprobación de seguridad (puedes adaptarlo si usas $is_admin en tu versión servidor)
    if (isset($is_admin) && !$is_admin && defined('APP_MODE') && APP_MODE === 'servidor') {
        echo json_encode(['error' => __('err_unauthorized') ?? 'No tienes permisos para realizar esta acción.']);
        exit();
    }
    
    $url_zip = trim($_POST['zip_url'] ?? '');
    if (empty($url_zip)) {
        // Por defecto usamos la rama main de GitHub si no llega la URL
        $url_zip = "https://github.com/Gartyl/Gartys-Architect/archive/refs/heads/main.zip";
    }
    
    // Rutas temporales y de destino
    $archivo_temporal = __DIR__ . '/../temp_update.zip';
    $ruta_destino = realpath(__DIR__ . '/../'); // La carpeta raíz de Garty's Architect
    
    // 1. DESCARGAMOS EL ARCHIVO ZIP DESDE GITHUB
    $fp = fopen($archivo_temporal, 'w+');
    if (!$fp) {
		echo json_encode(['error' => __('err_temp_file_write') ?? 'No se tiene permiso de escritura para crear el archivo temporal en el servidor.']);
        exit();
    }
    
    $ch = curl_init($url_zip);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos máximo
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Gartys-Architect-Updater)');
    curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);
    
    if ($http_status !== 200 || !file_exists($archivo_temporal)) {
        @unlink($archivo_temporal);
		echo json_encode(['error' => (__('err_download_update') ?? 'No se pudo descargar el paquete de actualización.') . " (HTTP $http_status)"]);
        exit();
    }

    // 2. DESCOMPRESIÓN BLINDADA (ESQUIVANDO DATOS DE USUARIO)
    $zip = new ZipArchive;
    if ($zip->open($archivo_temporal) === TRUE) {
        
        // --- LISTA NEGRA: Archivos y extensiones INTOCABLES ---
        $protegidos = [
            'config.php',
            'database.sqlite',
            'ia_prompts.sqlite',
            'ia_prompts.db'
        ];
        
        $archivos_actualizados = 0;
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre_en_zip = $zip->getNameIndex($i);
            
            // GitHub mete todo dentro de una carpeta principal tipo "Gartys-Architect-main/". La separamos:
            $partes = explode('/', $nombre_en_zip, 2);
            if (count($partes) < 2 || empty($partes[1])) continue; 
            
            $ruta_relativa = $partes[1];
            
            // Si el archivo termina en / es un directorio, lo creamos y seguimos
            if (substr($ruta_relativa, -1) === '/') {
                if (!is_dir($ruta_destino . '/' . $ruta_relativa)) {
                    @mkdir($ruta_destino . '/' . $ruta_relativa, 0777, true);
                }
                continue;
            }
            
            // Evaluamos el nombre del archivo
            $nombre_archivo = basename($ruta_relativa);
            
            // PROTECCIÓN: Si está en la lista negra o dentro de carpetas de usuario (/data/, /galeria/), LO SALTAMOS
            if (in_array($nombre_archivo, $protegidos) || 
                strpos($ruta_relativa, 'data/') === 0 || 
                strpos($ruta_relativa, 'galeria/') === 0) {
                continue; 
            }
            
            // Extraemos y sobreescribimos el fichero en el sistema local
            $contenido = $zip->getFromIndex($i);
            if ($contenido !== false) {
                // Si la subcarpeta donde va el archivo no existe, la creamos
                $directorio_padre = dirname($ruta_destino . '/' . $ruta_relativa);
                if (!is_dir($directorio_padre)) {
                    @mkdir($directorio_padre, 0777, true);
                }
                
                file_put_contents($ruta_destino . '/' . $ruta_relativa, $contenido);
                $archivos_actualizados++;
            }
        }
        
        $zip->close();
        @unlink($archivo_temporal); // Limpiamos el archivo ZIP temporal
        
       echo json_encode([
		'success' => true, 
		'mensaje' => sprintf(__('msg_update_success_count') ?? "¡Actualización completada! (%s archivos actualizados correctamente).", $archivos_actualizados)
	]);
    } else {
        @unlink($archivo_temporal);
		echo json_encode(['error' => __('err_unzip_fail') ?? 'Error al abrir o descomprimir el archivo ZIP descargado.']);
    }
    exit();
}
?>