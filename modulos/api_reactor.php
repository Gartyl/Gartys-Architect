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
        $stmt = $pdo->prepare("SELECT id, face_name, filename FROM reactor_faces WHERE user_id = ? ORDER BY face_name ASC");
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
                    "face_model_name" => $safe_filename,
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
        // 1. CONSTRUIR LA RUTA FÍSICA A COMFYUI
        $base_models_dir = defined('COMFY_MODELS_DIR') ? rtrim(COMFY_MODELS_DIR, '/\\') : 'C:/ComfyUI/models';
        $ruta_fisica = $base_models_dir . '/reactor/faces/' . basename($filename);
        
        // Forzamos barras correctas para Windows (F:\ComfyUI\...)
        $ruta_fisica = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $ruta_fisica);

        $mensaje_debug = "";

        // 2. ELIMINAR EL ARCHIVO FÍSICO DIRECTAMENTE
        if (file_exists($ruta_fisica)) {
            // Borrado fulminante de disco (sin papelera)
            if (!@unlink($ruta_fisica)) {
                $mensaje_debug = " No se ha podido borrar el archivo físico (Windows denegó el permiso o está en uso por ComfyUI): " . $ruta_fisica;
            }
        } else {
            $mensaje_debug = " El archivo físico no existía en esta ruta: " . $ruta_fisica;
        }

        // 3. ELIMINAR EL REGISTRO DE LA BASE DE DATOS
        $stmt = $pdo->prepare("DELETE FROM reactor_faces WHERE user_id = ? AND filename = ?");
        $stmt->execute([$user_id, $filename]);

        // Si hubo algún problema físico, mostramos la alerta para diagnosticar, pero la BD ya está limpia
        if ($mensaje_debug !== "") {
            echo json_encode(['error' => 'Registro borrado de la interfaz, pero:' . $mensaje_debug]);
        } else {
            echo json_encode(['success' => true]);
        }

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}