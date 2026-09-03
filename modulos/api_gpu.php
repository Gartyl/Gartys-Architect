<?php
// ==============================================================================
// --- HELPER: ESCÁNER FÍSICO DE CABECERAS .SAFETENSORS (2 milisegundos) ---
// ==============================================================================
function modeloTieneClipIntegrado($nombre_archivo) {
    // Para distribución, dependemos exclusivamente de la constante configurada por el usuario
    $ruta_base = defined('COMFY_MODEL_PATH') ? COMFY_MODEL_PATH : "";
    
    // Si el usuario no ha configurado la ruta física en el init.php, salimos con fallback optimista
    if (empty($ruta_base)) {
        return true; 
    }

    $ruta_completa = rtrim($ruta_base, '/\\') . '/' . ltrim($nombre_archivo, '/\\');

    if (!file_exists($ruta_completa)) {
        return true; // Fallback optimista si no encuentra el archivo exacto
    }

    $f = @fopen($ruta_completa, 'rb');
    if (!$f) return true;

    // Leemos los 8 bytes que indican el tamaño del diccionario JSON
    $bytes_tamano = fread($f, 8);
    $unpacked = unpack('P', $bytes_tamano);
    $tamano_json = $unpacked[1] ?? 0;

    if ($tamano_json <= 0 || $tamano_json > 5242880) {
        fclose($f);
        return true;
    }

    $cabecera_json = fread($f, $tamano_json);
    fclose($f);

    // Si encontramos cualquiera de estas firmas, el modelo lleva su propio CLIP
    if (strpos($cabecera_json, 'cond_stage_model') !== false || 
        strpos($cabecera_json, 'clip_l') !== false || 
        strpos($cabecera_json, 'text_model') !== false || 
        strpos($cabecera_json, 't5xxl') !== false) {
        return true;
    }

    return false; // Es un modelo "UNET-Only" desnudado
}

// ==============================================================================
// --- MÓDULO GPU: GENERACIÓN, EDICIÓN, VÍDEO Y ENSAMBLAJE DE WORKFLOWS ---
// ==============================================================================

if ($action === 'generar_imagen') {
        
    // ====================================================================
    // --- FILTROS DE SEGURIDAD FREEMIUM (VERSIÓN PRO) ---
    // ====================================================================
    if (!$is_pro) {
        $selector_solicitado = trim($_POST['selector'] ?? '[SDXL]');
        $modelo_solicitado = strtoupper($_POST['model_path'] ?? '');

        // 1. Bloqueo de Modelos Next-Gen y Vídeo
        if ($selector_solicitado === '[NATURAL_IMAGE]' || $selector_solicitado === '[VIDEO]' || 
            strpos($modelo_solicitado, 'FLUX') !== false || strpos($modelo_solicitado, 'SD3.5') !== false || 
            strpos($modelo_solicitado, 'QWEN') !== false || strpos($modelo_solicitado, 'WAN') !== false || 
            strpos($modelo_solicitado, 'LTX') !== false || strpos($modelo_solicitado, 'KREA') !== false) {
            echo json_encode(['success' => false, 'error' => __('err_pro_heavy_models')]);
            exit;
        }

        // 2. Bloqueo de Inpaint / Outpaint
        if (isset($_POST['mask_data']) && $_POST['mask_data'] !== '') {
            echo json_encode(['success' => false, 'error' => __('err_pro_advanced_edit')]);
            exit;
        }

        // 3. Bloqueo de ReActor (Face Swap)
        if (isset($_POST['reactor_enabled']) && $_POST['reactor_enabled'] === 'true' || 
            (isset($_POST['pure_faceswap']) && $_POST['pure_faceswap'] === 'true')) {
            echo json_encode(['success' => false, 'error' => __('err_pro_reactor')]);
            exit;
        }

        // 4. Bloqueo de IP-Adapter
        if (isset($_POST['ipadapter_enabled']) && $_POST['ipadapter_enabled'] === 'true') {
            echo json_encode(['success' => false, 'error' => __('err_pro_ipadapter')]);
            exit;
        }

        // 5. Bloqueo de Upscale
        if (isset($_POST['hires_fix']) && $_POST['hires_fix'] === 'true') {
            echo json_encode(['success' => false, 'error' => __('err_pro_upscale')]);
            exit;
        }

        // 6. Bloqueo de ADetailer (Reparador de Rostros)
        if (isset($_POST['adetailer']) && $_POST['adetailer'] === 'on') {
            echo json_encode(['success' => false, 'error' => __('err_pro_adetailer')]);
            exit;
        }

        // 7. Bloqueo de DDColor (Coloreado Neural) - NUEVO
        if (isset($_POST['ddcolor_enabled']) && ($_POST['ddcolor_enabled'] === '1' || $_POST['ddcolor_enabled'] === 'true' || $_POST['ddcolor_enabled'] === 'on')) {
            echo json_encode(['success' => false, 'error' => __('err_pro_ddcolor') ?? 'El coloreado neural es exclusivo para usuarios PRO.']);
            exit;
        }

        // 8. Bloqueo de IC-Light (Iluminación Neural) - NUEVO
        if (isset($_POST['iclight_enabled']) && ($_POST['iclight_enabled'] === '1' || $_POST['iclight_enabled'] === 'true' || $_POST['iclight_enabled'] === 'on')) {
            echo json_encode(['success' => false, 'error' => __('err_pro_iclight') ?? 'La iluminación neural (IC-Light) es exclusiva para usuarios PRO.']);
            exit;
        }
		
		// 9. Bloqueo de Eliminar Fondo (Rembg) - NUEVO
        if ((isset($_POST['remove_background']) && $_POST['remove_background'] === 'true') || 
            (isset($_POST['pure_rembg']) && $_POST['pure_rembg'] === 'true')) {
            echo json_encode(['success' => false, 'error' => __('err_pro_rembg') ?? 'La herramienta para eliminar el fondo es exclusiva para usuarios PRO.']);
            exit;
        }
		
		// 10. Bloqueo de Audio Pro y Standalone - AUDITORÍA
        if (!empty($_POST['audio_params']) || (isset($_POST['standalone']) && $_POST['standalone'] === '1')) {
            echo json_encode(['success' => false, 'error' => __('err_pro_audio') ?? 'La generación de Audio avanzado y Clonación de Voz es exclusiva para usuarios PRO.']);
            exit;
        }
    }
    // ====================================================================
    
    // Variables Post
    $selector = trim($_POST['selector'] ?? '[SDXL]'); 
    $posPrompt = $_POST['prompt'] ?? "";
	
	// ====================================================================
    // --- 🎵 INTERCEPTOR VIP OMNÍVORO: AUDIO AUTÓNOMO (STANDALONE) 🎵 ---
    // ====================================================================
    $audioConfigPro = !empty($_POST['audio_params']) ? json_decode($_POST['audio_params'], true) : [];

    $engine_pro   = $audioConfigPro['engine'] ?? ($_POST['engine'] ?? ($_POST['audio_pro_engine'] ?? 'sfx'));
    $prompt_audio = trim($audioConfigPro['prompt_text'] ?? ($_POST['prompt_text'] ?? ($_POST['audio_pro_prompt'] ?? ($_POST['sfx_prompt'] ?? ''))));
    $ref_file_pro = $audioConfigPro['ref_file'] ?? ($_POST['ref_file'] ?? ($_POST['audio_pro_file'] ?? ''));
    $ref_text_pro = trim($audioConfigPro['ref_text'] ?? ($_POST['ref_text'] ?? ($_POST['audio_pro_text'] ?? '')));
    $seconds_pro  = floatval($audioConfigPro['seconds'] ?? ($_POST['seconds'] ?? ($_POST['sfx_seconds'] ?? 5.0)));
    $steps_pro    = intval($audioConfigPro['steps'] ?? ($_POST['steps'] ?? ($_POST['sfx_steps'] ?? 20)));
    $speed_pro    = floatval($audioConfigPro['speed'] ?? ($_POST['speed'] ?? 1.0));
    
    $silence_raw  = $audioConfigPro['remove_silence'] ?? ($_POST['remove_silence'] ?? '1');
    $silence_pro  = ($silence_raw === '1' || $silence_raw === true || $silence_raw === 'true');

    // NUEVAS VARIABLES MULTI-MOTOR
    $tts_engine_pro  = $audioConfigPro['tts_engine'] ?? ($_POST['tts_engine'] ?? 'f5');
    $tts_emotion_pro = $audioConfigPro['tts_emotion'] ?? ($_POST['tts_emotion'] ?? 'calm');
    $idioma_tts 	 = $audioConfigPro['tts_language'] ?? ($_POST['tts_language'] ?? 'Spanish');
    $genero_pro      = $audioConfigPro['tts_gender'] ?? ($_POST['tts_gender'] ?? 'male');
    $edad_pro        = $audioConfigPro['tts_age'] ?? ($_POST['tts_age'] ?? 'None');

    // --- NUEVO: FILTRO BPE PARA AUDIO Y VÍDEO ---
    $acentos_mayus = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ä', 'Ë', 'Ï', 'Ö', 'Ü'];
    $acentos_minus = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ä', 'ë', 'ï', 'ö', 'ü'];
    if (isset($prompt_audio)) $prompt_audio = str_replace($acentos_mayus, $acentos_minus, $prompt_audio);
    if (isset($posPrompt)) $posPrompt = str_replace($acentos_mayus, $acentos_minus, $posPrompt);
    // --------------------------------------------

    // 2. ESCUDO CORTAFUEGOS: Detección inteligente de Audio Puro
    $is_audio_standalone = false;

    // Si NO viene empaquetado como vídeo (audio_params vacío), evaluamos de forma agresiva si es audio.
    if (empty($_POST['audio_params'])) {
        $flag_forzado = ($_POST['enable_audio_pro'] ?? '') === 'true' || ($_POST['audio_enabled'] ?? '') === 'true' || ($_POST['standalone'] ?? '') === '1';
        $selector_es_audio = (strpos(strtoupper($selector), '[AUDIO') !== false);
        $hay_texto_audio_exclusivo = (!empty($prompt_audio) && $prompt_audio !== $posPrompt);
        
        // Si hay cualquier mínima señal de que quiere audio, lo atrapamos
        if ($flag_forzado || $selector_es_audio || $hay_texto_audio_exclusivo || !empty($ref_file_pro)) {
            $is_audio_standalone = true;
        } elseif (empty(trim($posPrompt)) && (isset($_POST['engine']) || isset($_POST['tts_engine']))) {
            $is_audio_standalone = true;
        }
    }

    if ($is_audio_standalone) {
        
        // Rescatamos el texto si se quedó en el prompt principal por culpa del JS
        if (empty($prompt_audio) && !empty(trim($posPrompt))) {
            $prompt_audio = trim($posPrompt);
        }
        if (empty($prompt_audio)) {
            $prompt_audio = "Audio Standalone Generation";
        }
        
        $workflow = [];
        $output_port = 0; // 🛠️ CORRECCIÓN 5: Subimos el puerto
        
        if ($engine_pro === 'tts') {
           
            if ($tts_engine_pro === 'f5') {
                $workflow['1010'] = ['inputs' => ['audio' => $ref_file_pro], 'class_type' => 'LoadAudio'];
                $workflow['1011'] = [
                    'inputs' => [
                        'sample_audio' => ['1010', 0], 'sample_text' => $ref_text_pro, 'speech' => $prompt_audio,
                        'speed' => $speed_pro, 'seed' => mt_rand(1, 2147483647), 'model' => 'F5v1',
                        'vocoder' => 'auto', 'model_type' => 'F5TTS_v1_Base', 'remove_silence' => $silence_pro
                    ], 'class_type' => 'F5TTSAudioInputs'
                ];
                $output_node = '1011';
                
            } elseif ($tts_engine_pro === 'indextts') {
                $workflow['1125'] = [
                    'inputs' => [
                        'Happy' => ($tts_emotion_pro === 'happy') ? 0.85 : 0.1, 'Angry' => ($tts_emotion_pro === 'angry') ? 0.85 : 0.1,
                        'Sad' => ($tts_emotion_pro === 'sad') ? 0.85 : 0.1, 'Calm' => ($tts_emotion_pro === 'calm') ? 0.85 : 0.1,
                        'Surprised' => 0.1, 'Afraid' => 0.1, 'Disgusted' => 0.1, 'Melancholic' => 0.1, 'emotion_radar_canvas' => ""
                    ], 'class_type' => 'IndexTTSEmotionOptionsNode'
                ];
                $workflow['1065'] = ['inputs' => ['value' => $prompt_audio], 'class_type' => 'PrimitiveStringMultiline'];
                
                $workflow['1134'] = ['inputs' => ['audio' => $ref_file_pro], 'class_type' => 'LoadAudio'];
                $workflow['1130'] = ['inputs' => ['voice_name' => 'none', 'reference_text' => $ref_text_pro, 'trim_start' => 0, 'trim_end' => 0, 'customized' => true, 'opt_audio_input' => ['1134', 0]], 'class_type' => 'CharacterVoicesNode'];
                
                $workflow['1123'] = [
                    'inputs' => [
                        'language' => $idioma_tts,
                        'model_path' => 'IndexTTS-2', 'device' => 'auto', 'emotion_alpha' => 0.7, 'use_random' => false,
                        'max_text_tokens_per_segment' => 120, 'interval_silence' => 200, 'temperature' => 0.8, 'top_p' => 0.8,
                        'top_k' => 30, 'do_sample' => true, 'length_penalty' => 0, 'num_beams' => 3, 'repetition_penalty' => 9.5,
                        'max_mel_tokens' => 1500, 'use_fp16' => true, 'use_deepspeed' => true, 'use_cuda_kernel' => 'auto',
                        'use_torch_compile' => false, 'use_accel' => false, 'stream_return' => false, 'more_segment_before' => 0, 'low_vram' => false,
                        'emotion_control' => ['1125', 0]
                    ], 'class_type' => 'IndexTTSEngineNode'
                ];
                $workflow['1047'] = [
                    'inputs' => [
                        'text' => ['1065', 0], 'narrator_voice' => 'none', 'seed' => mt_rand(1, 2147483647), 'enable_chunking' => true,
                        'max_chars_per_chunk' => 400, 'chunk_combination_method' => 'auto', 'silence_between_chunks_ms' => 100,
                        'enable_audio_cache' => true, 'batch_size' => 0, 'TTS_engine' => ['1123', 0], 'opt_narrator' => ['1130', 0]
                    ], 'class_type' => 'UnifiedTTSTextNode'
                ];
                $output_node = '1047';
                
            } else {
                // OMNIVOICE
                // 🛠️ CORRECCIÓN 6
                $omni_style = ($tts_emotion_pro === 'whisper') ? 'whisper' : 'None';
                $omni_lang  = ($idioma_tts === 'Chinese') ? 'Chinese' : 'English';

                $workflow['1025'] = [
                    'inputs' => [
                        'gender' => $genero_pro, 'age' => $edad_pro, 'pitch' => 'None',
                        'style' => $omni_style, 'accent' => 'None', 'dialect' => 'None',
                        'output_language' => $omni_lang, 'instruct_text' => ''
                    ], 'class_type' => 'OmniVoiceInstructionBuilderNode'
                ];

                $workflow['1020'] = [
                    'inputs' => [
                        'model_variant' => 'OmniVoice', 'device' => 'auto', 'language' => 'Auto',
                        'num_step' => 32, 'guidance_scale' => 2, 't_shift' => 0.1, 'speed' => 1.0,
                        'duration' => 0, 'dtype' => 'auto', 'instruct' => '', 'layer_penalty_factor' => 5,
                        'position_temperature' => 5, 'class_temperature' => 0, 'denoise' => true,
                        'preprocess_prompt' => true, 'postprocess_output' => true, 'audio_chunk_duration' => 15,
                        'audio_chunk_threshold' => 30, 'mode' => 'Voice Design'
                    ], 'class_type' => 'OmniVoiceEngineNode'
                ];

                $workflow['1017'] = [
                    'inputs' => [
                        'reference_text' => $prompt_audio, 'seed' => mt_rand(1, 2147483647),
                        'voice_instruction' => ['1025', 0], 'TTS_engine' => ['1020', 0]
                    ], 'class_type' => 'UnifiedVoiceDesignerNode'
                ];
                
            $output_node = '1017';
                $output_port = 1;
            }
        } elseif ($engine_pro === 'foley') {
            // =======================================================
            // --- ESTRUCTURA HUNYUAN FOLEY (VIDEO TO AUDIO) ---
            // =======================================================
            $video_filename = "none";
            $media_base64 = $_POST['image_data'] ?? null;

            // Subimos el vídeo que hay en el visor a ComfyUI asegurando metadatos limpios
			if (!empty($media_base64)) {
				$vidData = strpos($media_base64, 'base64,') !== false ? explode('base64,', $media_base64)[1] : $media_base64;
				$raw_tmp = sys_get_temp_dir() . '/foley_raw_' . uniqid() . '.mp4';
				$tmp_vid = sys_get_temp_dir() . '/foley_api_' . uniqid() . '.mp4';
				file_put_contents($raw_tmp, base64_decode($vidData));

				// Forzamos a FFmpeg a reindexar los metadatos para que Decord los lea sin errores
				if (file_exists($raw_tmp)) {
					shell_exec("ffmpeg -y -i " . escapeshellarg($raw_tmp) . " -c:v libx264 -pix_fmt yuv420p -an " . escapeshellarg($tmp_vid));
					@unlink($raw_tmp);
					if (!file_exists($tmp_vid)) {
						// Si FFmpeg no está disponible en el entorno, usamos el crudo como fallback
						rename($raw_tmp, $tmp_vid);
					}
				}

				$cfile_vid = function_exists('curl_file_create') ? curl_file_create($tmp_vid, 'video/mp4', 'foley_ref.mp4') : '@' . realpath($tmp_vid);
				$ch_vid = curl_init(COMFY_URL . '/upload/image');
				curl_setopt($ch_vid, CURLOPT_POST, true);
				curl_setopt($ch_vid, CURLOPT_POSTFIELDS, ['image' => $cfile_vid]);
				curl_setopt($ch_vid, CURLOPT_RETURNTRANSFER, true);
				$res_vid = json_decode(curl_exec($ch_vid), true);
				@unlink($tmp_vid);

				if (isset($res_vid['name'])) $video_filename = $res_vid['name'];
			}

            if ($video_filename === "none") {
				echo json_encode(['error' => (__('err_foley_no_video') ?? 'Hunyuan Foley necesita que cargues un vídeo en el visor principal para sincronizar el sonido.')]);
				exit();
			}

            $ruta_json = __DIR__ . '/../workflows/Hunyuan_Foley.json';
            $reemplazos = [
                '__PROMPT__' => $prompt_audio,
                '__VIDEO_INPUT__' => $video_filename,
                '__STEPS__' => $steps_pro,
                '__SEED__' => mt_rand(1, 2147483647)
            ];

            $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);
			$output_node = '2'; // El nodo principal HunyuanVideoFoley
			$output_port = 2;   // El puerto que devuelve la pista de audio

        } elseif ($engine_pro === 'sfx') {
            // ESTRUCTURA STABLE AUDIO OPEN (SFX)
            $workflow['2020'] = ['inputs' => ['ckpt_name' => 'stable-audio-open-1.0.safetensors'], 'class_type' => 'CheckpointLoaderSimple'];
            $workflow['2021'] = ['inputs' => ['clip_name' => 't5-base.safetensors', 'type' => 'stable_audio'], 'class_type' => 'CLIPLoader'];
            $workflow['2022'] = ['inputs' => ['seconds' => $seconds_pro, 'batch_size' => 1], 'class_type' => 'EmptyLatentAudio'];
            $workflow['2023'] = ['inputs' => ['text' => $prompt_audio, 'clip' => ['2021', 0]], 'class_type' => 'CLIPTextEncode'];
            $workflow['2024'] = ['inputs' => ['text' => '', 'clip' => ['2021', 0]], 'class_type' => 'CLIPTextEncode'];
            $workflow['2025'] = [
                'inputs' => [
                    'seed' => mt_rand(1, 2147483647), 'steps' => $steps_pro, 'cfg' => 4.5, 'sampler_name' => 'euler', 'scheduler' => 'normal',
                    'denoise' => 1.0, 'model' => ['2020', 0], 'positive' => ['2023', 0], 'negative' => ['2024', 0], 'latent_image' => ['2022', 0]
                ], 'class_type' => 'KSampler'
            ];
            $workflow['2026'] = ['inputs' => ['samples' => ['2025', 0], 'vae' => ['2020', 2]], 'class_type' => 'VAEDecodeAudio'];
            
            $output_node = '2026';
        }

        // 3. Nodo de guardado de audio en ComfyUI
        $workflow['99'] = [
            'inputs' => [
                'filename_prefix' => 'byGarty_Audio_' . strtoupper($engine_pro),
                'audio' => [$output_node, $output_port] // <--- AQUÍ
            ],
            'class_type' => 'SaveAudio',
            '_meta' => ['title' => 'Save Output Audio']
        ];
        
        $posPrompt = $prompt_audio;
        $neg_prompt = "N/A (Audio Standalone)";
        $selector = "[AUDIO_" . strtoupper($engine_pro) . "]";
        $model_path = "Audio_" . strtoupper($engine_pro);
        $width = 0; $height = 0; $seed = mt_rand(1, 2147483647);
        $steps = $steps_pro; $cfg = 7.0; $sampler = "dpmpp_3m_sde"; $scheduler = "normal";
        $batch_size = 1; $lora_metadata_list = []; $use_adetailer = false;
        $historial_id = intval($_POST['historial_id'] ?? 0);

        goto EJECUTAR_COMFYUI;
    }
    // ====================================================================
    
    // --- 1.5 NUEVO: INTERCEPTOR DINÁMICO DE MODELOS COMFYUI ---
    $modelo_recibido = !empty($_POST['model_path']) ? $_POST['model_path'] : '';
    
    // Bloqueo de seguridad limpio si el JavaScript envía la orden vacía
    if (empty($modelo_recibido)) {
        echo json_encode(['error' => __('err_no_graphic_model')]);
        exit();
    }
    
    $info_modelo = obtener_info_modelo($modelo_recibido, $pdo, 'comfyui');
    
    // Reasignamos las variables para que el resto del código no se entere del cambio
    $model_path = $info_modelo['nombre_archivo'];
	
	// 👇 UNBUNDLED 👇
    $es_unbundled_db = isset($info_modelo['es_unbundled']) && $info_modelo['es_unbundled'] == 1;
    // 👆 ======================= 👆
	
    // Si en la base de datos has definido un VAE específico, lo preparamos aquí para el futuro
    if (!empty($info_modelo['vae_requerido'])) {
        $vae_name = $info_modelo['vae_requerido']; 
    }
    $historial_id = intval($_POST['historial_id'] ?? 0); 
    $width = intval($_POST['width'] ?? 1024);
    $height = intval($_POST['height'] ?? 1024);
    $denoise_slider = floatval($_POST['denoise'] ?? 1.0);
    $batch_size = intval($_POST['batch_size'] ?? 1);
    $video_frames = intval($_POST['video_frames'] ?? 33);
    $video_fps = intval($_POST['video_fps'] ?? 16); 
    
    // --- NUEVOS PARÁMETROS INPAINT ---
    $mask_blur = isset($_POST['mask_blur']) ? intval($_POST['mask_blur']) : 4;
    $inpaint_fill = isset($_POST['inpaint_fill']) ? $_POST['inpaint_fill'] : 'original';
    $inpaint_area = isset($_POST['inpaint_area']) ? $_POST['inpaint_area'] : 'Only Masked';
    
    // --- NUEVOS PARÁMETROS AVANZADOS ---
    $user_steps = isset($_POST['steps']) ? intval($_POST['steps']) : 0;
    $user_cfg = isset($_POST['cfg']) ? floatval($_POST['cfg']) : 0.0;
    $user_sampler = $_POST['sampler'] ?? "";
    $user_scheduler = $_POST['scheduler'] ?? "";
    $user_seed = isset($_POST['seed']) ? intval($_POST['seed']) : -1;
	// Capturamos el Flow Shift (si el usuario lo ha dejado vacío, valdrá null)
    $user_shift = (isset($_POST['flow_shift']) && $_POST['flow_shift'] !== '') ? floatval($_POST['flow_shift']) : null;
    $dynamic_thresholding = filter_var($_POST['dynamic_thresholding'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    
    // Image2Image & Outpainting
    // --- 1. CAPTURA DE IMAGEN INICIAL (NUEVO EXTRACTOR INTEGRADO) ---
    $previous_video = $_POST['previous_video'] ?? null;
    $is_chain_chunk = false; // Bandera para saber si es contexto autoregresivo
    
    if (!empty($previous_video)) {
        // Si venimos de encadenar, sacamos un mini-clip de contexto
        $real_vid_path = __DIR__ . '/../galeria/' . basename($previous_video);
        if (file_exists($real_vid_path)) {
            $is_chain_chunk = true; 
            // 🌟 CAMBIO: Extraemos el último MEDIO SEGUNDO (inercia) en MP4
            $tmp_clip = sys_get_temp_dir() . '/ltx_context_' . uniqid() . '.mp4';
            exec("ffmpeg -sseof -0.5 -i " . escapeshellarg($real_vid_path) . " -c:v libx264 -an -preset ultrafast -crf 18 " . escapeshellarg($tmp_clip) . " 2>&1");
            
            if (file_exists($tmp_clip)) {
                $init_image_base64 = base64_encode(file_get_contents($tmp_clip));
                @unlink($tmp_clip);
            }
        }
    } else {
        // Si no, leemos la imagen normal de la web
        $init_image_base64 = $_POST['init_image'] ?? null;
    }
	
	// ====================================================================
    // 🛡️ AUTO-ENRUTADOR INTELIGENTE PARA VLM (KREA-2 Y QWEN) 🛡️
    // ====================================================================
    $temp_mod_lower = strtolower($model_path);
    $es_modelo_vlm = (strpos($temp_mod_lower, 'qwen') !== false || strpos($temp_mod_lower, 'krea2') !== false || strpos($temp_mod_lower, 'krea-2') !== false);

    // Si el modelo es Krea/Qwen, el lienzo principal está vacío, pero el usuario subió foto al IP-Adapter...
    if ($es_modelo_vlm && empty($init_image_base64) && !empty($_POST['ipadapter_image'])) {
        $init_image_base64 = $_POST['ipadapter_image']; // Trasvasamos la imagen internamente
        $_POST['ipadapter_enabled'] = 'false'; // Desactivamos la bandera para que no salte la alerta roja
    }
    // ====================================================================
    
    // ====================================================================
    // --- 2. SUBIR IMÁGENES A COMFYUI (MODO CLÁSICO Y MULTICARGA) ---
    // ====================================================================
    $comfy_image_filename = "none";
    $comfy_audio_filename = "none";
    $tray_comfy_filenames = [];

    // A. Subir imágenes de la Bandeja Multicarga al servidor de ComfyUI
    if (isset($_POST['has_tray_images']) && $_POST['has_tray_images'] === 'true') {
        for ($i = 1; $i <= 9; $i++) {
            if (!empty($_POST["tray_image_$i"])) {
                $tmp_tray = sys_get_temp_dir() . '/tray_img_' . $i . '_' . uniqid() . '.png';
                file_put_contents($tmp_tray, base64_decode($_POST["tray_image_$i"]));
                $cfile_tray = function_exists('curl_file_create') ? curl_file_create($tmp_tray, 'image/png', "tray_ref_$i.png") : '@' . realpath($tmp_tray);
                
                $ch_tray = curl_init(COMFY_URL . '/upload/image');
                curl_setopt($ch_tray, CURLOPT_POST, true);
                curl_setopt($ch_tray, CURLOPT_POSTFIELDS, ['image' => $cfile_tray]);
                curl_setopt($ch_tray, CURLOPT_RETURNTRANSFER, true);
                $res_tray = json_decode(curl_exec($ch_tray), true);
                @unlink($tmp_tray);
                
                if (isset($res_tray['name'])) {
                    $tray_comfy_filenames[$i] = $res_tray['name']; // Guardamos el nombre que le dio ComfyUI
                }
            }
        }
    }

    // B. Subir Imagen Base Clásica o Contexto de Vídeo
    if (!empty($init_image_base64)) {
        $imgData = $init_image_base64;
        if (strpos($imgData, 'base64,') !== false) {
            $imgData = explode('base64,', $imgData)[1];
        }
        
        // 🌟 CAMBIO: Detectar automáticamente si le pasamos foto o vídeo
        // Detectamos si es un trozo de cadena, o si el usuario subió un vídeo manualmente
		$es_video_manual = (strpos($imgData, 'data:video/') === 0);
		$is_video = ($is_chain_chunk || $es_video_manual);

		$extension = $is_video ? 'mp4' : 'png';
		$mime_type = $is_video ? 'video/mp4' : 'image/png';
        
        $tmp_file = sys_get_temp_dir() . '/upload_media_' . uniqid() . '.' . $extension;
        file_put_contents($tmp_file, base64_decode($imgData));
        $cfile = function_exists('curl_file_create') ? curl_file_create($tmp_file, $mime_type, 'init_media.' . $extension) : '@' . realpath($tmp_file);
        
        $ch_img = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_img, CURLOPT_POST, true);
        curl_setopt($ch_img, CURLOPT_POSTFIELDS, ['image' => $cfile]);
        curl_setopt($ch_img, CURLOPT_RETURNTRANSFER, true);
        $res_img = curl_exec($ch_img);
        @unlink($tmp_file);
        
        if ($res_img) {
            $img_data_res = json_decode($res_img, true);
            if (isset($img_data_res['name'])) {
                $comfy_image_filename = $img_data_res['name'];
            }
        }
    } elseif (!empty($tray_comfy_filenames[1])) {
        // Magia Retrocompatible...
        $comfy_image_filename = $tray_comfy_filenames[1];
        $init_image_base64 = "TRAY_IMAGE_USED";
    }
    
    // ====================================================================
    // --- 3. SUBIR EL AUDIO A COMFYUI (NOMBRE ÚNICO, RAÍZ) ---
    // ====================================================================
    if (!empty($_POST['audio_data'])) {
        $audData = $_POST['audio_data'];
        if (strpos($audData, 'base64,') !== false) {
            $audData = explode('base64,', $audData)[1];
        }
        
        // 1. FORZAMOS UN NOMBRE ÚNICO PARA EVITAR CACHÉ Y RENOMBRES
        $nombre_unico_mp3 = 'aud_' . time() . '_' . mt_rand(100,999) . '.mp3';
        $tmp_audio = sys_get_temp_dir() . '/' . $nombre_unico_mp3;
        file_put_contents($tmp_audio, base64_decode($audData));
        
        $cfile_aud = function_exists('curl_file_create') ? curl_file_create($tmp_audio, 'audio/mpeg', $nombre_unico_mp3) : '@' . realpath($tmp_audio) . ';filename=' . $nombre_unico_mp3;
        
        $ch_aud = curl_init(COMFY_URL . '/upload/image'); 
        curl_setopt($ch_aud, CURLOPT_POST, true);
        curl_setopt($ch_aud, CURLOPT_POSTFIELDS, ['image' => $cfile_aud]);
        curl_setopt($ch_aud, CURLOPT_RETURNTRANSFER, true);
        $res_aud = curl_exec($ch_aud);
        
        @unlink($tmp_audio);
        
        if ($res_aud) {
            $aud_data_res = json_decode($res_aud, true);
            if (isset($aud_data_res['name'])) {
                // Capturamos el nombre exacto que ComfyUI ha guardado
                $comfy_audio_filename = $aud_data_res['name'];
            }
        }
    }
    // ====================================================================
   
    $mask_data_base64 = $_POST['mask_data'] ?? null; 
    
    $out_top = intval($_POST['outpaint_top'] ?? 0);
    $out_bottom = intval($_POST['outpaint_bottom'] ?? 0);
    $out_left = intval($_POST['outpaint_left'] ?? 0);
    $out_right = intval($_POST['outpaint_right'] ?? 0);
    $is_outpainting = ($out_top > 0 || $out_bottom > 0 || $out_left > 0 || $out_right > 0);
    
    // ====================================================================
    // --- CAPTURA DE EXTENSIONES Y PARÁMETROS (RESTAURADO) ---
    // ====================================================================
    
    // --- REACTOR ---
    $reactor_enabled = $_POST['reactor_enabled'] ?? 'false';
    $reactor_image_base64 = $_POST['reactor_image'] ?? null;
    $reactor_saved_face = $_POST['reactor_saved_face'] ?? null;
    $reactor_target_index = $_POST['reactor_target_index'] ?? "0";
    $reactor_source_index = $_POST['reactor_source_index'] ?? "0";
    $reactor_restore_model = $_POST['reactor_restore_model'] ?? "codeformer-v0.1.0.pth";
    if (strtolower($reactor_restore_model) === 'none') {
        $reactor_restore_model = "none"; // Forzamos minúsculas para Python
    }
    $reactor_gender = $_POST['reactor_gender'] ?? "no";
    $reactor_detector = $_POST['reactor_detector'] ?? "retinaface_resnet50";
    $reactor_fidelity = isset($_POST['reactor_fidelity']) ? floatval($_POST['reactor_fidelity']) : 0.75;
    $reactor_visibility = isset($_POST['reactor_visibility']) ? floatval($_POST['reactor_visibility']) : 1.0;
    $pure_faceswap = filter_var($_POST['pure_faceswap'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    // --- IP-ADAPTER ---
    $ipadapter_enabled = $_POST['ipadapter_enabled'] ?? 'false';
    $ipadapter_image_base64 = $_POST['ipadapter_image'] ?? null;
    $ipa_model = $_POST['ipa_model'] ?? "ip-adapter-plus_sdxl_vit-h.safetensors";
    $ipa_weight_type = $_POST['ipa_weight_type'] ?? "linear";
    $ipa_noise = isset($_POST['ipa_noise']) ? floatval($_POST['ipa_noise']) : 0.0;
    // CORREGIDO: Renombrado a $ipa_weight para que coincida con el array inferior
    $ipa_weight = isset($_POST['ipa_weight']) ? floatval($_POST['ipa_weight']) : 0.8; 
    $ipa_start = isset($_POST['ipa_start']) ? floatval($_POST['ipa_start']) : 0.0;
    $ipa_end = isset($_POST['ipa_end']) ? floatval($_POST['ipa_end']) : 1.0;

    // --- CONTROLNET ---
    $controlnet_enabled = $_POST['controlnet_enabled'] ?? 'false';
    $controlnet_image_base64 = $_POST['controlnet_image'] ?? null;
    $controlnet_model = $_POST['controlnet_model'] ?? '';
    $controlnet_preprocessor = $_POST['controlnet_preprocessor'] ?? 'none';
    $controlnet_weight = floatval($_POST['controlnet_weight'] ?? 1.0);
    $controlnet_start = isset($_POST['controlnet_start']) ? floatval($_POST['controlnet_start']) : 0.0;
    $controlnet_end = isset($_POST['controlnet_end']) ? floatval($_POST['controlnet_end']) : 1.0;
    $controlnet_mode = $_POST['controlnet_mode'] ?? 'Balanced';

    // --- ADETAILER ---
    $use_adetailer = isset($_POST['adetailer']) && $_POST['adetailer'] === 'on';
    $adetailer_denoise = isset($_POST['adetailer_denoise']) ? floatval($_POST['adetailer_denoise']) : 0.4;
    // Capturamos el modelo o usamos el de caras por defecto si falla
    $adetailer_model = $_POST['adetailer_model'] ?? 'bbox/face_yolov8m.pt';

    // --- EXTRAS (UPSCALE Y REMBG) ---
    $hires_fix = filter_var($_POST['hires_fix'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $upscale_model = $_POST['upscale_model'] ?? '';
    $upscale_factor = floatval($_POST['upscale_factor'] ?? 1.5);
    $aurasr_enabled = filter_var($_POST['aurasr_enabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    $remove_background = filter_var($_POST['remove_background'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $pure_rembg = filter_var($_POST['pure_rembg'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    
    // --- NUEVO: COLOREADO NEURAL (DDColor) ---
    $ddcolor_enabled = isset($_POST['ddcolor_enabled']) && ($_POST['ddcolor_enabled'] === '1' || $_POST['ddcolor_enabled'] === 'true' || $_POST['ddcolor_enabled'] === 'on');
    $ddcolor_model = $_POST['ddcolor_model'] ?? 'ddcolor_artistic.pth';
    $pure_ddcolor = filter_var($_POST['pure_ddcolor'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    
    // --- NUEVO: ILUMINACIÓN NEURAL (IC-Light) ---
    $iclight_enabled = isset($_POST['iclight_enabled']) && ($_POST['iclight_enabled'] === '1' || $_POST['iclight_enabled'] === 'true' || $_POST['iclight_enabled'] === 'on');
    $iclight_direction = $_POST['iclight_direction'] ?? 'Left Light';
    $iclight_prompt_panel = trim($_POST['iclight_prompt'] ?? '');
    
    // Bloqueo de seguridad contextual: si no es categoría fotográfica, anulamos IC-Light
    if (in_array($selector, ['[VIDEO]', '[LLM]', '[CHANT]', '[VISION]', '[NATURAL_IMAGE]'])) {
        $iclight_enabled = false;
    }
	
	// --- NUEVO: BORRADO MÁGICO (LaMa) ---
    $lama_enabled = filter_var($_POST['lama_enabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    // -----------------------------------------

    $dynamic_thresholding = filter_var($_POST['dynamic_thresholding'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    // ====================================================================

    $neg_prompt_post = trim($_POST['negative_prompt'] ?? "");
    $neg_prompt = !empty($neg_prompt_post) ? $neg_prompt_post : "low quality, blurry, text, watermark";
    
    // --- ACTIVACIÓN DEL MOTOR DE WILDCARDS ---
    $posPrompt = process_dynamic_prompts($posPrompt);
    $neg_prompt = process_dynamic_prompts($neg_prompt);
    
    // Array de LoRAs Avanzado (High/Low)
    $lora_names = $_POST['lora_names'] ?? [];
    $lora_strengths_high = $_POST['lora_strengths_high'] ?? [];
    $lora_strengths_low = $_POST['lora_strengths_low'] ?? [];
    $lora_metadata_list = []; 
    if (!is_array($lora_names) && !empty($lora_names)) {
        $lora_names = [$lora_names]; 
        $lora_strengths_high = [$_POST['lora_strength_high'] ?? 0.8];
        $lora_strengths_low = [$_POST['lora_strength_low'] ?? 0.8];
    }
    
    // Restricciones Especiales para Avanzado / Pro (Filtro de Carpetas Privadas)
    if ($is_pro && !$is_admin) {
        if (preg_match('/_C[\/\\\\]/i', $model_path)) { 
            echo json_encode(['error' => __('err_pro_private_folder')]);
            exit();
        }
        foreach ($lora_names as $k => $lname) {
            if (preg_match('/_C[\/\\\\]/i', $lname)) { 
                unset($lora_names[$k]); 
                unset($lora_strengths_high[$k]); 
                unset($lora_strengths_low[$k]);  
            }
        }
        $lora_names = array_values($lora_names); 
        $lora_strengths_high = array_values($lora_strengths_high); 
        $lora_strengths_low = array_values($lora_strengths_low);
    }
    
    // Parámetros Base
    $seed = ($user_seed > -1) ? $user_seed : mt_rand(1, 2147483647); 
    $steps = 30; 
    $cfg = 5.0; 
    $sampler = "euler_ancestral"; 
    $scheduler = "karras"; 
    $sampler_denoise = 1.0; 

    // --- DETECCIÓN DE ARQUITECTURA ---
    $model_lower = strtolower($model_path);
    
    // 1. Separamos Arquitecturas
    $is_flux = (strpos($model_lower, 'flux') !== false);
    $is_sd35 = (strpos($model_lower, 'sd35') !== false || strpos($model_lower, 'sd3.5') !== false);
    $is_qwen = (strpos($model_lower, 'qwen') !== false);
    $is_krea2 = (strpos($model_lower, 'krea2') !== false || strpos($model_lower, 'krea-2') !== false);
    $is_gguf = (strpos($model_lower, '.gguf') !== false);
    $is_chroma = (strpos($model_lower, 'chroma') !== false && strpos($model_lower, 'zavy') === false);
    $is_hunyuan = (strpos($model_lower, 'hunyuan') !== false && strpos($model_lower, 'video') === false);
    $is_hidream = (strpos($model_lower, 'hidream') !== false);
    $is_anima = (strpos($model_lower, 'anima') !== false);
    
    // 2. Bandera exclusiva para Z-Image
    $is_zimage = (strpos($model_lower, 'z-image') !== false || strpos($model_lower, 'z_image') !== false || strpos($model_lower, 'zimage') !== false);
    
    // 3. Añadimos a la familia UNETs
    $is_unet = (stripos($model_path, 'unet/') !== false || stripos($model_path, 'unet\\') !== false || $is_flux || $is_chroma || $is_sd35 || $is_zimage || $is_qwen || $is_gguf || $is_krea2 || $is_hunyuan || $is_hidream || $is_anima);
    
    // 4. Turbo (Añadida también la versión sin guion)
    $is_turbo = (strpos($model_lower, 'turbo') !== false || strpos($model_lower, 'schnell') !== false);
    
    // --- SELECCIÓN INTELIGENTE DE VAE ---
    $vae_name = "ae.safetensors"; 
    
    if (strpos($model_lower, 'flux2') !== false) {
        $vae_name = "flux2_vae.safetensors";
    } elseif ($is_flux) {
        $vae_name = "flux_vae.safetensors";
    } elseif ($is_sd35) {
        $vae_name = "sd35_vae.safetensors";
    } elseif ($is_qwen || $is_krea2) {
        $vae_name = "qwen_image_vae.safetensors";
    } elseif ($is_chroma) {
        $vae_name = "flux_vae.safetensors";    
    } elseif ($is_hunyuan) {
        $vae_name = "hunyuan_image_2.1_vae_fp16.safetensors";
    } elseif ($is_hidream) {
        $vae_name = "ae.safetensors";
    } elseif ($is_anima) {
        $vae_name = "qwen_image_vae.safetensors";
    } elseif ($is_zimage) {
        if ($is_turbo) {
            $vae_name = "zImageTurbo_vae.safetensors";
        } else {
            $vae_name = "zImageClearVae_clear.safetensors";
        }
    }

    // --- CONFIGURACIÓN DE SAMPLER ---
    if ($is_turbo) {
        $steps = 6; 
        $cfg = 1.5; 
        $sampler = "euler"; 
        $scheduler = "simple";
    } elseif ($is_qwen) {
        $steps = 20; 
        $cfg = 1.0; 
        $sampler = "euler"; 
        $scheduler = "simple";
    } elseif ($is_krea2) {
        $steps = 8; 
        $cfg = 1.0; 
        $sampler = "euler"; 
        $scheduler = "simple";
    } elseif ($is_chroma) {
        $steps = 10; 
        $cfg = 1.0; 
        $sampler = "euler"; 
        $scheduler = "simple";
    } elseif ($is_hunyuan) {
        $steps = 25; 
        $cfg = 6.0; 
        $sampler = "euler"; 
        $scheduler = "normal";
    } elseif ($is_hidream) {
        $steps = 30; 
        $cfg = 5.0; 
        $sampler = "ipndm"; 
        $scheduler = "beta"; 
    } elseif ($is_anima) {
        $steps = 30; 
        $cfg = 5.0; 
        $sampler = "euler"; 
        $scheduler = "normal"; 
    } elseif ($is_flux || $is_sd35 || $is_zimage) {
        $steps = 25; 
        $cfg = 4.0; 
        $sampler = "euler"; 
        $scheduler = "simple";
    }
    
	// ====================================================================
    // --- APLICAR CONFIGURACIÓN (INTERFAZ vs BENCHMARK) ---
    // ====================================================================
    $es_benchmark = filter_var($_POST['is_benchmark'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    if ($es_benchmark) {
        // 🚀 MODO BENCHMARK: Consultamos directamente a la BD
        $ruta_limpia = str_replace('\\', '/', $model_path);
        $solo_nombre = basename($ruta_limpia); 
        
        $stmt_ajustes = $pdo->prepare("SELECT * FROM modelos_ia WHERE nombre_archivo LIKE ? LIMIT 1");
        $stmt_ajustes->execute(['%' . $solo_nombre]);
        $ajustes_bd = $stmt_ajustes->fetch(PDO::FETCH_ASSOC);

        if ($ajustes_bd) {
            // ¡Nombres de columnas extraídos de tu tabla real!
            if (!empty($ajustes_bd['default_steps']) && intval($ajustes_bd['default_steps']) > 0) {
                $steps = intval($ajustes_bd['default_steps']);
            }
            if (!empty($ajustes_bd['default_cfg']) && floatval($ajustes_bd['default_cfg']) > 0) {
                $cfg = floatval($ajustes_bd['default_cfg']);
            }
            if (!empty($ajustes_bd['default_sampler'])) {
                $sampler = $ajustes_bd['default_sampler'];
            }
            if (!empty($ajustes_bd['default_scheduler'])) {
                $scheduler = $ajustes_bd['default_scheduler'];
            }
        }
    } else {
        // 🖱️ MODO NORMAL: Mandan las cajas de texto de la interfaz
        if (isset($_POST['steps']) && intval($_POST['steps']) > 0) {
            $steps = intval($_POST['steps']);
        }
        if (isset($_POST['cfg']) && floatval($_POST['cfg']) > 0) {
            $cfg = floatval($_POST['cfg']);
        }
        if (!empty($_POST['sampler'])) {
            $sampler = $_POST['sampler'];
        }
        if (!empty($_POST['scheduler'])) {
            $scheduler = $_POST['scheduler'];
        }
    }
	
	// =========================================================
    // 🛡️ ESCUDO ANTI-INTERFAZ (Protección FLUX/Chroma) AUTOMATIZA EL CFG Y LOS STEPS🛡️
    // =========================================================
 /*   if ($is_flux) {
        if (strpos($sampler, 'ancestral') !== false || strpos($sampler, 'sde') !== false) {
            $sampler = "euler"; 
        }
    }
    if ($is_turbo) {
        if ($cfg > 2.0) $cfg = 1.5;
        if ($steps > 15) $steps = 6;
    }*/
    // =========================================================

  // Añadimos excepciones a la lista permitida para no bloquear la carga
    if ($is_unet && !$is_flux && !$is_chroma && !$is_sd35 && !$is_zimage && !$is_qwen && !$is_gguf && !$is_krea2 && !$is_hunyuan && !$is_hidream && !$is_anima && strpos($model_lower, 'video') === false) {
        echo json_encode(['error' => __('err_pure_unet_load')]); 
        exit();
    }
    
    // Punteros de Enrutamiento Dinámico
    $current_positive = ["6", 0];
    $current_negative = ["7", 0];
    $current_model_node = "4"; 
    $current_image_node = "8"; 
    $base_clip_node = "4"; 
    $base_clip_index = 1; 
    $base_vae_node = "4"; 
    $base_vae_index = 2;

    $workflow = [];
    $modelo_seguro = $model_path; 
    $is_video_mode = ($selector === '[VIDEO]' || strpos(strtolower($modelo_seguro), 'ltx') !== false);
    
    // ====================================================================
    // --- NUEVO MOTOR HÍBRIDO BLINDADO (LTX VIA JSON) ---
    // ====================================================================
    if ($is_video_mode) { 
        
        $video_frames = isset($_POST['video_frames']) ? intval($_POST['video_frames']) : 33;
        
        $aspect = $_POST['video_aspect_ratio'] ?? '832x480';
        $parts = explode('x', $aspect);
        if (isset($parts[0]) && intval($parts[0]) > 0) $width = intval($parts[0]);
        if (isset($parts[1]) && intval($parts[1]) > 0) $height = intval($parts[1]);

        // ====================================================================
        // --- 1. BLOQUE LTX-VIDEO ---
        // ====================================================================
        if (strpos(strtolower($modelo_seguro), 'ltx') !== false) {
            
            $n = round(($video_frames - 1) / 8);
            $video_frames_ltx = max(9, ($n * 8) + 1);

            $nombre_modelo_limpio = strtolower(basename($modelo_seguro));
			
			// ==========================================================
            // 🌟 NUEVA RUTA: LTX 2.5 (Vía JSON Plantilla Estricta)
            // ==========================================================
            if (strpos($nombre_modelo_limpio, '25') !== false || strpos($nombre_modelo_limpio, '2.5') !== false) {
                
                // 🛡️ ESCUDO MATEMÁTICO LTX 2.5 🛡️
                $width  = floor($width / 32) * 32;
                $height = floor($height / 32) * 32;
                
                $n = round(($video_frames - 1) / 8);
                $video_frames_ltx = max(9, ($n * 8) + 1);

                $ruta_json = __DIR__ . '/../workflows/LTX2.5_Video.json';

                $reemplazos = [
                    '__SEED__' => $seed,
                    '__WIDTH__' => $width,
                    '__HEIGHT__' => $height,
                    '__VIDEO_FRAMES__' => $video_frames_ltx,
                    '__STEPS__' => $steps,
                    '__CFG__' => $cfg,
                    '__SAMPLER__' => $sampler,
                    '__SCHEDULER__' => $scheduler,
                    '__MODELO__' => $modelo_seguro,
                    '__PROMPT_POSITIVO__' => $posPrompt,
                    '__PROMPT_NEGATIVO__' => $neg_prompt,
                    '__INIT_IMAGE__' => $comfy_image_filename
                ];

                $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);

                // --- 🛡️ FIX 1: CARGADOR GGUF DINÁMICO ---
                if (strpos(strtolower($modelo_seguro), '.gguf') !== false) {
                    $workflow["434:422"]["class_type"] = "UnetLoaderGGUF";
                    unset($workflow["434:422"]["inputs"]["weight_dtype"]);
                }

                // --- 🛡️ FIX 2: RECONEXIÓN A CALIDAD MÁXIMA ---
                $workflow["451"]["inputs"]["images"] = ["435:430", 0]; 
                $workflow["451"]["inputs"]["audio"] = ["435:426", 0];
                
                unset($workflow["341"]);

                // --- REPARACIÓN LTX: GUARDAR HISTORIAL Y LORAS ---
                $lora_active = false;
                if (empty($lora_metadata_list) && is_array($lora_names)) {
                    foreach ($lora_names as $index => $lname) {
                        if (!empty(trim($lname)) && strtolower($lname) !== 'ninguno') {
                            $lstr = floatval($lora_strengths_high[$index] ?? 0.8);
                            $lora_metadata_list[] = basename($lname, '.safetensors') . " ($lstr)";
                            
                            if ($index === 0) { 
                                $workflow["432"]["inputs"]["lora_name"] = $lname;
                                $workflow["432"]["inputs"]["strength_model"] = $lstr;
                                $lora_active = true;
                            }
                        }
                    }
                }

                // 🛡️ RECONEXIÓN DE CABLES CUANDO NO HAY LORA 🛡️
                if (!$lora_active) {
                    $workflow["391"]["inputs"]["model"] = ["434:422", 0]; // CFG Guider 1
                    $workflow["442"]["inputs"]["model"] = ["434:422", 0]; // CFG Guider 2
                    
                    // 👇 AQUÍ ESTÁN LOS DOS CABLES QUE FALTABAN 👇
                    $workflow["388"]["inputs"]["model"] = ["434:422", 0]; // Scheduler 1
                    $workflow["440"]["inputs"]["model"] = ["434:422", 0]; // Scheduler 2 (Upscale)
                    
                    unset($workflow["432"]); // Destruimos el cargador de LoRAs
                }

                $meta_json_array = [
                    'Model' => basename($model_path), 
                    'Resolution' => $width . 'x' . $height, 
                    'Seed' => $seed, 
                    'Steps' => $steps, 
                    'CFG Scale' => $cfg, 
                    'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')', 
                    'Batch Size' => 1, 
                    'LoRAs' => empty($lora_metadata_list) ? __('lbl_none') : implode(', ', $lora_metadata_list)
                ];
                if (isset($user_shift) && $user_shift !== null) {
					$meta_json_array['Flow Shift'] = $user_shift;
				}
				$meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

                if ($historial_id > 0) {
                    $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET prompt_positivo = ?, prompt_negativo = ?, metadata = ? WHERE id = ?");
                    $stmt_upd->execute([$posPrompt, $neg_prompt, $meta_json, $historial_id]);
                } else {
                    $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_ins->execute([$user_id, $selector, $posPrompt, $posPrompt, $neg_prompt, $meta_json]);
                    $historial_id = $pdo->lastInsertId();
                }

                // --- ESCUDO TEXT-TO-VIDEO ---
                $tiene_imagen = ($comfy_image_filename !== "none" && !empty($comfy_image_filename));
                if (!$tiene_imagen) {
                    $workflow["269"] = [
                        "inputs" => ["width" => $width, "height" => $height, "batch_size" => 1, "color" => 0],
                        "class_type" => "EmptyImage"
                    ];
                    if (isset($workflow["434:386"])) {
                        $workflow["434:386"]["inputs"]["value"] = true; 
                    }
                }

                // Ajuste de FPS final
                if (isset($workflow["451"]["inputs"]["frame_rate"])) {
                    $workflow["451"]["inputs"]["frame_rate"] = $video_fps;
                }

                $current_image_node = "451";
                goto EJECUTAR_COMFYUI;
            }
            // ==========================================================
            // 🧱 FIN RUTA LTX 2.5
            // ==========================================================
			
            if (strpos($nombre_modelo_limpio, 'dev') !== false || strpos($nombre_modelo_limpio, 'q4_') !== false || strpos($nombre_modelo_limpio, 'q5_') !== false) {
                $ruta_json = __DIR__ . '/../workflows/LTX_Video_Dev.json';
            } else {
                $ruta_json = __DIR__ . '/../workflows/LTX_Video_Distilled.json';
            }

            $reemplazos = [
                '__SEED__' => $seed,
                '__WIDTH__' => $width,
                '__HEIGHT__' => $height,
                '__VIDEO_FRAMES__' => $video_frames_ltx,
                '__STEPS__' => $steps,
                '__CFG__' => $cfg,
                '__SAMPLER__' => $sampler,
                '__SCHEDULER__' => $scheduler,
                '__MODELO__' => $modelo_seguro,
                '__PROMPT_POSITIVO__' => $posPrompt,
                '__PROMPT_NEGATIVO__' => $neg_prompt,
                '__INIT_IMAGE__' => $comfy_image_filename,
                '__INIT_AUDIO__' => $comfy_audio_filename
            ];

            $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);

            // --- REPARACIÓN LTX: GUARDAR HISTORIAL Y LORAS ANTES DEL SALTO ---
            if (empty($lora_metadata_list) && is_array($lora_names)) {
                foreach ($lora_names as $index => $lname) {
                    if (!empty(trim($lname))) {
                        $lstr = floatval($lora_strengths_high[$index] ?? 0.8);
                        $lora_metadata_list[] = basename($lname, '.safetensors') . " ($lstr)";
                    }
                }
            }

            $meta_json_array = [
                'Model' => basename($model_path), 
                'Resolution' => $width . 'x' . $height, 
                'Seed' => $seed, 
                'Steps' => $steps, 
                'CFG Scale' => $cfg, 
                'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')', 
                'Batch Size' => $batch_size, 
                'LoRAs' => empty($lora_metadata_list) ? __('lbl_none') : implode(', ', $lora_metadata_list)
            ];
            if (isset($user_shift) && $user_shift !== null) {
				$meta_json_array['Flow Shift'] = $user_shift;
			}
			$meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

            if ($historial_id > 0) {
                $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET prompt_positivo = ?, prompt_negativo = ?, metadata = ? WHERE id = ?");
                $stmt_upd->execute([$posPrompt, $neg_prompt, $meta_json, $historial_id]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([$user_id, $selector, $posPrompt, $posPrompt, $neg_prompt, $meta_json]);
                $historial_id = $pdo->lastInsertId();
            }
            // -----------------------------------------------------------------

            // 🛡️ EL ESCUDO DE PHP 🛡️
            if (isset($workflow["302"]["inputs"]["type"])) { $workflow["302"]["inputs"]["type"] = "ltxv"; }
            
            if (isset($workflow["241"])) {
                $workflow["241"] = [
                    "inputs" => ["upscale_method" => "bilinear", "width" => $width, "height" => $height, "crop" => "center", "image" => ["240", 0]],
                    "class_type" => "ImageScale"
                ];
            }

            if (isset($workflow["320"])) {
                $workflow["320"]["class_type"] = "VAELoader";
                unset($workflow["320"]["inputs"]["device"], $workflow["320"]["inputs"]["weight_dtype"]);
            }

            if (isset($workflow["162"]["inputs"])) { $workflow["162"]["inputs"]["width"] = $width; $workflow["162"]["inputs"]["height"] = $height; }
            if (isset($workflow["249"]["inputs"])) { $workflow["249"]["inputs"]["width"] = $width; $workflow["249"]["inputs"]["height"] = $height; }

            if (isset($workflow["302"]["inputs"]["type"]) && $workflow["302"]["inputs"]["type"] === "ltx") {
                $workflow["302"]["inputs"]["type"] = "ltxv";
            }

           // ==========================================================
           // ✂️ TIJERAS QUIRÚRGICAS PARA LTX DISTILLED (LEGACY RESTORED) ✂️
           // ==========================================================
           $tiene_imagen = ($comfy_image_filename !== "none" && !empty($comfy_image_filename));
           
           // --- NUEVO: CAPTURAR SI VENIMOS DEL PANEL PRO ---
           $tiene_audio_pro = (!empty($audioConfigPro) && $audioConfigPro['sync_with_video'] === true);

           // LA ÚNICA REGLA EXTRA: Si el vídeo es largo o un chunk, apagamos TODO el audio
           if ($video_frames > 121 || !empty($previous_video)) {
               $comfy_audio_filename = "none";
               $tiene_audio_pro = false;
           }
           
           $tiene_audio = ($comfy_audio_filename !== "none" && !empty($comfy_audio_filename));

           // --- NUEVA LÓGICA DE FPS DINÁMICO ---
           // Si hay audio (viejo o nuevo), LTX necesita 24 FPS para la sincronización.
           $fps_final_ltx = ($tiene_audio || $tiene_audio_pro) ? 24 : $video_fps;

           if (isset($workflow["292"]["inputs"]["value"])) {
               $workflow["292"]["inputs"]["value"] = floatval($fps_final_ltx);
           }

           $video_origen = ["162", 0]; // Por defecto, lienzo vacío (Text-to-Video)

           if ($tiene_imagen) {
               $video_origen = ["239", 0]; // Lienzo con imagen (Image-to-Video)
           } else {
               // Borramos la rama de la imagen y la máscara paramétrica
               unset($workflow["240"], $workflow["241"], $workflow["269"], $workflow["239"], $workflow["249"]); 
           }

           // =======================================================
           // 🛑 1. ENRUTAMIENTO LTX-AV NATIVO (SÁNDWICH OBLIGATORIO)
           // =======================================================
           if (isset($workflow["166"])) {
               // 1. Conectamos siempre el lienzo de vídeo (vacío o con imagen) al fusionador
               $workflow["166"]["inputs"]["video_latent"] = $video_origen;

               // 2. Decidimos qué audio le damos de comer al modelo
               if ($tiene_audio && !$tiene_audio_pro) {
                   $workflow["166"]["inputs"]["audio_latent"] = ["248", 0];
               } else {
                   // INYECCIÓN DINÁMICA: Creamos el nodo de silencio desde PHP por si el JSON físico no lo tiene
                    $workflow["500"] = [
                        "inputs" => [
                            "frames_number" => $video_frames_ltx,
                            "frame_rate" => 24,
                            "batch_size" => 1,
                            "audio_vae" => ["320", 0] // <-- ¡Aquí está el cable obligatorio que me faltó poner!
                        ],
                        "class_type" => "LTXVEmptyLatentAudio"
                    ];
                   $workflow["166"]["inputs"]["audio_latent"] = ["500", 0];
                   
                   // Limpiamos los nodos de carga física para evitar fallos si el usuario no sube audio
                   unset($workflow["309"], $workflow["242"], $workflow["248"]);
               }

               // INYECCIÓN DINÁMICA: Aseguramos que exista el decodificador de audio para extraer el sonido al final
               if (!isset($workflow["501"])) {
                   $workflow["501"] = [
                       "inputs" => [
                           "samples" => ["245", 1],
                           "audio_vae" => ["320", 0]
                       ],
                       "class_type" => "LTXVAudioVAEDecode"
                   ];
               }
           }

            // =======================================================
            // 🎵 2. GESTIÓN UNIFICADA DE AUDIO (PRO + ANTIGUO) EN LTX
            // =======================================================
            $audio_source_node = null;

            if ($tiene_audio_pro) {
                $guion_locutar = !empty($audioConfigPro['prompt_text']) ? $audioConfigPro['prompt_text'] : $posPrompt;

                if ($audioConfigPro['engine'] === 'tts') {
                    if ($tts_engine_pro === 'f5') {
                        $workflow["1010"] = ["inputs" => ["audio" => $audioConfigPro['ref_file']], "class_type" => "LoadAudio"];
                        $workflow["1011"] = [
                            "inputs" => [
                                "sample_audio" => ["1010", 0], "sample_text"  => $audioConfigPro['ref_text'],
                                "speech" => $guion_locutar, "speed" => floatval($audioConfigPro['speed']), "seed" => mt_rand(1, 2147483647),
                                "model" => 'F5v1', "vocoder" => 'auto', "model_type" => 'F5TTS_v1_Base',
                                "remove_silence" => ($audioConfigPro['remove_silence'] === '1' || $audioConfigPro['remove_silence'] === true)
                            ], "class_type" => "F5TTSAudioInputs"
                        ];
                        $audio_source_node = ["1011", 0];

                    } elseif ($tts_engine_pro === 'indextts') {
                        $workflow['1125'] = [
                            'inputs' => [
                                'Happy' => ($tts_emotion_pro === 'happy') ? 0.85 : 0.1, 'Angry' => ($tts_emotion_pro === 'angry') ? 0.85 : 0.1,
                                'Sad' => ($tts_emotion_pro === 'sad') ? 0.85 : 0.1, 'Calm' => ($tts_emotion_pro === 'calm') ? 0.85 : 0.1,
                                'Surprised' => 0.1, 'Afraid' => 0.1, 'Disgusted' => 0.1, 'Melancholic' => 0.1, 'emotion_radar_canvas' => ""
                            ], 'class_type' => 'IndexTTSEmotionOptionsNode'
                        ];
                        $workflow['1065'] = ['inputs' => ['value' => $guion_locutar], 'class_type' => 'PrimitiveStringMultiline'];
                        $workflow['1134'] = ['inputs' => ['audio' => $audioConfigPro['ref_file']], 'class_type' => 'LoadAudio'];
                        $workflow['1130'] = [
                            'inputs' => ['voice_name' => 'none', 'reference_text' => $audioConfigPro['ref_text'], 'trim_start' => 0, 'trim_end' => 0, 'customized' => true, 'opt_audio_input' => ['1134', 0]],
                            'class_type' => 'CharacterVoicesNode'
                        ];
                        $workflow['1123'] = [
                            'inputs' => [
                                'language' => $idioma_tts,
                                'model_path' => 'IndexTTS-2', 'device' => 'auto', 'emotion_alpha' => 0.7, 'use_random' => false,
                                'max_text_tokens_per_segment' => 120, 'interval_silence' => 200, 'temperature' => 0.8, 'top_p' => 0.8,
                                'top_k' => 30, 'do_sample' => true, 'length_penalty' => 0, 'num_beams' => 3, 'repetition_penalty' => 9.5,
                                'max_mel_tokens' => 1500, 'use_fp16' => true, 'use_deepspeed' => true, 'use_cuda_kernel' => 'auto',
                                'use_torch_compile' => false, 'use_accel' => false, 'stream_return' => false, 'more_segment_before' => 0,
                                'low_vram' => false, 'emotion_control' => ['1125', 0]
                            ], 'class_type' => 'IndexTTSEngineNode'
                        ];
                        $workflow['1047'] = [
                            'inputs' => [
                                'text' => ['1065', 0], 'narrator_voice' => 'none', 'seed' => mt_rand(1, 2147483647), 'enable_chunking' => true,
                                'max_chars_per_chunk' => 400, 'chunk_combination_method' => 'auto', 'silence_between_chunks_ms' => 100,
                                'enable_audio_cache' => true, 'batch_size' => 0, 'TTS_engine' => ['1123', 0], 'opt_narrator' => ['1130', 0]
                            ], 'class_type' => 'UnifiedTTSTextNode'
                        ];
                        $audio_source_node = ["1047", 0];

                    } else {
                        // OmniVoice
                        $omni_style = ($tts_emotion_pro === 'whisper') ? 'whisper' : 'None';
                        $omni_lang  = ($idioma_tts === 'Chinese') ? 'Chinese' : 'English';
                        $workflow['1025'] = [
                            'inputs' => [
                                'gender' => $genero_pro, 'age' => $edad_pro, 'pitch' => 'None',
                                'style' => $omni_style, 'accent' => 'None', 'dialect' => 'None',
                                'output_language' => $omni_lang, 'instruct_text' => ''
                            ], 'class_type' => 'OmniVoiceInstructionBuilderNode'
                        ];
                        $workflow['1020'] = [
                            'inputs' => [
                                'model_variant' => 'OmniVoice', 'device' => 'auto', 'language' => 'Auto',
                                'num_step' => 32, 'guidance_scale' => 2, 't_shift' => 0.1, 'speed' => 1.0,
                                'duration' => 0, 'dtype' => 'auto', 'instruct' => '', 'layer_penalty_factor' => 5,
                                'position_temperature' => 5, 'class_temperature' => 0, 'denoise' => true,
                                'preprocess_prompt' => true, 'postprocess_output' => true, 'audio_chunk_duration' => 15,
                                'audio_chunk_threshold' => 30, 'mode' => 'Voice Design'
                            ], 'class_type' => 'OmniVoiceEngineNode'
                        ];
                        $workflow['1017'] = [
                            'inputs' => [
                                'reference_text' => $guion_locutar, 'seed' => mt_rand(1, 2147483647),
                                'voice_instruction' => ['1025', 0], 'TTS_engine' => ['1020', 0]
                            ], 'class_type' => 'UnifiedVoiceDesignerNode'
                        ];
                        $audio_source_node = ["1017", 1];
                    }
                } elseif ($audioConfigPro['engine'] === 'sfx') {
                    $workflow["2020"] = ["inputs" => ["ckpt_name" => "stable-audio-open-1.0.safetensors"], "class_type" => "CheckpointLoaderSimple"];
                    $workflow["2021"] = ["inputs" => ["clip_name" => "t5-base.safetensors", "type" => "stable_audio"], "class_type" => "CLIPLoader"];
                    $workflow["2022"] = ["inputs" => ["seconds" => floatval($audioConfigPro['seconds']), "batch_size" => 1], "class_type" => "EmptyLatentAudio"];
                    $workflow["2023"] = ["inputs" => ["text" => $guion_locutar, "clip" => ["2021", 0]], "class_type" => "CLIPTextEncode"];
                    $workflow["2024"] = ["inputs" => ["text" => "", "clip" => ["2021", 0]], "class_type" => "CLIPTextEncode"];
                    $workflow["2025"] = [
                        "inputs" => [
                            "seed" => mt_rand(1, 2147483647), "steps" => 20, "cfg" => 4.0, "sampler_name" => "euler",
                            "scheduler" => "normal", "denoise" => 1.0, "model" => ["2020", 0], "positive" => ["2023", 0],
                            "negative" => ["2024", 0], "latent_image" => ["2022", 0]
                        ], "class_type" => "KSampler"
                    ];
                    $workflow["2026"] = ["inputs" => ["samples" => ["2025", 0], "vae" => ["2020", 2]], "class_type" => "VAEDecodeAudio"];
                    $audio_source_node = ["2026", 0];
                }
				} else {
					// ¡LA MAGIA DE LTX-AV!
					// Si no usas Audio Pro, el audio FINAL siempre saldrá del decodificador de LTX (Nodo 501)
					// Ya sea porque subiste un mp3 (y el modelo lo procesó) o porque lo generó de la nada (silencio)
					if (isset($workflow["501"])) {
						$audio_source_node = ["501", 0]; 
					} elseif ($tiene_audio) {
						// Fallback de seguridad por si usas un modelo antiguo
						$audio_source_node = ["309", 0]; 
					}
            }

            // =======================================================
            // 👄 3. APLICACIÓN DE WAV2LIP Y ENSAMBLAJE FINAL
            // =======================================================
            if ($audio_source_node === null) {
                if (isset($workflow["291"]["inputs"]["audio"])) {
                    unset($workflow["291"]["inputs"]["audio"]);
                }
            } else {
                // Conectamos el audio resultante al empaquetador de vídeo (Nodo 291 en LTX)
                if (isset($workflow["291"]["inputs"])) {
                    $workflow["291"]["inputs"]["audio"] = $audio_source_node;
                }

                // Capturamos si el usuario ha pedido sincronización labial (Panel clásico o Panel Pro)
                $usar_wav2lip_clasico = filter_var($_POST['wav2lip'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
                $usar_wav2lip_pro = (!empty($audioConfigPro['sync_with_video']) && $audioConfigPro['sync_with_video'] === true);
                
                $usar_wav2lip = ($usar_wav2lip_clasico || $usar_wav2lip_pro);
                
                $es_sfx = ($tiene_audio_pro && isset($audioConfigPro['engine']) && $audioConfigPro['engine'] === 'sfx');

                if ($usar_wav2lip && !$es_sfx) {
                    $workflow["1002_wav2lip"] = [
                        "inputs" => [
                            "mode" => "sequential",       
                            "face_detect_batch" => 8,     
                            "images" => ["290", 0], // En LTX, el VAEDecode suele ser el 290 (equivalente al 87 de Wan)
                            "audio" => $audio_source_node                      
                        ],
                        "class_type" => "Wav2Lip"
                    ];
                    
                    if (isset($workflow["291"]["inputs"])) {
                        // Sobreescribimos el vídeo mudo por el vídeo procesado con Wav2Lip
                        $workflow["291"]["inputs"]["images"] = ["1002_wav2lip", 0]; 
                        $workflow["291"]["inputs"]["frame_rate"] = 30; // Obligatorio para Wav2Lip
                    }
                }
            }

            $current_image_node = "290";
            goto EJECUTAR_COMFYUI;
        } // 🚨 ¡AQUÍ ESTABA EL ERROR MORTAL! Faltaba esta llave de cierre de LTX.
		
		// ====================================================================
        // --- 1.5. BLOQUE MINIMAX H3 VIDEO ---
        // ====================================================================
        if (strpos(strtolower($modelo_seguro), 'minimax') !== false) {

            // Minimax razona en segundos, no en frames absolutos. 
            // Calculamos los segundos a partir de los frames que manda la web (Asumiendo 24fps)
            $segundos = max(1.0, round($video_frames / 24, 1));

            $ruta_json = __DIR__ . '/../workflows/minimax_h3.json';

            $reemplazos = [
                '__SEED__' => $seed,
                '__WIDTH__' => $width,
                '__HEIGHT__' => $height,
                '__TIEMPO_SEGUNDOS__' => $segundos,
                '__STEPS__' => $steps,
                '__SAMPLER__' => $sampler,
                '__SCHEDULER__' => $scheduler,
                '__MODELO__' => $modelo_seguro,
                '__PROMPT_POSITIVO__' => $posPrompt
            ];

            $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);

            // --- GESTIÓN DEL LORA Y PUENTEO INTELIGENTE ---
            $lora_active = false;
            if (empty($lora_metadata_list) && is_array($lora_names)) {
                foreach ($lora_names as $index => $lname) {
                    if (!empty(trim($lname)) && strtolower($lname) !== 'ninguno') {
                        $lstr = floatval($lora_strengths_high[$index] ?? 1.0);
                        $lora_metadata_list[] = basename($lname, '.safetensors') . " ($lstr)";

                        // Minimax base solo soporta 1 LoRA encadenado en este nodo
                        if ($index === 0) { 
                            $ruta_lora = $lname;
                            
                            // Solo añadimos la carpeta si el texto viene sin rutas previas
                            if (strpos($ruta_lora, '\\') === false && strpos($ruta_lora, '/') === false) {
                                $ruta_lora = "minimax_h3/" . $ruta_lora;
                            }
                            
                            // Aseguramos extensión
                            if (!str_ends_with(strtolower($ruta_lora), '.safetensors')) {
                                $ruta_lora .= '.safetensors';
                            }

                            $workflow["137"]["inputs"]["lora_name"] = $ruta_lora;
                            $workflow["137"]["inputs"]["strength_model"] = $lstr;
                            $lora_active = true;
                        }
                    }
                }
            }

            if (!$lora_active) {
                // PUENTEO: Si no hay LoRA, conectamos SigmaShift(149) directo a SageAttention(146)
                $workflow["149"]["inputs"]["model"][0] = "146";
                unset($workflow["137"]); // Destruimos el nodo LoRA para que ComfyUI no crashee
            }

            // --- INYECCIÓN MULTIMODAL MINIMAX H3 (ReferenceToVideo) ---
            $tiene_multimodal = false;

            // 1. ¿Hay imagen principal que NO provenga de la bandeja? (Image-to-Video clásico)
            $foto_principal = null;
            if (!empty($comfy_image_filename) && $comfy_image_filename !== "none" && $init_image_base64 !== "TRAY_IMAGE_USED") {
                $foto_principal = $comfy_image_filename;
                $tiene_multimodal = true;
            }

            // 2. ¿Hay imágenes en la bandeja multicarga? (Referenciación Múltiple)
            $fotos_bandeja = [];
            if (!empty($tray_comfy_filenames)) {
                $fotos_bandeja = $tray_comfy_filenames;
                $tiene_multimodal = true;
            }

            if ($tiene_multimodal) {
                // Modificamos el Nodo Base a la nueva arquitectura ReferenceToVideo
                $workflow["131"]["class_type"] = "MiniMaxH3ReferenceToVideo";
                
                // Limpiamos la basura de la arquitectura antigua
                unset($workflow["131"]["inputs"]["image"]);
                unset($workflow["131"]["inputs"]["images"]);
                unset($workflow["131"]["inputs"]["first_frame"]);

                // 🌟 FIX: PARÁMETROS OBLIGATORIOS DEL NUEVO NODO 🌟
                // El VAE de audio ya está en el nodo 120 de tu plantilla original
                $workflow["131"]["inputs"]["audio_vae"] = ["120", 0];
                $workflow["131"]["inputs"]["ref_image_size"] = "max"; 

                $indice_conector = 0; // El enrutador empieza a contar desde el 0

                // A) Conectamos la foto principal DIRECTAMENTE al nodo de vídeo
                if ($foto_principal) {
                    $node_img_prin = "131_prin_load";
                    $workflow[$node_img_prin] = ["inputs" => ["image" => $foto_principal, "upload" => "image"], "class_type" => "LoadImage"];
                    
                    // ¡Enchufe quirúrgico!
                    $workflow["131"]["inputs"]["ref_images.ref_image_" . $indice_conector] = [$node_img_prin, 0];
                    $indice_conector++;
                }

                // B) Conectamos el resto de fotos de la bandeja DIRECTAMENTE
                foreach ($fotos_bandeja as $tray_filename) {
                    if ($indice_conector > 8) break; // Máximo 9 imágenes según Kijai

                    $node_img_tray = "131_tray_load_" . $indice_conector;
                    $workflow[$node_img_tray] = ["inputs" => ["image" => $tray_filename, "upload" => "image"], "class_type" => "LoadImage"];
                    
                    // ¡Enchufe quirúrgico!
                    $workflow["131"]["inputs"]["ref_images.ref_image_" . $indice_conector] = [$node_img_tray, 0];
                    $indice_conector++;
                }
            }

            // --- HISTORIAL Y METADATOS (Base de datos) ---
            $meta_json_array = [
                'Model' => basename($model_path),
                'Resolution' => $width . 'x' . $height,
                'Seed' => $seed,
                'Steps' => $steps,
                'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')',
                'Duration' => $segundos . 's',
                'LoRAs' => empty($lora_metadata_list) ? __('lbl_none') : implode(', ', $lora_metadata_list)
            ];
            if (isset($user_shift) && $user_shift !== null) {
				$meta_json_array['Flow Shift'] = $user_shift;
			}
			$meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

            if ($historial_id > 0) {
                $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET prompt_positivo = ?, prompt_negativo = ?, metadata = ? WHERE id = ?");
                $stmt_upd->execute([$posPrompt, $neg_prompt, $meta_json, $historial_id]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([$user_id, $selector, $posPrompt, $posPrompt, $neg_prompt, $meta_json]);
                $historial_id = $pdo->lastInsertId();
            }

            $current_image_node = "142"; // El nodo VHS_VideoCombine de Minimax
            goto EJECUTAR_COMFYUI;
        }
		
		// ====================================================================
        // --- 1.6. BLOQUE HUNYUAN VIDEO ---
        // ====================================================================
        if (strpos(strtolower($modelo_seguro), 'hunyuan') !== false && strpos(strtolower($modelo_seguro), 'video') !== false) {

            $tiene_imagen = (!empty($init_image_base64) && $comfy_image_filename !== "none" && $init_image_base64 !== "TRAY_IMAGE_USED");

            if ($tiene_imagen) {
                $ruta_json = __DIR__ . '/../workflows/Hunyuan_I2V.json';
            } else {
                $ruta_json = __DIR__ . '/../workflows/Hunyuan_T2V.json';
            }

            $reemplazos = [
                '__SEED__' => $seed,
                '__WIDTH__' => $width,
                '__HEIGHT__' => $height,
                '__VIDEO_FRAMES__' => $video_frames,
                '__STEPS__' => $steps,
                '__CFG__' => $cfg,
                '__SAMPLER__' => $sampler,
                '__SCHEDULER__' => $scheduler,
                '__MODELO__' => $modelo_seguro,
                '__PROMPT_POSITIVO__' => $posPrompt,
                '__PROMPT_NEGATIVO__' => $neg_prompt,
                '__INIT_IMAGE__' => $comfy_image_filename
            ];

            $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);

            // --- GESTIÓN DINÁMICA DEL LORA ---
            $lora_active = false;
            $current_model_node = "12"; // Nodo original UNETLoader

            // Detectamos las IDs de los nodos de Sampling de acuerdo a si es I2V o T2V
            $target_sampler_node = $tiene_imagen ? "130" : "132"; // ModelSamplingSD3
            $target_scheduler_node = $tiene_imagen ? "126" : "128"; // BasicScheduler

            if (empty($lora_metadata_list) && is_array($lora_names)) {
                foreach ($lora_names as $index => $lname) {
                    if (!empty(trim($lname)) && strtolower($lname) !== 'ninguno') {
                        $lstr = floatval($lora_strengths_high[$index] ?? 1.0);
                        $lora_metadata_list[] = basename($lname, '.safetensors') . " ($lstr)";

                        // Hunyuan soporta 1 LoRA encadenado mediante esta ruta
                        if ($index === 0) {
                            $ruta_lora = $lname;
                            if (strpos($ruta_lora, '\\') === false && strpos($ruta_lora, '/') === false) {
                                $ruta_lora = "Hunyuan\\" . $ruta_lora;
                            }
                            if (!str_ends_with(strtolower($ruta_lora), '.safetensors')) $ruta_lora .= '.safetensors';

                            $workflow["142_lora"] = [
                                "inputs" => ["lora_name" => $ruta_lora, "strength_model" => $lstr, "model" => [$current_model_node, 0]],
                                "class_type" => "LoraLoaderModelOnly"
                            ];
                            $current_model_node = "142_lora";
                            $lora_active = true;
                        }
                    }
                }
            }

            // Conectamos quirúrgicamente el modelo (con o sin LoRA) a los nodos que lo necesitan
            $workflow[$target_sampler_node]["inputs"]["model"] = [$current_model_node, 0];
            $workflow[$target_scheduler_node]["inputs"]["model"] = [$current_model_node, 0];

            // --- HISTORIAL Y METADATOS ---
            $meta_json_array = [
                'Model' => basename($model_path),
                'Resolution' => $width . 'x' . $height,
                'Seed' => $seed,
                'Steps' => $steps,
                'CFG Scale' => $cfg,
                'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')',
                'Duration' => $video_frames . ' frames',
                'LoRAs' => empty($lora_metadata_list) ? __('lbl_none') : implode(', ', $lora_metadata_list)
            ];
            $meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

            if ($historial_id > 0) {
                $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET prompt_positivo = ?, prompt_negativo = ?, metadata = ? WHERE id = ?");
                $stmt_upd->execute([$posPrompt, $neg_prompt, $meta_json, $historial_id]);
            } else {
                $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([$user_id, $selector, $posPrompt, $posPrompt, $neg_prompt, $meta_json]);
                $historial_id = $pdo->lastInsertId();
            }

            $current_image_node = "102"; // Nodo SaveVideo
            goto EJECUTAR_COMFYUI;
        }

        // ====================================================================
        // --- 2. BLOQUE WAN VIDEO ---
        // ====================================================================
        
        $high_noise_model = $model_path;
        
        $find_high = ['high_noise', 'HIGH_NOISE', 'High_Noise', 'High_noise', '_high_', '_HIGH_', '_High_', 'High.gguf', 'high.gguf', 'High.safetensors', 'high.safetensors'];
        $repl_low  = ['low_noise',  'LOW_NOISE',  'Low_Noise',  'Low_noise',  '_low_',  '_LOW_',  '_Low_',  'Low.gguf', 'low.gguf', 'Low.safetensors', 'low.safetensors'];
        
        $low_noise_model = str_replace($find_high, $repl_low, $model_path);
        $wan_vae = (strpos(strtolower($model_path), '5b') !== false) ? "Wan2.2_VAE.safetensors" : "wan_2.1_VAE.safetensors";

        // 1. Cargadores UNET y VAE
        if (strpos(strtolower($model_path), '.gguf') !== false) {
            $workflow["95"] = ["inputs" => ["unet_name" => $high_noise_model], "class_type" => "UnetLoaderGGUF"];
            $workflow["96"] = ["inputs" => ["unet_name" => $low_noise_model], "class_type" => "UnetLoaderGGUF"];
        } else {
            $workflow["95"] = ["inputs" => ["unet_name" => $high_noise_model, "weight_dtype" => "default"], "class_type" => "UNETLoader"];
            $workflow["96"] = ["inputs" => ["unet_name" => $low_noise_model, "weight_dtype" => "default"], "class_type" => "UNETLoader"];
        }

        $workflow["90"] = ["inputs" => ["vae_name" => $wan_vae], "class_type" => "VAELoader"];

        // 2. Cargador de Texto (Dinámico: GGUF o Safetensors según el modelo base)
        if (strpos(strtolower($model_path), '.gguf') !== false) {
            $workflow["84"] = [
                "inputs" => ["clip_name" => "umt5-xxl-encoder-Q5_K_M.gguf", "type" => "wan"], 
                "class_type" => "CLIPLoaderGGUF"
            ];
        } else {
            $workflow["84"] = [
                "inputs" => ["clip_name" => "umt5_xxl_fp8_e4m3fn_scaled.safetensors", "type" => "wan", "device" => "default"], 
                "class_type" => "CLIPLoader"
            ];
        }

        // 3. Inyección Inteligente de LoRAs
        $current_hn = ["95", 0];
        $current_ln = ["96", 0];
        $current_clip = ["84", 0];

        if (!empty($lora_names)) {
            $v_lora_id = 200;
            $processed_loras = []; 

            for ($j = 0; $j < count($lora_names); $j++) {
                if (in_array($j, $processed_loras)) continue;

                $lname = trim($lora_names[$j]);
                if (empty($lname)) continue;
                
                $lstr_high = floatval($lora_strengths_high[$j] ?? 0.8);
                $lstr_low = floatval($lora_strengths_low[$j] ?? $lstr_high);

                $is_high_by_name = preg_match('/(high_noise|_high_|High\.gguf|High\.safetensors)/i', $lname);
                $is_low_by_name  = preg_match('/(low_noise|_low_|Low\.gguf|Low\.safetensors)/i', $lname);

                $actual_high = $lname;
                $actual_low  = $lname;

                if ($is_high_by_name) {
                    $actual_low = str_replace($find_high, $repl_low, $lname);
                } elseif ($is_low_by_name) {
                    $actual_high = str_replace($repl_low, $find_high, $lname);
                }

                $prefix_parts = preg_split('/(high_noise|low_noise|_high_|_low_|High\.gguf|Low\.gguf)/i', $lname);
                $prefix = $prefix_parts[0] ?? ''; 

                if (($is_high_by_name || $is_low_by_name) && !empty($prefix)) {
                    for ($k = 0; $k < count($lora_names); $k++) {
                        if ($j === $k || in_array($k, $processed_loras)) continue;
                        
                        $lname2 = trim($lora_names[$k]);
                        if (empty($lname2)) continue;

                        $prefix_parts2 = preg_split('/(high_noise|low_noise|_high_|_low_|High\.gguf|Low\.gguf)/i', $lname2);
                        $prefix2 = $prefix_parts2[0] ?? '';

                        if ($prefix === $prefix2) {
                            $is_lname2_low = preg_match('/(low_noise|_low_|Low\.gguf|Low\.safetensors)/i', $lname2);
                            $is_lname2_high = preg_match('/(high_noise|_high_|High\.gguf|High\.safetensors)/i', $lname2);

                            if ($is_high_by_name && $is_lname2_low) {
                                $actual_low = $lname2;
                                $lstr_low = floatval($lora_strengths_high[$k] ?? $lstr_low);
                                $processed_loras[] = $k; 
                                break;
                            } elseif ($is_low_by_name && $is_lname2_high) {
                                $actual_high = $lname2;
                                $lstr_high = floatval($lora_strengths_high[$k] ?? $lstr_high);
                                $processed_loras[] = $k;
                                break;
                            }
                        }
                    }
                }

                $processed_loras[] = $j;

                $workflow[(string)$v_lora_id] = ["inputs" => ["lora_name" => $actual_high, "strength_model" => $lstr_high, "strength_clip" => $lstr_high, "model" => $current_hn, "clip" => $current_clip], "class_type" => "LoraLoader"];
                $current_hn = [(string)$v_lora_id, 0]; 
                $current_clip = [(string)$v_lora_id, 1]; 
                $v_lora_id++;

                $workflow[(string)$v_lora_id] = ["inputs" => ["lora_name" => $actual_low, "strength_model" => $lstr_low, "strength_clip" => 0.0, "model" => $current_ln, "clip" => $current_clip], "class_type" => "LoraLoader"];
                $current_ln = [(string)$v_lora_id, 0]; 
                $v_lora_id++;

                $nombre_h = basename($actual_high);
                $nombre_l = basename($actual_low);
                if ($nombre_h === $nombre_l) {
                    $lora_metadata_list[] = "$nombre_h (High: $lstr_high / Low: $lstr_low)";
                } else {
                    $lora_metadata_list[] = "$nombre_h ($lstr_high) + $nombre_l ($lstr_low)";
                }
            }
        }

        // 3. Text Encoders y Muestreo
        $wan_shift = ($user_shift !== null) ? $user_shift : 5.0; // Si el usuario lo cambió, lo usamos
        
        $workflow["93"] = ["inputs" => ["text" => $posPrompt, "clip" => $current_clip], "class_type" => "CLIPTextEncode"];
        $workflow["89"] = ["inputs" => ["text" => $neg_prompt, "clip" => $current_clip], "class_type" => "CLIPTextEncode"];
        $workflow["104"] = ["inputs" => ["shift" => $wan_shift, "model" => $current_hn], "class_type" => "ModelSamplingSD3"];
        $workflow["103"] = ["inputs" => ["shift" => $wan_shift, "model" => $current_ln], "class_type" => "ModelSamplingSD3"];

        // 4. Preparación del Latente Blindada (Truco de la imagen negra)
        $split_step = max(1, round($steps / 2));
        $latent_source = "";
        $positive_source = ["93", 0]; 
        $negative_source = ["89", 0]; 

        if (!empty($init_image_base64)) {
            if ($init_image_base64 === "TRAY_IMAGE_USED") {
                // Si la imagen viene de la bandeja, ya está en ComfyUI
                $workflow["97"] = ["inputs" => ["image" => $comfy_image_filename, "upload" => "image"], "class_type" => "LoadImage"];
            } else {
                // Subida estándar de Wan
                $tmp_file = sys_get_temp_dir() . '/init_vid_' . uniqid() . '.png';
                file_put_contents($tmp_file, base64_decode($init_image_base64));
                $cfile = function_exists('curl_file_create') ? curl_file_create($tmp_file, 'image/png', 'init_image.png') : '@' . realpath($tmp_file);
                
                $ch_up = curl_init(COMFY_URL . '/upload/image');
                curl_setopt($ch_up, CURLOPT_POST, true); curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile]); curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
                $res_up = json_decode(curl_exec($ch_up), true); @unlink($tmp_file);

                if (isset($res_up['name'])) {
                    $workflow["97"] = ["inputs" => ["image" => $res_up['name'], "upload" => "image"], "class_type" => "LoadImage"];
                }
            }
        } else {
            // TRUCO: Creamos una imagen negra al vuelo para T2V
            $workflow["97"] = ["inputs" => ["width" => $width, "height" => $height, "batch_size" => 1, "color" => 0], "class_type" => "EmptyImage"];
        }
        
        $workflow["97_scale"] = [
            "inputs" => ["upscale_method" => "bilinear", "width" => $width, "height" => $height, "crop" => "center", "image" => ["97", 0]], 
            "class_type" => "ImageScale"
        ];

        $workflow["98"] = [
            "inputs" => ["width" => $width, "height" => $height, "length" => $video_frames, "batch_size" => 1, "positive" => ["93", 0], "negative" => ["89", 0], "vae" => ["90", 0], "start_image" => ["97_scale", 0]],
            "class_type" => "WanImageToVideo"
        ];
        
        $positive_source = ["98", 0];
        $negative_source = ["98", 1];
        $latent_source = ["98", 2];

        // 5. El Corazón de Wan: Doble KSampler
        $workflow["86"] = [
            "inputs" => ["add_noise" => "enable", "noise_seed" => $seed, "steps" => $steps, "cfg" => $cfg, "sampler_name" => $sampler, "scheduler" => $scheduler, "start_at_step" => 0, "end_at_step" => $split_step, "return_with_leftover_noise" => "enable", "model" => ["104", 0], "positive" => $positive_source, "negative" => $negative_source, "latent_image" => $latent_source],
            "class_type" => "KSamplerAdvanced"
        ];
        
        $workflow["85"] = [
            "inputs" => ["add_noise" => "disable", "noise_seed" => $seed, "steps" => $steps, "cfg" => $cfg, "sampler_name" => $sampler, "scheduler" => $scheduler, "start_at_step" => $split_step, "end_at_step" => $steps, "return_with_leftover_noise" => "disable", "model" => ["103", 0], "positive" => $positive_source, "negative" => $negative_source, "latent_image" => ["86", 0]],
            "class_type" => "KSamplerAdvanced"
        ];

        // 6. Decodificador y Salida Dinámica (WebP o MP4)
            $workflow["87"] = ["inputs" => ["samples" => ["85", 0], "vae" => ["90", 0]], "class_type" => "VAEDecode"];
            
            $video_format = $_POST['video_format'] ?? 'image/webp';
            
            // 1. Audio del sistema ANTIGUO (Carga directa / Wav2Lip)
            $tiene_audio = ($comfy_audio_filename !== "none" && !empty($comfy_audio_filename));
            
            // 2. Audio del sistema NUEVO (Panel Pro TTS/SFX)
            $audioConfigPro = !empty($_POST['audio_params']) ? json_decode($_POST['audio_params'], true) : false;
            $tiene_audio_pro = ($audioConfigPro && $audioConfigPro['sync_with_video'] === true);

           // Si hay audio antiguo O hay audio pro, forzamos la creación del nodo MP4
            if ($video_format === 'video/h264-mp4' || $tiene_audio || $tiene_audio_pro) {
                
                // Nodo base VHS_VideoCombine
                $workflow["99"] = [
                    "inputs" => [
                        "frame_rate" => $video_fps,
                        "loop_count" => 0,
                        "filename_prefix" => "byGartyVideo",
                        "format" => "video/h264-mp4", 
                        "pix_fmt" => "yuv420p", 
                        "crf" => 15,                
                        "save_metadata" => true,
                        "pingpong" => false,
                        "save_output" => true,
                        "images" => ["87", 0]
                    ],
                    "class_type" => "VHS_VideoCombine"
                ];

                // =======================================================
                // 🎵 GESTIÓN UNIFICADA DE AUDIO (PRO + ANTIGUO) Y WAV2LIP
                // =======================================================
                $audio_source_node = null;

                // 1. RUTA AUDIO PRO (Generado por IA)
                if ($tiene_audio_pro) {
                    
                    // Definimos el guion a locutar aislando el texto del audio (con fallback de seguridad)
                    $guion_locutar = !empty($audioConfigPro['prompt_text']) ? $audioConfigPro['prompt_text'] : $posPrompt;

                    if ($audioConfigPro['engine'] === 'tts') {
                        
                        if ($tts_engine_pro === 'f5') {
                            $workflow["1010"] = ["inputs" => ["audio" => $audioConfigPro['ref_file']], "class_type" => "LoadAudio"];
                            $workflow["1011"] = [
                                "inputs" => [
                                    "sample_audio" => ["1010", 0], "sample_text"  => $audioConfigPro['ref_text'],
                                    "speech" => $guion_locutar, "speed" => floatval($audioConfigPro['speed']), "seed" => mt_rand(1, 2147483647),
                                    "model" => 'F5v1', "vocoder" => 'auto', "model_type" => 'F5TTS_v1_Base',
                                    "remove_silence" => ($audioConfigPro['remove_silence'] === '1' || $audioConfigPro['remove_silence'] === true)
                                ],
                                "class_type" => "F5TTSAudioInputs"
                            ];
                            $audio_source_node = ["1011", 0];

                        } elseif ($tts_engine_pro === 'indextts') {
                            $workflow['1125'] = [
                                'inputs' => [
                                    'Happy' => ($tts_emotion_pro === 'happy') ? 0.85 : 0.1, 'Angry' => ($tts_emotion_pro === 'angry') ? 0.85 : 0.1,
                                    'Sad' => ($tts_emotion_pro === 'sad') ? 0.85 : 0.1, 'Calm' => ($tts_emotion_pro === 'calm') ? 0.85 : 0.1,
                                    'Surprised' => 0.1, 'Afraid' => 0.1, 'Disgusted' => 0.1, 'Melancholic' => 0.1, 'emotion_radar_canvas' => ""
                                ], 'class_type' => 'IndexTTSEmotionOptionsNode'
                            ];
                            $workflow['1065'] = ['inputs' => ['value' => $guion_locutar], 'class_type' => 'PrimitiveStringMultiline'];
                            
                            // NUEVOS NODOS: LoadAudio + CharacterVoicesNode dinámico
                            $workflow['1134'] = ['inputs' => ['audio' => $audioConfigPro['ref_file']], 'class_type' => 'LoadAudio'];
                            $workflow['1130'] = [
                                'inputs' => ['voice_name' => 'none', 'reference_text' => $audioConfigPro['ref_text'], 'trim_start' => 0, 'trim_end' => 0, 'customized' => true, 'opt_audio_input' => ['1134', 0]],
                                'class_type' => 'CharacterVoicesNode'
                            ];
                            
                            $workflow['1123'] = [
                                'inputs' => [
                                    'language' => $idioma_tts, // <--- INYECTADO EN EL MOTOR
                                    'model_path' => 'IndexTTS-2', 'device' => 'auto', 'emotion_alpha' => 0.7, 'use_random' => false,
                                    'max_text_tokens_per_segment' => 120, 'interval_silence' => 200, 'temperature' => 0.8, 'top_p' => 0.8,
                                    'top_k' => 30, 'do_sample' => true, 'length_penalty' => 0, 'num_beams' => 3, 'repetition_penalty' => 9.5,
                                    'max_mel_tokens' => 1500, 'use_fp16' => true, 'use_deepspeed' => true, 'use_cuda_kernel' => 'auto',
                                    'use_torch_compile' => false, 'use_accel' => false, 'stream_return' => false, 'more_segment_before' => 0,
                                    'low_vram' => false, 'emotion_control' => ['1125', 0]
                                ], 'class_type' => 'IndexTTSEngineNode'
                            ];
                            $workflow['1047'] = [
                                'inputs' => [
                                    'text' => ['1065', 0], 'narrator_voice' => 'none', 'seed' => mt_rand(1, 2147483647), 'enable_chunking' => true,
                                    'max_chars_per_chunk' => 400, 'chunk_combination_method' => 'auto', 'silence_between_chunks_ms' => 100,
                                    'enable_audio_cache' => true, 'batch_size' => 0, 'TTS_engine' => ['1123', 0], 'opt_narrator' => ['1130', 0]
                                ], 'class_type' => 'UnifiedTTSTextNode'
                            ];
                            $audio_source_node = ["1047", 0];

                        } else {
                            // --- MOTOR 3: OMNIVOICE (Diseño Zero-Shot sin Clonación) ---
                            $omni_style = ($tts_emotion_pro === 'whisper') ? 'whisper' : 'None';
                            $omni_lang  = ($idioma_tts === 'Chinese') ? 'Chinese' : 'English';

                            $workflow['1025'] = [
                                'inputs' => [
                                    'gender' => $genero_pro, 'age' => $edad_pro, 'pitch' => 'None',
                                    'style' => $omni_style, 'accent' => 'None', 'dialect' => 'None',
                                    'output_language' => $omni_lang, 'instruct_text' => ''
                                ], 'class_type' => 'OmniVoiceInstructionBuilderNode'
                            ];

                            $workflow['1020'] = [
                                'inputs' => [
                                    'model_variant' => 'OmniVoice', 'device' => 'auto', 'language' => 'Auto',
                                    'num_step' => 32, 'guidance_scale' => 2, 't_shift' => 0.1, 'speed' => 1.0,
                                    'duration' => 0, 'dtype' => 'auto', 'instruct' => '', 'layer_penalty_factor' => 5,
                                    'position_temperature' => 5, 'class_temperature' => 0, 'denoise' => true,
                                    'preprocess_prompt' => true, 'postprocess_output' => true, 'audio_chunk_duration' => 15,
                                    'audio_chunk_threshold' => 30, 'mode' => 'Voice Design'
                                ], 'class_type' => 'OmniVoiceEngineNode'
                            ];

                            $workflow['1017'] = [
                                'inputs' => [
                                    'reference_text' => $guion_locutar, 'seed' => mt_rand(1, 2147483647),
                                    'voice_instruction' => ['1025', 0], 'TTS_engine' => ['1020', 0]
                                ], 'class_type' => 'UnifiedVoiceDesignerNode'
                            ];
                            
                            $audio_source_node = ["1017", 1]; // ⚠️ OmniVoice saca el audio por el puerto 1
                        }
                        
                    } elseif ($audioConfigPro['engine'] === 'sfx') {
                        $workflow["2020"] = [
                            "inputs" => ["ckpt_name" => "stable-audio-open-1.0.safetensors"],
                            "class_type" => "CheckpointLoaderSimple"
                        ];
                        $workflow["2021"] = [
                            "inputs" => ["clip_name" => "t5-base.safetensors", "type" => "stable_audio"],
                            "class_type" => "CLIPLoader"
                        ];
                        $workflow["2022"] = [
                            "inputs" => ["seconds" => floatval($audioConfigPro['seconds']), "batch_size" => 1],
                            "class_type" => "EmptyLatentAudio"
                        ];
                        $workflow["2023"] = [
                            "inputs" => ["text" => $guion_locutar, "clip" => ["2021", 0]],
                            "class_type" => "CLIPTextEncode"
                        ];
                        $workflow["2024"] = [
                            "inputs" => ["text" => "", "clip" => ["2021", 0]],
                            "class_type" => "CLIPTextEncode"
                        ];
                        $workflow["2025"] = [
                            "inputs" => [
                                "seed" => mt_rand(1, 2147483647),
                                "steps" => 20,
                                "cfg" => 4.0,
                                "sampler_name" => "euler",
                                "scheduler" => "normal",
                                "denoise" => 1.0,
                                "model" => ["2020", 0],
                                "positive" => ["2023", 0],
                                "negative" => ["2024", 0],
                                "latent_image" => ["2022", 0]
                            ],
                            "class_type" => "KSampler"
                        ];
                        $workflow["2026"] = [
                            "inputs" => ["samples" => ["2025", 0], "vae" => ["2020", 2]],
                            "class_type" => "VAEDecodeAudio"
                        ];
                        $audio_source_node = ["2026", 0];
                    }
                } 
                // 2. RUTA AUDIO ANTIGUA (Carga de archivo subido directamente)
                elseif ($tiene_audio) {
                    $workflow["1001_audio"] = [
                        "inputs" => ["audio" => $comfy_audio_filename],
                        "class_type" => "LoadAudio"
                    ];
                    $audio_source_node = ["1001_audio", 0];
                }

                // =======================================================
                // 👄 APLICACIÓN DE WAV2LIP Y ENSAMBLAJE FINAL
                // =======================================================
                if ($audio_source_node !== null) {
                    
                    // Capturamos si el usuario ha pedido sincronización labial (Panel clásico o Panel Pro)
					$usar_wav2lip_clasico = filter_var($_POST['wav2lip'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
					$usar_wav2lip_pro = (!empty($audioConfigPro['sync_with_video']) && $audioConfigPro['sync_with_video'] === true);
					
					$usar_wav2lip = ($usar_wav2lip_clasico || $usar_wav2lip_pro);
					
					$es_sfx = ($tiene_audio_pro && isset($audioConfigPro['engine']) && $audioConfigPro['engine'] === 'sfx');

                    // Aplicamos lip-sync solo si se pide y NO es un efecto de sonido
                    if ($usar_wav2lip && !$es_sfx) {
                        $fps_final_wan = 30; // Wav2Lip exige 30fps
                        
                        $workflow["1002_wav2lip"] = [
                            "inputs" => [
                                "mode" => "sequential",       
                                "face_detect_batch" => 8,     
                                "images" => ["87", 0], // Vídeo descodificado original
                                "audio" => $audio_source_node                      
                            ],
                            "class_type" => "Wav2Lip"
                        ];
                        
                        $workflow["99"]["inputs"]["images"] = ["1002_wav2lip", 0]; 
                        $workflow["99"]["inputs"]["frame_rate"] = $fps_final_wan;
                    }

                    // Enchufamos el audio resultante al combinador de vídeo y aseguramos MP4
                    $workflow["99"]["inputs"]["audio"] = $audio_source_node;
                    $workflow["99"]["inputs"]["format"] = "video/h264-mp4";
                }
                // =======================================================
                
            } else {
                // Mantenemos WebP para vídeos mudos ligeros
                $workflow["99"] = [
                    "inputs" => ["filename_prefix" => "byGartyVideo", "fps" => $video_fps, "lossless" => false, "quality" => 95, "method" => "default", "images" => ["87", 0]],
                    "class_type" => "SaveAnimatedWEBP"
                ];
            }

            $current_image_node = "99";
            goto EJECUTAR_COMFYUI;
    }
    
    // ==============================================================================
    // --- TUBERÍA EXCLUSIVA: MODO PURO FACESWAP (BYPASS KSAMPLER) ---
    // ==============================================================================
    if ($reactor_enabled === 'true' && $pure_faceswap && !empty($init_image_base64) && (!empty($reactor_image_base64) || !empty($reactor_saved_face))) {
        // 1. Subimos la imagen original (Base/Destino)
        $tmp_base = sys_get_temp_dir() . '/base_' . uniqid() . '.png';
        file_put_contents($tmp_base, base64_decode($init_image_base64));
        $cfile_base = function_exists('curl_file_create') ? curl_file_create($tmp_base, 'image/png', 'base.png') : '@' . realpath($tmp_base);
        $ch_base = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_base, CURLOPT_POST, true); curl_setopt($ch_base, CURLOPT_POSTFIELDS, ['image' => $cfile_base]); curl_setopt($ch_base, CURLOPT_RETURNTRANSFER, true);
        $res_base = json_decode(curl_exec($ch_base), true);
        @unlink($tmp_base);

        if (isset($res_base['name'])) {
            $use_adetailer = false;
            $workflow["10"] = ["inputs" => ["image" => $res_base['name'], "upload" => "image"], "class_type" => "LoadImage"];

            // 2. Lógica Híbrida: ¿Foto temporal o modelo .safetensors guardado?
            if (!empty($reactor_saved_face)) {
                $workflow["11"] = [
                    "inputs" => ["face_model" => $reactor_saved_face], // <--- CORREGIDO AQUÍ
                    "class_type" => "ReActorLoadFaceModel"
                ];
                $tipo_entrada_rostro = "face_model";
            } else {
                $tmp_face = sys_get_temp_dir() . '/face_' . uniqid() . '.png';
                file_put_contents($tmp_face, base64_decode($reactor_image_base64));
                $cfile_face = function_exists('curl_file_create') ? curl_file_create($tmp_face, 'image/png', 'face.png') : '@' . realpath($tmp_face);
                $ch_face = curl_init(COMFY_URL . '/upload/image');
                curl_setopt($ch_face, CURLOPT_POST, true); curl_setopt($ch_face, CURLOPT_POSTFIELDS, ['image' => $cfile_face]); curl_setopt($ch_face, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch_face, CURLOPT_TIMEOUT, 30);
                $res_face = json_decode(curl_exec($ch_face), true);
                @unlink($tmp_face);
                
                if (!isset($res_face['name'])) {
                    echo json_encode(['error' => __('err_faceswap_upload')]); exit();
                }
                $workflow["11"] = ["inputs" => ["image" => $res_face['name'], "upload" => "image"], "class_type" => "LoadImage"];
                $tipo_entrada_rostro = "source_image";
            }
            
            // 3. Nodo de ReActor Adaptativo
            $workflow["101"] = [ 
                "inputs" => [ 
                    "enabled" => true,  
                    "swap_model" => "inswapper_128.onnx",  
                    "facedetection" => $reactor_detector,
                    "face_restore_model" => $reactor_restore_model,
                    "face_restore_visibility" => $reactor_visibility,
                    "codeformer_weight" => $reactor_fidelity,
                    "detect_gender_input" => $reactor_gender,
                    "detect_gender_source" => "no", 
                    "input_faces_index" => $reactor_target_index,
                    "source_faces_index" => $reactor_source_index,
                    "console_log_level" => 1, 
                    "input_image" => ["10", 0], 
                    $tipo_entrada_rostro => ["11", 0] // <- Enchufe dinámico
                ], 
                "class_type" => "ReActorFaceSwap" 
            ];

            $workflow["9"] = [ "inputs" => ["images" => ["101", 0]], "class_type" => "PreviewImage" ];
            goto EJECUTAR_COMFYUI; 
        } else {
            echo json_encode(['error' => __('err_faceswap_upload')]);
            exit();
        }
    }
    
    // ==============================================================================
    // --- TUBERÍA EXCLUSIVA: MODO PURO REMBG ---
    // ==============================================================================
    if ($remove_background && $pure_rembg && !empty($init_image_base64)) {
        $tmp_file = sys_get_temp_dir() . '/init_' . uniqid() . '.png';
        file_put_contents($tmp_file, base64_decode($init_image_base64));
        $cfile = function_exists('curl_file_create') ? curl_file_create($tmp_file, 'image/png', 'init_image.png') : '@' . realpath($tmp_file);
        
        $ch_up = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_up, CURLOPT_POST, true); 
        curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile]); 
        curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
        $res_up = json_decode(curl_exec($ch_up), true); 
        @unlink($tmp_file);

        if (isset($res_up['name'])) {
            $workflow["11"] = [
                "inputs" => ["image" => $res_up['name'], "upload" => "image"], 
                "class_type" => "LoadImage"
            ];

            $workflow["500"] = [
                "inputs" => [
                    "images" => ["11", 0],
                    "transparency" => true,
                    "model" => "u2net",
                    "post_processing" => false,
                    "only_mask" => false,
                    "alpha_matting" => false,
                    "alpha_matting_foreground_threshold" => 240,
                    "alpha_matting_background_threshold" => 10,
                    "alpha_matting_erode_size" => 10,
                    "background_color" => "none"
                ], 
                "class_type" => "Image Rembg (Remove Background)" 
            ];

            $workflow["9"] = [
                "inputs" => [
                    "filename_prefix" => "byGarty_Rembg", 
                    "images" => ["500", 0]
                ], 
                "class_type" => "SaveImage"
            ];
            
            goto EJECUTAR_COMFYUI; 
        } else {
            echo json_encode(['error' => __('err_rembg_upload')]);
            exit();
        }
    }
    
    // ==============================================================================
    // --- NUEVO: TUBERÍA EXCLUSIVA MODO PURO COLOREADO (DDColor) ---
    // ==============================================================================
    if ($ddcolor_enabled && $pure_ddcolor && !empty($init_image_base64)) {
        // 1. Subimos la imagen en B/N al servidor temporal de ComfyUI
        $tmp_dd = sys_get_temp_dir() . '/init_dd_' . uniqid() . '.png';
        file_put_contents($tmp_dd, base64_decode($init_image_base64));
        $cfile_dd = function_exists('curl_file_create') ? curl_file_create($tmp_dd, 'image/png', 'init_dd.png') : '@' . realpath($tmp_dd);
        
        $ch_up = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_up, CURLOPT_POST, true); 
        curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile_dd]); 
        curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
        $res_up = json_decode(curl_exec($ch_up), true); 
        @unlink($tmp_dd);

        if (isset($res_up['name'])) {
            // 2. Cargamos la imagen en el nodo de entrada
            $workflow["10"] = [
                "inputs" => ["image" => $res_up['name'], "upload" => "image"], 
                "class_type" => "LoadImage"
            ];

            // 3. Aplicamos el modelo neural DDColor (Sintaxis exacta de tu nodo)
            $workflow["501"] = [
                "inputs" => [
                    "image" => ["10", 0],
                    "model_input_size" => 512,
                    "checkpoint" => $ddcolor_model 
                ], 
                "class_type" => "DDColor_Colorize" 
            ];

            // 4. Salida directa al visor
            $workflow["9"] = [
                "inputs" => [
                    "filename_prefix" => "byGarty_DDColor", 
                    "images" => ["501", 0]
                ], 
                "class_type" => "SaveImage" 
            ];
            
            // Bypass total del KSampler, vamos directos a la ejecución
            goto EJECUTAR_COMFYUI; 
        } else {
            echo json_encode(['error' => __('err_ddcolor_upload') ?? 'Error subiendo la imagen para colorear.']);
            exit();
        }
    }
    
	// ==============================================================================
    // --- NUEVO: TUBERÍA EXCLUSIVA BORRADO MÁGICO (LaMa Remover - Faxuan Cai) ---
    // ==============================================================================
    if ($lama_enabled && !empty($_POST['init_image']) && !empty($_POST['mask_data'])) {
        
        // 0.1 SUBIR IMAGEN BASE A LA API DE COMFYUI
        $tmp_img = sys_get_temp_dir() . '/lama_init_' . uniqid() . '.png';
        file_put_contents($tmp_img, base64_decode($_POST['init_image']));
        $cfile_img = function_exists('curl_file_create') ? curl_file_create($tmp_img, 'image/png', 'lama_init.png') : '@' . realpath($tmp_img);
        
        $ch_img = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_img, CURLOPT_POST, true); 
        curl_setopt($ch_img, CURLOPT_POSTFIELDS, ['image' => $cfile_img]); 
        curl_setopt($ch_img, CURLOPT_RETURNTRANSFER, true);
        $res_img = json_decode(curl_exec($ch_img), true); 
        @unlink($tmp_img);

        // 0.2 SUBIR MÁSCARA ROJA A LA API DE COMFYUI
        $tmp_mask = sys_get_temp_dir() . '/lama_mask_' . uniqid() . '.png';
        file_put_contents($tmp_mask, base64_decode($_POST['mask_data']));
        $cfile_mask = function_exists('curl_file_create') ? curl_file_create($tmp_mask, 'image/png', 'lama_mask.png') : '@' . realpath($tmp_mask);
        
        $ch_mask = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_mask, CURLOPT_POST, true); 
        curl_setopt($ch_mask, CURLOPT_POSTFIELDS, ['image' => $cfile_mask]); 
        curl_setopt($ch_mask, CURLOPT_RETURNTRANSFER, true);
        $res_mask = json_decode(curl_exec($ch_mask), true); 
        @unlink($tmp_mask);

        // Si ComfyUI nos devuelve el OK de que ha recibido las imágenes
        if (isset($res_img['name']) && isset($res_mask['name'])) {
            
            // 1. Cargar imagen base devuelta por la API (Nodo 10)
            $workflow["10"] = [
                "inputs" => ["image" => $res_img['name'], "upload" => "image"], 
                "class_type" => "LoadImage"
            ];
            
            // 2. Cargar máscara roja devuelta por la API (Nodo 11)
            $workflow["11"] = [
                "inputs" => ["image" => $res_mask['name'], "channel" => "red"], 
                "class_type" => "LoadImageMask"
            ];

            // 3. Nodo neural de borrado mágico LaMa (Sintaxis Faxuan Cai)
            $workflow["502"] = [
                "inputs" => [
                    "images" => ["10", 0],
                    "masks" => ["11", 0],
                    "invert_mask" => false,
                    "mask_threshold" => 0,
                    "gaussblur_radius" => intval($_POST['mask_blur'] ?? 4)
                ],
                "class_type" => "LamaRemover"
            ];

            // 4. Salida directa al visor (Preview)
            $workflow["9"] = [
                "inputs" => ["images" => ["502", 0]],
                "class_type" => "PreviewImage"
            ];

            goto EJECUTAR_COMFYUI;
            
        } else {
            echo json_encode(['error' => __('err_lama_upload') ?? 'Error al transferir las imágenes a la GPU para el Borrado Mágico.']);
            exit();
        }
    }
	
	// ==============================================================================
    // --- INTERCEPCIÓN: MODO ILUMINACIÓN NEURAL (IC-Light KIJAI - DEFINITIVO) ---
    // ==============================================================================
    if ($iclight_enabled && !empty($init_image_base64)) {
        
        // 1. Lógica de herencia para el prompt de luz
        $prompt_iluminacion = !empty($iclight_prompt_panel) ? $iclight_prompt_panel : ($posPrompt ?? '');
        if (empty(trim($prompt_iluminacion))) {
            $prompt_iluminacion = "detailed cinematic lighting, realistic shadows, ambient illumination, 8k resolution, photorealistic";
        }

        $dir_limpia = strtolower(str_replace(' Light', '', $iclight_direction));
        if ($dir_limpia === 'detail / ambient') $dir_limpia = 'ambient';
        $prompt_iluminacion = "light from " . $dir_limpia . ", " . $prompt_iluminacion;

        // 2. Subimos la imagen base a la API de ComfyUI
        $tmp_ic = sys_get_temp_dir() . '/init_ic_' . uniqid() . '.png';
        file_put_contents($tmp_ic, base64_decode($init_image_base64));
        $cfile_ic = function_exists('curl_file_create') ? curl_file_create($tmp_ic, 'image/png', 'init_ic.png') : '@' . realpath($tmp_ic);
        
        $ch_up = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_up, CURLOPT_POST, true); 
        curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile_ic]); 
        curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
        $res_up = json_decode(curl_exec($ch_up), true); 
        @unlink($tmp_ic);

        if (isset($res_up['name'])) {
            // 3. Cargamos la imagen subida en el nodo 10 (Formato IMAGE)
            $workflow["10"] = [
                "inputs" => ["image" => $res_up['name'], "upload" => "image"], 
                "class_type" => "LoadImage"
            ];

            // 4. Cargamos tu modelo base SD1.5/SDXL seleccionado en la web (Nodo 4)
            $workflow["4"] = [
                "inputs" => ["ckpt_name" => $model_path], 
                "class_type" => "CheckpointLoaderSimple"
            ];

            // 4.5. ¡NUEVO! Convertimos la imagen a LATENTE usando el VAE del modelo base
            $workflow["10_latent"] = [
                "inputs" => [
                    "pixels" => ["10", 0],
                    "vae" => ["4", 2]
                ],
                "class_type" => "VAEEncode"
            ];

            // 5. Cargador de UNET de Kijai (Corregido: usa "model_path" en vez de "model_name")
            $ic_model_name = (strpos(strtolower($selector), 'sdxl') !== false) ? "iclight_sdxl_fc.safetensors" : "iclight_sd15_fc.safetensors"; 
            $workflow["11"] = [
                "inputs" => [
                    "model" => ["4", 0], // UNET base del Checkpoint
                    "model_path" => $ic_model_name // <-- CORREGIDO AQUÍ
                ], 
                "class_type" => "LoadAndApplyICLightUnet"
            ];

           // 6. Text Encoders (Usando el CLIP del modelo base: Nodo 4, salida 1)
            $workflow["12"] = ["inputs" => ["text" => $prompt_iluminacion, "clip" => ["4", 1]], "class_type" => "CLIPTextEncode"];
            $workflow["16"] = ["inputs" => ["text" => $neg_prompt, "clip" => ["4", 1]], "class_type" => "CLIPTextEncode"];

            // 6.5. Captura de la fuerza de la luz (Multiplicador) desde el panel
            $iclight_mult = isset($_POST['iclight_multiplier']) ? floatval($_POST['iclight_multiplier']) : 0.18;

            // 7. El Nodo Mágico de Kijai: IC-Light Conditioning
            $workflow["17"] = [
                "inputs" => [
                    "positive" => ["12", 0],
                    "negative" => ["16", 0],
                    "vae" => ["4", 2],
                    "foreground" => ["10_latent", 0], // Imagen latente
                    "multiplier" => $iclight_mult // <-- NUEVO: Ahora recibe el valor de tu barra
                ],
                "class_type" => "ICLightConditioning"
            ];

            // 8. KSampler
            $workflow["19"] = [
                "inputs" => [
                    "seed" => $seed,
                    "steps" => intval($steps ?? 25),
                    "cfg" => floatval($cfg ?? 2.0),
                    "sampler_name" => "euler",
                    "scheduler" => "sgm_uniform",
                    "denoise" => 1.0,
                    "model" => ["11", 0],       
                    "positive" => ["17", 0],    
                    "negative" => ["17", 1],    
                    "latent_image" => ["17", 2] 
                ],
                "class_type" => "KSampler"
            ];

            // 9. VAEDecode: Traduce el latente del KSampler a píxeles visibles
            $workflow["20"] = [
                "inputs" => [
                    "samples" => ["19", 0],
                    "vae" => ["4", 2]
                ],
                "class_type" => "VAEDecode"
            ];

            // 10. Salida directa al visor
            $workflow["21"] = [
                "inputs" => [
                    "filename_prefix" => "byGarty_ICLight_", 
                    "images" => ["20", 0]
                ], 
                "class_type" => "SaveImage" 
            ];
            
            goto EJECUTAR_COMFYUI; 
        } else {
            echo json_encode(['error' => __('err_iclight_upload') ?? 'Error subiendo la imagen para iluminación neural.']);
            exit();
        }
    }

    // ==============================================================================
    // --- TUBERÍA EXCLUSIVA: MODO PURO ADETAILER (RESTAURACIÓN DE ROSTROS) ---
    // ==============================================================================
    $pure_adetailer = filter_var($_POST['pure_adetailer'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    if ($use_adetailer && $pure_adetailer && !empty($init_image_base64)) {
        // 1. Subimos la imagen base al servidor de ComfyUI
        $tmp_file = sys_get_temp_dir() . '/init_ad_' . uniqid() . '.png';
        file_put_contents($tmp_file, base64_decode($init_image_base64));
        $cfile = function_exists('curl_file_create') ? curl_file_create($tmp_file, 'image/png', 'init_ad.png') : '@' . realpath($tmp_file);
        
        $ch_up = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_up, CURLOPT_POST, true);
        curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile]);
        curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
        $res_up = json_decode(curl_exec($ch_up), true);
        @unlink($tmp_file);

        if (isset($res_up['name'])) {
            // 2. Cargamos la imagen y el detector YOLO
            $workflow["10"] = ["inputs" => ["image" => $res_up['name'], "upload" => "image"], "class_type" => "LoadImage"];
            $workflow["900"] = ["inputs" => ["model_name" => $adetailer_model], "class_type" => "UltralyticsDetectorProvider"];

            // 3. Selección Inteligente del Modelo de Restauración
            $modelo_restauracion = $model_path;
            
            // Si el modelo activo es un DiT avanzado o carece de CLIP integrado (como Krea-2),
            // delegamos la restauración facial a un modelo rápido y robusto (SD1.5 / SDXL)
            if ($is_flux || $is_chroma || $is_krea2 || $is_qwen || $is_zimage || $is_hunyuan || $is_hidream || $is_anima || !modeloTieneClipIntegrado($model_path)) {
                $stmt_ref = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE categoria = 'sys_refiner' LIMIT 1");
                $row_ref = $stmt_ref->fetch();
                
                if ($row_ref && !empty(trim($row_ref['nombre_archivo']))) {
                    $modelo_restauracion = trim($row_ref['nombre_archivo']);
                } else {
                    // Fallback: Si no hay refiner explícito, busca cualquier SD1.5 o SDXL
                    $stmt_auto = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE categoria IN ('sd15', 'sdxl') OR (nombre_archivo NOT LIKE '%flux%' AND nombre_archivo NOT LIKE '%chroma%') LIMIT 1");
                    $row_auto = $stmt_auto->fetch();
                    if ($row_auto) {
                        $modelo_restauracion = $row_auto['nombre_archivo'];
                    } else {
                        echo json_encode(['error' => "Para usar el Modo Puro con Krea-2 o Flux, asigna un modelo SD1.5 o SDXL en la categoría 'sys_refiner'."]);
                        exit();
                    }
                }
            }

            $workflow["4"] = [
                "inputs" => ["ckpt_name" => $modelo_restauracion], 
                "class_type" => "CheckpointLoaderSimple"
            ];
            
            // 4. Prompts específicos y dinámicos según el modelo de detección elegido
            if (!empty(trim($posPrompt))) {
                $ad_pos_prompt = $posPrompt;
            } else {
                $ad_model_low = strtolower($adetailer_model);
                if (strpos($ad_model_low, 'hand') !== false) {
                    $ad_pos_prompt = "high quality, detailed hand, realistic proportions, flawless, 8k";
                } elseif (strpos($ad_model_low, 'person') !== false || strpos($ad_model_low, 'body') !== false) {
                    $ad_pos_prompt = "high quality, highly detailed person, realistic human body, masterpiece, 8k";
                } else {
                    $ad_pos_prompt = "high quality, highly detailed face, sharp focus, detailed eyes, 8k";
                }
            }
            $workflow["6"] = ["inputs" => ["text" => $ad_pos_prompt, "clip" => ["4", 1]], "class_type" => "CLIPTextEncode"];
            $workflow["7"] = ["inputs" => ["text" => $neg_prompt, "clip" => ["4", 1]], "class_type" => "CLIPTextEncode"];

            // 5. Aplicamos FaceDetailer con el diccionario 100% completo (Impact Pack)
            $workflow["901"] = [
                "inputs" => [
                    "guide_size" => 384,
                    "guide_size_for" => "bbox",
                    "max_size" => 1024,
                    "seed" => $seed,
                    "steps" => 25,
                    "cfg" => 6.0,
                    "sampler_name" => "dpmpp_2m",
                    "scheduler" => "karras",
                    "denoise" => isset($adetailer_denoise) ? floatval($adetailer_denoise) : 0.35,
                    "feather" => 5,
                    "noise_mask" => true,
                    "force_inpaint" => true,
                    "bbox_threshold" => 0.5,
                    "bbox_dilation" => 10,
                    "bbox_margin" => 15,
                    "bbox_crop_factor" => 3.0,
                    "drop_size" => 10,
                    "sam_detection_hint" => "center-1",
                    "sam_dilation" => 0,
                    "sam_threshold" => 0.93,
                    "sam_bbox_expansion" => 0,
                    "sam_mask_hint_threshold" => 0.7,
                    "sam_mask_hint_use_negative" => "False",
                    "refiner_ratio" => 0.2,
                    "cycle" => 1,
                    "wildcard" => "",
                    "image" => ["10", 0],
                    "model" => ["4", 0],
                    "clip" => ["4", 1],
                    "vae" => ["4", 2],
                    "positive" => ["6", 0],
                    "negative" => ["7", 0],
                    "bbox_detector" => ["900", 0]
                ],
                "class_type" => "FaceDetailer"
            ];

            $workflow["9"] = ["inputs" => ["images" => ["901", 0]], "class_type" => "PreviewImage"];
            $current_image_node = "901";
            
            // CRÍTICO: Apagamos la variable para evitar que al saltar abajo intente volver a procesarse
            $use_adetailer = false; 
            
            goto EJECUTAR_COMFYUI;
        } else {
            echo json_encode(['error' => __('err_adetailer_upload')]);
            exit();
        }
    }
	
	// ==============================================================================
    // --- TUBERÍA EXCLUSIVA: MODO PURO UPSCALE (Sin Prompt, Directo a píxel) ---
    // ==============================================================================
    $pure_upscale = filter_var($_POST['pure_upscale'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    if ($hires_fix && $pure_upscale && !empty($init_image_base64) && (!empty($upscale_model) || $aurasr_enabled)) {
        
        // 1. Limpiamos la cadena base64 y guardamos archivo temporal en disco
        $clean_img_data = $init_image_base64;
        if (strpos($clean_img_data, 'base64,') !== false) {
            $clean_img_data = explode('base64,', $clean_img_data)[1];
        }
        
        $tmp_up = sys_get_temp_dir() . '/upscale_' . uniqid() . '.png';
        file_put_contents($tmp_up, base64_decode($clean_img_data));
        unset($clean_img_data); // Liberamos RAM inmediatamente
        
        // 2. LEEMOS DIMENSIONES REALES DESDE EL ARCHIVO EN DISCO (Cero consumo de RAM)
        $orig_w = $width;
        $orig_h = $height;
        $img_info = @getimagesize($tmp_up);
        if ($img_info !== false && isset($img_info[0], $img_info[1])) {
            $orig_w = $img_info[0];
            $orig_h = $img_info[1];
        }
        
        // 3. Subimos la imagen a la API de ComfyUI mediante cURL nativo
        $cfile_up = function_exists('curl_file_create') ? curl_file_create($tmp_up, 'image/png', 'upscale_ref.png') : '@' . realpath($tmp_up);
        $ch_up = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_up, CURLOPT_POST, true);
        curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile_up]);
        curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
        $res_up_raw = curl_exec($ch_up);
        @unlink($tmp_up); // Borramos el archivo temporal del servidor web
        
        $res_up = json_decode($res_up_raw, true);

        // 4. Calcular resolución final matemática sobre los píxeles reales de tu foto
        $target_w = intval($orig_w * $upscale_factor);
        $target_h = intval($orig_h * $upscale_factor);

        if (isset($res_up['name'])) {
            $workflow["10"] = ["inputs" => ["image" => $res_up['name'], "upload" => "image"], "class_type" => "LoadImage"];
            
            if ($aurasr_enabled) {
                // Ruta VIP: AuraSR GigaGAN
                $workflow["11"] = ["inputs" => ["model" => "AuraSR-v2.safetensors"], "class_type" => "LoadAuraSR"];
                $workflow["12"] = ["inputs" => ["avoid_seams" => true, "AURASR_MODEL" => ["11", 0], "IMAGE" => ["10", 0]], "class_type" => "RunAuraSR"];
            } else {
                // Ruta Clásica: UltraSharp / ESRGAN
                $workflow["11"] = ["inputs" => ["model_name" => $upscale_model], "class_type" => "UpscaleModelLoader"];
                $workflow["12"] = ["inputs" => ["upscale_model" => ["11", 0], "image" => ["10", 0]], "class_type" => "ImageUpscaleWithModel"];
            }
            
            // Re-escalamos con precisión matemática sobre el objetivo real
            $workflow["13"] = [
                "inputs" => ["upscale_method" => "bicubic", "width" => $target_w, "height" => $target_h, "crop" => "disabled", "image" => ["12", 0]],
                "class_type" => "ImageScale"
            ];
            
            $workflow["9"] = ["inputs" => ["images" => ["13", 0]], "class_type" => "PreviewImage"];
            goto EJECUTAR_COMFYUI; 
        } else {
            echo json_encode(['error' => __('err_upscale_upload') ?? 'Error subiendo la imagen para Upscale.']);
            exit();
        }
    }

    // ==============================================================================
    // --- TUBERÍA ORIGINAL DE IMAGEN ESTÁTICA ---
    // ==============================================================================
    $prueba_json_activada = false;

    if ($prueba_json_activada) {
        $reemplazos = [
            '__SEED__'            => intval($seed),
            '__STEPS__'           => intval($steps),
            '__CFG__'             => floatval($cfg),
            '__SAMPLER__'         => $sampler,
            '__SCHEDULER__'       => $scheduler,
            '__MODELO__'          => $model_path, 
            '__WIDTH__'           => intval($width),
            '__HEIGHT__'          => intval($height),
            '__PROMPT_POSITIVO__' => $posPrompt, 
            '__PROMPT_NEGATIVO__' => $neg_prompt
        ];

        $ruta_json = __DIR__ . '/../workflows/base_txt2img.json';
        $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);

    } else { 
        if ($is_chroma) {
            $workflow["5"] = [ "inputs" => ["width" => $width, "height" => $height, "batch_size" => $batch_size], "class_type" => "EmptySD3LatentImage" ];
        } else {
            $workflow["5"] = [ "inputs" => ["width" => $width, "height" => $height, "batch_size" => $batch_size], "class_type" => "EmptyLatentImage" ];
        }
        $workflow["6"] = [ "inputs" => ["text" => $posPrompt, "clip" => [$base_clip_node, $base_clip_index]], "class_type" => "CLIPTextEncode" ];
        $workflow["7"] = [ "inputs" => ["text" => $neg_prompt, "clip" => [$base_clip_node, $base_clip_index]], "class_type" => "CLIPTextEncode" ];
    
    // --- CARGADOR DE MODELO (ARCHITECTURE AWARE) ---
    $ruta_minusculas = strtolower($model_path);
    
    $esta_en_carpeta_unet = (strpos($ruta_minusculas, 'unet') !== false || strpos($ruta_minusculas, 'diffusion_models') !== false);
    
    // El control absoluto lo tiene la BD ($es_unbundled_db). 
    // Pero MANTENEMOS las heurísticas como salvavidas automático
    $es_arquitectura_nueva = ($es_unbundled_db || $esta_en_carpeta_unet || $is_flux || $is_chroma || $is_zimage || $is_gguf || $is_qwen || $is_krea2 || $is_hunyuan || $is_hidream || $is_anima);

    if ($es_arquitectura_nueva) {
        // Dejamos la ruta absolutamente intacta para que coincida 100% con Windows/ComfyUI
        $unet_filename = $model_path;
        
        // 1. CARGAMOS EL UNET (100% Nativos para que ComfyUI no sature)
        if ($is_gguf) {
            $workflow["4"] = [ "inputs" => ["unet_name" => $unet_filename], "class_type" => "UnetLoaderGGUF" ];
            $current_model_node = "4";
        } else {
            $workflow["4"] = [ "inputs" => ["unet_name" => $unet_filename, "weight_dtype" => "default"], "class_type" => "UNETLoader" ];
            
            // Inyectamos el "Flow Shift" = 1.0 (El secreto de calidad de Chroma)
            if ($is_chroma) {
                $workflow["4_shift"] = [
                    "inputs" => [
                        "shift" => 1.0, // Parámetro antiguo por si acaso
                        "base_shift" => 1.0, // Engañamos al nuevo ComfyUI para que sea estático
                        "max_shift" => 1.0,  // Engañamos al nuevo ComfyUI para que sea estático
                        "width" => $width,
                        "height" => $height,
                        "model" => ["4", 0]
                    ],
                    "class_type" => "ModelSamplingFlux" // Nodo oficial, sin fallos
                ];
                $current_model_node = "4_shift";
            } else {
                $current_model_node = "4";
            }
        }
        
		// 2. TEXT ENCODERS (Incluyendo FP8)
        if ($is_sd35) {
            $workflow["90"] = [ "inputs" => ["clip_name1" => "clip_l.safetensors", "clip_name2" => "clip_g.safetensors", "clip_name3" => "t5xxl_fp16.safetensors"], "class_type" => "TripleCLIPLoader" ];
        } elseif ($is_qwen) {
            $workflow["90"] = [ "inputs" => ["clip_name" => "qwen_2.5_vl_7b_fp8_scaled.safetensors", "type" => "qwen_image"], "class_type" => "CLIPLoader" ];
        } elseif ($is_krea2) {
            $workflow["90"] = [ "inputs" => ["clip_name" => "qwen3vl_4b_fp8_scaled.safetensors", "type" => "krea2", "device" => "default"], "class_type" => "CLIPLoader" ];
        } elseif ($is_zimage) {
            // 👇 ¡RESTAURAMOS TU BLOQUE ORIGINAL! 👇
            $workflow["90"] = [ "inputs" => ["clip_name" => "qwen_3_4b.safetensors", "type" => "lumina2"], "class_type" => "CLIPLoader" ];
        } elseif ($is_hunyuan) {
            // HunyuanDiT nativo de ComfyUI: Arquitectura Dual (CLIP-L + uMT5)
            $workflow["90"] = [ 
                "inputs" => [
					"clip_name1" => "qwen_2.5_vl_7b_fp8_scaled.safetensors", 
					"clip_name2" => "byt5_small_glyphxl_fp16.safetensors",    
					"type" => "hunyuan_image" 
				],
                "class_type" => "DualCLIPLoader" 
            ];
        } elseif ($is_hidream) {
            // HiDream-I1: Arquitectura Quadruple CLIP (CLIP-L + CLIP-G + T5XXL + Llama 3.1 8B FP8)
            $workflow["90"] = [ 
                "inputs" => [
                    "clip_name1" => "clip_l_hidream.safetensors", 
                    "clip_name2" => "clip_g_hidream.safetensors", 
                    "clip_name3" => "t5xxl_fp8_e4m3fn.safetensors", 
                    "clip_name4" => "llama_3.1_8b_instruct_fp8_scaled.safetensors" 
                ], 
                "class_type" => "QuadrupleCLIPLoader" 
            ];
        } elseif ($is_anima) {
            // Anima: Usa Qwen con la denominación genérica stable_diffusion
            $workflow["90"] = [ 
                "inputs" => [
                    "clip_name" => "qwen_3_06b_base.safetensors", 
                    "type" => "stable_diffusion" 
                ], 
                "class_type" => "CLIPLoader" 
            ];
        } elseif (strpos($model_lower, 'flux2') !== false) {
            // EL INTOCABLE FLUX 2
            $workflow["90"] = [ "inputs" => ["clip_name" => "qwen_3_8b.safetensors", "type" => "flux2"], "class_type" => "CLIPLoader" ];
        } elseif ($is_chroma) {
            // CLONADO EXACTO DEL JSON PARA CHROMA
            $workflow["90"] = [ "inputs" => ["clip_name" => "t5xxl_fp8_e4m3fn.safetensors", "type" => "chroma", "device" => "default"], "class_type" => "CLIPLoader" ];
            $workflow["90_opt"] = [ "inputs" => ["min_padding" => 0, "min_length" => 0, "clip" => ["90", 0]], "class_type" => "T5TokenizerOptions" ];
        } else {
            // FLUX NORMAL
            $workflow["90"] = [ 
                "inputs" => [
                    "clip_name1" => "t5xxl_fp16.safetensors", 
                    "clip_name2" => "clip_l.safetensors", 
                    "type" => "flux"
                ], 
                "class_type" => "DualCLIPLoader"
            ];
        }
        
        $vae_filename = basename(str_replace('\\', '/', $vae_name));
        $workflow["91"] = [ "inputs" => ["vae_name" => $vae_filename], "class_type" => "VAELoader" ];

        // Si es Chroma, el clip principal pasa a ser el Tokenizer (90_opt). Si no, el 90 normal.
        $base_clip_node = $is_chroma ? "90_opt" : "90"; 
        $base_clip_index = 0;
        $workflow["6"]["inputs"]["clip"] = [$base_clip_node, $base_clip_index]; 
        $workflow["7"]["inputs"]["clip"] = [$base_clip_node, $base_clip_index]; 
        
        $base_vae_node = "91"; $base_vae_index = 0;

        foreach ($workflow as $id_nodo => &$nodo) {
            if (isset($nodo['class_type']) && $nodo['class_type'] === 'VAEDecode') {
                $nodo['inputs']['vae'] = [$base_vae_node, $base_vae_index];
            }
            if (isset($nodo['class_type']) && $nodo['class_type'] === 'CLIPTextEncode') {
                $nodo['inputs']['clip'] = [$base_clip_node, $base_clip_index];
            }
        }

    } else { 
        $workflow["4"] = [ "inputs" => ["ckpt_name" => $model_path], "class_type" => "CheckpointLoaderSimple" ]; 
    }

    // ==============================================================================
    // 🛡️ RESCATE AUTOMÁTICO DE CLIP (Para modelos sin CLIP en el archivo físico) 🛡️
    // ==============================================================================
    if (!$es_arquitectura_nueva && !modeloTieneClipIntegrado($model_path)) {
        // Al detectar en 2ms que el archivo físico en F:/ no lleva CLIP integrado,
        // inyectamos un cargador externo para evitar el error "clip input is invalid: None"
        $workflow["100_auto_clip"] = [
            "inputs" => [
                "clip_name1" => "t5xxl_fp8_e4m3fn.safetensors",
                "clip_name2" => "clip_l.safetensors",
                "type" => "flux"
            ],
            "class_type" => "DualCLIPLoader"
        ];
        $base_clip_node = "100_auto_clip";
        $base_clip_index = 0;
        
        $workflow["6"]["inputs"]["clip"] = [$base_clip_node, $base_clip_index];
        $workflow["7"]["inputs"]["clip"] = [$base_clip_node, $base_clip_index];
    }

    // --- FIX GUIDANCE FLUX ---
    $flux_guidance = null;
    if ($is_flux && !$is_chroma) { // CHROMA NO USA FLUX GUIDANCE, USA CFG PURO
        // Si ya has programado un deslizador independiente de Guidance en tu web, lo usamos.
        if (isset($_POST['flux_guidance']) && floatval($_POST['flux_guidance']) > 0) {
            $flux_guidance = floatval($_POST['flux_guidance']);
        } else {
            // LIBERTAD ABSOLUTA
            $flux_guidance = ($cfg == 5.0) ? 3.5 : $cfg;
        }
        
        // El KSampler requiere físicamente CFG 1.0 en familia Flux para no generar puntos de ruido.
        $cfg = 1.0; 
        
        $workflow["600"] = [
            "inputs" => [
                "guidance" => $flux_guidance,
                "conditioning" => $current_positive
            ],
            "class_type" => "FluxGuidance"
        ];
        $current_positive = ["600", 0]; 
    }
    
    // ==============================================================================
    // --- FASE 5: CONTROLNET STUDIO ---
    // ==============================================================================
    if ($controlnet_enabled === 'true' && !empty($controlnet_image_base64) && !empty($controlnet_model)) {
        $tmp_cn = sys_get_temp_dir() . '/cn_' . uniqid() . '.png';
        file_put_contents($tmp_cn, base64_decode($controlnet_image_base64));
        $cfile_cn = function_exists('curl_file_create') ? curl_file_create($tmp_cn, 'image/png', 'cn_ref.png') : '@' . realpath($tmp_cn);
        
        $ch_cn = curl_init(COMFY_URL . '/upload/image');
        curl_setopt($ch_cn, CURLOPT_POST, true); curl_setopt($ch_cn, CURLOPT_POSTFIELDS, ['image' => $cfile_cn]); curl_setopt($ch_cn, CURLOPT_RETURNTRANSFER, true);
        $res_cn = json_decode(curl_exec($ch_cn), true); @unlink($tmp_cn);

        if (isset($res_cn['name'])) {
            $workflow["300"] = ["inputs" => ["image" => $res_cn['name'], "upload" => "image"], "class_type" => "LoadImage"];
            $final_cn_image_node = ["300", 0];

            if (isset($is_zimage) && $is_zimage) {
                $workflow["300_scale"] = [
                    "inputs" => [
                        "upscale_method" => "lanczos", 
                        "width" => $width,
                        "height" => $height,
                        "crop" => "center",
                        "image" => ["300", 0]
                    ],
                    "class_type" => "ImageScale"
                ];
                $final_cn_image_node = ["300_scale", 0]; 
            }

            if ($controlnet_preprocessor !== 'none') {
                $inputs_preprocesador = ["image" => $final_cn_image_node];
                
                if (strpos(strtolower($controlnet_preprocessor), 'lineart') !== false) {
                    $inputs_preprocesador["coarse"] = "disable";
                    $inputs_preprocesador["resolution"] = 512; 
                } 
               // 👇 NUEVO BLOQUE DW POSE (DINÁMICO) 👇
                elseif (strpos(strtolower($controlnet_preprocessor), 'dwpose') !== false || strpos(strtolower($controlnet_preprocessor), 'dw preprocessor') !== false) {
                    // Forzamos el nombre exacto de la clase del nodo
                    $controlnet_preprocessor = "DWPreprocessor"; 
                    
                    // Capturamos el estado de los switches del panel web (por defecto enable por seguridad)
                    $inputs_preprocesador["detect_body"] = ($_POST['dw_body'] ?? 'true') === 'true' ? "enable" : "disable";
                    $inputs_preprocesador["detect_face"] = ($_POST['dw_face'] ?? 'true') === 'true' ? "enable" : "disable";
                    $inputs_preprocesador["detect_hand"] = ($_POST['dw_hand'] ?? 'true') === 'true' ? "enable" : "disable";
                    
                    $inputs_preprocesador["resolution"] = 512;
                    $inputs_preprocesador["bbox_detector"] = "yolox_l.onnx";
                    $inputs_preprocesador["pose_estimator"] = "dw-ll_ucoco_384.onnx"; 
                }
                // 👆 FIN BLOQUE DW POSE 👆

                $workflow["301"] = ["inputs" => $inputs_preprocesador, "class_type" => $controlnet_preprocessor];
                $final_cn_image_node = ["301", 0]; 
            }

            // --- REPARACIÓN DE ENRUTAMIENTO CONTROLNET ---
            if (isset($is_zimage) && $is_zimage) {
                $workflow["302"] = [
                    "inputs" => ["name" => $controlnet_model], 
                    "class_type" => "ModelPatchLoader"
                ];
                $workflow["303"] = [
                    "inputs" => [
                        "model" => [$current_model_node, 0],
                        "model_patch" => ["302", 0],
                        "vae" => [$base_vae_node, $base_vae_index],
                        "image" => $final_cn_image_node,        
                        "strength" => $controlnet_weight
                    ],
                    "class_type" => "QwenImageDiffsynthControlnet"
                ];
                $current_model_node = "303"; 

            } else {
                // 🏛️ FLUJO UNIVERSAL TRADICIONAL Y DiT (SD1.5 / SDXL / Flux / Chroma / Krea-2)
                // Usamos el nodo nativo ControlNetApplyAdvanced reconocido por todo ComfyUI
                $workflow["302"] = ["inputs" => ["control_net_name" => $controlnet_model], "class_type" => "ControlNetLoader"];
                $workflow["303"] = [
                    "inputs" => [
                        "positive" => $current_positive,
                        "negative" => $current_negative,
                        "control_net" => ["302", 0], 
                        "image" => $final_cn_image_node, 
                        "strength" => $controlnet_weight,
                        "start_percent" => $controlnet_start,
                        "end_percent" => $controlnet_end
                    ],
                    "class_type" => "ControlNetApplyAdvanced"
                ];
                $current_positive = ["303", 0];
                $current_negative = ["303", 1];
            }
        }
    }

    // ==============================================================================
    // --- FASE 6: IP-ADAPTER AVANZADO Y FLUX REDUX (MULTI-IMAGE BATCH) ---
    // ==============================================================================
    $ipadapter_images = $_POST['ipadapter_images'] ?? [];
    
    if ($ipadapter_enabled === 'true' && !empty($ipadapter_images)) {
        
        // 0. ADELANTAMOS LA DETECCIÓN DE FLUX 2 PARA QUE EL ESCUDO NO ESTÉ CIEGO
        $temp_mod_lower = strtolower($model_path);
        $es_flux2_local = (strpos($temp_mod_lower, 'flux2') !== false || strpos($temp_mod_lower, 'klein') !== false || strpos($temp_mod_lower, 'kontext') !== false);

        // 1. ESCUDO 1: Qwen y Krea-2 (Son VLMs Nativos)
        if (isset($is_qwen) && $is_qwen || isset($is_krea2) && $is_krea2) {
            echo json_encode(['error' => __('err_ipa_vlm') ?? 'IP-Adapter no es compatible con modelos VLM (Qwen/Krea-2). Usa la bandeja de multicarga normal.']);
            exit();
        }

        // 2. ESCUDO 2: Flux Kontext (Klein)
        $temp_mod_lower = strtolower($model_path);
        if (strpos($temp_mod_lower, 'klein') !== false || strpos($temp_mod_lower, 'kontext') !== false) {
            echo json_encode(['error' => __('err_ipa_flux_kontext') ?? 'Flux Kontext utiliza su propio Conditioner nativo. Apaga IP-Adapter y sube las fotos a la bandeja multicarga.']);
            exit();
        }

        // 3. ESCUDO 3: Z-Image y otros DiTs Next-Gen Incompatibles
        // No soportan las matrices del IP-Adapter clásico ni los pesos visuales de Flux Redux
        $is_ideogram = (strpos($temp_mod_lower, 'ideogram') !== false);
        if ((isset($is_zimage) && $is_zimage) || (isset($is_sd35) && $is_sd35) || (isset($is_hunyuan) && $is_hunyuan) || (isset($is_hidream) && $is_hidream) || (isset($is_anima) && $is_anima) || $is_ideogram) {
            echo json_encode(['error' => __('err_ipa_incompatible_dit') ?? 'La herramienta IP-Adapter / Referencia de Estilo no es compatible con la arquitectura de este modelo. Por favor, desactívala en el panel para poder renderizar.']);
            exit();
        }

        $ipa_uploaded_names = [];
        foreach ($ipadapter_images as $idx => $b64) {
            if ($idx >= 4) break; // Máximo 4 imágenes por límite de batch
            
            $tmp_ipa = sys_get_temp_dir() . '/ipa_' . uniqid() . "_{$idx}.png";
            file_put_contents($tmp_ipa, base64_decode($b64));
            
            $cfile_ipa = function_exists('curl_file_create') ? curl_file_create($tmp_ipa, 'image/png', "ipa_ref_{$idx}.png") : '@' . realpath($tmp_ipa);
            
            $ch_ipa = curl_init(COMFY_URL . '/upload/image');
            curl_setopt($ch_ipa, CURLOPT_POST, true); 
            curl_setopt($ch_ipa, CURLOPT_POSTFIELDS, ['image' => $cfile_ipa]); 
            curl_setopt($ch_ipa, CURLOPT_RETURNTRANSFER, true);
            $res_ipa = json_decode(curl_exec($ch_ipa), true); 
            @unlink($tmp_ipa);

            if (isset($res_ipa['name'])) {
                $ipa_uploaded_names[] = $res_ipa['name'];
            }
        }

        if (!empty($ipa_uploaded_names)) {
            
            // Empaquetador Universal (ImageBatch) para todos los modelos compatibles
            $last_image_node = null;
            foreach ($ipa_uploaded_names as $idx => $name) {
                $node_id = "200_" . $idx;
                $workflow[$node_id] = [
                    "inputs" => ["image" => $name, "upload" => "image"], 
                    "class_type" => "LoadImage"
                ];
                
                if ($idx === 0) {
                    $last_image_node = [$node_id, 0];
                } else {
                    $batch_id = "200_batch_" . $idx;
                    $workflow[$batch_id] = [
                        "inputs" => [
                            "image1" => $last_image_node,
                            "image2" => [$node_id, 0]
                        ],
                        "class_type" => "ImageBatch"
                    ];
                    $last_image_node = [$batch_id, 0];
                }
            }

            // 👇 CORRECCIÓN: ZImage eliminado de esta ruta 👇
            if ((isset($is_flux) && $is_flux) || (isset($is_chroma) && $is_chroma)) {
                // 🌟 RUTA NEXT-GEN: FLUX REDUX 🌟
                $workflow["201"] = [
                    "inputs" => ["clip_name" => "sigclip_vision_patch14_384.safetensors"],
                    "class_type" => "CLIPVisionLoader"
                ];
                $workflow["202"] = [
                    "inputs" => ["crop" => "center", "clip_vision" => ["201", 0], "image" => $last_image_node],
                    "class_type" => "CLIPVisionEncode"
                ];
                $workflow["203"] = [
                    "inputs" => ["style_model_name" => "flux1-redux-dev.safetensors"],
                    "class_type" => "StyleModelLoader"
                ];
                $workflow["204"] = [
                    "inputs" => [
                        "conditioning" => $current_positive,
                        "style_model" => ["203", 0],
                        "clip_vision_output" => ["202", 0],
                        "strength" => floatval($ipa_weight),
                        "strength_type" => "multiply" 
                    ],
                    "class_type" => "StyleModelApply"
                ];
                
                $current_positive = ["204", 0];

            } else {
                // 🧱 RUTA CLÁSICA: IP-Adapter Advanced (SDXL / SD1.5) 🧱
                $workflow["201"] = [
                    "inputs" => ["ipadapter_file" => $ipa_model],
                    "class_type" => "IPAdapterModelLoader"
                ];
                $workflow["202"] = [
                    "inputs" => ["clip_name" => "CLIP-ViT-H-14-laion2B-s32B-b79K.safetensors"],
                    "class_type" => "CLIPVisionLoader"
                ];
                $workflow["203"] = [
                    "inputs" => [
                        "weight" => $ipa_weight,
                        "weight_type" => $ipa_weight_type,
                        "combine_embeds" => "concat",
                        "start_at" => $ipa_start,
                        "end_at" => $ipa_end,
                        "embeds_scaling" => "V only",
                        "model" => [$current_model_node, 0],
                        "ipadapter" => ["201", 0],
                        "image" => $last_image_node,
                        "clip_vision" => ["202", 0]
                    ], 
                    "class_type" => "IPAdapterAdvanced"
                ];
                $current_model_node = "203";
            }
       } else {
            echo json_encode(['error' => __('err_ipadapter_upload') ?? 'Error subiendo imágenes al IP-Adapter.']);
            exit();
        }
    }

    // ==============================================================================
    // --- FASE 7: INPAINTING & OUTPAINTING ---
    // ==============================================================================
    $current_latent = ["5", 0];
    
    // 🎯 INYECCIÓN: Detección FLUX FILL
    $is_flux_fill = ($is_flux && (strpos($model_lower, 'fill') !== false || strpos($model_lower, 'inpaint') !== false));

    if (!empty($init_image_base64)) {
        
        $comfy_filename = null;

        if ($init_image_base64 === "TRAY_IMAGE_USED") {
            // Ya subimos la imagen de la bandeja al inicio, reutilizamos su nombre validado
            $comfy_filename = $comfy_image_filename;
        } else {
            // Proceso normal de decodificación y subida para el visor gigante
            $tmp_file = sys_get_temp_dir() . '/init_' . uniqid() . '.png';
            file_put_contents($tmp_file, base64_decode($init_image_base64));
            $cfile = function_exists('curl_file_create') ? curl_file_create($tmp_file, 'image/png', 'init_image.png') : '@' . realpath($tmp_file);
            
            $ch_up = curl_init(COMFY_URL . '/upload/image');
            curl_setopt($ch_up, CURLOPT_POST, true); curl_setopt($ch_up, CURLOPT_POSTFIELDS, ['image' => $cfile]); curl_setopt($ch_up, CURLOPT_RETURNTRANSFER, true);
            $res_up = json_decode(curl_exec($ch_up), true); @unlink($tmp_file);

            if (isset($res_up['name'])) {
                $comfy_filename = $res_up['name'];
            }
        }

        if ($comfy_filename && $comfy_filename !== "none") {
            $workflow["11"] = ["inputs" => ["image" => $comfy_filename, "upload" => "image"], "class_type" => "LoadImage"];

            $flux_fill_pixels = null;
            $flux_fill_mask = null;
            $flux_noise_mask = false;
            
            if ($is_outpainting) {
                $sampler_denoise = 1.0; 
                $workflow["111"] = [
                    "inputs" => [
                        "left" => $out_left, "top" => $out_top, "right" => $out_right, "bottom" => $out_bottom,
                        "feathering" => 40, "image" => ["11", 0]
                    ],
                    "class_type" => "ImagePadForOutpaint"
                ];

                if ($is_flux_fill) {
                    $flux_fill_pixels = ["111", 0];
                    $flux_fill_mask = ["111", 1];
                    $flux_noise_mask = false;
                } else {
                    $workflow["12"] = [
                        "inputs" => [
                            "grow_mask_by" => 12, "pixels" => ["111", 0], "vae" => [$base_vae_node, $base_vae_index], "mask" => ["111", 1]
                        ],
                        "class_type" => "VAEEncodeForInpaint"
                    ];
                    unset($workflow["5"]); 
                    $workflow["14"] = ["inputs" => ["amount" => $batch_size, "samples" => ["12", 0]], "class_type" => "RepeatLatentBatch"];
                    $current_latent = ["14", 0];
                }

            } else {
                $sampler_denoise = $denoise_slider;
                $workflow["13"] = ["inputs" => ["upscale_method" => "bilinear", "width" => $width, "height" => $height, "crop" => "center", "image" => ["11", 0]], "class_type" => "ImageScale"];

                if (!empty($mask_data_base64)) {
                    $tmp_mask = sys_get_temp_dir() . '/mask_' . uniqid() . '.png';
                    file_put_contents($tmp_mask, base64_decode($mask_data_base64));
                    $cfile_mask = function_exists('curl_file_create') ? curl_file_create($tmp_mask, 'image/png', 'mask.png') : '@' . realpath($tmp_mask);
                    
                    $ch_mask = curl_init(COMFY_URL . '/upload/image');
                    curl_setopt($ch_mask, CURLOPT_POST, true); curl_setopt($ch_mask, CURLOPT_POSTFIELDS, ['image' => $cfile_mask]); curl_setopt($ch_mask, CURLOPT_RETURNTRANSFER, true);
                    $res_mask = json_decode(curl_exec($ch_mask), true); @unlink($tmp_mask);

                    if (isset($res_mask['name'])) {
                        $workflow["21"] = ["inputs" => ["image" => $res_mask['name'], "upload" => "image"], "class_type" => "LoadImage"];
                        $workflow["22"] = ["inputs" => ["upscale_method" => "bilinear", "width" => $width, "height" => $height, "crop" => "center", "image" => ["21", 0]], "class_type" => "ImageScale"];
                        $workflow["23"] = ["inputs" => ["channel" => "red", "image" => ["22", 0]], "class_type" => "ImageToMask"];
                        
                        if ($is_flux_fill) {
                            $flux_fill_pixels = ["13", 0];
                            $flux_fill_mask = ["23", 0];
                            $flux_noise_mask = true;
                        } else {
                            $workflow["12"] = ["inputs" => ["grow_mask_by" => $mask_blur, "pixels" => ["13", 0], "vae" => [$base_vae_node, $base_vae_index], "mask" => ["23", 0]], "class_type" => "VAEEncodeForInpaint"];
                            
                            $latent_source = ["12", 0];
                            if ($inpaint_fill === 'latent_noise') {
                                $workflow["12_noise"] = ["inputs" => ["samples" => ["12", 0], "mask" => ["23", 0]], "class_type" => "SetLatentNoiseMask"];
                                $latent_source = ["12_noise", 0];
                            }
                        }
                    }
                }
                
                if (!isset($workflow["12"]) && !$is_flux_fill) {
                    $workflow["12"] = ["inputs" => ["pixels" => ["13", 0], "vae" => [$base_vae_node, $base_vae_index]], "class_type" => "VAEEncode"];
                    $latent_source = ["12", 0];
                }

                if (!$is_flux_fill) {
                    unset($workflow["5"]);
                    $workflow["14"] = ["inputs" => ["amount" => $batch_size, "samples" => $latent_source], "class_type" => "RepeatLatentBatch"];
                    $current_latent = ["14", 0];
                }
            }
            
            // 🌟 ENSAMBLAJE FINAL PARA FLUX FILL 🌟
            if ($is_flux_fill && isset($flux_fill_pixels) && isset($flux_fill_mask)) {
                
                // 🚀 FIX CRÍTICO: Flux Fill requiere Guidance 30. Si se queda en 3.5, produce el ruido marrón.
                if (isset($workflow["600"]["inputs"]["guidance"])) {
                    $workflow["600"]["inputs"]["guidance"] = 30.0;
                }

                // 1. Conditioning Zero Out (Reutiliza el prompt positivo base sin guiar)
                $workflow["800_zero"] = [
                    "inputs" => ["conditioning" => ["6", 0]], 
                    "class_type" => "ConditioningZeroOut"
                ];
                $current_negative = ["800_zero", 0];

                // 2. Differential Diffusion en el modelo
                $workflow["801_diff"] = [
                    "inputs" => ["strength" => 1.0, "model" => [$current_model_node, 0]],
                    "class_type" => "DifferentialDiffusion"
                ];
                $current_model_node = "801_diff";

                // 3. Inpaint Model Conditioning
                $workflow["802_inpaint_cond"] = [
                    "inputs" => [
                        "noise_mask" => $flux_noise_mask,
                        "positive" => $current_positive, 
                        "negative" => $current_negative,
                        "vae" => [$base_vae_node, $base_vae_index],
                        "pixels" => $flux_fill_pixels,
                        "mask" => $flux_fill_mask
                    ],
                    "class_type" => "InpaintModelConditioning"
                ];

                // Redirigir el KSampler para que tome todo del InpaintConditioning
                $current_positive = ["802_inpaint_cond", 0];
                $current_negative = ["802_inpaint_cond", 1];
                $current_latent   = ["802_inpaint_cond", 2];
                
                // Forzamos parámetros óptimos
                $sampler_denoise = 1.0;
                $sampler = "euler";
                $scheduler = "normal";

                unset($workflow["5"]); // Matamos el EmptyLatentImage
                
                // 🚀 EXTRA: Blindaje por si al usuario le da por poner Batch Size > 1 en la interfaz
                if ($batch_size > 1) {
                    $workflow["803_batch"] = [
                        "inputs" => ["amount" => $batch_size, "samples" => $current_latent],
                        "class_type" => "RepeatLatentBatch"
                    ];
                    $current_latent = ["803_batch", 0];
                }
            }
        }
    }
	
	// ==============================================================================
    // 🌟 INYECCIÓN QWEN EDIT (Reescritura de CLIPTextEncode por el VLM Integrado)
    // ==============================================================================
    if ($is_qwen && !empty($init_image_base64)) {
        // Buscamos cuál es el nodo final de la imagen (escalada o expandida)
        $qwen_image_source = ["11", 0]; 
        if ($is_outpainting && isset($workflow["111"])) {
            $qwen_image_source = ["111", 0]; 
        } elseif (isset($workflow["13"])) {
            $qwen_image_source = ["13", 0]; 
        }

        $workflow["6"] = [
            "inputs" => [
                "prompt" => $posPrompt, // Qwen usa "prompt" en lugar de "text"
                "clip" => [$base_clip_node, $base_clip_index],
                "vae" => [$base_vae_node, $base_vae_index],
                "image" => $qwen_image_source // <-- CONEXIÓN VISUAL DIRECTA
            ],
            "class_type" => "TextEncodeQwenImageEdit"
        ];
        
        $workflow["7"] = [
            "inputs" => [
                "prompt" => $neg_prompt,
                "clip" => [$base_clip_node, $base_clip_index],
                "vae" => [$base_vae_node, $base_vae_index],
                "image" => $qwen_image_source
            ],
            "class_type" => "TextEncodeQwenImageEdit"
        ];
        
        // Qwen Edit reconstruye el latente condicionado por la imagen en el TextEncode.
        // Evaluamos si el panel de edición avanzada está activo
        $is_edit_panel_active = !empty($_POST['edit_tools_active']) || !empty($mask_base64); // Puedes usar también la existencia de la máscara como trigger
        $panel_denoise = isset($_POST['denoise']) ? (float)$_POST['denoise'] : 0.65;
        
        // Si el panel o la máscara están activos, toma el valor del slider. Si no, 1.0 por defecto.
        $sampler_denoise = $is_edit_panel_active ? $panel_denoise : 1.0; 
    }
	
	// ==============================================================================
    // 🌟 INYECCIÓN FLUX 2 KLEIN EDIT & MULTICARGA (Kontext Conditioner)
    // ==============================================================================
    $is_flux2 = (strpos($model_lower, 'flux2') !== false || strpos($model_lower, 'klein') !== false || strpos($model_lower, 'kontext') !== false);

    if ($is_flux2 && (!empty($init_image_base64) || !empty($tray_comfy_filenames)) && !$is_outpainting) {
        
        // 1. Agrupar Imágenes (de la bandeja o del visor)
        $kontext_images = [];
        
        if (!empty($tray_comfy_filenames)) {
            // MODO BANDEJA: Cargamos dinámicamente tantas fotos como subiera el usuario
            foreach ($tray_comfy_filenames as $index => $filename) {
                $node_id = "11_tray_" . $index;
                $workflow[$node_id] = [
                    "inputs" => ["image" => $filename, "upload" => "image"],
                    "class_type" => "LoadImage"
                ];
                $kontext_images[$index] = [$node_id, 0];
            }
        } else {
            // MODO VISOR GIGANTE: Solo hay una
            $kontext_images[1] = isset($workflow["13"]) ? ["13", 0] : ["11", 0];
        }

        // 2. Construimos la estructura base del Conditioner
        $inputs_pos = [
            "prompt" => $posPrompt, 
            "target_resolution" => 1024,
            "alignment" => 16,
            "upscale_method" => "lanczos",
            "guidance" => $flux_guidance ?? 3.5,
            "system_prompt_mode" => "none",
            "vae_tiling" => false,
            "custom_system_prompt" => "",
            "clip" => [$base_clip_node, $base_clip_index],
            "vae" => [$base_vae_node, $base_vae_index] 
        ];
        
        // 3. Inyectamos dinámicamente image1, image2, image3...
        $contador = 1;
        foreach ($kontext_images as $real_index => $node_ref) {
            $inputs_pos["image" . $contador] = $node_ref;
            $inputs_pos["image" . $contador . "_strength"] = 1.0;
            $inputs_pos["image" . $contador . "_resolution"] = 1024;
            $contador++;
        }
        
        // 4. Replicamos todo exactamente igual para el Negativo
        $inputs_neg = $inputs_pos;
        $inputs_neg["prompt"] = $neg_prompt;

        $workflow["6"] = [
            "inputs" => $inputs_pos,
            "class_type" => "Flux2KontextConditioner"
        ];
        
        $workflow["7"] = [
            "inputs" => $inputs_neg,
            "class_type" => "Flux2KontextConditioner" 
        ];
        
        // 5. Matamos el FluxGuidance estándar para evitar conflictos
        if (isset($workflow["600"])) {
            $current_positive = ["6", 0]; 
            unset($workflow["600"]);
        }
        
        // 6. Modo edición dinámico
        $is_edit_panel_active = !empty($_POST['edit_tools_active']) || !empty($mask_base64);
        $panel_denoise = isset($_POST['denoise']) ? (float)$_POST['denoise'] : 0.65;
        $sampler_denoise = $is_edit_panel_active ? $panel_denoise : 1.0; 
    }

    // ==============================================================================
    // 🌟 LORAS
    // ==============================================================================
    $lora_node_id = 700; 

    if (is_array($lora_names) && count($lora_names) > 0) {
        for ($j = 0; $j < count($lora_names); $j++) {
            $lname = trim($lora_names[$j]); if (empty($lname)) continue; 
            
            // AUTOMATIZACIÓN CARPETA CHROMA (Solo si no trae ruta manual)
            if ($is_chroma && strpos($lname, '\\') === false && strpos($lname, '/') === false) {
                $lname = "Chroma\\" . $lname;
            }
            
            // AUTOMATIZACIÓN CARPETA KREA2 (Solo si no trae ruta manual)
            if (isset($is_krea2) && $is_krea2 && strpos($lname, '\\') === false && strpos($lname, '/') === false) {
                $lname = "Krea2\\" . $lname;
            }
			
			// =========================================================
            // --- NUEVO: AUTOMATIZACIÓN CARPETAS HUNYUAN, HIDREAM y ANIMA ---
            // =========================================================
            if ($is_hunyuan && strpos($lname, '\\') === false && strpos($lname, '/') === false) {
                $lname = "Hunyuan\\" . $lname; // (o "HunyuanImage\\" si prefieres ese nombre en tu disco)
            }
            
            if ($is_hidream && strpos($lname, '\\') === false && strpos($lname, '/') === false) {
                $lname = "HiDream\\" . $lname;
            }
			
			if ($is_anima && strpos($lname, '\\') === false && strpos($lname, '/') === false) {
                $lname = "Anima\\" . $lname;
            }
            // =========================================================
            
            $lstr = floatval($lora_strengths_high[$j] ?? 0.8); $curr_node = (string)$lora_node_id;
            
            if (isset($is_krea2) && $is_krea2) {
                // Krea-2 exige cargar LoRAs solo al modelo (bypass del CLIP)
                $workflow[$curr_node] = [ 
                    "inputs" => [ 
                        "lora_name" => $lname, "strength_model" => $lstr, 
                        "model" => [$current_model_node, 0] 
                    ], 
                    "class_type" => "LoraLoaderModelOnly" 
                ];
                $current_model_node = $curr_node; 
            } else {
                // Comportamiento Universal para el resto de Modelos
                $workflow[$curr_node] = [ 
                    "inputs" => [ 
                        "lora_name" => $lname, "strength_model" => $lstr, "strength_clip" => $lstr, 
                        "model" => [$current_model_node, 0], "clip" => [$base_clip_node, $base_clip_index] 
                    ], 
                    "class_type" => "LoraLoader" 
                ];
                $current_model_node = $curr_node; 
                $base_clip_node = $curr_node; $base_clip_index = 1; 
            }
            
            $lora_node_id++;
            $lora_metadata_list[] = basename($lname, '.safetensors') . " ($lstr)";
        }
        $workflow["6"]["inputs"]["clip"] = [$base_clip_node, $base_clip_index];
        $workflow["7"]["inputs"]["clip"] = [$base_clip_node, $base_clip_index];
    }
    
    // --- SHIFT DE CHROMA (CLONADO DEL JSON: Va DESPUÉS de los LoRAs) ---
    if ($is_chroma) {
        $workflow["850_shift"] = [
            "inputs" => [
                "max_shift" => 1.15,
                "base_shift" => 0.50,
                "width" => $width,
                "height" => $height,
                "model" => [$current_model_node, 0]
            ],
            "class_type" => "ModelSamplingFlux"
        ];
        $current_model_node = "850_shift"; 
    }
    
    // =========================================================================
    // --- NUEVO: SHIFT DE HIDREAM (ModelSamplingSD3 con Shift 3.0) ---
    // =========================================================================
    if ($is_hidream) {
        $hidream_shift = ($user_shift !== null) ? $user_shift : 3.0; // Si el usuario lo cambió, lo usamos
        
        $workflow["850_hidream_shift"] = [
            "inputs" => [
                "shift" => $hidream_shift,
                "model" => [$current_model_node, 0]
            ],
            "class_type" => "ModelSamplingSD3" 
        ];
        // Actualizamos el puntero para que el KSampler reciba este nodo automáticamente
        $current_model_node = "850_hidream_shift"; 
    }
    // =========================================================================

   // --- WRAPPERS OBLIGATORIOS PARA QWEN EDIT ---
    if ($is_qwen && !empty($init_image_base64)) {
        $workflow["850_qwen_aura"] = [
            "inputs" => [
                "shift" => 3.0,
                "model" => [$current_model_node, 0]
            ],
            "class_type" => "ModelSamplingAuraFlow"
        ];
        $workflow["851_qwen_cfg"] = [
            "inputs" => [
                "strength" => 1.0,
                "pre_cfg" => false,
                "model" => ["850_qwen_aura", 0]
            ],
            "class_type" => "CFGNorm"
        ];
        // Enganchamos el modelo normalizado para que el KSampler lo recoja
        $current_model_node = "851_qwen_cfg"; 
    }
	
	// ==============================================================================
    // 🌟 INYECCIÓN KREA-2 BODYSWAP (DOBLE IMAGEN: CUERPO + CARA)
    // ==============================================================================
    if ($is_krea2 && $comfy_image_filename !== "none" && !empty($tray_comfy_filenames[1])) {
        
        // 1. Preparamos el prompt: Si escribes algo, manda TU texto exacto. Solo usa el salvavidas si la caja está vacía.
        $prompt_texto = !empty(trim($posPrompt)) ? trim($posPrompt) : "body_swap: replace the person with the reference person.";

        // 2. Extraemos el LoRA de la UI y su FUERZA, o usamos los valores por defecto
        $lora_krea = !empty($lora_names[0]) ? $lora_names[0] : "Krea2\\bfs_body_swap_v1_krea2.safetensors";
        $lora_fuerza = isset($lora_strengths_high[0]) ? floatval($lora_strengths_high[0]) : 1.0;
        
        if (strpos($lora_krea, '\\') === false && strpos($lora_krea, '/') === false) {
            $lora_krea = "Krea2\\" . $lora_krea;
        }

        // 3. Montaje del Workflow Nativo en PHP
        $workflow = [
            "57" => [ "class_type" => "VAELoader", "inputs" => [ "vae_name" => "qwen_image_vae.safetensors" ] ],
            "56" => [ "class_type" => "CLIPLoader", "inputs" => [ "clip_name" => "qwen3vl_4b_fp8_scaled.safetensors", "type" => "krea2", "device" => "default" ] ],
            "55" => [ "class_type" => "UNETLoader", "inputs" => [ "unet_name" => $model_path, "weight_dtype" => "default" ] ],
            "127" => [ "class_type" => "LoraLoaderModelOnly", "inputs" => [ "lora_name" => $lora_krea, "strength_model" => $lora_fuerza, "model" => ["55", 0] ] ],
            
            // Inyectamos las imágenes (Cuerpo = Principal | Cara = Bandeja 1)
            "72" => [ "class_type" => "LoadImage", "inputs" => [ "image" => $comfy_image_filename ] ],
            "139" => [ "class_type" => "LoadImage", "inputs" => [ "image" => $tray_comfy_filenames[1] ] ],
            
            // Redimensionado
            "123" => [ "class_type" => "ImageResizeKJv2", "inputs" => [ "width" => 1024, "height" => 1024, "upscale_method" => "lanczos", "keep_proportion" => "resize", "pad_color" => "0, 0, 0", "crop_position" => "center", "divisible_by" => 2, "device" => "cpu", "image" => ["72", 0] ] ],
            "140" => [ "class_type" => "ImageResizeKJv2", "inputs" => [ "width" => 1024, "height" => 1024, "upscale_method" => "lanczos", "keep_proportion" => "resize", "pad_color" => "0, 0, 0", "crop_position" => "center", "divisible_by" => 2, "device" => "cpu", "image" => ["139", 0] ] ],
            
            // Encoders VAE
            "73" => [ "class_type" => "VAEEncode", "inputs" => [ "pixels" => ["123", 0], "vae" => ["57", 0] ] ],
            "117" => [ "class_type" => "VAEEncode", "inputs" => [ "pixels" => ["140", 0], "vae" => ["57", 0] ] ],
            "121" => [ "class_type" => "GetImageSize", "inputs" => [ "image" => ["123", 0] ] ],
            "135" => [ "class_type" => "EmptySD3LatentImage", "inputs" => [ "width" => ["121", 0], "height" => ["121", 1], "batch_size" => 1 ] ],
            
            // Nodos curados de edición Krea-2
            "119" => [ "class_type" => "Krea2EditGroundedEncode", "inputs" => [ "prompt" => $prompt_texto, "grounding_px" => 0, "system_prompt" => "", "clip" => ["56", 0], "image" => ["123", 0], "image_b" => ["140", 0] ] ],
            "85"  => [ "class_type" => "Krea2EditGroundedEncode", "inputs" => [ "prompt" => $neg_prompt, "grounding_px" => 0, "system_prompt" => "", "clip" => ["56", 0], "image" => ["123", 0], "image_b" => ["140", 0] ] ],
            "120" => [ "class_type" => "Krea2EditModelPatch", "inputs" => [ "ref_boost" => 1, "ref_boost_a" => 1, "fit_mode" => "fit", "model" => ["127", 0], "source_latent" => ["73", 0], "source_latent_b" => ["117", 0], "ref_boost_mask" => ["123", 3], "vae" => ["57", 0], "source_image" => ["123", 0], "source_image_b" => ["140", 0] ] ],
            
            // Generación y Guardado
            "53" => [ "class_type" => "KSampler", "inputs" => [ "seed" => $seed, "steps" => $steps, "cfg" => $cfg, "sampler_name" => $sampler, "scheduler" => $scheduler, "denoise" => 1, "model" => ["120", 0], "positive" => ["119", 0], "negative" => ["85", 0], "latent_image" => ["135", 0] ] ],
            "54" => [ "class_type" => "VAEDecode", "inputs" => [ "samples" => ["53", 0], "vae" => ["57", 0] ] ],
            "9" => [ "class_type" => "PreviewImage", "inputs" => [ "images" => ["54", 0] ] ]
        ];

        // 4. Metadatos y Base de Datos
        $meta_json_array = [
            'Model' => basename($model_path), 
            'Resolution' => $width . 'x' . $height, 
            'Seed' => $seed, 
            'Steps' => $steps, 
            'CFG Scale' => $cfg, 
            'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')', 
            'LoRAs' => basename($lora_krea),
            'Modo' => 'Krea-2 Bodyswap'
        ];
        $meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

        if ($historial_id > 0) {
            $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET prompt_positivo = ?, prompt_negativo = ?, metadata = ? WHERE id = ?");
            $stmt_upd->execute([$posPrompt, $neg_prompt, $meta_json, $historial_id]);
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_ins->execute([$user_id, $selector, $posPrompt, $posPrompt, $neg_prompt, $meta_json]);
            $historial_id = $pdo->lastInsertId();
        }

        $current_image_node = "9";
        goto EJECUTAR_COMFYUI;
    }
	
	
	// ==============================================================================
    // 🌟 INYECCIÓN IDEOGRAM 4 (Vía JSON Plantilla Estricta)
    // ==============================================================================
    $is_ideogram = (strpos($model_lower, 'ideogram') !== false);
    
    // 👇 Fíjate que el IF ahora solo pregunta si es ideogram. Lo atrapa SIEMPRE.
    if ($is_ideogram) {
        
        // 🛑 ESCUDO ANTI-CRASH: Si el usuario intenta usar Img2Img con Ideogram, 
        // lo frenamos aquí mismo y le mostramos un aviso en la web.
        if (!empty($init_image_base64) || $is_outpainting) {
            echo json_encode(['error' => 'Ideogram 4 actualmente es un modelo puro de Texto-a-Imagen. Por favor, elimina la imagen base o la máscara del panel para poder renderizar.']);
            exit();
        }
        
        $ruta_json = __DIR__ . '/../workflows/Ideogram4.json';
        
        $reemplazos = [
            '__SEED__' => $seed,
            '__WIDTH__' => $width,
            '__HEIGHT__' => $height,
            '__SAMPLER__' => $sampler,
            '__MODELO__' => $model_path,
            '__PROMPT_POSITIVO__' => $posPrompt
        ];

        // Cargamos el JSON e inyectamos los valores del usuario
        $workflow = cargarWorkflowJSON($ruta_json, $reemplazos);
        
        // --- 1. Lógica interna de calidad Ideogram ---
        // Ideogram no usa "steps" directos en el UI, usa 3 modos (Quality, Default, Turbo)
        // Convertimos los steps de Garty's Architect a esos modos para no romper la matemática
        $modo_calidad = "Quality";
        if ($steps <= 15) {
            $modo_calidad = "Turbo";
        } elseif ($steps > 15 && $steps <= 25) {
            $modo_calidad = "Default";
        }
        $workflow["98:156"]["inputs"]["choice"] = $modo_calidad;
        
        // --- 2. Inyección de LoRAs ---
        // Convertimos los punteros en arrays completos [nodo, puerto]
        $current_model_ideo = ["98:23", 0];
        $current_clip_ideo  = ["98:177", 0];
        
        if (!empty($lora_names)) {
            $l_id = 500;
            foreach ($lora_names as $j => $lname) {
                if (empty(trim($lname))) continue;
                $lstr = floatval($lora_strengths_high[$j] ?? 0.8);
                
                // Formateamos la ruta para Ideogram (Ajustado a ideogram4)
                if (strpos($lname, '\\') === false && strpos($lname, '/') === false) {
                    $lname = "ideogram4\\" . $lname;
                }
                
                $workflow[(string)$l_id] = [
                    "inputs" => [
                        "lora_name" => $lname,
                        "strength_model" => $lstr,
                        "strength_clip" => $lstr,
                        "model" => $current_model_ideo,
                        "clip" => $current_clip_ideo
                    ],
                    "class_type" => "LoraLoader"
                ];
                
                // ACTUALIZAMOS LOS PUNTEROS: 
                // El modelo sale por el puerto 0 y el CLIP por el puerto 1 del LoraLoader
                $current_model_ideo = [(string)$l_id, 0];
                $current_clip_ideo  = [(string)$l_id, 1];
                
                $l_id++;
            }
            
            // Actualizamos los nodos principales para que recojan la salida con los LoRAs
            $workflow["98:157"]["inputs"]["model"] = $current_model_ideo;
            $workflow["98:24"]["inputs"]["clip"] = $current_clip_ideo;
        }

        $current_image_node = "184"; // El nodo SaveImage
        goto EJECUTAR_COMFYUI;
    }
    
    // --- FASE EXTRA: DYNAMIC THRESHOLDING ---
    if ($dynamic_thresholding) {
        $mimic_value = $is_video_mode ? 3.5 : 7.0; 

        $workflow["800"] = [
            "class_type" => "DynamicThresholdingSimple",
            "inputs" => [
                "model" => [$current_model_node, 0],
                "mimic_scale" => $mimic_value, 
                "threshold_percentile" => 0.999
            ]
        ];
        $current_model_node = "800"; 
    }
    
    // --- ENSAMBLAJE DEL GENERADOR PRINCIPAL ---
    if ($inpaint_area === 'Only Masked' && !empty($mask_data_base64) && isset($workflow["23"]) && !$is_flux_fill) {
        
        $workflow["800"] = [
            "inputs" => [
                "mask" => ["23", 0],
                "combined" => false,
                "crop_factor" => 3.0,
                "bbox_fill" => false,
                "drop_size" => 10,
                "contour_fill" => false
            ],
            "class_type" => "MaskToSEGS"
        ];
        
        $workflow["801"] = [
            "inputs" => [
                "image" => ["13", 0], 
                "segs" => ["800", 0],
                "model" => [$current_model_node, 0],
                "clip" => [$base_clip_node, $base_clip_index],
                "vae" => [$base_vae_node, $base_vae_index],
                "positive" => $current_positive,
                "negative" => $current_negative,
                "guide_size" => 1024,
                "guide_size_for" => true,
                "max_size" => 1024,
                "seed" => $seed,
                "steps" => $steps,
                "cfg" => $cfg,
                "sampler_name" => $sampler,
                "scheduler" => $scheduler,
                "denoise" => $sampler_denoise,
                "feather" => $mask_blur, 
                "noise_mask" => ($inpaint_fill === 'latent_noise') ? true : false, 
                "force_inpaint" => true,
                "bbox_margin" => 10,
                "drop_size" => 10,
                "wildcard" => "",
                "cycle" => 1
            ],
            "class_type" => "DetailerForEach"
        ];
        
        $current_image_node = "801"; 
        
        unset($workflow["12"]);
        if (isset($workflow["12_noise"])) unset($workflow["12_noise"]);
        unset($workflow["14"]);
        
    } else {
        
        // --- BYPASS: UPSCALE CREATIVO (SALTO DEL KSAMPLER) ---
        // Si hay imagen cargada, hires_fix activado, NO es outpainting y tiene prompt (no es puro)
        if ($hires_fix && !empty($init_image_base64) && !$is_outpainting && !$pure_upscale) {
            // Enganchamos la salida de la imagen al nodo 13 (ImageScale de la Fase 7) o al 11 (LoadImage original)
            $current_image_node = isset($workflow["13"]) ? "13" : "11";
            
            // Limpiamos la memoria de los nodos latentes que ya no vamos a usar
            unset($workflow["12"]); 
            if (isset($workflow["12_noise"])) unset($workflow["12_noise"]);
            unset($workflow["14"]);
            
        } else {
            // Generación Text2Img o Img2Img normal
            $workflow["3"] = [
                "inputs" => [
                    "seed" => $seed, "steps" => $steps, "cfg" => $cfg, "sampler_name" => $sampler, "scheduler" => $scheduler, "denoise" => $sampler_denoise, 
                    "model" => [$current_model_node, 0], 
                    "positive" => $current_positive, 
                    "negative" => $current_negative, 
                    "latent_image" => $current_latent
                ], 
                "class_type" => "KSampler" 
            ];

            $workflow["8"] = [ "inputs" => ["samples" => ["3", 0], "vae" => [$base_vae_node, $base_vae_index]], "class_type" => "VAEDecode" ];
            $current_image_node = "8";
        }
    }

    // --- FASE 5: UPSCALE ---
    if ($hires_fix) {
        if ($aurasr_enabled) {
            // RUTA VIP: AuraSR toma el latente decodificado (current_image_node) y lo escala a píxel puro
            $workflow["400"] = ["inputs" => ["model" => "AuraSR-v2.safetensors"], "class_type" => "LoadAuraSR"];
            $workflow["401"] = ["inputs" => ["avoid_seams" => true, "AURASR_MODEL" => ["400", 0], "IMAGE" => [$current_image_node, 0]], "class_type" => "RunAuraSR"];
            
            // Calculamos el tamaño final exacto
            $target_w = intval($width * $upscale_factor);
            $target_h = intval($height * $upscale_factor);
            
            // Ajustamos el monstruo 4x a lo que ha pedido el usuario
            $workflow["402"] = [
                "inputs" => ["upscale_method" => "bicubic", "width" => $target_w, "height" => $target_h, "crop" => "disabled", "image" => ["401", 0]],
                "class_type" => "ImageScale"
            ];
            $current_image_node = "402";

        } elseif (!empty($upscale_model)) {
            // RUTA CLÁSICA: Ultimate SD Upscale con modelo estándar
            $workflow["400"] = ["inputs" => ["model_name" => $upscale_model], "class_type" => "UpscaleModelLoader"];
            $workflow["401"] = [
                "inputs" => [
                    "image" => [$current_image_node, 0],
                    "model" => [$current_model_node, 0], 
                    "positive" => $current_positive,
                    "negative" => $current_negative,
                    "vae" => [$base_vae_node, $base_vae_index],
                    "upscale_model" => ["400", 0],
                    "upscale_by" => $upscale_factor,
                    "seed" => $seed,
                    "steps" => $steps,
                    "cfg" => $cfg,
                    "sampler_name" => $sampler,
                    "scheduler" => $scheduler,
                    "denoise" => 0.25, 
                    "mode_type" => "Linear",
                    "tile_width" => 512,
                    "tile_height" => 512,
                    "mask_blur" => 8,
                    "tile_padding" => 32,
                    "seam_fix_mode" => "None",
					"seam_fix_denoise" => 1.0,               
					"seam_fix_width" => 64,                  
					"seam_fix_mask_blur" => 8,               
					"seam_fix_padding" => 16,
					"force_uniform_tiles" => true,
                    "tiled_decode" => false,
                    "batch_size" => 1                   
                ],
                "class_type" => "UltimateSDUpscale"
            ];
            $current_image_node = "401";
        }
    }

    // --- FASE 7: REMBG (ELIMINACIÓN DE FONDO) ---
    if ($remove_background) {
        $workflow["500"] = [
            "inputs" => [
                "images" => [$current_image_node, 0],
                "transparency" => true,
                "model" => "u2net",
                "post_processing" => false,
                "only_mask" => false,
                "alpha_matting" => false,
                "alpha_matting_foreground_threshold" => 240,
                "alpha_matting_background_threshold" => 10,
                "alpha_matting_erode_size" => 10,
                "background_color" => "none"
            ],
            "class_type" => "Image Rembg (Remove Background)"
        ];
        $current_image_node = "500";
    }

    // --- FASE 8: DDCOLOR INTEGRADO (CON PROMPT) ---
    if ($ddcolor_enabled && !$pure_ddcolor) {
        $workflow["501"] = [
            "inputs" => [
                "image" => [$current_image_node, 0],
                "model_input_size" => 512,
                "checkpoint" => $ddcolor_model 
            ], 
            "class_type" => "DDColor_Colorize" 
        ];
        $current_image_node = "501";
    }

    if (!$is_video_mode) {
        $workflow["9"] = [ "inputs" => ["images" => [$current_image_node, 0]], "class_type" => "PreviewImage" ];
    }
    
    } // 🚨 <--- CIERRE DEL IF (PRUEBA_JSON_ACTIVADA)

    EJECUTAR_COMFYUI:
	
	// ==============================================================================
    // 🌟 INYECCIÓN AUTOREGRESIVA: VIDEO-TO-VIDEO UNIVERSAL (LTX / WAN)
    // ==============================================================================
	if (isset($is_video) && $is_video && $comfy_image_filename !== "none") {
        foreach ($workflow as $id_nodo => $nodo) {
            if (isset($nodo['class_type']) && $nodo['class_type'] === 'LoadImage') {
                if (isset($nodo['inputs']['image']) && $nodo['inputs']['image'] === $comfy_image_filename) {
                    $workflow[$id_nodo] = [
                        "class_type" => "VHS_LoadVideo",
                        "inputs" => [
                            "video" => $comfy_image_filename,
                            "force_rate" => 0,
                            "force_size" => "Disabled",
                            "custom_width" => 0,
                            "custom_height" => 0,
                            "frame_load_cap" => 0,
                            "skip_first_frames" => 0,
                            "select_every_nth" => 1
                        ]
                    ];
                }
            }
        }
    }
	
	// ==============================================================================
    // 🌟 INYECCIÓN UNIVERSAL DE REACTOR (FACE SWAP PARA IMAGEN Y VÍDEO)
    // ==============================================================================
    if (isset($_POST['reactor_enabled']) && $_POST['reactor_enabled'] === 'true' && (!empty($_POST['reactor_image']) || !empty($_POST['reactor_saved_face']))) { 
        
        $reactor_saved_face = $_POST['reactor_saved_face'] ?? null;
        $reactor_image_base64 = $_POST['reactor_image'] ?? null;

        // 1. Carga de la cara de origen (Híbrida: Temporal o Guardada)
        if (!empty($reactor_saved_face)) {
            $workflow["100_reactor_face"] = [
                "inputs" => ["face_model" => $reactor_saved_face], 
                "class_type" => "ReActorLoadFaceModel"
            ];
            $tipo_entrada_rostro = "face_model";
        } else {
            $tmp_face = sys_get_temp_dir() . '/face_' . uniqid() . '.png'; 
            file_put_contents($tmp_face, base64_decode($reactor_image_base64)); 
            $cfile_face = function_exists('curl_file_create') ? curl_file_create($tmp_face, 'image/png', 'face_ref.png') : '@' . realpath($tmp_face); 
            
            $ch_face = curl_init(COMFY_URL . '/upload/image'); 
            curl_setopt($ch_face, CURLOPT_POST, true); 
            curl_setopt($ch_face, CURLOPT_POSTFIELDS, ['image' => $cfile_face]); 
            curl_setopt($ch_face, CURLOPT_RETURNTRANSFER, true); 
            curl_setopt($ch_face, CURLOPT_TIMEOUT, 30);
            
            $res_face = json_decode(curl_exec($ch_face), true); @unlink($tmp_face);

            if (isset($res_face['name'])) { 
                $workflow["100_reactor_face"] = ["inputs" => ["image" => $res_face['name'], "upload" => "image"], "class_type" => "LoadImage"]; 
                $tipo_entrada_rostro = "source_image";
            }
        }

        if (isset($tipo_entrada_rostro)) {
            $nodo_empaquetador = null;

            // 2. RADAR INTELIGENTE (Bug de referencia de PHP solucionado usando $n_temp)
            foreach ($workflow as $id_nodo => $n_temp) {
                if (isset($n_temp['class_type']) && in_array($n_temp['class_type'], ['PreviewImage', 'SaveImage', 'SaveAnimatedWEBP', 'VHS_VideoCombine'])) {
                    $nodo_empaquetador = $id_nodo;
                    break;
                }
            }

            if ($nodo_empaquetador !== null && isset($workflow[$nodo_empaquetador]["inputs"]["images"])) {
                
                // 3. Desconectamos las imágenes/frames del empaquetador
                $nodo_origen_imagenes = $workflow[$nodo_empaquetador]["inputs"]["images"];

                // 4. Inyectamos ReActor en medio (¡Y con límite de seguridad!)
                $workflow["101_reactor_swap"] = [ 
                    "inputs" => [ 
                        "enabled" => true,  
                        "swap_model" => "inswapper_128.onnx",  
                        "facedetection" => $_POST['reactor_detector'] ?? "retinaface_resnet50",              
                        "face_restore_model" => $_POST['reactor_restore_model'] ?? "none",   
                        // 👇 FIX CRÍTICO: max(0.1, ...) para que nunca baje del mínimo legal de ComfyUI
                        "face_restore_visibility" => isset($_POST['reactor_visibility']) ? max(0.1, floatval($_POST['reactor_visibility'])) : 1.0, 
                        "codeformer_weight" => isset($_POST['reactor_fidelity']) ? max(0.0, floatval($_POST['reactor_fidelity'])) : 0.75,         
                        "detect_gender_input" => $_POST['reactor_gender'] ?? "no",         
                        "detect_gender_source" => "no", 
                        "input_faces_index" => $_POST['reactor_target_index'] ?? "0",     
                        "source_faces_index" => $_POST['reactor_source_index'] ?? "0",    
                        "console_log_level" => 1, 
                        "input_image" => $nodo_origen_imagenes, // Toma el cable original
                        $tipo_entrada_rostro => ["100_reactor_face", 0]             
                    ], "class_type" => "ReActorFaceSwap" 
                ]; 

                // 5. Conectamos la salida de ReActor al empaquetador
                $workflow[$nodo_empaquetador]["inputs"]["images"] = ["101_reactor_swap", 0];
            }
        } 
    }

    // ==============================================================================
    // --- INYECCIÓN DE ADETAILER (Reparador con alineación de tensores DiT) ---
    // ==============================================================================
    if (isset($use_adetailer) && $use_adetailer) {
        if (isset($workflow["9"]["inputs"]["images"])) {
            
            // 1. ENRUTAMIENTO INTELIGENTE (ADETAILER ANTES QUE REACTOR)
            $nodo_origen_imagen = $workflow["9"]["inputs"]["images"]; 
            $es_previo_a_reactor = false;

            // Si ReActor está activo, interceptamos el cable ANTES de que ReActor actúe
            if (isset($workflow["101"]) && $workflow["101"]["class_type"] === "ReActorFaceSwap") {
                $nodo_origen_imagen = $workflow["101"]["inputs"]["input_image"];
                $es_previo_a_reactor = true;
            }

            $workflow["900"] = ["inputs" => ["model_name" => $adetailer_model], "class_type" => "UltralyticsDetectorProvider"];

            // Enrutamiento por defecto (Si la imagen base ya es SD1.5 o SDXL)
            $face_model_source = [$current_model_node, 0];
            $face_clip_source  = [$base_clip_node, $base_clip_index];
            $face_vae_source   = [$base_vae_node, $base_vae_index];
            $face_pos_source   = $current_positive;
            $face_neg_source   = $current_negative;
            $adetailer_cfg     = $cfg;
            $adetailer_steps   = $steps;

            // 🛡️ ESTRATEGIA ANTI-COLLISION PARA DiT (Flux / Chroma / Krea-2 / Qwen / Anima / etc)
            if ($is_flux || $is_chroma || $is_krea2 || $is_qwen || $is_zimage || $is_hunyuan || $is_hidream || $is_anima) {
                $modelo_rostros = "";

                $stmt_ref = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE categoria = 'sys_refiner' LIMIT 1");
                $row_ref = $stmt_ref->fetch();
                
                if ($row_ref && !empty(trim($row_ref['nombre_archivo']))) {
                    $modelo_rostros = trim($row_ref['nombre_archivo']);
                } else {
                    $stmt_auto = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE categoria IN ('sd15', 'sdxl') OR (nombre_archivo NOT LIKE '%flux%' AND nombre_archivo NOT LIKE '%chroma%') LIMIT 1");
                    $row_auto = $stmt_auto->fetch();
                    if ($row_auto) $modelo_rostros = $row_auto['nombre_archivo'];
                }

                if (!empty($modelo_rostros)) {
                    // 1. Cargamos el modelo de apoyo (SD1.5 / SDXL)
                    $workflow["902"] = [
                        "inputs" => ["ckpt_name" => $modelo_rostros],
                        "class_type" => "CheckpointLoaderSimple"
                    ];
                    
                    // 2. CRÍTICO: Creamos nodos CLIPTextEncode dedicados con el CLIP del modelo 902
                    $workflow["903_pos"] = [
                        "inputs" => ["text" => $posPrompt, "clip" => ["902", 1]],
                        "class_type" => "CLIPTextEncode"
                    ];
                    $workflow["903_neg"] = [
                        "inputs" => ["text" => $neg_prompt, "clip" => ["902", 1]],
                        "class_type" => "CLIPTextEncode"
                    ];

                    $face_model_source = ["902", 0];
                    $face_clip_source  = ["902", 1];
                    $face_vae_source   = ["902", 2];
                    $face_pos_source   = ["903_pos", 0]; 
                    $face_neg_source   = ["903_neg", 0]; 
                    
                    $adetailer_denoise = 0.35; 
                    $adetailer_cfg     = 5.5; 
                } else {
                    // Si no hay refiner en la BD, apagamos ADetailer o avisamos para no romper Flux
                    echo json_encode(['error' => "Para usar ADetailer con modelos Flux/DiT, necesitas asignar un modelo SD1.5 o SDXL en la categoría 'sys_refiner'."]);
                    exit();
                }
            }

            // Nodo principal FaceDetailer
            $workflow["901"] = [
                "inputs" => [
                    "guide_size" => 384, 
                    "guide_size_for" => "bbox", 
                    "max_size" => 1024,
                    "seed" => $seed, 
                    "steps" => $adetailer_steps > 30 ? 20 : $adetailer_steps,
                    "cfg" => $adetailer_cfg, 
                    "sampler_name" => $sampler, 
                    "scheduler" => $scheduler,
                    "denoise" => isset($adetailer_denoise) ? floatval($adetailer_denoise) : 0.35,
                    "feather" => 5, 
                    "noise_mask" => true, 
                    "force_inpaint" => true,
                    "bbox_threshold" => 0.5,
                    "bbox_dilation" => 10,
                    "bbox_margin" => 15,
                    "bbox_crop_factor" => 3.0,
                    "drop_size" => 10,
                    "sam_detection_hint" => "center-1",
                    "sam_dilation" => 0,
                    "sam_threshold" => 0.93,
                    "sam_bbox_expansion" => 0,
                    "sam_mask_hint_threshold" => 0.7,
                    "sam_mask_hint_use_negative" => "False",
                    "refiner_ratio" => 0.2,
                    "cycle" => 1,
                    "wildcard" => "",
                    "image" => $nodo_origen_imagen, // <--- Bug arreglado (antes anidaba arrays)
                    "model" => $face_model_source,
                    "clip" => $face_clip_source,
                    "vae" => $face_vae_source,
                    "positive" => $face_pos_source, 
                    "negative" => $face_neg_source, 
                    "bbox_detector" => ["900", 0]
                ],
                "class_type" => "FaceDetailer"
            ];

            // 2. RECONECTAMOS LOS CABLES SEGÚN EL ORDEN
            if ($es_previo_a_reactor) {
                // ReActor ahora tomará la cara mejorada y en alta resolución de ADetailer
                $workflow["101"]["inputs"]["input_image"] = ["901", 0];
            } else {
                // Comportamiento normal: salida directa al visor
                $workflow["9"]["inputs"]["images"] = ["901", 0]; 
            }
        }
    }
    
    // --- EJECUCIÓN COMFYUI ---
    $ch = curl_init(COMFY_URL . "/prompt");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600); // 10 Minutos
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["prompt" => $workflow]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = json_decode(curl_exec($ch), true);
    
    if (!isset($res['prompt_id'])) { 
        $err_msg = __('err_gpu_saturated');
        
        $archivo_faltante = "";
        $nodos_faltantes = [];

        // 1. Buscamos errores específicos en los nodos
        if (isset($res['node_errors']) && is_array($res['node_errors']) && !empty($res['node_errors'])) {
            foreach ($res['node_errors'] as $id => $nodo) {
                if (isset($nodo['errors']) && is_array($nodo['errors'])) {
                    foreach ($nodo['errors'] as $err) {
                        $msg_err = $err['message'] ?? '';
                        $det_err = $err['details'] ?? '';
                        
                        // Capturamos tanto 'not found' como 'not in list' (archivos faltantes)
                        if (strpos($msg_err, 'not found') !== false || strpos($msg_err, 'not in list') !== false || strpos($det_err, 'not in list') !== false) {
                            if (preg_match("/['\"](.*?\.(safetensors|pth|onnx|pt|bin))['\"]/i", $det_err . ' ' . $msg_err, $coincidencias)) {
                                $archivo_faltante = $coincidencias[1];
                            } elseif (preg_match("/:\s*['\"](.*?)['\"]/i", $det_err, $coincidencias)) {
                                $archivo_faltante = $coincidencias[1];
                            } else {
                                $archivo_faltante = $det_err ?: $msg_err;
                            }
                            break 2; 
                        }
                    }
                }
                if (isset($nodo['class_type'])) {
                    $nodos_faltantes[] = $nodo['class_type'] . " (ID: $id)";
                }
            }
        }

        // 2. Formateamos el mensaje usando estrictamente variables multi-idioma
        if (!empty($archivo_faltante)) {
            $err_msg = "<div style='text-align: left; font-size: 0.95em;'>"
                     . __('err_comfy_file_missing') . "<br><br>"
                     . "<div class='bg-dark text-warning border border-warning rounded p-2 mb-2' style='font-family: monospace; word-break: break-all;'>"
                     . __('lbl_file_detail') . "<b>" . htmlspecialchars($archivo_faltante) . "</b>"
                     . "</div>" . __('err_check_models_folder') . "</div>";
        } elseif (!empty($nodos_faltantes)) {
            $lista_nodos = implode('<br>⭐ ', array_unique($nodos_faltantes));
            $details = $res['error']['details'] ?? ($res['error']['message'] ?? '');
            $err_msg = "<div style='text-align: left; font-size: 0.95em;'>"
                     . __('err_missing_nodes_1') . "<br><br>"
                     . "<div class='bg-dark text-warning border border-secondary rounded p-2 mb-2' style='font-family: monospace;'>"
                     . __('lbl_missing_nodes') . $lista_nodos . "<br>" . __('lbl_error_detail') . htmlspecialchars($details)
                     . "</div>"
                     . __('err_missing_nodes_2')
                     . "</div>";
        } elseif (isset($res['error']['message'])) { 
            $details = $res['error']['details'] ?? '';
            $err_msg = "<div style='text-align: left; font-size: 0.95em;'>" . __('err_comfyui_reported') . ' <b>' . htmlspecialchars($res['error']['message']) . "</b>";
            if (!empty($details)) {
                $err_msg .= "<div class='bg-dark text-warning border border-secondary rounded p-2 mt-2 small' style='font-family: monospace;'>" . htmlspecialchars($details) . "</div>";
            }
            $err_msg .= "</div>";
        } elseif (curl_error($ch)) { 
            $err_msg = __('err_gpu_conn') . ' ' . curl_error($ch); 
        }
        
        echo json_encode(['error' => $err_msg]); 
        exit(); 
    }
    
    $prompt_id = $res['prompt_id'];
    
    // ====================================================================
    // --- PREPARAR METADATA Y GUARDAR ESTADO 'PENDIENTE' EN LA BD ---
    // ====================================================================
    $final_w = $width; 
    $final_h = $height;
    
    if ($hires_fix && ($aurasr_enabled || !empty($upscale_model))) { 
        if (!empty($target_w) && !empty($target_h)) {
            $final_w = $target_w;
            $final_h = $target_h;
        } else {
            $final_w = intval($width * $upscale_factor); 
            $final_h = intval($height * $upscale_factor); 
        }
    } elseif ($is_outpainting) {
        $final_w = "Auto (" . __('lbl_expanded') . ")"; 
        $final_h = "Auto (" . __('lbl_expanded') . ")";
    }

    $meta_json_array = [
        'Model' => basename($model_path), 
        'Resolution' => $final_w . 'x' . $final_h, 
        'Seed' => $seed, 
        'Steps' => $steps, 
        'CFG Scale' => $cfg, 
        'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')', 
        'Batch Size' => $batch_size, 
        'LoRAs' => empty($lora_metadata_list) ? __('lbl_none') : implode(', ', $lora_metadata_list)
    ];

    if ($flux_guidance !== null) $meta_json_array['Guidance (Flux)'] = $flux_guidance;

    if ($sampler_denoise < 1.0 && !$is_outpainting) {
        if (!empty($mask_data_base64)) { 
            $meta_json_array['Modo Inpainting'] = __('lbl_activated') . ' (Denoise: ' . $sampler_denoise . ')'; 
        } else { 
            $meta_json_array['Fuerza de Edición'] = $sampler_denoise . ' (Image-to-Image)'; 
        }
    }
    
    if ($is_outpainting) {
        $meta_json_array['Modo Outpaint'] = __('lbl_up') . ":$out_top, " . __('lbl_down') . ":$out_bottom, " . __('lbl_left') . ":$out_left, " . __('lbl_right') . ":$out_right";
    }
    
    if ($hires_fix) { 
        if ($aurasr_enabled) {
            $meta_json_array['Hi-Res Fix'] = 'AuraSR GigaGAN (' . $upscale_factor . 'x)';
        } elseif (!empty($upscale_model)) {
            $meta_json_array['Hi-Res Fix'] = 'Tiled Upscale (' . $upscale_factor . 'x, ' . basename($upscale_model) . ')';
        }
    }

    if ($reactor_enabled === 'true' && !empty($reactor_image_base64)) $meta_json_array['Face Swap (ReActor)'] = __('lbl_activated'); 
    if ($ipadapter_enabled === 'true' && !empty($ipadapter_image_base64)) $meta_json_array['IP-Adapter'] = __('lbl_activated'); 
    if ($controlnet_enabled === 'true' && !empty($controlnet_model)) $meta_json_array['ControlNet'] = basename($controlnet_model);
    if ($remove_background) $meta_json_array['Fondo Transparente'] = __('lbl_activated') . ' (Rembg)';
    if ($ddcolor_enabled) $meta_json_array['Coloreado Neural'] = __('lbl_activated') . ' (DDColor: ' . basename($ddcolor_model) . ')';
    if ($iclight_enabled) $meta_json_array['IC-Light (Relighting)'] = __('lbl_activated') . ' (' . $iclight_direction . ')';

    if (isset($user_shift) && $user_shift !== null) {
        $meta_json_array['Flow Shift'] = $user_shift;
		}
    $meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

    $desc_original = $_POST['descripcion_original'] ?? __('lbl_direct_gen_edit');
    $safe_desc_direct = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $desc_original);

    if ($historial_id > 0) {
        $stmt_check = $pdo->prepare("SELECT imagen_path, descripcion_original FROM historial_prompts WHERE id = ?");
        $stmt_check->execute([$historial_id]);
        $row_hist = $stmt_check->fetch();

        if ($row_hist) {
            if (!empty($row_hist['imagen_path'])) {
                $safe_desc = $row_hist['descripcion_original'] ?? $safe_desc_direct;
                $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_ins->execute([$user_id, $selector, $safe_desc, $posPrompt, $neg_prompt, $meta_json]);
                $historial_id = $pdo->lastInsertId();
            } else {
                $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET prompt_positivo = ?, prompt_negativo = ?, metadata = ? WHERE id = ?");
                $stmt_upd->execute([$posPrompt, $neg_prompt, $meta_json, $historial_id]);
            }
        } else {
            $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_ins->execute([$user_id, $selector, $safe_desc_direct, $posPrompt, $neg_prompt, $meta_json]);
            $historial_id = $pdo->lastInsertId();
        }
    } else {
        $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_ins->execute([$user_id, $selector, $safe_desc_direct, $posPrompt, $neg_prompt, $meta_json]);
        $historial_id = $pdo->lastInsertId();
    }

    // ====================================================================
    // --- NUEVO: GUARDAR WORKFLOW JSON (Infalible para Asíncrono) ---
    // ====================================================================
    if ($historial_id > 0) {
        $galeria_dir = __DIR__ . '/../galeria';
        if (!is_dir($galeria_dir)) @mkdir($galeria_dir, 0777, true);
        @file_put_contents($galeria_dir . '/workflow_' . $historial_id . '.json', json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    // ====================================================================

   // ====================================================================
    // --- 2. INTERCEPTOR MODO ASÍNCRONO (TICKETS Y ÁNGEL) ---
    // ====================================================================
    if (isset($_POST['async_mode']) && $_POST['async_mode'] === 'true') {
        
        $url_angel = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'];
        $post_fields = http_build_query([
            'action' => 'angel_guardia',
            'prompt_id' => $prompt_id,
            'historial_id' => $historial_id,
            'user_id' => $user_id
        ]);
        
        $ch_angel = curl_init();
        curl_setopt($ch_angel, CURLOPT_URL, $url_angel);
        curl_setopt($ch_angel, CURLOPT_POST, true);
        curl_setopt($ch_angel, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch_angel, CURLOPT_TIMEOUT, 1); 
        curl_setopt($ch_angel, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch_angel, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_angel, CURLOPT_COOKIE, session_name() . '=' . session_id()); 
        @curl_exec($ch_angel);
        
        echo json_encode(['status' => 'ticket_issued', 'prompt_id' => $prompt_id, 'historial_id' => $historial_id]);
        exit();
    }

    // --- GUARDADO DE IMÁGENES (SINCRÓNICO) ---
    $final_base64_responses = [];
    $filenames_for_db = []; 
    $is_first_image = true;

    $fetched_images_raw = [];
    $max_retries = 600; 
    for ($i = 0; $i < $max_retries; $i++) {
        sleep(2);
        $ch_hist = curl_init(COMFY_URL . '/history/' . $prompt_id);
        curl_setopt($ch_hist, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_hist, CURLOPT_TIMEOUT, 5);
        $res_hist = curl_exec($ch_hist);
        
        $history = json_decode($res_hist, true);
        
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
                
                // Formateamos el error para que tu JS (core.js/herramientas.js) lo atrape y dispare tu SwalDark
                $err_msg = "<b>" . __('err_gen_node_fail') . "</b><br>"
                         . "<span class='badge bg-danger mt-2 mb-1'>Nodo: " . htmlspecialchars($node_fail) . "</span><br>"
                         . "<span class='text-warning small'>" . htmlspecialchars($exception) . "</span>";

                echo json_encode(['error' => $err_msg]);
                exit();
            }

            $outputs = $history[$prompt_id]['outputs'] ?? [];
            
            // 🔍 RAYOS X: Guardamos la respuesta real del historial para auditar qué envía ComfyUI
            file_put_contents(__DIR__ . '/../debug_comfy.txt', json_encode($history[$prompt_id], JSON_PRETTY_PRINT));

            foreach ($outputs as $node_id => $output) {
                $files = [];
                
                // 1. ESCÁNER TOTAL (Caza arrays estándar, listas de strings y nombres sueltos)
                if (is_array($output)) {
                    // Caso A: El nodo devuelve directamente el array del archivo {"filename": "..."}
                    if (!empty($output['filename'])) {
                        $files[] = $output;
                    } else {
                        // Caso B: El nodo devuelve listas bajo claves como "images", "audio", "files", etc.
                        foreach ($output as $key => $file_list) {
                            if (is_array($file_list)) {
                                foreach ($file_list as $item) {
                                    if (is_array($item) && !empty($item['filename'])) {
                                        // Formato estándar de ComfyUI (ej. SaveImage)
                                        $files[] = $item;
                                    } elseif (is_string($item) && preg_match('/\.(wav|mp3|flac|ogg|m4a|png|jpg|webp)$/i', $item)) {
                                        // Formato simplificado (nodos de audio que devuelven solo el nombre en string)
                                        $files[] = ['filename' => $item, 'subfolder' => '', 'type' => 'output'];
                                    }
                                }
                            } elseif (is_string($file_list) && preg_match('/\.(wav|mp3|flac|ogg|m4a|png|jpg|webp)$/i', $file_list)) {
                                // Formato directo ("audio": "mi_archivo.wav")
                                $files[] = ['filename' => $file_list, 'subfolder' => '', 'type' => 'output'];
                            }
                        }
                    }
                }
                
                foreach ($files as $file_info) {
                    $filename = $file_info['filename'] ?? '';
                    $subfolder = $file_info['subfolder'] ?? '';
                    $type = $file_info['type'] ?? 'output';
                    if (empty($filename)) continue;
                    
                    $file_url = COMFY_URL . '/view?filename=' . urlencode($filename) . '&subfolder=' . urlencode($subfolder) . '&type=' . urlencode($type);
                    $ch_file = curl_init($file_url);
                    curl_setopt($ch_file, CURLOPT_RETURNTRANSFER, true);
                    $file_data = curl_exec($ch_file);
                    
                    if ($file_data) {
                        $ext = pathinfo($filename, PATHINFO_EXTENSION);
                        if (empty($ext)) $ext = 'png';
                        $fetched_images_raw[] = ['data' => $file_data, 'ext' => $ext];
                    }
                }
            }
            break; 
        }
    }

    foreach ($fetched_images_raw as $index => $item) { 
        $img_binary = $item['data'];
        $ext = $item['ext'];
        
        // --- NUEVO: MOTOR DE CONVERSIÓN DE FORMATOS (PNG -> WEBP/JPG) ---
        $formato_salida = $_POST['image_format'] ?? 'png';
        
        // 🛡️ PROTECCIÓN INTELIGENTE: Si el usuario quitó el fondo (Rembg) y eligió JPG, 
        // lo pasamos a WebP automáticamente para que no pierda la transparencia.
        if (isset($remove_background) && $remove_background && $formato_salida === 'jpg') {
            $formato_salida = 'webp';
        }

        // Si el usuario eligió algo distinto a PNG, convertimos la imagen al vuelo
        if ($formato_salida !== 'png' && !empty($img_binary)) {
            $im = @imagecreatefromstring($img_binary);
            if ($im !== false) {
                ob_start(); // Encendemos el buffer de salida para atrapar el nuevo archivo
                
                if ($formato_salida === 'webp') {
                    imagesavealpha($im, true); // Preservar transparencias
                    imagewebp($im, null, 90);  // 90% de calidad (excelente compresión/calidad)
                } elseif ($formato_salida === 'jpg') {
                    // El JPG odia las transparencias. Le ponemos un fondo blanco "por si acaso" 
                    // venía algún pixel transparente para evitar artefactos negros.
                    $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
                    $blanco = imagecolorallocate($bg, 255, 255, 255);
                    imagefill($bg, 0, 0, $blanco);
                    imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
                    imagejpeg($bg, null, 90); // 90% de calidad
                    imagedestroy($bg);
                    $im = $bg; // Truco técnico para el imagedestroy final
                }
                
                $img_convertida = ob_get_clean(); // Apagamos el buffer y nos guardamos la imagen
                imagedestroy($im); // Liberamos la memoria RAM del servidor
                
                if (!empty($img_convertida)) {
                    $img_binary = $img_convertida; // Sustituimos el original
                    $ext = $formato_salida;        // Le cambiamos la extensión al archivo
                }
            }
        }
        // ------------------------------------------------------------------

        // Bloque unificado y limpio
        $meta_json_array = [
            'Model' => basename($model_path), 
            'Resolution' => $final_w . 'x' . $final_h, 
            'Seed' => $seed, 
            'Steps' => $steps, 
            'CFG Scale' => $cfg, 
            'Sampler' => ucfirst($sampler) . ' (' . ucfirst($scheduler) . ')', 
            'Batch Index' => ($index + 1) . ' / ' . $batch_size, 
            'LoRAs' => empty($lora_metadata_list) ? __('lbl_none') : implode(', ', $lora_metadata_list)
        ];

        if ($flux_guidance !== null) $meta_json_array['Guidance (Flux)'] = $flux_guidance;

        if ($sampler_denoise < 1.0 && !$is_outpainting) {
            if (!empty($mask_data_base64)) { 
                $meta_json_array['Modo Inpainting'] = __('lbl_activated') . ' (Denoise: ' . $sampler_denoise . ')'; 
            } else { 
                $meta_json_array['Fuerza de Edición'] = $sampler_denoise . ' (Image-to-Image)'; 
            }
        }
        
        if ($is_outpainting) {
            $meta_json_array['Modo Outpaint'] = __('lbl_up') . ":$out_top, " . __('lbl_down') . ":$out_bottom, " . __('lbl_left') . ":$out_left, " . __('lbl_right') . ":$out_right";
        }
        
        if ($hires_fix) { 
            if ($aurasr_enabled) {
                $meta_json_array['Hi-Res Fix'] = 'AuraSR GigaGAN (' . $upscale_factor . 'x)';
            } elseif (!empty($upscale_model)) {
                $meta_json_array['Hi-Res Fix'] = 'Tiled Upscale (' . $upscale_factor . 'x, ' . basename($upscale_model) . ')';
            }
        }
        
        if ($reactor_enabled === 'true' && !empty($reactor_image_base64)) { $meta_json_array['Face Swap (ReActor)'] = __('lbl_activated'); }
        if ($ipadapter_enabled === 'true' && !empty($ipadapter_image_base64)) { $meta_json_array['IP-Adapter (Estilo)'] = __('lbl_activated') . ' (' . __('lbl_strength') . ': ' . $ipa_weight . ')'; }
        if ($controlnet_enabled === 'true' && !empty($controlnet_model)) {
            $meta_json_array['ControlNet'] = basename($controlnet_model) . ' (' . $controlnet_preprocessor . ', ' . __('lbl_strength') . ': ' . $controlnet_weight . ')';
        }
        if ($remove_background) {
            $meta_json_array['Fondo Transparente'] = __('lbl_activated') . ' (Rembg)';
        }
        if ($ddcolor_enabled) {
            $meta_json_array['Coloreado Neural'] = __('lbl_activated') . ' (DDColor: ' . basename($ddcolor_model) . ')';
        }
        if ($iclight_enabled) {
            $meta_json_array['IC-Light (Relighting)'] = __('lbl_activated') . ' (' . $iclight_direction . ')';
        }

        if (isset($user_shift) && $user_shift !== null) {
            $meta_json_array['Flow Shift'] = $user_shift;
        }
        $meta_json = json_encode($meta_json_array, JSON_UNESCAPED_UNICODE);

        if ($historial_id > 0) {
            $galeria_dir = __DIR__ . '/../galeria';
            if (!is_dir($galeria_dir)) @mkdir($galeria_dir, 0777, true);
            if (is_dir($galeria_dir)) {
                $filename = 'img_' . $historial_id . '_' . mt_rand(1000, 9999) . '_' . time() . '_' . $index . '.' . $ext;
                if (@file_put_contents($galeria_dir . '/' . $filename, $img_binary)) {
                    
                    // --- NUEVO: GUARDAR WORKFLOW JSON CON EL MISMO NOMBRE EXACTO ---
                    $ruta_json = $galeria_dir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '.json';
                    @file_put_contents($ruta_json, json_encode($workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    // ---------------------------------------------------------------
                    
                    $stmt_check = $pdo->prepare("SELECT imagen_path, user_id, modelo, descripcion_original, prompt_negativo FROM historial_prompts WHERE id = ?");
                    $stmt_check->execute([$historial_id]);
                    $row = $stmt_check->fetch();
                    if ($row) {
                        $safe_desc = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$row['descripcion_original']);
                        $safe_pos = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$posPrompt);
                        $safe_neg = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$row['prompt_negativo']);

                        if ($is_first_image && empty($row['imagen_path'])) {
                            $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET imagen_path = ?, prompt_positivo = ?, metadata = ? WHERE id = ?");
                            $stmt_upd->execute([$filename, $safe_pos, $meta_json, $historial_id]);
                        } else {
                            $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt_ins->execute([$row['user_id'], $row['modelo'], $safe_desc, $safe_pos, $safe_neg, $filename, $meta_json]);
                        }
                    }
                    $is_first_image = false;
                }
            }
        }
        $final_base64_responses[] = base64_encode($img_binary);
        $filenames_for_db[] = $filename ?? '';
    }
	
	echo json_encode(['status' => 'completed', 'images' => $final_base64_responses, 'filenames' => $filenames_for_db]);
    exit();
}
?>