<?php
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => __('err_session_expired')]);
    exit();
}

// 👇 AÑADE ESTAS 4 LÍNEAS COMO ESCUDO ANTI-HACKERS FREE 👇
if (!isset($is_pro) || !$is_pro) {
    echo json_encode(['error' => __('err_pro_exclusive')]);
    exit();
}
// 👆 -------------------------------------------------- 👆

$user_id = $_SESSION['user_id'];

// ---------------------------------------------------------
// OBTENER CARAS DEL USUARIO
// ---------------------------------------------------------
if ($action === 'obtener_caras_reactor') {
    try {
        $stmt = $pdo->prepare("SELECT id, face_name, filename FROM reactor_faces WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id]);
        $caras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'caras' => $caras]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// ---------------------------------------------------------
// GUARDAR NUEVA CARA (.SAFETENSORS) EN COMFYUI
// ---------------------------------------------------------
if ($action === 'guardar_cara_reactor') {
    $face_name = trim($_POST['face_name'] ?? '');
    $image_base64 = $_POST['image'] ?? '';

    if (empty($face_name) || empty($image_base64)) {
        echo json_encode(['error' => __('err_missing_data') ?? 'Faltan datos.']);
        exit();
    }

    try {
        // 1. Subir la imagen temporal a ComfyUI
        $img_data = strpos($image_base64, 'base64,') !== false ? explode('base64,', $image_base64)[1] : $image_base64;
        $tmp_file = sys_get_temp_dir() . '/reactor_new_' . uniqid() . '.png';
        file_put_contents($tmp_file, base64_decode($img_data));
        
        $cfile = function_exists('curl_file_create') ? curl_file_create($tmp_file, 'image/png', 'reactor_new.png') : '@' . realpath($tmp_file);
        $ch_up = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_up, CURLOPT_POST, true);
        curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile]);
        curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
        $res_up = json_decode(curl_exec($ch_up), true);
        @unlink($tmp_file);

        if (!isset($res_up['name'])) {
			throw new Exception(__('err_upload_reference_failed'));
		}

        // 2. Crear workflow mínimo para aislar el rostro y guardarlo
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $face_name));
        $safe_filename = "usr_" . $user_id . "_" . $safe_name . "_" . time(); 
        
        $workflow = [
            "1" => [
                "inputs" => ["image" => $res_up['name'], "upload" => "image"],
                "class_type" => "LoadImage"
            ],
            "2" => [
                "inputs" => [
                    "save_mode" => true,
                    "face_model_name" => $safe_filename, // <--- AQUí SÍ ES "face_model_name" para el Guardado
                    "select_face_index" => 0,
                    "image" => ["1", 0]
                ],
                "class_type" => "ReActorSaveFaceModel"
            ]
        ];

        // 3. Enviar a la cola de ComfyUI
        $ch = curl_init(COMFY_URL . "/prompt");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["prompt" => $workflow]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $res = json_decode(curl_exec($ch), true);

        if (!isset($res['prompt_id'])) {
			throw new Exception(__('err_extractor_node_failed'));
		}

        // 4. Guardar registro en base de datos local
        // Guardamos el original ($face_name) para la interfaz
        // y el sanitizado con la extensión ($safe_filename) para el sistema de archivos
        $archivo_final = $safe_filename . ".safetensors";
        
        $stmt = $pdo->prepare("INSERT INTO reactor_faces (user_id, face_name, filename) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $face_name, $archivo_final]);
        
        echo json_encode([
            'success' => true, 
            'cara' => [
                'id' => $pdo->lastInsertId(),
                'face_name' => $face_name,
                'filename' => $archivo_final
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// ---------------------------------------------------------
// ELIMINAR CARA (.SAFETENSORS)
// ---------------------------------------------------------
if ($action === 'eliminar_cara_reactor') {
    $filename = $_POST['filename'] ?? '';
    
    if (empty($filename)) {
        echo json_encode(['error' => __('err_missing_data') ?? 'Falta el nombre del archivo.']);
        exit();
    }
    
    try {
        // 1. ELIMINAR EL ARCHIVO FÍSICO EN COMFYUI
        $ruta_checkpoints = defined('COMFY_MODEL_PATH') ? rtrim(COMFY_MODEL_PATH, '/\\') : "";
        if (!empty($ruta_checkpoints)) {
            // Asumimos que COMFY_MODEL_PATH apunta a "models/checkpoints", subimos un nivel
            $base_models_dir = dirname($ruta_checkpoints);
            $ruta_fisica = $base_models_dir . DIRECTORY_SEPARATOR . 'reactor' . DIRECTORY_SEPARATOR . 'faces' . DIRECTORY_SEPARATOR . basename($filename);
            
            if (file_exists($ruta_fisica)) {
                @unlink($ruta_fisica); // Borra el archivo físico del disco
            }
        }

        // 2. ELIMINAR EL REGISTRO DE LA BASE DE DATOS
        $stmt = $pdo->prepare("DELETE FROM reactor_faces WHERE user_id = ? AND filename = ?");
        $stmt->execute([$user_id, $filename]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}