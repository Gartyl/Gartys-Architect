<?php
// ==============================================================================
// --- MÓDULO GALERÍA: FAVORITOS, VISIBILIDAD, FFMPEG Y GESTIÓN DE ARCHIVOS ---
// ==============================================================================

if ($action === 'toggle_public') {
    $prompt_id = intval($_POST['prompt_id'] ?? 0);
    $estado = intval($_POST['estado'] ?? 0);
    $stmt = $pdo->prepare("UPDATE historial_prompts SET is_public = ? WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$estado, $prompt_id, $user_id]);
    echo json_encode(['success' => $success]);
    exit();
}

if ($action === 'toggle_favorito') {
    $prompt_id = intval($_POST['prompt_id'] ?? 0);
    $estado = intval($_POST['estado'] ?? 0);
    $stmt = $pdo->prepare("UPDATE historial_prompts SET favorito = ? WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$estado, $prompt_id, $user_id]);
    echo json_encode(['success' => $success]);
    exit();
}

if ($action === 'get_recent_images') {
    $page = max(1, intval($_POST['page'] ?? 1));
    $limit = 24;
    $offset = ($page - 1) * $limit;
    
    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM historial_prompts WHERE user_id = ? AND imagen_path IS NOT NULL");
    $stmt_total->execute([$user_id]);
    $total = $stmt_total->fetchColumn();
    
    $total_pages = ceil($total / $limit);
    if ($total_pages < 1) $total_pages = 1;

    $stmt = $pdo->prepare("SELECT id, imagen_path FROM historial_prompts WHERE user_id = ? AND imagen_path IS NOT NULL ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['images' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'current_page' => $page, 'total_pages' => $total_pages]);
    exit();
}

if ($action === 'eliminar_prompt') {
    $prompt_id = intval($_POST['prompt_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT imagen_path FROM historial_prompts WHERE id = ? AND user_id = ?");
    $stmt->execute([$prompt_id, $user_id]);
    $row = $stmt->fetch();
    
    if ($row && !empty($row['imagen_path'])) {
        $filepath = __DIR__ . '/../galeria/' . $row['imagen_path'];
        if (file_exists($filepath)) { @unlink($filepath); }
    }
    
    $stmt = $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND user_id = ?");
    $success = $stmt->execute([$prompt_id, $user_id]);
    echo json_encode(['success' => $success]);
    exit();
}

// ==============================================================================
// --- OBTENER PROMPT PARA REUTILIZAR EN PORTADA ---
// ==============================================================================

if ($action === 'get_single_image_data') {
    $img_id = intval($_POST['img_id']);
    // Permitimos cargar el prompt si es del usuario O si está puesto como público en la Galería
    $stmt = $pdo->prepare("SELECT modelo, metadata, descripcion_original, prompt_positivo, prompt_negativo FROM historial_prompts WHERE id = ? AND (user_id = ? OR is_public = 1)");
    $stmt->execute([$img_id, $user_id]);
    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit();
}

if ($action === 'concatenar_videos') {
    $videos_json = $_POST['videos_array'] ?? '[]';
    $audio_data = $_POST['audio_data'] ?? null;
    $tramos = json_decode($videos_json, true);
    
    if (empty($tramos) || count($tramos) < 2) { echo json_encode(['error' => __('err_ffmpeg_not_enough_clips')]); exit(); }

    $tmp_dir = sys_get_temp_dir();
    $lista_txt_path = $tmp_dir . '/lista_concat_' . uniqid() . '.txt';
    $lista_content = "";
    
    foreach ($tramos as $tramo) {
        $path = __DIR__ . '/../galeria/' . basename($tramo);
        if (file_exists($path)) { $lista_content .= "file '" . str_replace('\\', '/', $path) . "'\n"; }
    }
    file_put_contents($lista_txt_path, $lista_content);

    $primer_video = __DIR__ . '/../galeria/' . basename($tramos[0]);
    $lienzo_w = 1280; $lienzo_h = 720;

    if (file_exists($primer_video)) {
        $probe_cmd = "ffprobe -v error -select_streams v:0 -show_entries stream=width,height -of csv=s=x:p=0 " . escapeshellarg($primer_video);
        $dimensiones = exec($probe_cmd);
        if (!empty($dimensiones) && strpos($dimensiones, 'x') !== false) {
            list($lienzo_w, $lienzo_h) = explode('x', trim($dimensiones));
        }
    }

    $nombre_final = 'byGarty_LTX_Epic_' . time() . '.mp4';
    $ruta_final = __DIR__ . '/../galeria/' . $nombre_final;
    //$vf_scale = "scale={$lienzo_w}:{$lienzo_h}:force_original_aspect_ratio=decrease,pad={$lienzo_w}:{$lienzo_h}:(ow-iw)/2:(oh-ih)/2,fps=24";
	
	// NUEVO: Recibimos los FPS reales (por defecto 16 si no llega nada)
    $video_fps = isset($_POST['video_fps']) ? intval($_POST['video_fps']) : 16;
    if ($video_fps <= 0) $video_fps = 16;

    $nombre_final = 'byGarty_LTX_Epic_' . time() . '.mp4';
    $ruta_final = __DIR__ . '/../galeria/' . $nombre_final;
    
    // Inyectamos la variable $video_fps en lugar del 24 estático
    $vf_scale = "scale={$lienzo_w}:{$lienzo_h}:force_original_aspect_ratio=decrease,pad={$lienzo_w}:{$lienzo_h}:(ow-iw)/2:(oh-ih)/2,fps={$video_fps}";

    if (!empty($audio_data)) {
        $tmp_audio = $tmp_dir . '/audio_concat_' . uniqid() . '.mp3';
        file_put_contents($tmp_audio, base64_decode($audio_data));
        // Fusión H.265 con Audio: Añadido -shortest para alinear el final del audio al milisegundo exacto en que acaba la pista de vídeo
        $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($lista_txt_path) . " -i " . escapeshellarg($tmp_audio) . " -vf \"{$vf_scale}\" -c:v libx265 -preset fast -crf 12 -pix_fmt yuv420p10le -tag:v hvc1 -c:a aac -map 0:v:0 -map 1:a:0 -shortest " . escapeshellarg($ruta_final) . " 2>&1";
    } else {
        // Fusión H.265 sin Audio: Alta compresión (libx265), Máxima Calidad (crf 12), 8-bits web (yuv420p10le) y Etiqueta Apple/Google (hvc1)
        $cmd = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($lista_txt_path) . " -vf \"{$vf_scale}\" -c:v libx265 -preset fast -crf 12 -pix_fmt yuv420p10le -tag:v hvc1 " . escapeshellarg($ruta_final) . " 2>&1";
    }

    exec($cmd, $output, $return_var);
    @unlink($lista_txt_path);
    if (isset($tmp_audio)) @unlink($tmp_audio);

    if ($return_var === 0 && file_exists($ruta_final)) {
        $video_base64 = base64_encode(file_get_contents($ruta_final));

        try {
            global $pdo; 
            $nombre_primer_tramo = basename($tramos[0]);
            $stmt = $pdo->prepare("SELECT user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata FROM historial_prompts WHERE imagen_path = ?");
            $stmt->execute([$nombre_primer_tramo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $row = [
                    'user_id' => $_SESSION['user_id'] ?? 1, 'modelo' => __('lbl_manual_fusion'), 'descripcion_original' => __('lbl_fusion_description'),
                    'prompt_positivo' => '', 'prompt_negativo' => '', 'metadata' => '{"Nota":"' . __('lbl_fusion_metadata') . '"}'
                ];
            }

            $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$row['user_id'], $row['modelo'], $row['descripcion_original'], $row['prompt_positivo'], $row['prompt_negativo'], $nombre_final, $row['metadata']]);
            
            $borrar_origenes = filter_var($_POST['borrar_origenes'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
            if ($borrar_origenes) {
                $nombres_limpios = array_map('basename', $tramos);
                $inQuery = implode(',', array_fill(0, count($nombres_limpios), '?'));
                $pdo->prepare("DELETE FROM historial_prompts WHERE imagen_path IN ($inQuery)")->execute($nombres_limpios);
                foreach ($tramos as $tramo) { @unlink(__DIR__ . '/../galeria/' . basename($tramo)); }
            }
        } catch (Exception $e) { error_log(__('log_err_gallery_sync') . ": " . $e->getMessage()); }

        echo json_encode(['status' => 'completed', 'images' => [$video_base64], 'final_filename' => $nombre_final]);
    } else {
        echo json_encode(['error' => __('err_ffmpeg_assembly'), 'log' => $output]);
    }
    exit();
}

if ($action === 'subir_video_externo') {
    if (isset($_FILES['video_file'])) {
        $ext = strtolower(pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['mp4', 'webm'])) {
            $new_name = 'byGarty_Externo_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['video_file']['tmp_name'], __DIR__ . '/../galeria/' . $new_name);
            echo json_encode(['status' => 'completed', 'filename' => $new_name]);
        } else {
            echo json_encode(['error' => __('err_unsupported_format')]);
        }
    }
    exit();
}
?>