<?php
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

if (!function_exists('inyectarMetadatosCivitaiPNG')) {
    function inyectarMetadatosCivitaiPNG($png_binary, $historial_id, $pdo) {
        if (strlen($png_binary) < 64 || substr($png_binary, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return $png_binary;
        }

        // 1. Limpiamos los chunks de ComfyUI sin hash para evitar conflictos en Civitai
        $png_binary = limpiarChunksTextoComfyPNG($png_binary);

        // 2. Obtener prompts y metadata desde historial_prompts
        $pos_prompt = '';
        $neg_prompt = '';
        $meta_db = [];

        if ($historial_id > 0 && $pdo) {
            try {
                $st = $pdo->prepare("SELECT prompt_positivo, prompt_negativo, metadata FROM historial_prompts WHERE id = ?");
                $st->execute([$historial_id]);
                if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                    $pos_prompt = trim((string)($r['prompt_positivo'] ?? ''));
                    $neg_prompt = trim((string)($r['prompt_negativo'] ?? ''));
                    $meta_db = @json_decode($r['metadata'] ?? '{}', true) ?: [];
                }
            } catch (Exception $e) {}
        }

        // 3. Extraer datos del workflow_ID.json
        $model_file = $meta_db['Model'] ?? '';
        $steps      = $meta_db['Steps'] ?? 20;
        $cfg        = $meta_db['CFG Scale'] ?? 3.5;
        $seed       = $meta_db['Seed'] ?? 0;
        $res        = $meta_db['Resolution'] ?? '1024x1024';
        if (!preg_match('/^\d+x\d+$/', (string)$res)) $res = '1024x1024';

        $sampler_raw   = 'euler';
        $scheduler_raw = 'normal';
        $loras_encontrados = [];

        $wf_path = __DIR__ . '/../galeria/workflow_' . $historial_id . '.json';
        if ($historial_id > 0 && file_exists($wf_path)) {
            $wf = @json_decode(@file_get_contents($wf_path), true);
            if (is_array($wf)) {
                foreach ($wf as $nid => $node) {
                    $ct = $node['class_type'] ?? '';
                    $in = $node['inputs'] ?? [];

                    if (in_array($ct, ['CheckpointLoaderSimple', 'UNETLoader', 'UnetLoaderGGUF']) && (string)$nid !== '902') {
                        if (!empty($in['ckpt_name'])) $model_file = $in['ckpt_name'];
                        elseif (!empty($in['unet_name'])) $model_file = $in['unet_name'];
                    }

                    if (in_array($ct, ['LoraLoader', 'LoraLoaderModelOnly']) && !empty($in['lora_name'])) {
                        $loras_encontrados[] = [
                            'file' => $in['lora_name'],
                            'strength' => $in['strength_model'] ?? 0.8
                        ];
                    }

                    if (in_array($ct, ['KSampler', 'KSamplerAdvanced']) && !in_array((string)$nid, ['901', '401'])) {
                        if (isset($in['steps'])) $steps = $in['steps'];
                        if (isset($in['cfg'])) $cfg = $in['cfg'];
                        if (isset($in['seed'])) $seed = $in['seed'];
                        elseif (isset($in['noise_seed'])) $seed = $in['noise_seed'];
                        if (!empty($in['sampler_name'])) $sampler_raw = $in['sampler_name'];
                        if (!empty($in['scheduler'])) $scheduler_raw = $in['scheduler'];
                    }
                }
            }
        }

        if (empty($model_file) || strpos($model_file, 'Audio_') === 0) {
            return $png_binary;
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
            'uni_pc' => 'UniPC'
        ];
        $sampler_clean = $sampler_map[strtolower($sampler_raw)] ?? ucfirst($sampler_raw);
        if (!in_array(strtolower($scheduler_raw), ['normal', 'simple', ''])) {
            $sampler_clean .= ' ' . ucfirst($scheduler_raw);
        }

        // 4. Obtener el Hash AutoV2
        $model_name_clean = pathinfo(str_replace('\\', '/', $model_file), PATHINFO_FILENAME);
        $model_hash = obtenerHashAutoV2Civitai($model_file, $pdo);

        $hashes_dict = [];
        if ($model_hash) {
            $hashes_dict['model'] = $model_hash;
        }

        $lora_tags_prompt = [];
        $lora_hashes_str = [];
        foreach ($loras_encontrados as $l_item) {
            $l_clean = pathinfo(str_replace('\\', '/', $l_item['file']), PATHINFO_FILENAME);
            $l_str   = $l_item['strength'];
            $l_hash  = obtenerHashAutoV2Civitai($l_item['file'], $pdo);
            if ($l_hash) {
                $hashes_dict['lora:' . $l_clean] = $l_hash;
                $lora_hashes_str[] = "{$l_clean}: {$l_hash}";
            }
            if (stripos($pos_prompt, '<lora:' . $l_clean) === false) {
                $lora_tags_prompt[] = "<lora:{$l_clean}:{$l_str}>";
            }
        }

        if (!empty($lora_tags_prompt)) {
            $pos_prompt = rtrim($pos_prompt) . ' ' . implode(' ', $lora_tags_prompt);
        }

        $pos_prompt = str_replace(["\r", "\x00"], '', $pos_prompt);
        $neg_prompt = str_replace(["\r", "\x00"], '', $neg_prompt);

        // 5. Construir bloque oficial "parameters" (A1111 + Lora hashes + Hashes JSON)
        $param_parts = [
            "Steps: " . intval($steps),
            "Sampler: " . $sampler_clean,
            "CFG scale: " . floatval($cfg),
            "Seed: " . $seed,
            "Size: " . $res
        ];
        if ($model_hash) {
            $param_parts[] = "Model hash: " . strtolower($model_hash);
        }
        $param_parts[] = "Model: " . $model_name_clean;

        if (!empty($lora_hashes_str)) {
            $param_parts[] = 'Lora hashes: "' . implode(', ', $lora_hashes_str) . '"';
        }
        if (!empty($hashes_dict)) {
            $param_parts[] = "Hashes: " . json_encode($hashes_dict, JSON_UNESCAPED_SLASHES);
        }

        $parameters_text = $pos_prompt . "\n";
        if (!empty($neg_prompt)) {
            $parameters_text .= "Negative prompt: " . $neg_prompt . "\n";
        }
        $parameters_text .= implode(", ", $param_parts);

        // 6. Inyectar chunk tEXt tras IHDR
        $chunk_data = "parameters\x00" . $parameters_text;
        $chunk_type = "tEXt";
        $full_chunk = pack('N', strlen($chunk_data)) . $chunk_type . $chunk_data . pack('N', crc32($chunk_type . $chunk_data));

        $ihdr_len = unpack('N', substr($png_binary, 8, 4))[1];
        $insert_pos = 8 + 4 + 4 + $ihdr_len + 4;

        return substr($png_binary, 0, $insert_pos) . $full_chunk . substr($png_binary, $insert_pos);
    }
}

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
                            
                            // 🌟 INYECCIÓN METADATOS CIVITAI (Solo en PNG)
                            if (strtolower($ext) === 'png') {
                                $file_data = inyectarMetadatosCivitaiPNG($file_data, $historial_id, $pdo);
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
                    
                    // 🌟 INYECCIÓN METADATOS CIVITAI (Solo en PNG)
                    if (strtolower($ext) === 'png') {
                        $file_data = inyectarMetadatosCivitaiPNG($file_data, $historial_id, $pdo);
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