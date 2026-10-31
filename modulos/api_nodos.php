<?php
// ==============================================================================
// --- MÓDULO NODOS: LECTURA DE MODELOS Y LORAS DESDE COMFYUI ---
// ==============================================================================

if ($action === 'get_checkpoints') {
    
    $ch_ref = curl_init(COMFY_URL . "/refresh");
    curl_setopt($ch_ref, CURLOPT_POST, true);
    curl_setopt($ch_ref, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ref, CURLOPT_TIMEOUT, 5);
    curl_exec($ch_ref);
    
    $ch = curl_init(COMFY_URL . "/object_info");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res_json = curl_exec($ch);
    
    $checkpoints = []; 
    $unets = [];
    $controlnets = [];
    $upscalers = [];
	$adetailers = [];
    
    if ($res_json) {
        $res = json_decode($res_json, true);
        
        // Modelos estándar
        if (isset($res['CheckpointLoaderSimple']['input']['required']['ckpt_name'][0])) {
            $checkpoints = array_merge($checkpoints, $res['CheckpointLoaderSimple']['input']['required']['ckpt_name'][0]);
        }
        if (isset($res['UNETLoader']['input']['required']['unet_name'][0])) {
            $unets = array_merge($unets, $res['UNETLoader']['input']['required']['unet_name'][0]);
        }
        
        // Modelos GGUF (LTX) y Vídeo
        if (isset($res['UnetLoaderGGUF']['input']['required']['unet_name'][0])) {
            $unets = array_merge($unets, $res['UnetLoaderGGUF']['input']['required']['unet_name'][0]);
        }
        if (isset($res['VideoModelLoader']['input']['required']['ckpt_name'][0])) {
            $checkpoints = array_merge($checkpoints, $res['VideoModelLoader']['input']['required']['ckpt_name'][0]);
        }
        if (isset($res['HyVideoModelLoader']['input']['required']['model_name'][0])) {
            $unets = array_merge($unets, $res['HyVideoModelLoader']['input']['required']['model_name'][0]);
        }
        if (isset($res['WanModelLoader']['input']['required']['model_name'][0])) {
            $unets = array_merge($unets, $res['WanModelLoader']['input']['required']['model_name'][0]);
        }
        if (isset($res['LTXVideoModelLoader']['input']['required']['model_name'][0])) {
            $unets = array_merge($unets, $res['LTXVideoModelLoader']['input']['required']['model_name'][0]);
        }
        
        // ControlNet y Upscalers
        if (isset($res['ControlNetLoader']['input']['required']['control_net_name'][0]) && is_array($res['ControlNetLoader']['input']['required']['control_net_name'][0])) {
            $controlnets = array_merge($controlnets, $res['ControlNetLoader']['input']['required']['control_net_name'][0]);
        }
        
        if (isset($res['ModelPatchLoader']['input']['required']['name'][0]) && is_array($res['ModelPatchLoader']['input']['required']['name'][0])) {
            $controlnets = array_merge($controlnets, $res['ModelPatchLoader']['input']['required']['name'][0]);
        }
        
        if (isset($res['UpscaleModelLoader']['input']['required']['model_name'][0]) && is_array($res['UpscaleModelLoader']['input']['required']['model_name'][0])) {
            $upscalers = array_merge($upscalers, $res['UpscaleModelLoader']['input']['required']['model_name'][0]);
        }
		if (isset($res['UltralyticsDetectorProvider']['input']['required']['model_name'][0]) && is_array($res['UltralyticsDetectorProvider']['input']['required']['model_name'][0])) {
            $adetailers = array_merge($adetailers, $res['UltralyticsDetectorProvider']['input']['required']['model_name'][0]);
        }
        
        // Fix robusto para Upscalers (Bypass SwarmUI)
        foreach ($res as $node_name => $node_data) {
            if ((stripos($node_name, 'upscale') !== false || stripos($node_name, 'esrgan') !== false || stripos($node_name, 'swinir') !== false) && isset($node_data['input']['required'])) {
                foreach ($node_data['input']['required'] as $param_name => $param_data) {
                    if (is_array($param_data) && isset($param_data[0]) && is_array($param_data[0])) {
                        $first_item = $param_data[0][0] ?? '';
                        if (is_string($first_item) && (stripos($first_item, '.pth') !== false || stripos($first_item, '.safetensors') !== false || stripos($first_item, '.pt') !== false)) {
                            $upscalers = array_merge($upscalers, $param_data[0]);
                        }
                    }
                }
            }
        }
    }
    
    // Filtro anti-intrusos
    $filter_noise = function($list) {
        return array_values(array_filter($list, function($item) {
            $lower = strtolower($item);
            return (strpos($lower, 'clip') === false && strpos($lower, 't5') === false);
        }));
    };

    $checkpoints = $filter_noise(is_array($checkpoints) ? array_unique($checkpoints) : []);
    $unets = $filter_noise(is_array($unets) ? array_unique($unets) : []);
    $controlnets = is_array($controlnets) ? array_values(array_unique($controlnets)) : [];
    $upscalers = is_array($upscalers) ? array_values(array_unique($upscalers)) : [];
    
    if (isset($is_pro) && $is_pro && isset($is_admin) && !$is_admin) {
        $checkpoints = filter_admin_folders($checkpoints);
        $unets = filter_admin_folders($unets);
    }
    
    $todos_los_modelos = array_merge($checkpoints, $unets);
    $todos_los_modelos = is_array($todos_los_modelos) ? array_values(array_unique($todos_los_modelos)) : [];
    
    sort($todos_los_modelos, SORT_NATURAL | SORT_FLAG_CASE);
    sort($controlnets, SORT_NATURAL | SORT_FLAG_CASE);
    sort($upscalers, SORT_NATURAL | SORT_FLAG_CASE);
    // Ordenamos también los nuevos modelos de ADetailer si lo deseas
    if (is_array($adetailers)) { sort($adetailers, SORT_NATURAL | SORT_FLAG_CASE); }
    
    echo json_encode([
        'checkpoints' => $todos_los_modelos, 
        'unets' => [],
        'controlnets' => $controlnets,
        'upscalers' => $upscalers,
        'adetailers' => is_array($adetailers) ? array_values(array_unique($adetailers)) : []
    ]);
    exit();
}

if ($action === 'get_loras') {
    $ch_ref = curl_init(COMFY_URL . "/refresh");
    curl_setopt($ch_ref, CURLOPT_POST, true);
    curl_setopt($ch_ref, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_ref, CURLOPT_TIMEOUT, 5);
    curl_exec($ch_ref);
    
    $ch = curl_init(COMFY_URL . "/object_info");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res_json = curl_exec($ch);
    
    $loras = [];
    if ($res_json) {
        $res = json_decode($res_json, true);
        if (isset($res['LoraLoader']['input']['required']['lora_name'][0])) {
            $loras = $res['LoraLoader']['input']['required']['lora_name'][0];
        }
    }
    
    if ($is_pro && !$is_admin) { 
        $loras = filter_admin_folders($loras); 
    }
    
    $loras_ordenados = array_values($loras);
    sort($loras_ordenados, SORT_NATURAL | SORT_FLAG_CASE);
    
    echo json_encode(['loras' => $loras_ordenados]);
    exit();
}

if ($action === 'get_lora_trigger') {
    $lora_name = $_POST['lora_name'] ?? '';
    $base_dir = defined('COMFY_MODELS_DIR') ? rtrim(COMFY_MODELS_DIR, '/\\') : 'C:/ComfyUI/models';
    
    $lora_name = trim($lora_name);
    $lora_name = str_replace('\\', '/', $lora_name);

    $filepath = rtrim($base_dir, '/') . '/loras/' . ltrim($lora_name, '/');
    if (!file_exists($filepath)) {
        $filepath = rtrim($base_dir, '/') . '/Lora/' . ltrim($lora_name, '/');
    }

    if (empty($lora_name) || !file_exists($filepath)) {
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_not_found') . ': ' . $filepath]); 
        exit();
    }

    $base_no_ext = preg_replace('/\.(safetensors|pt|ckpt|bin)$/i', '', $filepath);
    $txt_filepath = $base_no_ext . '.txt';

    // ==============================================================================
    // 1. ARCHIVO .TXT GEMELO (Prioridad absoluta / caché local instantánea)
    // ==============================================================================
    if (file_exists($txt_filepath)) {
        $trigger_words = trim(file_get_contents($txt_filepath));
        echo json_encode([
            'triggers' => $trigger_words,
            'debug_exito' => 'Lectura desde archivo .txt gemelo',
            'debug_meta_keys' => ['manual_txt_override']
        ]);
        exit();
    }

    // ==============================================================================
    // 2. ARCHIVOS METADATA EXTERNOS (.civitai.info / .json de gestores)
    // ==============================================================================
    $sidecars = [$base_no_ext . '.civitai.info', $base_no_ext . '.json', $filepath . '.json'];
    foreach ($sidecars as $sc) {
        if (file_exists($sc)) {
            $sc_data = json_decode(@file_get_contents($sc), true);
            if (is_array($sc_data)) {
                $found_sc = [];
                if (!empty($sc_data['trainedWords']) && is_array($sc_data['trainedWords'])) {
                    $found_sc = $sc_data['trainedWords'];
                } elseif (!empty($sc_data['activation text'])) {
                    $found_sc = [$sc_data['activation text']];
                } elseif (!empty($sc_data['trigger_words'])) {
                    $found_sc = is_array($sc_data['trigger_words']) ? $sc_data['trigger_words'] : [$sc_data['trigger_words']];
                }
                $found_sc = array_values(array_unique(array_filter(array_map('trim', $found_sc))));
                if (!empty($found_sc)) {
                    $res_str = implode(', ', $found_sc);
                    @file_put_contents($txt_filepath, $res_str); // Lo guardamos en .txt para acelerar futuras cargas
                    echo json_encode([
                        'triggers' => $res_str,
                        'debug_exito' => 'Extraído de sidecar JSON/Civitai',
                        'debug_meta_keys' => ['sidecar_json']
                    ]);
                    exit();
                }
            }
        }
    }

    // ==============================================================================
    // 3. ESCÁNER BINARIO DE CABECERA SAFETENSORS (Mejorado)
    // ==============================================================================
    $triggers = [];
    $meta = [];

    $f = @fopen($filepath, 'rb');
    if ($f) {
        $size_bytes = fread($f, 8);
        if ($size_bytes !== false && strlen($size_bytes) === 8) {
            $unpacked = unpack('P', $size_bytes);
            $size = $unpacked[1] ?? 0;
            if ($size > 0 && $size <= 10000000) {
                $json_str = fread($f, $size);
                if ($json_str) {
                    $header = json_decode($json_str, true);
                    if (isset($header['__metadata__']) && is_array($header['__metadata__'])) {
                        $meta = $header['__metadata__'];
                    }
                }
            }
        }
        fclose($f);
    }

    if (!empty($meta)) {
        // A) Campos directos de diferentes entrenadores (Kohya, AI-Toolkit, FluxGym, Swarm, etc.)
        $direct_keys = [
            'modelspec.trigger_phrase',
            'trigger_words',
            'trigger_word',
            'ss_activation_tags',
            'activation_text',
            'trained_words',
            'prompt'
        ];
        foreach ($direct_keys as $k) {
            if (!empty($meta[$k])) {
                $val = $meta[$k];
                $decoded = json_decode($val, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        if (is_string($item)) $triggers[] = trim($item);
                    }
                } elseif (is_string($val)) {
                    $triggers[] = trim($val);
                }
            }
        }

        // B) Extraer de ss_tag_frequency ORDENADO POR FRECUENCIA REAL (de más repetido a menos)
        if (empty($triggers) && !empty($meta['ss_tag_frequency'])) {
            $tags_por_carpeta = json_decode($meta['ss_tag_frequency'], true);
            if (is_array($tags_por_carpeta)) {
                $acumulado = [];
                $ruido = [
                    '1girl', '1boy', 'solo', 'highres', 'absurdres', 'masterpiece', 'best quality',
                    'looking at viewer', 'simple background', 'white background', 'black background',
                    'portrait', 'upper body', 'full body', 'cowboy shot', 'close-up', 'realistic',
                    'photorealistic', ' blurry', 'depth of field', ' bokeh', 'smile', 'standing', 'sitting'
                ];
                foreach ($tags_por_carpeta as $folder => $tag_list) {
                    if (is_array($tag_list)) {
                        foreach ($tag_list as $tag => $count) {
                            $clean_t = trim($tag);
                            if ($clean_t !== '' && !in_array(strtolower($clean_t), $ruido) && strlen($clean_t) > 1) {
                                $acumulado[$clean_t] = ($acumulado[$clean_t] ?? 0) + intval($count);
                            }
                        }
                    }
                }
                if (!empty($acumulado)) {
                    arsort($acumulado); // Ordenamos de mayor a menor frecuencia
                    $max_count = reset($acumulado);
                    foreach ($acumulado as $tag => $count) {
                        // Cogemos el más repetido y los que tengan al menos el 50% de presencia respecto al líder
                        if (count($triggers) === 0 || ($count >= $max_count * 0.5 && count($triggers) < 4)) {
                            $triggers[] = $tag;
                        }
                    }
                }
            }
        }

        // C) Extraer del nombre de carpeta de dataset de Kohya (ej. "10_jinx_arcane" -> "jinx_arcane")
        if (empty($triggers) && !empty($meta['ss_dataset_dirs'])) {
            $dirs = json_decode($meta['ss_dataset_dirs'], true);
            if (is_array($dirs)) {
                foreach ($dirs as $dir_name => $info) {
                    $clean_dir = preg_replace('/^\d+_/', '', trim($dir_name));
                    $clean_dir = str_replace('_', ' ', $clean_dir);
                    if (!empty($clean_dir) && !in_array(strtolower($clean_dir), ['img', 'image', 'images', 'train', 'dataset', 'data'])) {
                        $triggers[] = $clean_dir;
                        break;
                    }
                }
            }
        }
    }

    $triggers = array_values(array_unique(array_filter(array_map('trim', $triggers))));

    // ==============================================================================
    // 4. FALLBACK AUTOMÁTICO A CIVITAI API POR HASH SHA256 (Y AUTO-GUARDADO EN .TXT)
    // ==============================================================================
    if (empty($triggers) && filesize($filepath) < 1500000000) { // Solo si pesa menos de 1.5GB para no bloquear
        @set_time_limit(60);
        $sha256 = @hash_file('sha256', $filepath);
        if ($sha256) {
            $ch = curl_init("https://civitai.com/api/v1/model-versions/by-hash/" . $sha256);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Gartys-Architect)');
            $civ_res = curl_exec($ch);
            $http_c = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($http_c === 200 && $civ_res) {
                $civ_data = json_decode($civ_res, true);
                if (!empty($civ_data['trainedWords']) && is_array($civ_data['trainedWords'])) {
                    foreach ($civ_data['trainedWords'] as $tw) {
                        if (is_string($tw) && trim($tw) !== '') {
                            $triggers[] = trim($tw);
                        }
                    }
                }
                
				$triggers = array_values(array_unique(array_filter($triggers)));
                if (!empty($triggers)) {
                    @file_put_contents($txt_filepath, implode(', ', $triggers));
                }
		    }
        }
    }

    $final_str = implode(', ', $triggers);
    if ($final_str !== '' && !file_exists($txt_filepath)) {
        // Cacheamos también en .txt los que salgan del binario para que la próxima vez sea instantáneo
        @file_put_contents($txt_filepath, $final_str);
    }

    echo json_encode([
        'triggers' => $final_str,
        'debug_exito' => __('msg_lora_fast_read'),
        'debug_meta_keys' => array_keys($meta)
    ]);
    exit();
}
?>