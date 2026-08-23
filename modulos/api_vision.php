<?php
// ==============================================================================
// --- MÓDULO VISIÓN: MICROSERVICIO PARA HERRAMIENTAS INTERNAS (ControlNet/IP-Adapter) ---
// ==============================================================================

if ($action === 'vision_extract') {
    $base64_image = $_POST['image'] ?? '';
    
    if (empty($base64_image)) {
        echo json_encode(['error' => __('err_no_image_received')]);
        exit();
    }

    try {
        // 1. Buscamos el motor de visión en la sombra (SYS_VISION)
        $stmtModel = $pdo->prepare("SELECT nombre_archivo FROM modelos_ia WHERE categoria = 'SYS_VISION' AND activo = 1 LIMIT 1");
        $stmtModel->execute();
        $visionModel = $stmtModel->fetchColumn();

        // 2. Salvavidas: si no hay SYS_VISION, pillamos el general de visión
        if (empty($visionModel)) {
            $stmtModel = $pdo->prepare("SELECT nombre_archivo FROM modelos_ia WHERE UPPER(categoria) = 'VISION' AND activo = 1 LIMIT 1");
            $stmtModel->execute();
            $visionModel = $stmtModel->fetchColumn();
        }

        if (empty($visionModel)) {
            echo json_encode(['error' => __('err_no_vision_model_db')]);
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error consultando la base de datos: ' . $e->getMessage()]);
        exit();
    }

    // Prompt estático ultrarrápido (Solo necesitamos tags puros, sin literatura)
    $promptVis = "Analyze this image and describe it using only a comma-separated list of highly descriptive keywords. Focus on subjects, style, and composition. Do not use full sentences, and DO NOT use any prefixes or labels like [REFERENCE]: or [PROMPT]:.";
    
    $data = [
        "model" => $visionModel,
        "prompt" => $promptVis,
        "images" => [$base64_image],
        "stream" => false,
        "keep_alive" => 0,
        "options" => [
            "temperature" => 0.0 // Cero creatividad, solo hechos objetivos
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
?>