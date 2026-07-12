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
    
    echo json_encode([
        'checkpoints' => $todos_los_modelos, 
        'unets' => [],
        'controlnets' => $controlnets,
        'upscalers' => $upscalers
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

    $f = fopen($filepath, 'rb');
    if (!$f) { 
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_read_lock')]); 
        exit(); 
    }

    $size_bytes = fread($f, 8);
    if ($size_bytes === false || strlen($size_bytes) !== 8) {
        fclose($f);
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_io_header')]); 
        exit();
    }

    $unpacked = unpack('P', $size_bytes);
    if (!$unpacked || !isset($unpacked[1])) {
        fclose($f);
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_corrupt_header')]); 
        exit();
    }
    
    $size = $unpacked[1]; 
    
    if ($size <= 0 || $size > 10000000) { 
        fclose($f); 
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_abnormal_size') . ': ' . $size]); 
        exit(); 
    }

    $json_str = fread($f, $size);
    fclose($f);

    if (!$json_str) {
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_ram_dump')]); 
        exit();
    }

    $header = json_decode($json_str, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['triggers' => '', 'debug_error' => __('err_lora_invalid_json')]); 
        exit();
    }

    $triggers = [];
    $meta = [];
    
    if (isset($header['__metadata__'])) {
        $meta = $header['__metadata__'];
        
        if (isset($meta['modelspec.trigger_phrase'])) {
            $triggers[] = $meta['modelspec.trigger_phrase'];
        }
        if (isset($meta['trigger_words'])) {
            $tw_decoded = json_decode($meta['trigger_words'], true);
            if (is_array($tw_decoded)) {
                $triggers = array_merge($triggers, $tw_decoded);
            } else {
                $triggers[] = $meta['trigger_words'];
            }
        }
        if (empty($triggers) && isset($meta['ss_tag_frequency'])) {
            $tags = json_decode($meta['ss_tag_frequency'], true);
            if (is_array($tags)) {
                foreach ($tags as $folder => $tag_list) {
                    foreach ($tag_list as $tag => $count) {
                        if (!in_array($tag, ['1girl', '1boy', 'solo', 'highres'])) {
                            $triggers[] = $tag;
                            if (count($triggers) >= 3) break 2; 
                        }
                    }
                }
            }
        }
    }
    
    $triggers = array_unique(array_filter($triggers));
    
    echo json_encode([
        'triggers' => implode(', ', $triggers),
        'debug_exito' => __('msg_lora_fast_read'),
        'debug_meta_keys' => array_keys($meta)
    ]);
    exit();
}
?>