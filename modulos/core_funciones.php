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
            // Reemplaza tanto si en el JSON el número lleva comillas ("__STEPS__") como si no las lleva (__STEPS__)
            $json_texto = str_replace('"' . $etiqueta . '"', $valor, $json_texto);
            $json_texto = str_replace($etiqueta, $valor, $json_texto);
        }
    }

    $workflow_decodificado = json_decode($json_texto, true);
    if (json_last_error() !== JSON_ERROR_NONE) die(json_encode(['error' => __('err_json_corrupted') . " " . json_last_error_msg()]));
    return $workflow_decodificado;
}

function process_dynamic_prompts($text, $depth = 0) {
    // 1. Conectamos ambas rutas: la local y la de ComfyUI
    $wildcards_dir_local = __DIR__ . '/../wildcards';
    $comfy_base = defined('COMFY_MODELS_DIR') ? dirname(rtrim(COMFY_MODELS_DIR, '/\\')) : 'C:/ComfyUI';
    $comfy_wildcards_dir = $comfy_base . '/custom_nodes/comfyui-dynamicprompts/wildcards';

    if ($depth > 10 || empty($text)) return $text; 

    // Procesar opciones en línea {opcion1|opcion2}
    $text = preg_replace_callback('/\{([^{}]*)\}/', function($matches) {
        $options = explode('|', $matches[1]);
        return trim($options[array_rand($options)]);
    }, $text);

    // Procesar wildcards externos
	if (preg_match_all('/__([a-zA-Z0-9_\-\/\\\]+)__/', $text, $matches)) {
        $wildcards_unicos = array_unique($matches[1]);
        
        foreach ($wildcards_unicos as $wildcard) {
            $valor_elegido = "__" . $wildcard . "__"; 
            $wildcard_limpio = str_replace('\\', '/', $wildcard);
            
            $partes_pedido = explode('/', $wildcard_limpio);
            $root_key = trim($partes_pedido[0]); 
            
            $rutas_busqueda = [];
            
            // A) Búsqueda por archivo directo
            $rutas_busqueda[] = $wildcards_dir_local . '/' . $wildcard_limpio . '.txt';
            $rutas_busqueda[] = $comfy_wildcards_dir . '/' . $wildcard_limpio . '.txt';
            $rutas_busqueda[] = $comfy_wildcards_dir . '/' . $wildcard_limpio . '.yaml';
            $rutas_busqueda[] = $comfy_wildcards_dir . '/' . $wildcard_limpio . '.yml';
            
            $ruta_actual_busq = $wildcard_limpio;
            while (strpos($ruta_actual_busq, '/') !== false) {
                $ruta_actual_busq = substr($ruta_actual_busq, 0, strrpos($ruta_actual_busq, '/'));
                $rutas_busqueda[] = $comfy_wildcards_dir . '/' . $ruta_actual_busq . '.yaml';
                $rutas_busqueda[] = $comfy_wildcards_dir . '/' . $ruta_actual_busq . '.yml';
                $rutas_busqueda[] = $wildcards_dir_local . '/' . $ruta_actual_busq . '.yaml';
                $rutas_busqueda[] = $wildcards_dir_local . '/' . $ruta_actual_busq . '.yml';
            }

            $filepath_encontrado = '';
            foreach ($rutas_busqueda as $ruta) {
                if (file_exists($ruta)) {
                    $filepath_encontrado = $ruta;
                    break;
                }
            }

            // B) ESCÁNER PROFUNDO RECURSIVO (Busca en TODAS las subcarpetas)
            if (empty($filepath_encontrado)) {
                $carpetas_escanear = [$comfy_wildcards_dir, $wildcards_dir_local];
                foreach ($carpetas_escanear as $carpeta) {
                    if (is_dir($carpeta)) {
                        try {
                            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta));
                            foreach ($iterator as $file) {
                                if ($file->isFile()) {
                                    $ext_arch = strtolower($file->getExtension());
                                    if ($ext_arch === 'yaml' || $ext_arch === 'yml') {
                                        $contenido_yaml = file_get_contents($file->getPathname());
                                        if (preg_match('/^' . preg_quote($root_key, '/') . '\s*:/m', $contenido_yaml)) {
                                            $filepath_encontrado = $file->getPathname();
                                            break 2; // Rompe el iterador y el foreach de carpetas
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) {}
                    }
                }
            }

            if (!empty($filepath_encontrado)) {
                $ext = strtolower(pathinfo($filepath_encontrado, PATHINFO_EXTENSION));
                $contenido = file_get_contents($filepath_encontrado);
                $opciones = [];

                if ($ext === 'txt') {
                    $lines = explode("\n", $contenido);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (!empty($line) && strpos($line, '#') !== 0) {
                            $opciones[] = $line;
                        }
                    }
                } elseif ($ext === 'yaml' || $ext === 'yml') {
                    // === PARSER INTELIGENTE DE YAML ===
                    array_shift($partes_pedido); 
                    
                    $lineas_yaml = explode("\n", $contenido);
                    $ruta_yaml_actual = [];
                    $niveles_indent = [];

                    foreach ($lineas_yaml as $linea) {
                        $linea_limpia = trim($linea);
                        if ($linea_limpia === '' || strpos($linea_limpia, '#') === 0 || strpos($linea_limpia, '---') === 0) continue;

                        preg_match('/^([ \t]*)/', $linea, $match_espacios);
                        $indent = strlen(str_replace("\t", "    ", $match_espacios[1]));

                        // Captura descripciones largas completas
                        if (preg_match('/^[ \t]*-\s*(.*)$/', $linea, $match_lista)) {
                            $valor = trim($match_lista[1], "\"' \t\r");
                            
                            // Filtra etiquetas mal formadas para que no rompan la lista
                            if (empty($valor) || strpos($valor, ':') === (strlen($valor) - 1)) continue;

                            $coincide = true;
                            if (!empty($partes_pedido)) {
                                $ruta_str = '/' . strtolower(implode('/', $ruta_yaml_actual)) . '/';
                                foreach ($partes_pedido as $parte) {
                                    if (strpos($ruta_str, '/' . strtolower($parte) . '/') === false) {
                                        $coincide = false;
                                        break;
                                    }
                                }
                            }
                            
                            if ($coincide) {
                                $opciones[] = $valor;
                            }
                        } 
                        // Captura cualquier etiqueta YAML (incluso con caracteres especiales como "&")
                        elseif (preg_match('/^[ \t]*([^-][^:]*):\s*$/', $linea, $match_clave)) {
                            $clave = trim($match_clave[1], "\"' \t\r");
                            
                            while (!empty($niveles_indent) && end($niveles_indent) >= $indent) {
                                array_pop($niveles_indent);
                                array_pop($ruta_yaml_actual);
                            }
                            
                            $niveles_indent[] = $indent;
                            $ruta_yaml_actual[] = $clave;
                        }
                    }
                }

                if (!empty($opciones)) {
                    $valor_elegido = $opciones[array_rand($opciones)];
                }
            }
            
            $text = str_replace("__" . $wildcard . "__", $valor_elegido, $text);
        }
    }
	
    if (preg_match('/\{.*?\}/', $text) || preg_match('/__.*?__/', $text)) {
        $text = process_dynamic_prompts($text, $depth + 1);
    }
    
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

// ==============================================================================
// --- MOTOR DE BÚSQUEDA SEMÁNTICA VECTORIAL (100% COMPATIBLE MYSQL + SQLITE) ---
// ==============================================================================

if (!function_exists('obtenerModeloEmbeddingActivo')) {
    function obtenerModeloEmbeddingActivo($pdo) {
        static $cached = false;
        static $modelo = null;
        if ($cached) return $modelo;
        $cached = true;
        try {
            $stmt = $pdo->query("SELECT nombre_archivo, keep_alive FROM modelos_ia WHERE motor = 'ollama' AND LOWER(categoria) = 'sys_embed' AND activo = 1 LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if ($row && !empty($row['nombre_archivo'])) {
                $modelo = $row;
            }
        } catch (Throwable $e) {}
        return $modelo;
    }
}

if (!function_exists('generarEmbeddingOllama')) {
    function generarEmbeddingOllama($texto, $pdo) {
        $mod = obtenerModeloEmbeddingActivo($pdo);
        if (!$mod || !defined('LLM_IP') || !defined('LLM_PORT')) return null;

        $texto = trim(preg_replace('/\s+/', ' ', (string)$texto));
        if ($texto === '') return null;
        $texto = mb_substr($texto, 0, 2500);

        $ka_raw = isset($mod['keep_alive']) && trim($mod['keep_alive']) !== '' ? trim($mod['keep_alive']) : '-1';
        if ($ka_raw === '0' || $ka_raw === 0) $ka_val = 0;
        elseif ($ka_raw === '-1' || $ka_raw === -1) $ka_val = -1;
        elseif (is_numeric($ka_raw)) $ka_val = $ka_raw . 'm';
        else $ka_val = $ka_raw;

        $payload = json_encode([
            'model' => $mod['nombre_archivo'],
            'prompt' => $texto,
            'keep_alive' => $ka_val
        ], JSON_INVALID_UTF8_SUBSTITUTE);

        $ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/embeddings");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        curl_close($ch);

        if (!$res) return null;
        $data = json_decode($res, true);
        $vec = $data['embedding'] ?? ($data['embeddings'][0] ?? null);
        if (!is_array($vec) || empty($vec)) return null;

        // Normalizamos el vector (norma L2 = 1) para que la Similitud del Coseno sea un producto escalar directo
        $norm = 0.0;
        foreach ($vec as $v) {
            $norm += $v * $v;
        }
        $norm = sqrt($norm);
        if ($norm <= 0) return null;

        $normalized = [];
        foreach ($vec as $v) {
            $normalized[] = (float)($v / $norm);
        }

        return pack('f*', ...$normalized);
    }
}

if (!function_exists('guardarEmbeddingHistorial')) {
    function guardarEmbeddingHistorial($id_historial, $pdo) {
        $id_historial = (int)$id_historial;
        if ($id_historial <= 0 || !obtenerModeloEmbeddingActivo($pdo)) return false;

        try {
            $stmt = $pdo->prepare("SELECT descripcion_original, prompt_positivo, texto_generado FROM historial_prompts WHERE id = ? LIMIT 1");
            $stmt->execute([$id_historial]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return false;

            $partes = [];
            if (!empty($row['descripcion_original'])) $partes[] = trim($row['descripcion_original']);
            if (!empty($row['prompt_positivo']) && $row['prompt_positivo'] !== $row['descripcion_original']) {
                $partes[] = trim($row['prompt_positivo']);
            }
            if (!empty($row['texto_generado'])) {
                $partes[] = mb_substr(trim($row['texto_generado']), 0, 600);
            }

            $texto_combinado = implode(" | ", $partes);
            $blob = generarEmbeddingOllama($texto_combinado, $pdo);
            if ($blob !== null) {
                $upd = $pdo->prepare("UPDATE historial_prompts SET embedding = ? WHERE id = ?");
                $upd->execute([$blob, $id_historial]);
                return true;
            }
        } catch (Throwable $e) {}
        return false;
    }
}

if (!function_exists('ejecutarBusquedaSemantica')) {
    function ejecutarBusquedaSemantica($search, $pdo, $where_clauses, $params, $es_galeria = false) {
        // 1. Auto-indexado progresivo de registros antiguos sin vector (máx. 25 por búsqueda)
        try {
            $stmt_null = $pdo->query("SELECT id FROM historial_prompts WHERE embedding IS NULL ORDER BY id DESC LIMIT 25");
            if ($stmt_null) {
                foreach ($stmt_null->fetchAll(PDO::FETCH_COLUMN) as $id_pend) {
                    guardarEmbeddingHistorial($id_pend, $pdo);
                }
            }
        } catch (Throwable $e) {}

        // 2. Vectorizamos la consulta del usuario
        $query_blob = generarEmbeddingOllama($search, $pdo);
        if (!$query_blob) return null; // Si Ollama está apagado, fará fallback al LIKE tradicional

        $q_vec = array_values(unpack('f*', $query_blob));
        $dim = count($q_vec);
        if ($dim === 0) return null;

        // 3. Traemos los candidatos que cumplen el resto de filtros (usuario, fechas, categoría, favoritos...)
        $where_sql = implode(" AND ", $where_clauses);
        $sql = $es_galeria
            ? "SELECT h.id, h.descripcion_original, h.prompt_positivo, h.prompt_negativo, h.modelo, h.embedding, u.nick FROM historial_prompts h JOIN usuarios u ON h.user_id = u.id WHERE $where_sql"
            : "SELECT id, descripcion_original, prompt_positivo, prompt_negativo, modelo, embedding FROM historial_prompts WHERE $where_sql";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return [];

        $scores = [];
        $exact_matches = [];
        $sem_values = [];

        foreach ($rows as $r) {
            $id = (int)$r['id'];
            // A) Coincidencia léxica directa (por si busca una semilla, nick o palabra literal)
            $hay_exacto = (
                (!empty($r['descripcion_original']) && mb_stripos($r['descripcion_original'], $search) !== false) ||
                (!empty($r['prompt_positivo']) && mb_stripos($r['prompt_positivo'], $search) !== false) ||
                (!empty($r['prompt_negativo']) && mb_stripos($r['prompt_negativo'], $search) !== false) ||
                (!empty($r['nick']) && mb_stripos($r['nick'], $search) !== false)
            );
            if ($hay_exacto) {
                $exact_matches[$id] = 1.0;
            }

            // B) Similitud del Coseno Vectorial
            if (!empty($r['embedding'])) {
                $doc_vec = array_values(unpack('f*', $r['embedding']));
                if (count($doc_vec) === $dim) {
                    $dot = 0.0;
                    for ($i = 0; $i < $dim; $i++) {
                        $dot += $q_vec[$i] * $doc_vec[$i];
                    }
                    $scores[$id] = $dot;
                    $sem_values[] = $dot;
                }
            }
        }

        // 4. Umbral adaptativo (se ajusta automáticamente a bge-m3, nomic-embed-text, mxbai, etc.)
        $umbral = 0.50;
        $n = count($sem_values);
        if ($n > 3) {
            $media = array_sum($sem_values) / $n;
            $var = 0.0;
            foreach ($sem_values as $val) {
                $var += ($val - $media) * ($val - $media);
            }
            $std = sqrt($var / $n);
            $umbral = min(0.62, max(0.48, $media + (1.25 * $std)));
        }

        $matched_ids = [];
        foreach ($exact_matches as $id => $sc) {
            $matched_ids[$id] = $sc;
        }
        foreach ($scores as $id => $sim) {
            if ($sim >= $umbral && !isset($matched_ids[$id])) {
                $matched_ids[$id] = $sim;
            }
        }

        arsort($matched_ids);
        return array_keys($matched_ids);
    }
}

// ==============================================================================
// --- FORMATO HISTORIAL DE CONVERSACIONES CON LLM ---
// ==============================================================================

function renderizarMarkdownChat($texto) {
    if (empty($texto)) return '';
    
    // 1. Escapamos HTML por seguridad y normalizamos saltos de línea
    $html = htmlspecialchars(trim($texto), ENT_QUOTES, 'UTF-8');
    $html = str_replace(["\r\n", "\r"], "\n", $html);

    // 2. Bloques de código ```codigo```
    $html = preg_replace_callback('/```(?:\w+)?\n([\s\S]*?)```/', function($m) {
        return '<pre class="bg-black text-info p-3 rounded border border-secondary my-2" style="overflow-x:auto;"><code>' . trim($m[1]) . '</code></pre>';
    }, $html);

    // 3. Código en línea `codigo`
    $html = preg_replace('/`([^`\n]+)`/', '<code class="bg-dark text-warning px-1 rounded">$1</code>',$html);

    // 4. Encabezados (### Título)
    $html = preg_replace('/^###\s+(.+)$/m', '<h6 class="text-info fw-bold mt-3 mb-1">$1</h6>',$html);
    $html = preg_replace('/^##\s+(.+)$/m', '<h5 class="text-info fw-bold mt-3 mb-2">$1</h5>',$html);
    $html = preg_replace('/^#\s+(.+)$/m', '<h4 class="text-info fw-bold mt-3 mb-2">$1</h4>',$html);

    // 5. Negritas (**texto**) y Cursivas (*texto*)
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong class="text-white">$1</strong>', $html);$html = preg_replace('/(?<!\*)\*([^\*\n]+)\*(?!\*)/', '<em>$1</em>',$html);

    // 6. Párrafos y saltos de línea controlados (evita los huecos gigantes)
    $bloques = preg_split('/\n{2,}/', $html);$salida = [];
    foreach ($bloques as$bloque) {
        $bloque = trim($bloque);
        if ($bloque === '') continue;
        // Si ya es un bloque <pre> o <h>, no lo envolvemos en <p>
        if (preg_match('/^<(pre|h[1-6])/i', $bloque)) {
            $salida[] =$bloque;
        } else {
            $salida[] = '<p class="mb-2">' . nl2br($bloque) . '</p>';
        }
    }

    return implode('', $salida);
}

// ==============================================================================
// --- CORRECTOR RUTAS LoRAs ---
// ==============================================================================
if (!function_exists('resolverRutaRealLoraComfy')) {
    function resolverRutaRealLoraComfy($lora_input) {
        if (empty($lora_input) || !is_string($lora_input)) return $lora_input;

        $base_models = defined('COMFY_MODELS_DIR') ? COMFY_MODELS_DIR : (defined('COMFY_MODEL_PATH') ? COMFY_MODEL_PATH : 'C:/ComfyUI/models');
        $base_norm = rtrim(str_replace('\\', '/', $base_models), '/');
        
        // Localizamos la carpeta real de loras
        $loras_dir = $base_norm . '/loras';
        if (!is_dir($loras_dir)) {
            $alt_dir = dirname($base_norm) . '/loras';
            if (is_dir($alt_dir)) $loras_dir = $alt_dir;
            else return $lora_input;
        }

        // Extraemos solo el nombre del archivo en minúsculas para buscarlo sin importar la carpeta
        $solo_archivo = strtolower(basename(str_replace('\\', '/', trim($lora_input))));
        $sep = (DIRECTORY_SEPARATOR === '\\') ? '\\' : '/';

        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($loras_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && strtolower($file->getFilename()) === $solo_archivo) {
                    // Obtenemos la ruta real exacta con las mayúsculas/minúsculas reales del sistema
                    $full_real = str_replace('\\', '/', $file->getRealPath() ?: $file->getPathname());
                    $root_real = str_replace('\\', '/', realpath($loras_dir) ?: $loras_dir);
                    $rel = ltrim(substr($full_real, strlen($root_real)), '/');
                    return str_replace('/', $sep, $rel);
                }
            }
        } catch (Exception $e) {}

        return $lora_input;
    }
}

// ==============================================================================
// --- HELPERS CIVITAI: HASH AUTOV2 EN BBDD E INYECCIÓN DE METADATOS PNG ---
// ==============================================================================

if (!function_exists('buscarRutaFisicaModeloCivitai')) {
    function buscarRutaFisicaModeloCivitai($nombre_archivo) {
        $ruta_cfg = defined('COMFY_MODELS_DIR') ? COMFY_MODELS_DIR : (defined('COMFY_MODEL_PATH') ? COMFY_MODEL_PATH : 'C:/ComfyUI/models');
        if (empty($nombre_archivo) || empty($ruta_cfg)) {
            return null;
        }
        $rel = ltrim(str_replace('\\', '/', trim($nombre_archivo)), '/');
        $solo_nombre = strtolower(basename($rel));
        $base = rtrim(str_replace('\\', '/', $ruta_cfg), '/');
        $padre = dirname($base);

        $raices = array_unique([$base, $padre]);
        $subcarpetas = ['', 'checkpoints', 'unet', 'diffusion_models', 'loras'];

        // 1. Búsqueda directa rápida
        foreach ($raices as $r) {
            foreach ($subcarpetas as $sub) {
                $prefijo = $sub === '' ? $r : ($r . '/' . $sub);
                
                $cand1 = $prefijo . '/' . $rel;
                if (@file_exists($cand1) && @is_file($cand1)) return $cand1;

                $cand2 = $prefijo . '/' . basename($rel);
                if (@file_exists($cand2) && @is_file($cand2)) return $cand2;
            }
        }

        // 2. Búsqueda recursiva en subcarpetas (ej. checkpoints/SDXL/modelo.safetensors)
        foreach ($raices as $r) {
            foreach (['checkpoints', 'unet', 'diffusion_models', 'loras'] as $sub) {
                $dir_scan = $r . '/' . $sub;
                if (!is_dir($dir_scan)) continue;
                try {
                    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir_scan, RecursiveDirectoryIterator::SKIP_DOTS));
                    foreach ($it as $file) {
                        if ($file->isFile() && strtolower($file->getFilename()) === $solo_nombre) {
                            return $file->getPathname();
                        }
                    }
                } catch (Exception $e) {}
            }
        }
        return null;
    }
}

if (!function_exists('obtenerHashAutoV2Civitai')) {
    function obtenerHashAutoV2Civitai($nombre_archivo, $pdo) {
        if (empty($nombre_archivo)) return null;
        $solo_nombre = basename(str_replace('\\', '/', trim($nombre_archivo)));
        $modelo_id = null;
        $archivo_real_bd = $nombre_archivo;

        // 1. Consultamos primero la tabla modelos_ia en la Base de Datos
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("SELECT id, nombre_archivo, civitai_hash FROM modelos_ia WHERE nombre_archivo LIKE ? LIMIT 1");
                $stmt->execute(['%' . $solo_nombre]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $modelo_id = $row['id'];
                    if (!empty($row['nombre_archivo'])) {
                        $archivo_real_bd = $row['nombre_archivo'];
                    }
                    if (!empty(trim((string)$row['civitai_hash']))) {
                        return strtoupper(trim($row['civitai_hash']));
                    }
                }
            } catch (Exception $e) {}
        }

        // 2. Si está vacío en la BD, lo calculamos desde el archivo físico
        $ruta_fisica = buscarRutaFisicaModeloCivitai($archivo_real_bd);
        if (!$ruta_fisica) return null;

        @set_time_limit(180);
        $full_sha256 = @hash_file('sha256', $ruta_fisica);
        if (!$full_sha256) return null;

        $autov2 = strtolower(substr($full_sha256, 0, 10));

        // 3. Lo guardamos en modelos_ia para siempre
        if ($pdo && $modelo_id) {
            try {
                $upd = $pdo->prepare("UPDATE modelos_ia SET civitai_hash = ? WHERE id = ?");
                $upd->execute([$autov2, $modelo_id]);
            } catch (Exception $e) {}
        }

        return strtoupper($autov2);
    }
}

if (!function_exists('limpiarChunksTextoComfyPNG')) {
    function limpiarChunksTextoComfyPNG($png_binary) {
        // Elimina los chunks tEXt/iTXt nativos de ComfyUI ("prompt" y "workflow") 
        // para que Civitai no ignore nuestro bloque "parameters" con los hashes
        $out = substr($png_binary, 0, 8);
        $pos = 8;
        $len = strlen($png_binary);

        while ($pos + 8 <= $len) {
            $chunk_len = unpack('N', substr($png_binary, $pos, 4))[1];
            $chunk_type = substr($png_binary, $pos + 4, 4);
            $total_chunk_size = 4 + 4 + $chunk_len + 4;

            if ($pos + $total_chunk_size > $len) break;

            if (!in_array($chunk_type, ['tEXt', 'iTXt', 'zTXt'])) {
                $out .= substr($png_binary, $pos, $total_chunk_size);
            }
            $pos += $total_chunk_size;
        }
        return strlen($out) > 33 ? $out : $png_binary;
    }
}

if (!function_exists('construirTextoParametrosCivitai')) {
    function construirTextoParametrosCivitai($historial_id, $pdo) {$pos_prompt = '';
        $neg_prompt = '';$meta_db = [];

        if ($historial_id > 0 &&$pdo) {
            try {
                $st =$pdo->prepare("SELECT prompt_positivo, prompt_negativo, metadata FROM historial_prompts WHERE id = ?");
                $st->execute([$historial_id]);
                if ($r =$st->fetch(PDO::FETCH_ASSOC)) {
                    $pos_prompt = trim((string)($r['prompt_positivo'] ?? ''));
                    $neg_prompt = trim((string)($r['prompt_negativo'] ?? ''));
                    $meta_db = @json_decode($r['metadata'] ?? '{}', true) ?: [];
                }
            } catch (Exception $e) {}
        }

        $model_file =$meta_db['Model'] ?? '';
        $steps      =$meta_db['Steps'] ?? 20;
        $cfg        =$meta_db['CFG Scale'] ?? 3.5;
        $seed       =$meta_db['Seed'] ?? 0;
        $res        =$meta_db['Resolution'] ?? '1024x1024';
        if (!preg_match('/^\d+x\d+$/', (string)$res))$res = '1024x1024';

        $sampler_raw   = 'euler';$scheduler_raw = 'normal';
        if (!empty($meta_db['Sampler']) && preg_match('/^([^\s(]+)(?:\s*\(([^)]+)\))?/', (string)$meta_db['Sampler'],$m_samp)) {
            $sampler_raw =$m_samp[1];
            if (!empty($m_samp[2])) $scheduler_raw =$m_samp[2];
        }

        $loras_encontrados = [];$loras_vistos = [];

        $wf_path = __DIR__ . '/../galeria/workflow_' .$historial_id . '.json';
        if ($historial_id > 0 && file_exists($wf_path)) {
            $wf = @json_decode(@file_get_contents($wf_path), true);
            if (is_array($wf)) {
                foreach ($wf as $nid =>$node) {
                    $ct =$node['class_type'] ?? '';
                    $in =$node['inputs'] ?? [];

                    if (in_array($ct, ['CheckpointLoaderSimple', 'UNETLoader', 'UnetLoaderGGUF', 'LTXVLoader']) && (string)$nid !== '902') {
                        if (!empty($in['ckpt_name'])) $model_file =$in['ckpt_name'];
                        elseif (!empty($in['unet_name'])) $model_file =$in['unet_name'];
                    }

                    if (in_array($ct, ['LoraLoader', 'LoraLoaderModelOnly']) && !empty($in['lora_name'])) {
                        $l_key = strtolower(basename(str_replace('\\', '/',$in['lora_name'])));
                        if (!isset($loras_vistos[$l_key])) {
                            $loras_vistos[$l_key] = true;
                            $loras_encontrados[] = [
                                'file' => $in['lora_name'],
                                'strength' => $in['strength_model'] ?? 0.8
                            ];
                        }
                    }

                    if (in_array($ct, ['KSampler', 'KSamplerAdvanced']) && !in_array((string)$nid, ['901', '401'])) {
                        if (isset($in['steps'])) $steps =$in['steps'];
                        if (isset($in['cfg'])) $cfg =$in['cfg'];
                        if (isset($in['seed'])) $seed =$in['seed'];
                        elseif (isset($in['noise_seed'])) $seed =$in['noise_seed'];
                        if (!empty($in['sampler_name'])) $sampler_raw =$in['sampler_name'];
                        if (!empty($in['scheduler'])) $scheduler_raw =$in['scheduler'];
                    } elseif ($ct === 'RandomNoise' && isset($in['noise_seed'])) {
                        $seed =$in['noise_seed'];
                    } elseif (in_array($ct, ['BasicScheduler', 'LTXVScheduler'])) {
                        if (isset($in['steps'])) $steps =$in['steps'];
                        if (!empty($in['scheduler'])) $scheduler_raw =$in['scheduler'];
                    } elseif ($ct === 'KSamplerSelect' && !empty($in['sampler_name'])) {
                        $sampler_raw =$in['sampler_name'];
                    }
                }
            }
        }

        // Respaldo para LoRAs guardados en metadata de BBDD (ej. flujos de vídeo específicos)
        if (empty($loras_encontrados) && !empty($meta_db['LoRAs']) && is_string($meta_db['LoRAs'])) {
            foreach (explode(',', $meta_db['LoRAs']) as$part) {
                if (preg_match('/^(.+?)\s*\((?:High:\s*)?([\d.-]+)/i', trim($part), $m_l)) {$loras_encontrados[] = [
                        'file' => trim($m_l[1]) . '.safetensors',
                        'strength' => floatval($m_l[2])
                    ];
                }
            }
        }

        if (empty($model_file) || strpos($model_file, 'Audio_') === 0) {
            return '';
        }

        $sampler_map = [
            'euler' => 'Euler',
            'euler_ancestral' => 'Euler a',
            'heun' => 'Heun',
            'dpm_2' => 'DPM2',
            'dpm_2_ancestral' => 'DPM2 a',
            'lms' => 'LMS',
            'dpmpp_2s_ancestral' => 'DPM++ 2S a',
            'dpmpp_sde' => 'DPM++ SDE',
            'dpmpp_2m' => 'DPM++ 2M',
            'dpmpp_2m_sde' => 'DPM++ 2M SDE',
            'dpmpp_3m_sde' => 'DPM++ 3M SDE',
            'ddim' => 'DDIM',
            'uni_pc' => 'UniPC',
            'res_multistep' => 'res_multistep'
        ];
        $sampler_clean =$sampler_map[strtolower($sampler_raw)] ?? ucfirst($sampler_raw);
        if (!in_array(strtolower($scheduler_raw), ['normal', 'simple', ''])) {
            $sampler_clean .= ' ' . ucfirst($scheduler_raw);
        }

        $model_name_clean = pathinfo(str_replace('\\', '/', $model_file), PATHINFO_FILENAME);$model_hash = obtenerHashAutoV2Civitai($model_file,$pdo);

        $hashes_dict = [];
        if ($model_hash) {
            $hashes_dict['model'] =$model_hash;
        }

        $lora_tags_prompt = [];$lora_hashes_str = [];
        foreach ($loras_encontrados as $l_item) {$l_clean = pathinfo(str_replace('\\', '/', $l_item['file']), PATHINFO_FILENAME);$l_str   = $l_item['strength'];$l_hash  = obtenerHashAutoV2Civitai($l_item['file'],$pdo);
            if ($l_hash) {$hashes_dict['lora:' . $l_clean] =$l_hash;
                $lora_hashes_str[] = "{$l_clean}: {$l_hash}";
            }
            if (stripos($pos_prompt, '<lora:' . $l_clean) === false) {$lora_tags_prompt[] = "<lora:{$l_clean}:{$l_str}>";
            }
        }

        if (!empty($lora_tags_prompt)) {$pos_prompt = rtrim($pos_prompt) . ' ' . implode(' ', $lora_tags_prompt);
        }

        $pos_prompt = str_replace(["\r", "\x00"], '', $pos_prompt);
        $neg_prompt = str_replace(["\r", "\x00"], '', $neg_prompt);

        $param_parts = [
            "Steps: " . intval($steps),
            "Sampler: " . $sampler_clean,
            "Schedule type: " . ucfirst($scheduler_raw),
            "CFG scale: " . floatval($cfg),
            "Seed: " . $seed,
            "Size: " . $res
        ];
        if ($model_hash) {$param_parts[] = "Model hash: " . strtolower($model_hash);
        }
        $param_parts[] = "Model: " . $model_name_clean;

        if (!empty($lora_hashes_str)) {$param_parts[] = 'Lora hashes: "' . implode(', ', $lora_hashes_str) . '"';
        }
        if (!empty($hashes_dict)) {
            $param_parts[] = "Hashes: " . json_encode($hashes_dict, JSON_UNESCAPED_SLASHES);
        }

        $parameters_text =$pos_prompt . "\n";
        if (!empty($neg_prompt)) {$parameters_text .= "Negative prompt: " . $neg_prompt . "\n";
        }
        $parameters_text .= implode(", ", $param_parts);

        return $parameters_text;
    }
}

if (!function_exists('inyectarMetadatosCivitaiPNG')) {
    function inyectarMetadatosCivitaiPNG($png_binary, $historial_id, $pdo) {
        if (strlen($png_binary) < 64 || substr($png_binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return $png_binary;
        }

        $png_binary = limpiarChunksTextoComfyPNG($png_binary);
        $parameters_text = construirTextoParametrosCivitai($historial_id, $pdo);
        if ($parameters_text === '') {
            return $png_binary;
        }

        $chunk_data = "parameters\x00" . $parameters_text;
        $chunk_type = "tEXt";
        $full_chunk = pack('N', strlen($chunk_data)) . $chunk_type . $chunk_data . pack('N', crc32($chunk_type . $chunk_data));

        $ihdr_len = unpack('N', substr($png_binary, 8, 4))[1];
        $insert_pos = 8 + 4 + 4 + $ihdr_len + 4;

        return substr($png_binary, 0, $insert_pos) . $full_chunk . substr($png_binary, $insert_pos);
    }
}

if (!function_exists('inyectarMetadatosCivitaiWebP')) {
    function inyectarMetadatosCivitaiWebP($webp_binary, $historial_id, $pdo) {
        if (strlen($webp_binary) < 16 || substr($webp_binary, 0, 4) !== 'RIFF' || substr($webp_binary, 8, 4) !== 'WEBP') {
            return $webp_binary;
        }

        $parameters_text = construirTextoParametrosCivitai($historial_id, $pdo);
        if ($parameters_text === '') {
            return $webp_binary;
        }

        $info = @getimagesizefromstring($webp_binary);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return $webp_binary;
        }

        $w = max(1, intval($info[0])) - 1;
        $h = max(1, intval($info[1])) - 1;

        $cuerpo = substr($webp_binary, 12);
        if (strlen($cuerpo) % 2 !== 0) {
            $cuerpo .= "\x00";
        }

        $primerChunk = substr($cuerpo, 0, 4);
        if ($primerChunk !== 'VP8X') {
            $flags  = "\x08\x00\x00\x00"; // Bit EXIF activo
            $w24    = substr(pack('V', $w), 0, 3);
            $h24    = substr(pack('V', $h), 0, 3);
            $vp8x   = 'VP8X' . pack('V', 10) . $flags . $w24 . $h24;
            $cuerpo = $vp8x . $cuerpo;
        } else {
            $cuerpo[8] = chr(ord($cuerpo[8]) | 0x08);
        }

        // Prefijo ASCII estándar de 8 bytes en EXIF UserComment (0x9286) compatible con Civitai y navegadores
        $userComment = "ASCII\x00\x00\x00" . $parameters_text;
        $ucLen       = strlen($userComment);
        $tiff = "II\x2A\x00"
              . pack('V', 8)
              . pack('v', 1)
              . pack('v', 0x9286)
              . pack('v', 7)
              . pack('V', $ucLen)
              . pack('V', 26)
              . pack('V', 0)
              . $userComment;

        $exifLen   = strlen($tiff);
        $padding   = ($exifLen % 2 !== 0) ? "\x00" : "";
        $exifChunk = 'EXIF' . pack('V', $exifLen) . $tiff . $padding;

        $nuevoCuerpo = $cuerpo . $exifChunk;
        return 'RIFF' . pack('V', strlen($nuevoCuerpo) + 4) . 'WEBP' . $nuevoCuerpo;
    }
}

if (!function_exists('inyectarMetadatosCivitaiMP4')) {
    function inyectarMetadatosCivitaiMP4($mp4_binary, $historial_id, $pdo) {
        $len = strlen($mp4_binary);
        if ($len < 32 || substr($mp4_binary, 4, 4) !== 'ftyp') {
            return $mp4_binary;
        }

        $parameters_text = construirTextoParametrosCivitai($historial_id, $pdo);
        if ($parameters_text === '') {
            return $mp4_binary;
        }

        // Construimos la estructura ISO/MP4: udta -> meta -> [hdlr, ilst -> ©cmt -> data]
        $data_payload = pack('N', 1) . pack('N', 0) . $parameters_text; // Flag 1 = UTF-8
        $data_box     = pack('N', 8 + strlen($data_payload)) . 'data' . $data_payload;
        $cmt_box      = pack('N', 8 + strlen($data_box)) . "\xA9cmt" . $data_box;
        $ilst_box     = pack('N', 8 + strlen($cmt_box)) . 'ilst' . $cmt_box;
        $hdlr_box     = pack('N', 33) . 'hdlr' . "\x00\x00\x00\x00" . "\x00\x00\x00\x00" . 'mdirappl' . "\x00\x00\x00\x00\x00\x00\x00\x00\x00";
        $meta_payload = "\x00\x00\x00\x00" . $hdlr_box . $ilst_box;
        $meta_box     = pack('N', 8 + strlen($meta_payload)) . 'meta' . $meta_payload;
        $udta_box     = pack('N', 8 + strlen($meta_box)) . 'udta' . $meta_box;

        // Localizamos los átomos principales moov y mdat
        $pos = 0;
        $moov_offset = -1;
        $moov_size   = 0;
        $moov_hdr    = 8;
        $mdat_offset = -1;

        while ($pos + 8 <= $len) {
            $box_size = unpack('N', substr($mp4_binary, $pos, 4))[1];
            $box_type = substr($mp4_binary, $pos + 4, 4);
            $hdr_len  = 8;

            if ($box_size === 1) {
                if ($pos + 16 > $len) break;
                $hi = unpack('N', substr($mp4_binary, $pos + 8, 4))[1];
                $lo = unpack('N', substr($mp4_binary, $pos + 12, 4))[1];
                $box_size = ($hi * 4294967296) + $lo;
                $hdr_len  = 16;
            } elseif ($box_size === 0) {
                $box_size = $len - $pos;
            }

            if ($box_size < $hdr_len || $pos + $box_size > $len) break;

            if ($box_type === 'moov') {
                $moov_offset = $pos;
                $moov_size   = $box_size;
                $moov_hdr    = $hdr_len;
            } elseif ($box_type === 'mdat' && $mdat_offset === -1) {
                $mdat_offset = $pos;
            }

            $pos += $box_size;
        }

        if ($moov_offset === -1) {
            return $mp4_binary;
        }

        // Limpiamos cualquier caja udta previa dentro de moov e insertamos la nuestra
        $moov_body = substr($mp4_binary, $moov_offset + $moov_hdr, $moov_size - $moov_hdr);
        $moov_body_len = strlen($moov_body);
        $clean_moov_body = '';
        $sub_pos = 0;

        while ($sub_pos + 8 <= $moov_body_len) {
            $sub_size = unpack('N', substr($moov_body, $sub_pos, 4))[1];
            $sub_type = substr($moov_body, $sub_pos + 4, 4);
            if ($sub_size === 0) $sub_size = $moov_body_len - $sub_pos;
            if ($sub_size < 8 || $sub_pos + $sub_size > $moov_body_len) {
                $clean_moov_body .= substr($moov_body, $sub_pos);
                break;
            }
            if ($sub_type !== 'udta') {
                $clean_moov_body .= substr($moov_body, $sub_pos, $sub_size);
            }
            $sub_pos += $sub_size;
        }

        $new_moov_body = $clean_moov_body . $udta_box;
        $new_moov_box  = pack('N', 8 + strlen($new_moov_body)) . 'moov' . $new_moov_body;

        // Si moov va detrás de mdat, reemplazamos directo. Si iba delante, anulamos con 'free' del mismo tamaño para no alterar offsets stco/co64
        if ($mdat_offset !== -1 && $moov_offset > $mdat_offset) {
            return substr($mp4_binary, 0, $moov_offset)
                 . $new_moov_box
                 . substr($mp4_binary, $moov_offset + $moov_size);
        } else {
            $free_box = substr($mp4_binary, $moov_offset, 4) . 'free' . substr($mp4_binary, $moov_offset + 8, $moov_size - 8);
            return substr($mp4_binary, 0, $moov_offset)
                 . $free_box
                 . substr($mp4_binary, $moov_offset + $moov_size)
                 . $new_moov_box;
        }
    }
}

?>