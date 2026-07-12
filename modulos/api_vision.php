<?php
// ==============================================================================
// --- MÓDULO VISIÓN: EXTRACCIÓN DE PROMPTS Y DESCRIPCIÓN ---
// ==============================================================================

if ($action === 'vision_extract') {
    $base64_image = $_POST['image'] ?? '';
    $idioma_usuario = strtolower($_POST['idioma'] ?? $lang);
    $proposito = $_POST['proposito'] ?? 'extension'; 
    
    if (empty($base64_image)) {
        echo json_encode(['error' => __('err_no_image_received')]);
        exit();
    }

    $visionModel = '';
    $promptVis = '';
    $temp_vision = 0.0;

    try {
        if ($proposito === 'exhaustivo') {
            $stmtModel = $pdo->prepare("SELECT nombre_archivo FROM modelos_ia WHERE UPPER(categoria) = 'VISION' AND activo = 1 LIMIT 1");
            $stmtModel->execute();
            $visionModel = $stmtModel->fetchColumn();

            $stmtPrompt = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'vision_analyst' AND LOWER(idioma) = LOWER(?) AND activo = 1 LIMIT 1");
            $stmtPrompt->execute([$idioma_usuario]);
            $resultado_vision = $stmtPrompt->fetch(PDO::FETCH_ASSOC);

            if (!$resultado_vision) {
                $stmtPrompt->execute(['en']);
                $resultado_vision = $stmtPrompt->fetch(PDO::FETCH_ASSOC);
            }

            $promptVis = $resultado_vision ? $resultado_vision['prompt_texto'] : "Describe this image in detail, literally and objectively.";
            
            if ($resultado_vision && !empty($resultado_vision['parametros'])) {
                $json_params = json_decode($resultado_vision['parametros'], true);
                if (isset($json_params['temperature'])) {
                    $temp_vision = (float)$json_params['temperature'];
                }
            }
        } else {
            $stmtModel = $pdo->prepare("SELECT nombre_archivo FROM modelos_ia WHERE categoria = 'SYS_VISION' AND activo = 1 LIMIT 1");
            $stmtModel->execute();
            $visionModel = $stmtModel->fetchColumn();

            // Si no hay SYS_VISION, salvavidas: pillamos el grande de forma estricta
            if (empty($visionModel)) {
                $stmtModel = $pdo->prepare("SELECT nombre_archivo FROM modelos_ia WHERE UPPER(categoria) = 'VISION' AND activo = 1 LIMIT 1");
                $stmtModel->execute();
                $visionModel = $stmtModel->fetchColumn();
            }

            $promptVis = "Analyze this image and describe it using only a comma-separated list of highly descriptive keywords. Focus on subjects, style, and composition. Do not use full sentences, and DO NOT use any prefixes or labels like [REFERENCE]: or [PROMPT]:.";
            $temp_vision = 0.0; 
        }

        if (empty($visionModel)) {
            echo json_encode(['error' => __('err_no_vision_model_db')]);
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error consultando la base de datos: ' . $e->getMessage()]);
        exit();
    }

    $data = [
        "model" => $visionModel,
        "prompt" => (string)$promptVis,
        "images" => [$base64_image],
        "stream" => false,
        "keep_alive" => 0,
        "options" => [
            "temperature" => $temp_vision
        ]
    ];

    $ch = curl_init('http://' . LLM_IP . ':11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); 

    $respuesta = curl_exec($ch);

    if ($respuesta) {
        $resultado = json_decode($respuesta, true);
        if (isset($resultado['error'])) {
            echo json_encode(['error' => $resultado['error']]);
        } else {
            $texto_crudo = $resultado['response'] ?? '';
            $texto_limpio = preg_replace('/^\[.*?\]:\s*/', '', $texto_crudo);
            $texto_limpio = trim($texto_limpio); 
            echo json_encode(['response' => $texto_limpio]);
        }
    } else {
        echo json_encode(['error' => __('err_php_ollama_conn')]);
    }
    exit();
}

if ($action === 'describir_imagen') {
    $image_data = $_POST['image_data'] ?? '';
    $base64_clean = preg_replace('#^data:image/[^;]+;base64,#', '', $image_data);
    
    $idioma_usuario = $_POST['idioma'] ?? 'ES';
    
	// 1. OBTENER MODELO DE VISIÓN (Búsqueda estricta y segura)
    $stmt_vis = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE UPPER(categoria) = 'VISION' AND motor = 'ollama' AND activo = 1 LIMIT 1");
	$vision_model_dinamico = $stmt_vis->fetchColumn();
    
    if (!$vision_model_dinamico) {
        echo json_encode(['error' => __('err_no_vision_model_active')]);
        exit();
    }

    $stmtAnalista = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'vision_analyst' AND activo = 1 AND LOWER(idioma) = LOWER(?) LIMIT 1");
    $stmtAnalista->execute([$idioma_usuario]);
    $resultado_analista = $stmtAnalista->fetch(PDO::FETCH_ASSOC);

    if (!$resultado_analista) {
        $stmtAnalista = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE LOWER(tipo) = 'vision_analyst' AND activo = 1 AND LOWER(idioma) = 'es' LIMIT 1");
        $stmtAnalista->execute();
        $resultado_analista = $stmtAnalista->fetch(PDO::FETCH_ASSOC);
    }

    if (!$resultado_analista || empty($resultado_analista['prompt_texto'])) {
        echo json_encode(['error' => __('err_vision_analyst_not_found')]);
        exit();
    }

    $prompt_vision = $resultado_analista['prompt_texto'];
    $temperatura_vision = 0.2; 

    if (!empty($resultado_analista['parametros'])) {
        $json_params = json_decode($resultado_analista['parametros'], true);
        if (isset($json_params['temperature'])) {
            $temperatura_vision = (float)$json_params['temperature'];
        }
    }

    $payload = [
        "model" => $vision_model_dinamico, 
        "messages" => [
            [
                "role" => "user", 
                "content" => $prompt_vision, 
                "images" => [$base64_clean]
            ]
        ],
        "stream" => false,
        "keep_alive" => 0,
        "options" => [
            "temperature" => $temperatura_vision
        ]
    ];
    
    $ch = curl_init("http://" . LLM_IP . ":11434/api/chat");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $api_res = curl_exec($ch);
    
    if ($api_res === false) { 
        echo json_encode(['error' => __('err_vision_conn_fail') . ': ' . curl_error($ch)]); 
        exit(); 
    }
    
    $res = json_decode($api_res, true);
    
    if (isset($res['message']['content'])) {
        $raw = $res['message']['content'];
        
        $clean = preg_replace('/<think>.*?<\/think>/is', '', $raw);
        if ($clean === null) $clean = $raw; 
        
        $clean = preg_replace('/<think>.*/is', '', $clean); 
        if ($clean === null) $clean = $raw;
        
        $clean = trim(strip_tags($clean));
        
        if (empty($clean)) { 
            echo json_encode(['error' => __('err_vision_vram_loading')]);
            exit();
        }
        
        echo json_encode([
            'choices' => [
                [ 'message' => [ 'content' => $clean ] ]
            ]
        ]);
        
    } else {
        echo json_encode(['error' => __('err_ollama_reject') . ' ' . $api_res]);
    }
    exit();
}
?>