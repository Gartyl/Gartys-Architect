<?php
// ==============================================================================
// --- API AUDIO: CONTROLADOR DE F5-TTS Y STABLE AUDIO OPEN ---
// ==============================================================================
if (!defined('ABSPATH') && !isset($_SESSION['user_id'])) {
    exit(__('err_access_denied') ?? 'Acceso denegado');
}

// Validación estricta de seguridad: Solo Pro o Admin pueden usar este módulo
if ($user_rol !== 'pro' && !$is_admin) {
    echo json_encode(['error' => __('err_pro_only') ?? 'Módulo exclusivo para usuarios Pro.']);
    exit();
}

$comfy_server = $config['comfy_url'] ?? 'http://127.0.0.1:8188';

switch ($action) {
    
    // --------------------------------------------------------------------------
    // 1. SUBIR MUESTRA DE VOZ A COMFYUI (/input)
    // --------------------------------------------------------------------------
    case 'subir_audio_referencia':
        if (!isset($_FILES['audio_ref']) || $_FILES['audio_ref']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['error' => __('err_upload_failed') ?? 'Error al subir el archivo de audio.']);
            exit();
        }

        $file_tmp  = $_FILES['audio_ref']['tmp_name'];
        $file_name = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $_FILES['audio_ref']['name']);
        $file_type = $_FILES['audio_ref']['type'];

        // ComfyUI usa el endpoint /upload/image también para archivos de audio y vídeo
        $target_url = rtrim($comfy_server, '/') . '/upload/image';

        if (function_exists('curl_file_create')) {
            $cFile = curl_file_create($file_tmp, $file_type, $file_name);
        } else {
            $cFile = '@' . realpath($file_tmp);
        }

        $post_fields = [
            'image' => $cFile, // El parámetro en la API nativa de ComfyUI siempre se llama 'image'
            'type'  => 'input',
            'overwrite' => 'true'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $data = json_decode($response, true);
            echo json_encode([
                'success' => true,
                'file_name' => $data['name'] ?? $file_name,
                'subfolder' => $data['subfolder'] ?? '',
                'type' => $data['type'] ?? 'input'
            ]);
        } else {
            echo json_encode(['error' => (__('err_comfy_upload') ?? 'Error de comunicación con ComfyUI al subir audio. HTTP: ') . $http_code]);
        }
        break;

    // --------------------------------------------------------------------------
    // 2. PREPARAR O GENERAR WORKFLOW DE AUDIO (MULTI-MOTOR / STABLE AUDIO)
    // --------------------------------------------------------------------------
    case 'generar_audio':
        $engine = $_POST['engine'] ?? 'tts'; // 'tts' o 'sfx'
        $prompt_text = trim($_POST['prompt_text'] ?? '');
        
        // --- NUEVO: CAPTURA DE IDIOMA Y FILTRO BPE ---
        $idioma_tts = $_POST['tts_language'] ?? 'Spanish';
        $acentos_mayus = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ä', 'Ë', 'Ï', 'Ö', 'Ü'];
        $acentos_minus = ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ä', 'ë', 'ï', 'ö', 'ü'];
        $prompt_text = str_replace($acentos_mayus, $acentos_minus, $prompt_text);
        // ---------------------------------------------

        if (empty($prompt_text)) {
            echo json_encode(['error' => __('err_empty_prompt') ?? 'El prompt o texto está vacío.']);
            exit();
        }

        $workflow = [];
        $output_port = 0; // 🛠️ CORRECCIÓN 1: Lo sacamos aquí arriba para que SFX lo tenga

        if ($engine === 'tts') {
            $tts_engine = $_POST['tts_engine'] ?? 'f5';
            $ref_file = $_POST['ref_file'] ?? '';
            
            // 🛡️ EXCEPCIÓN VIP PARA OMNIVOICE: Solo exigimos audio si NO es OmniVoice
            if (empty($ref_file) && $tts_engine !== 'omnivoice') {
                echo json_encode(['error' => __('err_missing_ref_audio') ?? 'Falta el archivo de referencia de voz para clonar.']);
                exit();
            }

            if ($tts_engine === 'f5') {
                // --- MOTOR 1: F5-TTS (Legacy / Zombi) ---
                $speed_ui = floatval($_POST['speed'] ?? 1.0);
                $silence  = ($_POST['remove_silence'] ?? '1') === '1' || $_POST['remove_silence'] === 'true';
                $speed_real = ($speed_ui > 0.1) ? (1.0 / $speed_ui) : 1.0;

                $workflow['10'] = ['inputs' => ['audio' => $ref_file], 'class_type' => 'LoadAudio'];
                $workflow['11'] = [
                    'inputs' => [
                        'sample_audio' => ['10', 0], 'sample_text' => $ref_text, 'speech' => $prompt_text,
                        'speed' => $speed_real, 'seed' => mt_rand(1, 2147483647), 'model' => 'F5v1',
                        'vocoder' => 'auto', 'model_type' => 'F5TTS_v1_Base', 'remove_silence' => $silence
                    ], 'class_type' => 'F5TTSAudioInputs'
                ];
                $output_node = '11';

            } elseif ($tts_engine === 'indextts') {
                // --- MOTOR 2: INDEXTTS-2 (Clonación Pro) ---
                $emocion = $_POST['tts_emotion'] ?? 'calm';
                $workflow['125'] = [
                    'inputs' => [
                        'Happy' => ($emocion === 'happy') ? 0.85 : 0.1, 'Angry' => ($emocion === 'angry') ? 0.85 : 0.1,
                        'Sad' => ($emocion === 'sad') ? 0.85 : 0.1, 'Calm' => ($emocion === 'calm') ? 0.85 : 0.1,
                        'Surprised' => 0.1, 'Afraid' => 0.1, 'Disgusted' => 0.1, 'Melancholic' => 0.1, 'emotion_radar_canvas' => ""
                    ], 'class_type' => 'IndexTTSEmotionOptionsNode' // 🛠️ CORRECCIÓN 2: Le quitamos la doble 'E'
                ];
                $workflow['65'] = ['inputs' => ['value' => $prompt_text], 'class_type' => 'PrimitiveStringMultiline'];
                
                $workflow['134'] = ['inputs' => ['audio' => $ref_file], 'class_type' => 'LoadAudio'];
                $workflow['130'] = ['inputs' => ['voice_name' => 'none', 'reference_text' => $ref_text, 'trim_start' => 0, 'trim_end' => 0, 'customized' => true, 'opt_audio_input' => ['134', 0]], 'class_type' => 'CharacterVoicesNode'];
                
                $workflow['123'] = [
                    'inputs' => [
                        'language' => $idioma_tts, // Mantenemos la inyección por si acaso
                        'model_path' => 'IndexTTS-2', 'device' => 'auto', 'emotion_alpha' => 0.7, 'use_random' => false,
                        'max_text_tokens_per_segment' => 120, 'interval_silence' => 200, 'temperature' => 0.8, 'top_p' => 0.8,
                        'top_k' => 30, 'do_sample' => true, 'length_penalty' => 0, 'num_beams' => 3, 'repetition_penalty' => 9.5,
                        'max_mel_tokens' => 1500, 'use_fp16' => true, 'use_deepspeed' => true, 'use_cuda_kernel' => 'auto',
                        'use_torch_compile' => false, 'use_accel' => false, 'stream_return' => false, 'more_segment_before' => 0, 'low_vram' => false,
                        'emotion_control' => ['125', 0]
                    ], 'class_type' => 'IndexTTSEngineNode'
                ];
                $workflow['47'] = [
                    'inputs' => [
                        'text' => ['65', 0], 'narrator_voice' => 'none', 'seed' => mt_rand(1, 2147483647), 'enable_chunking' => true,
                        'max_chars_per_chunk' => 400, 'chunk_combination_method' => 'auto', 'silence_between_chunks_ms' => 100,
                        'enable_audio_cache' => true, 'batch_size' => 0, 'TTS_engine' => ['123', 0], 'opt_narrator' => ['130', 0]
                    ], 'class_type' => 'UnifiedTTSTextNode'
                ];
                $output_node = '47';

            } else {
                // --- MOTOR 3: OMNIVOICE (Diseño Zero-Shot) ---
                $emocion = $_POST['tts_emotion'] ?? 'normal';
                
                $genero = $_POST['tts_gender'] ?? 'male';
                $edad = $_POST['tts_age'] ?? 'None';

                // 🛠️ CORRECCIÓN 3: Traducción estricta para el nodo de Python
                $omni_style = ($emocion === 'whisper') ? 'whisper' : 'None';
                $omni_lang  = ($idioma_tts === 'Chinese') ? 'Chinese' : 'English';

                $workflow['25'] = [
                    'inputs' => [
                        'gender' => $genero, 'age' => $edad, 'pitch' => 'None',
                        'style' => $omni_style, 'accent' => 'None', 'dialect' => 'None',
                        'output_language' => $omni_lang, 'instruct_text' => ''
                    ], 'class_type' => 'OmniVoiceInstructionBuilderNode'
                ];

                $workflow['20'] = [
                    'inputs' => [
                        'model_variant' => 'OmniVoice', 'device' => 'auto', 'language' => 'Auto',
                        'num_step' => 32, 'guidance_scale' => 2, 't_shift' => 0.1, 'speed' => 1.0,
                        'duration' => 0, 'dtype' => 'auto', 'instruct' => '', 'layer_penalty_factor' => 5,
                        'position_temperature' => 5, 'class_temperature' => 0, 'denoise' => true,
                        'preprocess_prompt' => true, 'postprocess_output' => true, 'audio_chunk_duration' => 15,
                        'audio_chunk_threshold' => 30, 'mode' => 'Voice Design'
                    ], 'class_type' => 'OmniVoiceEngineNode'
                ];

                $workflow['17'] = [
                    'inputs' => [
                        'reference_text' => $prompt_text, 'seed' => mt_rand(1, 2147483647),
                        'voice_instruction' => ['25', 0], 'TTS_engine' => ['20', 0]
                    ], 'class_type' => 'UnifiedVoiceDesignerNode'
                ];
                
                $output_node = '17';
                $output_port = 1; // ⚠️ OmniVoice usa el puerto 1
            }
        } else {
            // ESTRUCTURA REAL PARA STABLE AUDIO OPEN 1.0 (SFX / MÚSICA)
            $seconds = floatval($_POST['seconds'] ?? 5.0);
            $steps = 20; 
            $cfg   = 4.0; 

            $calidad_ui = $_POST['quality'] ?? 'high';
            if ($calidad_ui === 'fast')      { $steps = 18; $cfg = 3.8; }
            if ($calidad_ui === 'cinematic') { $steps = 24; $cfg = 4.5; }

            $workflow['20'] = ['inputs' => ['ckpt_name' => 'stable-audio-open-1.0.safetensors'], 'class_type' => 'CheckpointLoaderSimple'];
            $workflow['20_clip'] = ['inputs' => ['clip_name' => 't5-base.safetensors', 'type' => 'stable_audio'], 'class_type' => 'CLIPLoader'];
            $workflow['21'] = ['inputs' => ['seconds' => $seconds, 'batch_size' => 1], 'class_type' => 'EmptyLatentAudio'];
            $workflow['22_pos'] = ['inputs' => ['text' => $prompt_text, 'clip' => ['20_clip', 0]], 'class_type' => 'CLIPTextEncode'];
            $workflow['22_neg'] = ['inputs' => ['text' => '', 'clip' => ['20_clip', 0]], 'class_type' => 'CLIPTextEncode'];
            $workflow['23'] = [
                'inputs' => [
                    'seed' => mt_rand(1, 2147483647), 'steps' => $steps, 'cfg' => $cfg, 'sampler_name' => 'euler', 'scheduler' => 'normal',
                    'denoise' => 1.0, 'model' => ['20', 0], 'positive' => ['22_pos', 0], 'negative' => ['22_neg', 0], 'latent_image' => ['21', 0]
                ], 'class_type' => 'KSampler'
            ];
            $workflow['24'] = ['inputs' => ['samples' => ['23', 0], 'vae' => ['20', 2]], 'class_type' => 'VAEDecodeAudio'];
            
            $output_node = '24';
        }

        // Si es una generación de audio autónoma (sin vídeo), añadimos el nodo de guardado
        if (($_POST['standalone'] ?? '0') === '1') {
            $workflow['99'] = [
                'inputs' => [
                    'filename_prefix' => 'Garty_Audio_' . strtoupper($engine),
                    'audio' => [$output_node, $output_port] // <--- AQUÍ
                ],
                'class_type' => 'SaveAudio',
                '_meta' => ['title' => 'Save Output Audio']
            ];

            // Enviar a ComfyUI /prompt via cURL
            $payload = json_encode(['prompt' => $workflow, 'client_id' => session_id()]);
            
            $ch = curl_init(rtrim($comfy_server, '/') . '/prompt');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                echo json_encode(['error' => (__('err_curl_comfy') ?? 'Error cURL hacia ComfyUI: ') . $err]);
            } else {
                $comfy_data = json_decode($res, true);
                
                // 🛠️ CORRECCIÓN 4: Si ComfyUI rechaza el audio (ej. por culpa de F5), lo mostramos en pantalla
                if (isset($comfy_data['error'])) {
                    echo json_encode(['error' => 'ComfyUI rechazó el audio: ' . ($comfy_data['error']['message'] ?? 'Error de nodo.')]);
                    exit();
                }

                $prompt_id = $comfy_data['prompt_id'] ?? '';
                
                // 1. Creamos el ticket oficial en la BD con imagen_path en NULL para que el radar vigile el audio
                $stmt = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, imagen_path, metadata) VALUES (?, ?, ?, ?, ?, NULL, ?)");
                $stmt->execute([
                    $_SESSION['user_id'] ?? 1, 
                    'Audio ' . strtoupper($engine ?? 'F5-TTS'), 
                    $prompt_text ?? '', 
                    $prompt_text ?? '', 
                    '', 
                    json_encode(['engine' => $engine ?? 'audio', 'prompt_id' => $prompt_id])
                ]);
                
                $historial_id = $pdo->lastInsertId();

                // 2. Devolvemos el JSON enriquecido para que el frontend tenga su ID
                echo json_encode([
                    'success' => true, 
                    'prompt_id' => $prompt_id,
                    'historial_id' => $historial_id,
                    'comfy_response' => $comfy_data
                ]);
            }
        } else {
            // Si no es standalone, devolvemos el sub-workflow para que core.js/video.js lo ensamble con VHS_VideoCombine
            echo json_encode([
                'success' => true,
                'sub_workflow' => $workflow,
                'output_node_id' => $output_node
            ]);
        }
        break;

    default:
        echo json_encode(['error' => __('err_unknown_audio_action') ?? 'Acción de audio no reconocida.']);
        break;
}
?>