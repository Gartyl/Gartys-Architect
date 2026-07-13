<?php
// ==============================================================================
// --- MÓDULO RADAR: EL ÁNGEL DE LA GUARDIA Y ESTADO DE COLAS ---
// ==============================================================================

if ($action === 'angel_guardia') {
    ignore_user_abort(true);
    set_time_limit(600); // 10 Minutos de vida independiente
    
    $prompt_id = $_POST['prompt_id'] ?? '';
    $historial_id = intval($_POST['historial_id'] ?? 0);
    $user_id_angel = intval($_POST['user_id'] ?? 0);
    
    if (empty($prompt_id) || $historial_id <= 0 || $user_id_angel <= 0) exit();
    
    for ($i = 0; $i < 120; $i++) {
        sleep(5);
        
        $ch = curl_init(COMFY_URL . '/history/' . $prompt_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res_hist = curl_exec($ch);
        
        $history = json_decode($res_hist, true);
        
        if (isset($history[$prompt_id])) {
            $filenames_for_db = [];
            $outputs = isset($history[$prompt_id]['outputs']) ? $history[$prompt_id]['outputs'] : [];
            
            if (is_array($outputs)) {
                foreach ($outputs as $node_id => $output) {
                    $files = [];
                    if (isset($output['images']) && is_array($output['images'])) $files = array_merge($files, $output['images']);
                    if (isset($output['gifs']) && is_array($output['gifs']))   $files = array_merge($files, $output['gifs']);
                    if (isset($output['videos']) && is_array($output['videos'])) $files = array_merge($files, $output['videos']);
                    
                    foreach ($files as $file_info) {
                        $filename = isset($file_info['filename']) ? $file_info['filename'] : '';
                        $subfolder = isset($file_info['subfolder']) ? $file_info['subfolder'] : '';
                        $type = isset($file_info['type']) ? $file_info['type'] : 'output';

                        if (empty($filename)) continue;

                        $file_url = COMFY_URL . '/view?filename=' . urlencode($filename) . '&subfolder=' . urlencode($subfolder) . '&type=' . urlencode($type);
                        
                        $ch_file = curl_init($file_url);
                        curl_setopt($ch_file, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch_file, CURLOPT_TIMEOUT, 30);
                        $file_data = curl_exec($ch_file);
                        $http_code = curl_getinfo($ch_file, CURLINFO_HTTP_CODE);
                        
                        if ($file_data && $http_code === 200) {
                            $ext = pathinfo($filename, PATHINFO_EXTENSION);
                            if (empty($ext)) $ext = 'png';
                            
                            $new_name = 'byGarty_' . md5($prompt_id . $filename) . '.' . $ext;
                            
                            @file_put_contents(__DIR__ . '/../galeria/' . $new_name, $file_data);
                            if (!in_array($new_name, $filenames_for_db)) {
                                $filenames_for_db[] = $new_name;
                            }
                        }
                    }
                }
            }

            if (!empty($filenames_for_db)) {
                try {
                    $stmt_check = $pdo->prepare("SELECT modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata, imagen_path FROM historial_prompts WHERE id = ?");
                    $stmt_check->execute([$historial_id]);
                    $row = $stmt_check->fetch();
                    $stmt_check->closeCursor(); 

                    if ($row) {
                        $is_first = true;
                        foreach ($filenames_for_db as $fn) {
                            
                            $stmt_dup = $pdo->prepare("SELECT COUNT(id) FROM historial_prompts WHERE imagen_path = ?");
                            $stmt_dup->execute([$fn]);
                            $existe = $stmt_dup->fetchColumn() > 0;
                            
                            if (!$existe) {
                                if ($is_first && empty($row['imagen_path'])) {
                                    $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET imagen_path = ? WHERE id = ?");
                                    $stmt_upd->execute([$fn, $historial_id]);
                                    $row['imagen_path'] = $fn; 
                                } else {
                                    $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                    $stmt_ins->execute([$user_id_angel, $row['modelo'], $row['descripcion_original'], $row['prompt_positivo'], $row['prompt_negativo'], $fn, $row['metadata']]);
                                }
                            }
                            $is_first = false;
                        }
                    }
                } catch (Exception $e) { }
            } else {
                try {
                    $stmt_del = $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL");
                    $stmt_del->execute([$historial_id]);
                } catch (Exception $e) {}
            }
            exit(); 
        }
        
        $ch_q = curl_init(COMFY_URL . '/queue');
        curl_setopt($ch_q, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_q, CURLOPT_TIMEOUT, 3);
        $res_q = curl_exec($ch_q);
        
        $queue = json_decode($res_q, true);
        $is_in_queue = false;
        if (is_array($queue)) {
            foreach (['queue_running', 'queue_pending'] as $q_type) {
                if (isset($queue[$q_type])) {
                    foreach ($queue[$q_type] as $item) {
                        if (isset($item[1]) && (string)$item[1] === (string)$prompt_id) { $is_in_queue = true; break 2; }
                    }
                }
            }
        }
        if (!$is_in_queue && $res_q) {
            try { $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL")->execute([$historial_id]); } catch (Exception $e) {}
            exit();
        }
    }
    exit(); 
}

if ($action === 'check_ticket') {
    $prompt_id = $_POST['prompt_id'] ?? '';
    $historial_id = intval($_POST['historial_id'] ?? 0);

    if (empty($prompt_id) || $historial_id <= 0) {
        echo json_encode(['error' => __('err_missing_ticket')]);
        exit();
    }

    $stmt = $pdo->prepare("SELECT imagen_path, user_id, descripcion_original FROM historial_prompts WHERE id = ?");
    $stmt->execute([$historial_id]);
    $row = $stmt->fetch();

    if ($row && !empty($row['imagen_path'])) {
        $stmt_lote = $pdo->prepare("SELECT imagen_path FROM historial_prompts WHERE user_id = ? AND descripcion_original = ? AND id >= ? AND imagen_path IS NOT NULL LIMIT 10");
        $stmt_lote->execute([$row['user_id'], $row['descripcion_original'], $historial_id]);
        $lote = $stmt_lote->fetchAll(PDO::FETCH_COLUMN);
        
        $filenames = !empty($lote) ? $lote : [$row['imagen_path']];
        
        echo json_encode(['status' => 'completed', 'images' => $filenames, 'filenames' => $filenames]);
        exit();
    }

    $ch_q = curl_init(COMFY_URL . '/queue');
    curl_setopt($ch_q, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_q, CURLOPT_TIMEOUT, 3);
    $res_q = curl_exec($ch_q);
    
    $queue = json_decode($res_q, true);
    $is_in_queue = false;
    
    if (is_array($queue)) {
        foreach (['queue_running', 'queue_pending'] as $q_type) {
            if (isset($queue[$q_type])) {
                foreach ($queue[$q_type] as $item) {
                    if (isset($item[1]) && (string)$item[1] === (string)$prompt_id) { $is_in_queue = true; break 2; }
                }
            }
        }
    }
    
    if ($is_in_queue) {
        echo json_encode(['status' => 'processing']);
        exit();
    }
    
    $ch_h = curl_init(COMFY_URL . '/history/' . $prompt_id);
    curl_setopt($ch_h, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_h, CURLOPT_TIMEOUT, 3);
    $res_h = curl_exec($ch_h);
    curl_close($ch_h);
    
    $history = json_decode($res_h, true);
    if (isset($history[$prompt_id])) {
        if (isset($history[$prompt_id]['error']) || (isset($history[$prompt_id]['status']['status_str']) && $history[$prompt_id]['status']['status_str'] === 'error')) {
            $node_fail = $history[$prompt_id]['error']['node_type'] ?? __('err_unknown_node');
            $exception = $history[$prompt_id]['error']['exception_message'] ?? __('err_vram_gpu_fail');

            $historial_raw = json_encode($history[$prompt_id] ?? []);
            file_put_contents(__DIR__ . '/../debug_comfy.txt', $historial_raw);

            if (strpos($historial_raw, 'mat1 and mat2') !== false) {
                $exception = __('err_mat_mismatch');
                $node_fail = "ControlNet";
            }
            
            try { $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL")->execute([$historial_id]); } catch (Exception $e) {}
            
            echo json_encode(['error' => __('err_engine_aborted') . " [$node_fail]: $exception"]);
            exit();
        }

        $outputs = $history[$prompt_id]['outputs'] ?? [];
        if (empty($outputs)) {
            try { $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL")->execute([$historial_id]); } catch (Exception $e) {}
            echo json_encode(['error' => __('err_ghost_task') ?? 'ComfyUI descartó la tarea silenciosamente. Tarea cancelada.']);
            exit();
        }

        $filenames_for_db = [];
        foreach ($outputs as $node_id => $output) {
            $files = [];
            if (isset($output['images'])) $files = array_merge($files, $output['images']);
            if (isset($output['gifs']))   $files = array_merge($files, $output['gifs']);
            if (isset($output['videos'])) $files = array_merge($files, $output['videos']);
            
            foreach ($files as $file_info) {
                $filename = $file_info['filename'] ?? '';
                $subfolder = $file_info['subfolder'] ?? '';
                $type = $file_info['type'] ?? 'output';
                if (empty($filename)) continue;
                
                $file_url = COMFY_URL . '/view?filename=' . urlencode($filename) . '&subfolder=' . urlencode($subfolder) . '&type=' . urlencode($type);
                $ch_file = curl_init($file_url);
                curl_setopt($ch_file, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_file, CURLOPT_TIMEOUT, 15);
                $file_data = curl_exec($ch_file);
                
                if ($file_data && curl_getinfo($ch_file, CURLINFO_HTTP_CODE) === 200) {
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $new_name = 'byGarty_' . md5($prompt_id . $filename) . '.' . ($ext ?: 'png');
                    @file_put_contents(__DIR__ . '/../galeria/' . $new_name, $file_data);
                    
                    if (!in_array($new_name, $filenames_for_db)) {
                        $filenames_for_db[] = $new_name;
                    }
                }
            }
        }

        if (!empty($filenames_for_db)) {
            $stmt_check = $pdo->prepare("SELECT user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata FROM historial_prompts WHERE id = ?");
            $stmt_check->execute([$historial_id]);
            $row_meta = $stmt_check->fetch();
            
            if ($row_meta) {
                $is_first = true;
                foreach ($filenames_for_db as $fn) {
                    $stmt_dup = $pdo->prepare("SELECT id FROM historial_prompts WHERE imagen_path = ?");
                    $stmt_dup->execute([$fn]);
                    
                    if (!$stmt_dup->fetch()) {
                        if ($is_first) {
                            $pdo->prepare("UPDATE historial_prompts SET imagen_path = ? WHERE id = ? AND imagen_path IS NULL")->execute([$fn, $historial_id]);
                        } else {
                            $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)")->execute([$row_meta['user_id'], $row_meta['modelo'], $row_meta['descripcion_original'], $row_meta['prompt_positivo'], $row_meta['prompt_negativo'], $fn, $row_meta['metadata']]);
                        }
                    }
                    $is_first = false;
                }
            }
            echo json_encode(['status' => 'completed', 'images' => $filenames_for_db, 'filenames' => $filenames_for_db]);
        } else {
            echo json_encode(['error' => __('err_php_download_fail') ?? 'La imagen está en ComfyUI, pero el servidor PHP no ha podido descargarla.']);
        }
        exit();
    } 
    
    try { $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL")->execute([$historial_id]); } catch (Exception $e) {}
    echo json_encode(['error' => __('err_ghost_task') ?? 'ComfyUI ha reiniciado o descartado la tarea por completo.']);
    exit(); 
}

if ($action === 'check_queue') {
    $ch = curl_init(COMFY_URL . '/queue');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
    $res = curl_exec($ch);

    if ($res) {
        $queue = json_decode($res, true);
        $running = isset($queue['queue_running']) ? count($queue['queue_running']) : 0;
        $pending = isset($queue['queue_pending']) ? count($queue['queue_pending']) : 0;
        $total = $running + $pending;
        
        echo json_encode(['status' => 'ok', 'total' => $total]);
    } else {
        echo json_encode(['status' => 'error', 'total' => 0]);
    }
    exit(); 
}

if ($action === 'cancelar_tarea') {
    // 1. ORDEN DE INTERRUPCIÓN: Detiene físicamente lo que está procesando la tarjeta gráfica
    $ch1 = curl_init(COMFY_URL . '/interrupt');
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 3);
    curl_exec($ch1);
    curl_close($ch1);

    // 2. ORDEN DE LIMPIEZA: Borra todas las imágenes o frames pendientes en la cola
    $ch2 = curl_init(COMFY_URL . '/queue');
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['clear' => true]));
    curl_setopt($ch2, CURLOPT_TIMEOUT, 3);
    curl_exec($ch2);
    curl_close($ch2);

    // 3. Devolvemos respuesta en formato JSON usando el diccionario de idiomas
    echo json_encode(['status' => 'cancelled', 'message' => __('msg_task_cancelled')]);
    exit();
}
?>