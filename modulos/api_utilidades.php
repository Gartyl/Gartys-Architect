<?php
// ==============================================================================
// --- MÓDULO UTILIDADES: VARITA MÁGICA, TRADUCTOR Y SORPRÉNDEME ---
// ==============================================================================

if ($action === 'amplificar_prompt') {
    $idea_basica = $_POST['descripcion'] ?? '';
    $idioma_usuario = $_POST['idioma'] ?? 'ES'; 
    if (empty($idea_basica)) { echo json_encode(['error' => __('err_no_idea_amplify')]); exit(); }

    $stmtVarita = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'enhance_prompt' AND activo = 1 AND LOWER(idioma) = ? LIMIT 1");
    $stmtVarita->execute([$idioma_usuario]);
    $resultado_varita = $stmtVarita->fetch(PDO::FETCH_ASSOC);

    if (!$resultado_varita) {
        $stmtVarita = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'enhance_prompt' AND activo = 1 AND LOWER(idioma) = 'es' LIMIT 1");
        $stmtVarita->execute();
        $resultado_varita = $stmtVarita->fetch(PDO::FETCH_ASSOC);
    }

    if (!$resultado_varita || empty($resultado_varita['prompt_texto'])) { echo json_encode(['error' => __('err_no_amplify_prompt_db')]); exit(); }

    $prompt_sistema = $resultado_varita['prompt_texto'];
    $temperatura_varita = 0.7; 
    
    // SIEMPRE forzamos la búsqueda del SYS_LLM para utilidades en la sombra
    $stmt_llm = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND categoria = 'SYS_LLM' AND activo = 1 LIMIT 1");
    $modelo_varita = $stmt_llm->fetchColumn() ?: $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND activo = 1 LIMIT 1")->fetchColumn();
    
    if (empty($modelo_varita)) { echo json_encode(['error' => __('err_no_sys_llm_active')]); exit(); }
    
    if (!empty($resultado_varita['parametros'])) {
        $json_params = json_decode($resultado_varita['parametros'], true);
        if (isset($json_params['temperature'])) $temperatura_varita = (float)$json_params['temperature'];
        if (isset($json_params['model'])) $modelo_varita = $json_params['model'];
    }

    // Buscamos si hay regla específica de keep_alive en la BD (con try-catch de seguridad)
    $ka_db = null;
    try {
        $stmt_ka = $pdo->prepare("SELECT keep_alive FROM modelos_ia WHERE nombre_archivo = ? LIMIT 1");
        $stmt_ka->execute([$modelo_varita]);
        $ka_db = $stmt_ka->fetchColumn();
    } catch (Exception $e) {}

    // FALLBACK: Como es una utilidad gráfica, liberamos VRAM al instante (0)
    $keep_alive_val = (!empty($ka_db)) ? trim($ka_db) : 0;

    $data = [
        "model" => $modelo_varita,
        "messages" => [
            ["role" => "system", "content" => $prompt_sistema],
            ["role" => "user", "content" => __('cmd_amplify_idea') . " " . $idea_basica]
        ],
        "stream" => false, 
        "keep_alive" => $keep_alive_val, 
        "options" => [ "temperature" => $temperatura_varita ]
    ];

    $ch = curl_init('http://' . LLM_IP . ':11434/api/chat');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); 
    $respuesta = curl_exec($ch);

    if ($respuesta) {
        $resultado = json_decode($respuesta, true);
        $clean = trim(str_replace('"', '', $resultado['message']['content'] ?? ''));
        if (empty($clean)) { echo json_encode(['error' => __('err_llm_empty_response')]); exit(); }
        echo json_encode(['prompt_amplificado' => $clean]);
    } else { echo json_encode(['error' => __('err_ollama_conn_failed')]); }
    exit();
}

if ($action === 'traducir_rapido') {
    $texto = trim($_POST['texto'] ?? '');
    if (empty($texto)) { echo json_encode(['error' => __('err_empty_text')]); exit(); }

    $stmt_llm =$pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND categoria = 'SYS_LLM' AND activo = 1 LIMIT 1");
    $modelo_traductor = $stmt_llm->fetchColumn() ?:$pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND activo = 1 LIMIT 1")->fetchColumn();

    if (empty($modelo_traductor)) { echo json_encode(['error' => __('err_no_sys_llm_active')]); exit(); }

    // Buscamos si hay regla específica de keep_alive en la BD (con try-catch)
    $ka_db = null;
    try {
        $stmt_ka = $pdo->prepare("SELECT keep_alive FROM modelos_ia WHERE nombre_archivo = ? LIMIT 1");
        $stmt_ka->execute([$modelo_traductor]);
        $ka_db = $stmt_ka->fetchColumn();
    } catch (Exception $e) {}

    // FALLBACK: El traductor siempre 0 por defecto para que ComfyUI tenga la GPU libre al instante
    $keep_alive_val = (!empty($ka_db)) ? trim($ka_db) : 0;

    $payload = [
        "model" => $modelo_traductor,
        "messages" => [
            // Prompt dictatorial
            ["role" => "system", "content" => "You are a direct translation engine. Translate the user's text to English. Output ONLY the English translation. NO introductions, NO explanations, NO markdown, NO quotes. IMPORTANT: Do NOT translate any word enclosed in asterisks (e.g. *word*) or quotes (e.g. \"word\")."],
            ["role" => "user", "content" => "Translate this exact text into English:\n\n" . $texto]
        ],
        "stream" => false, 
        "keep_alive" => $keep_alive_val, 
        "options" => ["temperature" => 0.1]
    ];
    
    $ch = curl_init("http://" . LLM_IP . ":11434/api/chat");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $api_res = curl_exec($ch);

    if ($api_res) {
        $res_trad = json_decode($api_res, true);
        if (isset($res_trad['message']['content'])) {
            $clean_trad =$res_trad['message']['content'];
            
            // 🌟 BLINDAJE 1: Limpiamos los tags de "pensamiento" (DeepSeek R1)
            $clean_trad = preg_replace('/<think>.*?<\/think>/is', '',$clean_trad);
            
            // 🌟 BLINDAJE 2: Extraer de bloques Markdown (```texto```) si el LLM se pone creativo
            if (preg_match('/```[a-z]*\s*([\s\S]*?)\s*```/i', $clean_trad, $matches)) {
                $clean_trad = $matches[1];
            }
            
            // 🌟 BLINDAJE 3: Si el modelo intenta devolver un JSON por su cuenta (como un rebelde)
            $json_attempt = json_decode($clean_trad, true);
            if (is_array($json_attempt)) {
                foreach ($json_attempt as $val) {
                    // Extraemos el primer texto que veamos en ese JSON rebelde
                    if (is_string($val)) { $clean_trad = $val; break; }
                }
            }
            
            // 🌟 BLINDAJE 4: Eliminar muletillas ("Here is the translation: Woman running")
            if (strpos($clean_trad, ':') !== false) {
                $partes = explode(':', $clean_trad, 2); // Cortamos por los dos puntos
                if (strlen(trim($partes[0])) < 60) {    // Si lo de la izquierda es corto, es una muletilla
                    $clean_trad = $partes[1];
                }
            }
            
            // 🌟 BLINDAJE 5: Limpieza final de comillas, asteriscos y espacios
            $clean_trad = trim(str_replace(['"', "'", '*', '`'], '', strip_tags($clean_trad)));
            
            // Ahora sí, devolvemos el texto puro al navegador
            echo json_encode(['success' => true, 'traduccion' => $clean_trad]);
        } else { echo json_encode(['error' => __('err_ollama_unexp_format') . ' ' . $api_res]); }
    } else { echo json_encode(['error' => __('err_ollama_conn_fail_curl') . ' ' . curl_error($ch)]); }
    exit();
}

if ($action === 'generar_prompt_sorpresa') {
    try {
        $idioma_usuario = strtolower($_POST['idioma'] ?? 'es');
        $contexto_recibido = strtolower($_POST['contexto'] ?? 'imagen'); 

        $mapa_contextos = [ 'imagen' => 'image', 'chat' => 'chat', 'video' => 'video' ];
        $contexto_en = $mapa_contextos[$contexto_recibido] ?? 'image';
        $tipo_semilla = 'seed_' . $contexto_en; 
        
        $semilla = false;
        $stmtSemilla = $pdo->prepare("SELECT prompt_texto FROM personalidades_prompts WHERE LOWER(tipo) = ? AND LOWER(idioma) = ? AND activo = 1 LIMIT 1");
        if ($stmtSemilla) { $stmtSemilla->execute([$tipo_semilla, $idioma_usuario]); $semilla = $stmtSemilla->fetchColumn(); }

        if (!$semilla && $idioma_usuario !== 'es') {
            $stmtSemillaFb = $pdo->prepare("SELECT prompt_texto FROM personalidades_prompts WHERE LOWER(tipo) = ? AND LOWER(idioma) = 'es' AND activo = 1 LIMIT 1");
            if ($stmtSemillaFb) { $stmtSemillaFb->execute([$tipo_semilla]); $semilla = $stmtSemillaFb->fetchColumn(); }
        }

        $resultado_db = false;
        $stmtDado = $pdo->prepare("SELECT titulo, prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'random_prompt' AND activo = 1 AND LOWER(idioma) = ? ORDER BY RAND() LIMIT 1");
        if ($stmtDado) { $stmtDado->execute([$idioma_usuario]); $resultado_db = $stmtDado->fetch(PDO::FETCH_ASSOC); }

        if (!$resultado_db && $idioma_usuario !== 'es') {
            $stmtDadoFb = $pdo->prepare("SELECT titulo, prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'random_prompt' AND activo = 1 AND LOWER(idioma) = 'es' ORDER BY RAND() LIMIT 1");
            if ($stmtDadoFb) { $stmtDadoFb->execute(); $resultado_db = $stmtDadoFb->fetch(PDO::FETCH_ASSOC); }
        }

        if (empty($semilla)) { echo json_encode(['error' => __('err_surprise_config_seed') . " '$tipo_semilla' / '$idioma_usuario'"]); exit(); }
        if (!$resultado_db || empty($resultado_db['prompt_texto'])) { echo json_encode(['error' => __('err_surprise_config_char') . " '$idioma_usuario'"]); exit(); }

        $sys = $semilla;
        $factor_caos = rand(10000, 99999);
        
        // 🛑 INSTRUCCIÓN BASE Y BLOQUEO DE REPETICIONES
        $usr = __('cmd_adopt_persona') . "\n[" . $resultado_db['prompt_texto'] . "]\n\n" . __('cmd_adopt_persona_rules') . "\n\n[SYSTEM DIRECTIVE: This is a highly creative task. Chaos Factor: " . $factor_caos . ". DO NOT rely on common concepts like A24 movies, miniatures, giant heads, robots, bugs, beetles, or clocks. Be radically original and explore completely different themes.]";

        $temperatura_final = 0.95; // Temperatura por defecto para asegurar máxima creatividad
        
        // SIEMPRE forzamos el SYS_LLM
        $stmt_llm = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND categoria = 'SYS_LLM' AND activo = 1 LIMIT 1");
        $modelo_base = $stmt_llm ? $stmt_llm->fetchColumn() : false;
        if (!$modelo_base) {
            $stmt_fb = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND activo = 1 LIMIT 1");
            $modelo_base = $stmt_fb ? $stmt_fb->fetchColumn() : false;
        }
        
        if (empty($modelo_base)) { echo json_encode(['error' => __('err_surprise_no_sys_llm')]); exit(); }
        
        $modelo_dado = $modelo_base;

        if (!empty($resultado_db['parametros'])) {
            $json_params = json_decode($resultado_db['parametros'], true);
            // ✅ Respetamos la temperatura de la BD si el usuario la ha configurado ahí
            if (isset($json_params['temperature'])) $temperatura_final = (float)$json_params['temperature'];
            if (isset($json_params['model']) && !empty($json_params['model'])) $modelo_dado = $json_params['model'];
        }

        // Buscamos si hay regla específica de keep_alive en la BD (con try-catch)
        $ka_db = null;
        try {
            $stmt_ka = $pdo->prepare("SELECT keep_alive FROM modelos_ia WHERE nombre_archivo = ? LIMIT 1");
            $stmt_ka->execute([$modelo_dado]);
            $ka_db = $stmt_ka->fetchColumn();
        } catch (Exception $e) {}

        // FALLBACK: Al ser utilidad para generar imagen, liberamos la memoria (0)
        $keep_alive_val = (!empty($ka_db)) ? trim($ka_db) : 0;

        // 🚀 PAYLOAD ÚNICO CON INYECCIÓN DE CAOS
        $payload = [
            "model" => $modelo_dado, 
            "messages" => [ ["role" => "system", "content" => $sys], ["role" => "user", "content" => $usr] ], 
            "stream" => false, 
            "keep_alive" => $keep_alive_val, 
            "options" => [ 
                "temperature" => $temperatura_final, 
                "top_p" => 0.95,            
                "top_k" => 80,              
                "repeat_penalty" => 1.3,   
                "seed" => rand(1, 2147483647) 
            ]
        ];
        
        $ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/chat");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 180); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload)); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $api_res = curl_exec($ch);
        
        if ($api_res === false) { echo json_encode(['error' => __('err_surprise_ollama_off')]); exit(); }
        
        $result_data = json_decode($api_res, true);

        if (isset($result_data['message']['content'])) {
            $raw = $result_data['message']['content'];
            $clean = preg_replace('/<think>.*?<\/think>/is', '', $raw);
            if ($clean === null) $clean = $raw;
            if (empty(trim($clean))) $clean = trim(strip_tags($raw)); 
            
            // Texto de salida final limpio
            $texto_final = trim(str_replace('"', '', $clean));
            
            echo json_encode(['success' => true, 'prompt' => $texto_final]);
        } else {
            $err = $result_data['error'] ?? __('err_surprise_empty');
            echo json_encode(['error' => is_string($err) ? $err : json_encode($err)]);
        }
        exit();
    } catch (Throwable $e) {
        echo json_encode(['error' => __('err_internal') . ': ' . $e->getMessage() . ' ' . __('err_line') . ' ' . $e->getLine()]);
        exit();
    }
}

if ($action === 'get_wildcards') {
    $wildcards_dir = __DIR__ . '/../wildcards';
    $files = [];
    if (is_dir($wildcards_dir)) {
        $items = scandir($wildcards_dir);
        foreach ($items as $item) {
            if (pathinfo($item, PATHINFO_EXTENSION) === 'txt') { $files[] = basename($item, '.txt'); }
        }
    }
    sort($files); 
    echo json_encode(['wildcards' => $files]);
    exit();
}

if ($action === 'get_ollama_models') {
    $url = "http://" . LLM_IP . ":11434/api/tags"; 
    $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    $models = [];
    if ($res) {
        $data = json_decode($res, true);
        if (isset($data['models'])) {
            foreach ($data['models'] as $m) { $models[] = $m['name']; }
            sort($models, SORT_NATURAL | SORT_FLAG_CASE);
        }
    } else { error_log(__('log_err_ollama_curl') . ": " . curl_error($ch)); }
    echo json_encode(['models' => $models]);
    exit();
}

// ==============================================================================
// --- MÓDULO: MONITOR DE SISTEMA EN TIEMPO REAL (VRAM, OLLAMA, COMFYUI) ---
// ==============================================================================

if ($action === 'liberar_modelo_ollama') {
    $modelo = trim($_POST['modelo'] ?? '');
    if (!empty($modelo)) {
        // Rescatamos tu constante dinámica o caemos al puerto por defecto
        $puerto = defined('LLM_PORT') ? LLM_PORT : '11434';
        
        // keep_alive: 0 es la orden nativa de Ollama para descargar de la VRAM
        $payload = ["model" => $modelo, "keep_alive" => 0];
        
        $ch = curl_init("http://" . LLM_IP . ":" . $puerto . "/api/generate");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true); // <-- CRÍTICO: Forzamos envío por POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        curl_exec($ch);
        curl_close($ch);
    }
    echo json_encode(['success' => true]);
    exit();
}

if ($action === 'get_system_stats') {
    session_write_close(); 
    $stats = ['ollama' => null, 'comfy_queue' => null, 'comfy_sys' => null];

    // Array de peticiones
    $requests = [
        'ollama'      => "http://" . LLM_IP . ":" . LLM_PORT . "/api/ps",
        'comfy_queue' => COMFY_URL . "/queue",
        'comfy_sys'   => COMFY_URL . "/system_stats"
    ];

    // Iniciamos cURL múltiple para consultar a la vez y no bloquearnos
    $mh = curl_multi_init();
    $handles = [];

    foreach ($requests as $key => $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2); // Máximo 2 segundos por intento
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    // Ejecutar todas las peticiones en paralelo
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    // Recoger los resultados
    foreach ($handles as $key => $ch) {
        $res = curl_multi_getcontent($ch);
        if ($res) {
            $stats[$key] = json_decode($res, true);
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
	
	// 👇 NUEVO: Consultar temperatura y uso del procesador a NVIDIA 👇
    $nvidia_stats = shell_exec('nvidia-smi --query-gpu=temperature.gpu,utilization.gpu --format=csv,noheader,nounits 2>nul');
    if ($nvidia_stats) {
        $valores = explode(',', trim($nvidia_stats));
        if (count($valores) >= 2) {
            $stats['gpu_extra'] = [
                'temp' => (int) trim($valores[0]),
                'util' => (int) trim($valores[1])
            ];
        }
    }
    // 👆 HASTA AQUÍ 👆

    echo json_encode($stats);
    exit();
}

?>