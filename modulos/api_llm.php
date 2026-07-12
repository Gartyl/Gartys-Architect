<?php
/*
 * ==============================================================================
 * --- MÓDULO LLM (Incluido desde procesar.php) ---
 * --- Maneja la Ejecución Directa de Chat y el Arquitecto IA ---
 * ==============================================================================
 */

// ==============================================================================
// --- 1. ACCIÓN: EJECUTAR LLM (Ejecución Directa / Redacción) ---
// ==============================================================================
if (isset($_POST['ejecutar_llm']) && $_POST['ejecutar_llm'] === 'true') {
    try {
        $prompt_id = intval($_POST['prompt_id'] ?? 0); 
        $modelo_recibido = !empty($_POST['llm_model']) ? $_POST['llm_model'] : '';
        
        if (empty($modelo_recibido)) {
            $stmt_llm = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND categoria = 'SYS_LLM' AND activo = 1 LIMIT 1");
            $modelo_recibido = $stmt_llm->fetchColumn() ?: ($pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND activo = 1 LIMIT 1")->fetchColumn());
        }
        
        if (empty($modelo_recibido)) { echo json_encode(['error' => __('err_no_llm_selected')]); exit(); }
        
        $info_modelo = obtener_info_modelo($modelo_recibido, $pdo, 'ollama');
        $llm_model_selected = $info_modelo['nombre_archivo'];
        
        $prompt_final = trim($_POST['prompt_final'] ?? '');
        $descripcion_original = $_POST['descripcion_original'] ?? $prompt_final;
        $document_text = $_POST['document_text'] ?? null;
        $image_data = $_POST['image_data'] ?? null;
        
        $stmtSys = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE tipo = 'sys_prompt_chat' AND idioma = ? AND activo = 1 LIMIT 1");
        $stmtSys->execute([$lang]);
        $rowSys = $stmtSys->fetch(PDO::FETCH_ASSOC);

        $sys_prompt = $rowSys ? $rowSys['prompt_texto'] : "You are a helpful and conversational AI assistant. Your default language is [" . strtoupper($lang) . "]. However, if the user speaks to you in another language, you must reply naturally in that same language.";
        $temp_llm = 0.7;

        if ($rowSys && !empty($rowSys['parametros'])) {
            $json_params = json_decode($rowSys['parametros'], true);
            if (isset($json_params['temperature'])) $temp_llm = (float)$json_params['temperature'];
        }

        $messages = [["role" => "system", "content" => $sys_prompt]];

        if (!empty($image_data)) {
            $base64_clean = preg_replace('#^data:image/[^;]+;base64,#', '', $image_data);
            $user_content = empty($prompt_final) ? __('cmd_analyze_image') : $prompt_final;
            $messages[] = ["role" => "user", "content" => $user_content, "images" => [$base64_clean]];
            $descripcion_original = "[" . __('lbl_attached_image') . "] " . $descripcion_original;
        } elseif (!empty($document_text)) {
            $instruction = empty($prompt_final) ? __('cmd_analyze_doc') : $prompt_final;
            $full_text = __('lbl_doc_content') . ":\n\n---\n" . $document_text . "\n---\n\n" . __('lbl_user_instruction') . ": " . $instruction;
            $messages[] = ["role" => "user", "content" => $full_text]; 
            $descripcion_original = "[" . __('lbl_attached_doc') . "] " . $descripcion_original;
        } else {
            $messages[] = ["role" => "user", "content" => $prompt_final];
        }

        $temp_segura = ($temp_llm < 0.4) ? 0.7 : $temp_llm;
        
        // Comprobamos si el Frontend nos está pidiendo Streaming
        $is_stream = isset($_POST['stream']) && $_POST['stream'] === 'true';

        $payload = [
            "model" => $llm_model_selected,
            "messages" => $messages, 
            "stream" => $is_stream, 
            "keep_alive" => "10m",
            "options" => [
                "temperature" => $temp_segura,
                "num_ctx" => 8192
            ]
        ];
        
        session_write_close();

        $ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/chat");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $full_generated_text = "";
        $buffer = "";

        // --- LÓGICA DE STREAMING (FASE 2) ---
        if ($is_stream) {
            // Anulamos el buffer de salida de PHP para que escupa los datos instantáneamente
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/x-ndjson');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');

            // Interceptamos los datos a medida que Ollama los genera
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$buffer, &$full_generated_text) {
                $buffer .= $data;
                // Leemos línea a línea
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);
                    
                    if (empty($line)) continue;

                    $decoded = json_decode($line, true);
                    if ($decoded && isset($decoded['message'])) {
                        $raw_content = $decoded['message']['content'] ?? '';
                        $raw_thinking = $decoded['message']['thinking'] ?? '';
                        
                        // Fix Magistral para Qwen al vuelo
                        $chunk = (empty($raw_content) && !empty($raw_thinking)) ? $raw_thinking : $raw_content;
                        $full_generated_text .= $chunk;

                        $decoded['message']['content'] = $chunk;
                        unset($decoded['message']['thinking']);
                        
                        // Enviamos el trozo al navegador web
                        echo json_encode($decoded, JSON_UNESCAPED_UNICODE) . "\n";
                    } else {
                        echo $line . "\n";
                    }
                    // Forzamos al servidor a enviar el paquete
                    flush();
                }
                return strlen($data);
            });
        }

        $api_res = curl_exec($ch);

        if ($api_res === false) { 
            $err_msg = json_encode(['error' => __('err_llm_unresponsive') . ' (cURL: ' . curl_error($ch) . ')']);
            if ($is_stream) { echo $err_msg . "\n"; flush(); exit(); }
            else { echo $err_msg; exit(); }
        }

        // --- FINALIZACIÓN DEL STREAMING Y GUARDADO EN BBDD ---
        if ($is_stream) {
            $clean = preg_replace('/<think>.*?<\/think>/is', '', $full_generated_text);
            $final_text = trim(preg_replace('/<think>.*/is', '', $clean ?? $full_generated_text));
            if (empty($final_text) && !empty(trim($full_generated_text))) $final_text = trim($full_generated_text);

            $safe_desc = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $descripcion_original);
            $safe_pos = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $prompt_final);
            $new_prompt_id = $prompt_id;

            if (!empty($final_text)) {
                if ($prompt_id > 0) {
                    $stmt_check = $pdo->prepare("SELECT texto_generado, user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata FROM historial_prompts WHERE id = ?");
                    $stmt_check->execute([$prompt_id]);
                    $row_hist = $stmt_check->fetch();

                    if ($row_hist) {
                        if (!empty($row_hist['texto_generado'])) {
                            $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata, texto_generado) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt_ins->execute([$row_hist['user_id'], $row_hist['modelo'], $row_hist['descripcion_original'], $row_hist['prompt_positivo'], $row_hist['prompt_negativo'], $row_hist['metadata'], $final_text]);
                            $new_prompt_id = $pdo->lastInsertId();
                        } else {
                            $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET texto_generado = ? WHERE id = ?");
                            $stmt_upd->execute([$final_text, $prompt_id]);
                        }
                    }
                } else {
                    $meta_json = json_encode(['Model' => basename($llm_model_selected), 'Modo' => __('lbl_mode_direct')], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata, texto_generado) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_ins->execute([$_SESSION['user_id'], '[LLM]', $safe_desc, $safe_pos, '', $meta_json, $final_text]);
                    $new_prompt_id = $pdo->lastInsertId();
                }
            }
            
            // Le mandamos el ID final al navegador como último paquete
            echo json_encode(['new_prompt_id' => $new_prompt_id]) . "\n";
            flush();
            exit();

        } else {
            // --- LÓGICA ORIGINAL POR BLOQUES (Para Retrocompatibilidad) ---
            $res_ollama = json_decode($api_res, true);
            
            if (isset($res_ollama['message'])) {
                $raw_content = $res_ollama['message']['content'] ?? '';
                $raw_thinking = $res_ollama['message']['thinking'] ?? '';
                
                $raw = (empty(trim($raw_content)) && !empty(trim($raw_thinking))) ? $raw_thinking : $raw_content;

                $clean = preg_replace('/<think>.*?<\/think>/is', '', $raw);
                $final_text = trim(preg_replace('/<think>.*/is', '', $clean ?? $raw));
                
                if (empty($final_text) && !empty(trim($raw))) $final_text = trim($raw);

                if (empty($final_text)) {
                    echo json_encode(['error' => 'El modelo devolvió una respuesta vacía real. Revisa debug_ollama.txt']);
                    exit();
                }

                $res = ['choices' => [['message' => ['content' => $final_text]]]];
                $safe_desc = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $descripcion_original);
                $safe_pos = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $prompt_final);

                if ($prompt_id > 0) {
                    $stmt_check = $pdo->prepare("SELECT texto_generado, user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata FROM historial_prompts WHERE id = ?");
                    $stmt_check->execute([$prompt_id]);
                    $row_hist = $stmt_check->fetch();

                    if ($row_hist) {
                        if (!empty($row_hist['texto_generado'])) {
                            $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata, texto_generado) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt_ins->execute([$row_hist['user_id'], $row_hist['modelo'], $row_hist['descripcion_original'], $row_hist['prompt_positivo'], $row_hist['prompt_negativo'], $row_hist['metadata'], $final_text]);
                            $res['new_prompt_id'] = $pdo->lastInsertId();
                        } else {
                            $stmt_upd = $pdo->prepare("UPDATE historial_prompts SET texto_generado = ? WHERE id = ?");
                            $stmt_upd->execute([$final_text, $prompt_id]);
                            $res['new_prompt_id'] = $prompt_id;
                        }
                    }
                } else {
                    $meta_json = json_encode(['Model' => basename($llm_model_selected), 'Modo' => __('lbl_mode_direct')], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    $stmt_ins = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo, metadata, texto_generado) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt_ins->execute([$_SESSION['user_id'], '[LLM]', $safe_desc, $safe_pos, '', $meta_json, $final_text]);
                    $res['new_prompt_id'] = $pdo->lastInsertId();
                }

                echo json_encode($res, JSON_INVALID_UTF8_SUBSTITUTE);
            } else { 
                echo json_encode(['error' => __('err_llm_format') . ': ' . $api_res]); 
            }
            exit();
        }

    } catch (Throwable $t) {
        file_put_contents(__DIR__ . '/debug_llm_fatal.txt', "Error en ejecutar_llm: " . $t->getMessage() . " en línea " . $t->getLine());
        echo json_encode(['error' => 'Fallo interno. Revisa debug_llm_fatal.txt']);
        exit();
    }
}

// ==============================================================================
// --- 2. ACCIÓN: ARQUITECTO IA (GENERACIÓN DE PROMPT Y CHAT) ---
// ==============================================================================
if (!empty($action) && $action !== 'generar_prompt') {
    exit();
}

$selector = trim($_POST['selector'] ?? '[LLM]');
$descripcion = trim($_POST['descripcion'] ?? '');
$descripcion = process_dynamic_prompts($descripcion);

$isChat = ($selector === '[CHAT]');
$image_data = $_POST['image_data'] ?? null;
$document_text = $_POST['document_text'] ?? null;

$temp_final = 0.7; 

if ($isChat) {
    if (isset($_POST['chat_role']) && $_POST['chat_role'] === 'custom') {
        if (!empty($_POST['custom_role'])) {
            $system_prompt = trim($_POST['custom_role']);
        } else {
            $stmtDef = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE tipo = 'chat_default' AND idioma = ? AND activo = 1 LIMIT 1");
            $stmtDef->execute([$lang]);
            $rowDef = $stmtDef->fetch(PDO::FETCH_ASSOC);
            if (!$rowDef) {
                $stmtDef->execute(['es']);
                $rowDef = $stmtDef->fetch(PDO::FETCH_ASSOC);
            }
            $system_prompt = $rowDef ? $rowDef['prompt_texto'] : "Eres un asistente de Inteligencia Artificial experto.";
            if ($rowDef && !empty($rowDef['parametros'])) {
                $json_params = json_decode($rowDef['parametros'], true);
                if (isset($json_params['temperature'])) $temp_final = (float)$json_params['temperature'];
            }
        }
    } else {
        if (!empty($_POST['chat_role'])) {
            $system_prompt = $_POST['chat_role'];
            $stmtParam = $pdo->prepare("SELECT parametros FROM personalidades_prompts WHERE prompt_texto = ? LIMIT 1");
            $stmtParam->execute([$system_prompt]);
            $param_str = $stmtParam->fetchColumn();
            if (!empty($param_str)) {
                $json_params = json_decode($param_str, true);
                if (isset($json_params['temperature'])) $temp_final = (float)$json_params['temperature'];
            }
        } else {
            $stmtDef = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE tipo = 'chat_default' AND idioma = ? AND activo = 1 LIMIT 1");
            $stmtDef->execute([$lang]);
            $rowDef = $stmtDef->fetch(PDO::FETCH_ASSOC);
            
            if (empty($rowDef['prompt_texto'])) {
                $system_prompt = "You are a helpful and conversational AI assistant. Your default language is [" . strtoupper($lang) . "]. However, if the user speaks to you in another language, you must reply naturally in that same language. NEVER act as a language teacher, do not correct grammar, and do not evaluate the user's text unless explicitly asked to do so.";
            } else {
                $system_prompt = $rowDef['prompt_texto'];
            }
            if ($rowDef && !empty($rowDef['parametros'])) {
                $json_params = json_decode($rowDef['parametros'], true);
                if (isset($json_params['temperature'])) $temp_final = (float)$json_params['temperature'];
            }
        }
    }
} else {
    try {
        $stmtCore = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE tipo = 'core_architect' AND activo = 1 LIMIT 1");
        $stmtCore->execute();
        $coreResult = $stmtCore->fetch(PDO::FETCH_ASSOC);
        
        if ($coreResult && !empty($coreResult['prompt_texto'])) {
            $system_prompt = $coreResult['prompt_texto'];
            if ($selector === '[LLM]' || $isChat) {
                $system_prompt .= "\n\nCRITICAL OVERRIDE: Ignore any previous rules about translating to English. For this specific task, you MUST write the output in the EXACT SAME LANGUAGE as the user's input. DO NOT translate.";
            }
            if (!empty($coreResult['parametros'])) {
                $json_params = json_decode($coreResult['parametros'], true);
                if (isset($json_params['temperature'])) $temp_final = (float)$json_params['temperature'];
            }
        } else {
            throw new Exception("No core_architect");
        }
    } catch (Exception $e) {
        $system_prompt = "[MACHINE_MODE: ACTIVATED]\nMISSION: You are an expert and highly creative prompt engineering API.\nABSOLUTE RULE 1: Return ONLY a valid JSON code block. No explanations, no markdown outside the JSON.\nABSOLUTE RULE 2: The generated prompt MUST be translated to ENGLISH. Do not just translate literally. You MUST EXPAND the user's idea creatively into a complex, professional prompt.\nMANDATORY OUTPUT SCHEMA:\n{\n\"category\": \"<detected category>\",\n\"prompt\": \"<insert the generated, highly creative English prompt here>\",\n\"negative_prompt\": \"<insert the negative prompt here, or leave empty if not needed>\"\n}";
    }

    $cat_limpia = strtolower(trim($selector, '[]'));
    if ($cat_limpia === 'natural_image') $cat_limpia = 'flux'; 
    $slug_estilo = 'estilo_' . $cat_limpia;
    
    try {
        $stmtEstilo = $pdo->prepare("SELECT prompt_texto, parametros FROM personalidades_prompts WHERE tipo = ? AND activo = 1 LIMIT 1");
        $stmtEstilo->execute([$slug_estilo]);
        $estiloResult = $stmtEstilo->fetch(PDO::FETCH_ASSOC);
        
        if ($estiloResult && !empty($estiloResult['prompt_texto'])) {
            $system_prompt .= "\n\nCRITICAL STYLE RULE FOR THIS GENERATION:\n" . $estiloResult['prompt_texto'];
            if (!empty($estiloResult['parametros'])) {
                $json_params = json_decode($estiloResult['parametros'], true);
                if (isset($json_params['temperature'])) $temp_final = (float)$json_params['temperature'];
            }
        } else {
            throw new Exception("No style");
        }
    } catch (Exception $e) {
        $reglas_fallback = [
            '[LLM]' => "MANDATORY FORMAT. You MUST build the 'prompt' field using EXACTLY this structure: '[ROLE]: <assign an expert persona>. [CONTEXT]: <create relevant background>. [TASK]: <expand the user request>. [CONSTRAINTS]: <add rules like tone, length, format>'. Do NOT just translate.",
            '[SD15]' => "Tag-heavy format. Mandatory negative prompt for quality and anatomy.",
            '[SDXL]' => "Hybrid tags/sentences. Optimized for technical photorealism. Mandatory negative prompt.",
            '[NATURAL_IMAGE]' => "Dense natural language descriptive paragraphs. Organic visual instructions. (negative_prompt empty).",
            '[VIDEO]' => "Cinematic. Focus on Subject motion and Camera movement. (negative_prompt empty)."
        ];
        $regla_dura = $reglas_fallback[$selector] ?? "Expand the idea creatively with highly descriptive details.";
        $system_prompt .= "\n\nCRITICAL STYLE RULE FOR THIS GENERATION:\n" . $regla_dura;
    }
} 

$messages = [];
$messages[] = ["role" => "system", "content" => $system_prompt];

if ($isChat && !empty($image_data)) {
    $base64_clean = preg_replace('#^data:image/[^;]+;base64,#', '', $image_data);
    $user_content = empty($descripcion) ? __('cmd_analyze_image') : $descripcion;
    $messages[] = [
        "role" => "user", 
        "content" => $user_content,
        "images" => [$base64_clean]
    ];
    $descripcion_historial = "[" . __('lbl_attached_image') . "] " . $descripcion;
} elseif (!empty($document_text)) {
    $instruction = empty($descripcion) ? __('cmd_analyze_doc') : $descripcion;
    $full_text = __('lbl_doc_content') . ":\n\n---\n" . $document_text . "\n---\n\n" . __('lbl_user_instruction') . ": " . $instruction;
    
    if ($isChat) {
        $messages[] = ["role" => "user", "content" => $full_text]; 
        $descripcion_historial = "[" . __('lbl_attached_doc') . "] " . $descripcion;
    } else {
        $extra = ($selector === '[LLM]') ? "Use [ROLE], [CONTEXT], [TASK] and [CONSTRAINTS]." : "";
        $messages[] = ["role" => "user", "content" => "Task: Transform this into a detailed ENGLISH prompt for category {$selector}. {$extra} Respond ONLY with JSON.\n\n" . $full_text];
        $descripcion_historial = "[" . __('lbl_attached_doc') . "] " . $descripcion;
    }
} else {
    if ($isChat) {
        $user_message = $descripcion;
    } else {
        if ($selector === '[LLM]') {
            $extra = "CRITICAL: You are a PROMPT ENGINEER. Do NOT execute the user's task. Do NOT answer the user's question. Your ONLY job is to write a highly detailed, professional PROMPT that I can copy and paste to another AI to perform this task. The prompt MUST be in the SAME LANGUAGE as the user's input. Do NOT use JSON. Do NOT wrap in quotes. Just output the prompt text.";
            $user_message = "Task: Create an optimized AI prompt based on this idea. {$extra}\nUser Idea: \"{$descripcion}\"";
        } else {
            $extra = "EXPAND the idea creatively with highly descriptive details.";
            $user_message = "Task: Transform the following user idea into an expert, highly detailed structural prompt in ENGLISH for category {$selector}. {$extra} Respond ONLY with a valid JSON block.\nIdea: \"{$descripcion}\"";
        }
    }
    $messages[] = ["role" => "user", "content" => $user_message];
    $descripcion_historial = $descripcion;
}

$modelo_recibido = !empty($_POST['model_path']) ? $_POST['model_path'] : '';
$selected_model = '';

if (($isChat || $selector === '[LLM]') && !empty($modelo_recibido)) {
    $info_modelo = obtener_info_modelo($modelo_recibido, $pdo, 'ollama');
    $selected_model = $info_modelo['nombre_archivo'];
}

if (empty($selected_model)) {
    $stmt_llm = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND categoria = 'SYS_LLM' AND activo = 1 LIMIT 1");
    $selected_model = $stmt_llm->fetchColumn();
    
    if (!$selected_model) {
        $stmt_fb = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE motor = 'ollama' AND activo = 1 LIMIT 1");
        $selected_model = $stmt_fb->fetchColumn();
    }
}

if (empty($selected_model)) {
    echo json_encode(['error' => __('err_no_sys_llm_architect')]);
    exit();
}

// --- CONTROL INTELIGENTE DE MEMORIA (VRAM) ---
// Mantenemos el modelo vivo ÚNICAMENTE si estamos en el Chat conversacional o en redacción de texto pura ([LLM]).
// Si el selector es gráfico ([SDXL], [FLUX], [VIDEO], etc.), forzamos keep_alive a 0 para liberar la VRAM al instante.
$keep_alive_val = ($isChat || $selector === '[LLM]') ? "10m" : 0;

$payload = [
    "model" => $selected_model, 
    "messages" => $messages, 
    "stream" => true, // <-- CAMBIADO A TRUE PARA MANTENER LA CONEXIÓN VIVA
    "options" => [
        "temperature" => $temp_final,
        "num_ctx" => 8192 
    ],
    "keep_alive" => $keep_alive_val
];

// Liberamos sesión para evitar bloqueos
session_write_close();

$ch = curl_init("http://" . LLM_IP . ":11434/api/chat");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 0); 
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE)); 
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

// Preparar cabeceras para Streaming
header('Content-Type: application/x-ndjson');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$raw_response_chunks = "";

// Interceptamos la respuesta letra a letra para enviar "latidos" al navegador
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$raw_response_chunks) {
    $raw_response_chunks .= $data;
    // Enviamos un latido invisible para que FrankenPHP no cierre la conexión
    echo json_encode(['status' => 'thinking']) . "\n";
    flush();
    return strlen($data);
});

$api_res = curl_exec($ch);

if ($api_res === false) { 
    echo json_encode(['error' => __('err_ai_timeout') . ' ' . curl_error($ch)]) . "\n"; 
    flush(); exit(); 
}

// Reconstruimos la respuesta completa a partir de los fragmentos
$raw_content = '';
$raw_thinking = '';
$lines = explode("\n", trim($raw_response_chunks));

foreach($lines as $line) {
    if(empty(trim($line))) continue;
    $dec = json_decode($line, true);
    if ($dec && isset($dec['message'])) {
        $raw_content .= $dec['message']['content'] ?? '';
        $raw_thinking .= $dec['message']['thinking'] ?? '';
    }
}

// --- FIX MAGISTRAL PARA QWEN (Reutilizado) ---
$raw = (empty(trim($raw_content)) && !empty(trim($raw_thinking))) ? $raw_thinking : $raw_content;

$finalP = ""; 
$finalN = "";

if ($isChat || $selector === '[LLM]') {
    $clean = $raw;
    if (strpos($clean, '<think>') !== false) {
        $clean = preg_replace('/<think>.*?<\/think>/is', '', $clean);
        $clean = preg_replace('/<think>.*/is', '', $clean);
    }
    
    $finalP = trim($clean);
    if (empty($finalP) && !empty(trim($raw))) {
        $finalP = trim($raw); 
    }
    $finalN = "";
} else {
    $clean = preg_replace('/<think>.*?<\/think>/is', '', $raw);
    if ($clean === null) $clean = $raw;
    $clean = preg_replace('/<think>.*/is', '', $clean); 
    if ($clean === null) $clean = $raw;

    if (preg_match('/\{[\s\S]*\}/s', $clean, $matches)) { 
        $json_str = $matches[0];
        $ai = json_decode($json_str, true); 
        if ($ai) {
            $finalP = $ai['prompt'] ?? $ai['positive_prompt'] ?? "";
            $finalN = $ai['negative_prompt'] ?? $ai['negative'] ?? "";
        }
    }
    
    if (empty(trim($finalP))) {
        $text = str_replace(['```json', '```', '`'], '', $clean);
        if (empty(trim($text)) || strpos($text, '<think>') !== false) { 
            preg_match('/<think>([\s\S]*?)(?:<\/think>|$)/is', $raw, $think_matches);
            if (!empty($think_matches[1])) {
                $text = trim($think_matches[1]);
            } else {
                $text = trim(strip_tags($raw)); 
            }
        }
        $finalP = !empty($text) ? trim($text) : trim($raw);
        
        if (stripos($finalP, 'negative') !== false && !$isChat) {
            $bits = preg_split('/"negative_prompt"\s*:\s*|negative_prompt:|negative:/i', $finalP);
            $finalP = trim(trim($bits[0], '", {}'));
            $finalN = trim(trim($bits[1] ?? "", '", {}'));
        }
    }
}

$noise = ["none", "n/a", "vacío", "empty", "null", "undefined"];
if (in_array(strtolower(trim($finalN)), $noise)) { $finalN = ""; }
if (in_array($selector, ['[LLM]', '[NATURAL_IMAGE]', '[VIDEO]'])) { $finalN = ""; }

$finalP = trim(preg_replace('/^(The user wants|Here is|Prompt:|Positive Prompt:)/i', '', trim($finalP)));

if (empty($finalN) && ($selector === '[SD15]' || $selector === '[SDXL]')) {
    $finalN = "lowres, bad quality, worst quality, blurry, text, (deformed, distorted:1.3), CGI, render, plastic skin, bad anatomy";
}

if (!empty(trim($finalP))) {
    $safe_desc = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$descripcion_historial);
    $safe_pos = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$finalP);
    $safe_neg = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', (string)$finalN);

    // Si $user_id no está definido en este scope, usamos $_SESSION
    $uid = $user_id ?? $_SESSION['user_id'] ?? 1;

    $stmt = $pdo->prepare("INSERT INTO historial_prompts (user_id, modelo, descripcion_original, prompt_positivo, prompt_negativo) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$uid, $selector, $safe_desc, $safe_pos, $safe_neg]);
    
    $inserted_id = $pdo->lastInsertId();
    $normalized = ["prompt" => $safe_pos, "negative_prompt" => $safe_neg];
    
    $fake_openai_response = [
        'choices' => [
            [ 'message' => [ 'content' => json_encode($normalized, JSON_INVALID_UTF8_SUBSTITUTE) ] ]
        ],
        'prompt_id' => $inserted_id
    ];
    
    // Enviamos la respuesta final
    echo json_encode($fake_openai_response, JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
    flush(); 
} else { 
    echo json_encode(['error' => __('err_empty_parser')]) . "\n"; flush();
}
?>