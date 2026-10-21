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

        $ka_raw = isset($mod['keep_alive']) && trim($mod['keep_alive']) !== '' ? trim($mod['keep_alive']) : '2m';
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

?>