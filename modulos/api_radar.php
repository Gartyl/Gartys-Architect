<?php
// ==============================================================================
// --- MÓDULO RADAR: EL ÁNGEL DE LA GUARDIA Y ESTADO DE COLAS ---
// ==============================================================================

if ($action === 'angel_guardia') {
    ignore_user_abort(true);
    set_time_limit(600); // 10 Minutos de vida independiente
    
    $prompt_id = $_POST['prompt_id'] ?? '';
    $historial_id = intval($_POST['historial_id'] ?? 0);
    $user_id_angel = intval($_POST['user_id'] ?? ($_SESSION['user_id'] ?? 1));
    
    if (empty($prompt_id) || $historial_id <= 0 || $user_id_angel <= 0) exit();
    
    for ($i = 0; $i < 120; $i++) {
        // Damos ventaja a check_ticket (WebSocket) cuando el navegador está abierto
        sleep($i === 0 ? 7 : 5);
        
        // 🌟 ESCUDO ANTI-DUPLICADOS: Si el radar de la web ya guardó la imagen, el ángel se retira.
        $stmt_check_done = $pdo->prepare("SELECT imagen_path FROM historial_prompts WHERE id = ?");
        $stmt_check_done->execute([$historial_id]);
        $row_done = $stmt_check_done->fetch();
        if (!$row_done || !empty($row_done['imagen_path'])) {
            exit(); 
        }
        
        // 1. Consultamos PRIMERO la cola (/queue) antes que /history
        $ch_q = curl_init(COMFY_URL . '/queue');
        curl_setopt($ch_q, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_q, CURLOPT_TIMEOUT, 8);
        $res_q = curl_exec($ch_q);
        $err_q = curl_errno($ch_q);
        curl_close($ch_q);

        if ($err_q == 28 || $err_q == 7 || $res_q === false) {
            continue; // Sigue trabajando a tope, esperamos al siguiente ciclo
        }
        
        $queue = json_decode($res_q, true);
        $is_in_queue = false;
        if (is_array($queue)) {
            foreach (['queue_running', 'queue_pending'] as $q_type) {
                if (isset($queue[$q_type])) {
                    foreach ($queue[$q_type] as $item) {
                        if (isset($item[1]) && (string)$item[1] === (string)$prompt_id) {
                            $is_in_queue = true;
                            break 2;
                        }
                    }
                }
            }
        }

        // Si sigue en cola o ejecutándose, esperamos al siguiente ciclo
        if ($is_in_queue) {
            continue;
        }

        // 2. Si YA NO está en la cola, consultamos /history (así nunca perdemos el milisegundo final)
        $ch = curl_init(COMFY_URL . '/history/' . $prompt_id);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res_hist = curl_exec($ch);
        curl_close($ch);
        
        $history = json_decode($res_hist, true);
        
        if (isset($history[$prompt_id])) {
            // Volvemos a verificar si check_ticket se nos adelantó mientras descargábamos /history
            $stmt_check_done->execute([$historial_id]);
            $row_done_2 = $stmt_check_done->fetch();
            if ($row_done_2 && !empty($row_done_2['imagen_path'])) {
                exit();
            }

            $filenames_for_db = [];
            $outputs = isset($history[$prompt_id]['outputs']) ? $history[$prompt_id]['outputs'] : [];
            
            if (is_array($outputs)) {
                foreach ($outputs as $node_id => $output) {
                    $files = [];
                    
                    if (is_array($output)) {
                        if (!empty($output['filename'])) {
                            $files[] = $output;
                        } else {
                            foreach ($output as $key => $file_list) {
                                if (is_array($file_list)) {
                                    if (!empty($file_list['filename'])) {
                                        $files[] = $file_list;
                                    } else {
                                        foreach ($file_list as $item) {
                                            if (is_array($item) && !empty($item['filename'])) {
                                                $files[] = $item;
                                            } elseif (is_string($item) && preg_match('/\.(wav|mp3|flac|ogg|m4a|png|jpg|webp|mp4)$/i', $item)) {
                                                $files[] = ['filename' => $item, 'subfolder' => '', 'type' => 'output'];
                                            }
                                        }
                                    }
                                } elseif (is_string($file_list) && preg_match('/\.(wav|mp3|flac|ogg|m4a|png|jpg|webp|mp4)$/i', $file_list)) {
                                    $files[] = ['filename' => $file_list, 'subfolder' => '', 'type' => 'output'];
                                }
                            }
                        }
                    }
                    
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
                        curl_close($ch_file);
                        
                        if ($file_data && $http_code === 200) {
                            $ext = pathinfo($filename, PATHINFO_EXTENSION);
                            if (empty($ext)) $ext = 'png';
                            
                            $formato_salida = $_POST['image_format'] ?? 'png';
                            if ($formato_salida !== 'png' && in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'webp'])) {
                                $im = @imagecreatefromstring($file_data);
                                if ($im !== false) {
                                    ob_start();
                                    if ($formato_salida === 'webp') {
                                        imagesavealpha($im, true);
                                        imagewebp($im, null, 90);
                                    } elseif ($formato_salida === 'jpg') {
                                        $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
                                        $blanco = imagecolorallocate($bg, 255, 255, 255);
                                        imagefill($bg, 0, 0, $blanco);
                                        imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                                        imagejpeg($bg, null, 90);
                                        imagedestroy($bg);
                                        $im = $bg;
                                    }
                                    $file_data_convertida = ob_get_clean();
                                    imagedestroy($im);
                                    if (!empty($file_data_convertida)) {
                                        $file_data = $file_data_convertida;
                                        $ext = $formato_salida;
                                    }
                                }
                            }
                            
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

                    $json_origen = __DIR__ . '/../galeria/workflow_' . $historial_id . '.json';

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
                                    $new_id = $pdo->lastInsertId();
                                    if ($new_id > 0 && file_exists($json_origen)) {
                                        @copy($json_origen, __DIR__ . '/../galeria/workflow_' . $new_id . '.json');
                                    }
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
                    if ($stmt_del->rowCount() > 0) {
                        @unlink(__DIR__ . '/../galeria/workflow_' . $historial_id . '.json');
                    }
                } catch (Exception $e) {}
            }
            exit(); 
        }
        
        // Si no estaba ni en /queue ni en /history, damos 1 segundo y hacemos una doble comprobación
        // antes de declarar la tarea como muerta/cancelada.
        sleep(2);
        $ch_retry = curl_init(COMFY_URL . '/history/' . $prompt_id);
        curl_setopt($ch_retry, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_retry, CURLOPT_TIMEOUT, 5);
        $res_retry = curl_exec($ch_retry);
        curl_close($ch_retry);
        $hist_retry = json_decode($res_retry, true);

        if (isset($hist_retry[$prompt_id])) {
            continue; // Acaba de entrar en history, la procesamos en la siguiente vuelta
        }

        try {
            $stmt_del = $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL");
            $stmt_del->execute([$historial_id]);
            if ($stmt_del->rowCount() > 0) {
                @unlink(__DIR__ . '/../galeria/workflow_' . $historial_id . '.json');
            }
        } catch (Exception $e) {}
        exit();
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

    // 1. Miramos primero si el Ángel de la Guardia ya la guardó en este registro exacto
    $stmt = $pdo->prepare("SELECT imagen_path, user_id, descripcion_original FROM historial_prompts WHERE id = ?");
    $stmt->execute([$historial_id]);
    $row = $stmt->fetch();

    if ($row && !empty($row['imagen_path'])) {
        echo json_encode(['status' => 'completed', 'images' => [$row['imagen_path']], 'filenames' => [$row['imagen_path']]]);
        exit();
    }

    // 2. Comprobamos si sigue en la cola de ComfyUI (/queue)
    $ch_q = curl_init(COMFY_URL . '/queue');
    curl_setopt($ch_q, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_q, CURLOPT_TIMEOUT, 8);
    $res_q = curl_exec($ch_q);
    $err_q = curl_errno($ch_q);
    curl_close($ch_q);

    if ($err_q == 28 || $err_q == 7 || $res_q === false) {
        echo json_encode(['status' => 'processing']);
        exit();
    }
    
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
    
    // 3. Consultamos el historial de ComfyUI (/history)
    $ch_h = curl_init(COMFY_URL . '/history/' . $prompt_id);
    curl_setopt($ch_h, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_h, CURLOPT_TIMEOUT, 8);
    $res_h = curl_exec($ch_h);
    $err_h = curl_errno($ch_h);
    curl_close($ch_h);

    if ($err_h == 28 || $err_h == 7 || $res_h === false) {
        echo json_encode(['status' => 'processing']);
        exit();
    }
    
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
            
            try {
                $stmt_del = $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL");
                $stmt_del->execute([$historial_id]);
                if ($stmt_del->rowCount() > 0) {
                    @unlink(__DIR__ . '/../galeria/workflow_' . $historial_id . '.json');
                }
            } catch (Exception $e) {}
            
            echo json_encode(['error' => __('err_engine_aborted') . " [$node_fail]: $exception"]);
            exit();
        }

        $outputs = $history[$prompt_id]['outputs'] ?? [];
        if (empty($outputs)) {
            try {
                $stmt_del = $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL");
                $stmt_del->execute([$historial_id]);
                if ($stmt_del->rowCount() > 0) {
                    @unlink(__DIR__ . '/../galeria/workflow_' . $historial_id . '.json');
                }
            } catch (Exception $e) {}
            echo json_encode(['error' => __('err_ghost_task') ?? 'ComfyUI descartó la tarea silenciosamente. Tarea cancelada.']);
            exit();
        }

        $filenames_for_db = [];
        foreach ($outputs as $node_id => $output) {
            $files = [];
            
            if (is_array($output)) {
                if (!empty($output['filename'])) {
                    $files[] = $output;
                } else {
                    foreach ($output as $key => $file_list) {
                        if (is_array($file_list)) {
                            if (!empty($file_list['filename'])) {
                                $files[] = $file_list;
                            } else {
                                foreach ($file_list as $item) {
                                    if (is_array($item) && !empty($item['filename'])) {
                                        $files[] = $item;
                                    } elseif (is_string($item) && preg_match('/\.(wav|mp3|flac|ogg|m4a|png|jpg|webp|mp4)$/i', $item)) {
                                        $files[] = ['filename' => $item, 'subfolder' => '', 'type' => 'output'];
                                    }
                                }
                            }
                        } elseif (is_string($file_list) && preg_match('/\.(wav|mp3|flac|ogg|m4a|png|jpg|webp|mp4)$/i', $file_list)) {
                            $files[] = ['filename' => $file_list, 'subfolder' => '', 'type' => 'output'];
                        }
                    }
                }
            }
            
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
                curl_close($ch_file);
                
                if ($file_data && $http_code === 200) {
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    if (empty($ext)) $ext = 'png';
                    
                    $formato_salida = $_POST['image_format'] ?? 'png';
                    if ($formato_salida !== 'png' && in_array(strtolower($ext), ['png', 'jpg', 'jpeg', 'webp'])) {
                        $im = @imagecreatefromstring($file_data);
                        if ($im !== false) {
                            ob_start();
                            if ($formato_salida === 'webp') {
                                imagesavealpha($im, true);
                                imagewebp($im, null, 90);
                            } elseif ($formato_salida === 'jpg') {
                                $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
                                $blanco = imagecolorallocate($bg, 255, 255, 255);
                                imagefill($bg, 0, 0, $blanco);
                                imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                                imagejpeg($bg, null, 90);
                                imagedestroy($bg);
                                $im = $bg;
                            }
                            $file_data_convertida = ob_get_clean();
                            imagedestroy($im);
                            if (!empty($file_data_convertida)) {
                                $file_data = $file_data_convertida;
                                $ext = $formato_salida;
                            }
                        }
                    }
                    
                    $new_name = 'byGarty_' . md5($prompt_id . $filename) . '.' . $ext;
                    @file_put_contents(__DIR__ . '/../galeria/' . $new_name, $file_data);
                    if (!in_array($new_name, $filenames_for_db)) {
                        $filenames_for_db[] = $new_name;
                    }
                }
            }
        }

        if (!empty($filenames_for_db)) {
            $stmt_check = $pdo->prepare("SELECT user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata, imagen_path FROM historial_prompts WHERE id = ?");
            $stmt_check->execute([$historial_id]);
            $row_meta = $stmt_check->fetch();
            
            $json_origen = __DIR__ . '/../galeria/workflow_' . $historial_id . '.json';

            if ($row_meta) {
                $is_first = true;
                foreach ($filenames_for_db as $fn) {
                    $stmt_dup = $pdo->prepare("SELECT id FROM historial_prompts WHERE imagen_path = ?");
                    $stmt_dup->execute([$fn]);
                    
                    if (!$stmt_dup->fetch()) {
                        if ($is_first && empty($row_meta['imagen_path'])) {
                            $pdo->prepare("UPDATE historial_prompts SET imagen_path = ? WHERE id = ?")->execute([$fn, $historial_id]);
                            $row_meta['imagen_path'] = $fn;
                        } else {
                            $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$row_meta['user_id'], $row_meta['modelo'], $row_meta['descripcion_original'], $row_meta['prompt_positivo'], $row_meta['prompt_negativo'], $fn, $row_meta['metadata']]);
                            $new_id = $pdo->lastInsertId();
                            if ($new_id > 0 && file_exists($json_origen)) {
                                @copy($json_origen, __DIR__ . '/../galeria/workflow_' . $new_id . '.json');
                            }
                        }
                    }
                    $is_first = false;
                }
            } else {
                // 🛡️ PARACAÍDAS DE RESCATE: Si la fila no existía en BBDD, la insertamos para que nunca quede huérfana en /galeria
                $uid_rescue = $_SESSION['user_id'] ?? 1;
                foreach ($filenames_for_db as $fn) {
                    $stmt_dup = $pdo->prepare("SELECT id FROM historial_prompts WHERE imagen_path = ?");
                    $stmt_dup->execute([$fn]);
                    if (!$stmt_dup->fetch()) {
                        $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)")
                            ->execute([$uid_rescue, '[RECUPERADO]', 'Generación recuperada', '', '', $fn, '{}']);
                        $new_id = $pdo->lastInsertId();
                        if ($new_id > 0 && file_exists($json_origen)) {
                            @rename($json_origen, __DIR__ . '/../galeria/workflow_' . $new_id . '.json');
                        }
                    }
                }
            }
            echo json_encode(['status' => 'completed', 'images' => $filenames_for_db, 'filenames' => $filenames_for_db]);
        } else {
            echo json_encode(['error' => __('err_php_download_fail') ?? 'La imagen está en ComfyUI, pero el servidor PHP no ha podido descargarla.']);
        }
        exit();
    } 
    
    try {
        $stmt_del = $pdo->prepare("DELETE FROM historial_prompts WHERE id = ? AND imagen_path IS NULL");
        $stmt_del->execute([$historial_id]);
        if ($stmt_del->rowCount() > 0) {
            @unlink(__DIR__ . '/../galeria/workflow_' . $historial_id . '.json');
        }
    } catch (Exception $e) {}
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
    $ch1 = curl_init(COMFY_URL . '/interrupt');
    curl_setopt($ch1, CURLOPT_POST, true);
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch1, CURLOPT_TIMEOUT, 3);
    curl_exec($ch1);
    curl_close($ch1);

    $ch2 = curl_init(COMFY_URL . '/queue');
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['clear' => true]));
    curl_setopt($ch2, CURLOPT_TIMEOUT, 3);
    curl_exec($ch2);
    curl_close($ch2);

    echo json_encode(['status' => 'cancelled', 'message' => __('msg_task_cancelled')]);
    exit();
}
?>