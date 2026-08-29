<?php
// ==============================================================================
// --- AUTO-ARQUITECTO (V3): ENRUTAMIENTO POR ID (BLINDAJE ABSOLUTO) ---
// ==============================================================================
if (!defined('COMFY_URL')) exit;

global $pdo;

$idea = trim($_POST['idea'] ?? '');
if (empty($idea)) {
    echo json_encode(['error' => __('err_falta_idea') ?? 'La idea está vacía.']);
    exit;
}

// 1. OBTENER MODELOS Y SUS IDs DE LA BASE DE DATOS
try {
    $stmt = $pdo->query("SELECT id, nombre_archivo, categoria, tags_uso FROM modelos_ia WHERE activo = 1 AND categoria IN ('sd15', 'sdxl', 'flux', 'video')");
    $modelos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['error' => __('err_db_query') ?? 'Error leyendo la base de datos.']);
    exit;
}

if (empty($modelos_db)) {
    echo json_encode(['error' => __('err_no_graphic_models') ?? 'No hay modelos gráficos activos en la base de datos.']);
    exit;
}

// 2. CONSTRUIR LISTA NUMERADA PARA EL LLM
$contexto_modelos = "";
$fallback_modelo = $modelos_db[0]; 
$encontrado_flux = false;

foreach ($modelos_db as $m) {
    // Si no hay etiquetas, le damos una de propósito general
    $tags = !empty(trim($m['tags_uso'])) ? $m['tags_uso'] : 'general purpose, any style';
    // Solo le pasamos el ID y la especialidad. Ocultamos el nombre de archivo para no confundirlo.
    $contexto_modelos .= "- ID: {$m['id']} | Category: {$m['categoria']} | Specialty: {$tags}\n";
    
    // Guardamos el primer FLUX como salvavidas por defecto
    if (strtolower($m['categoria']) === 'flux' && !$encontrado_flux) {
        $fallback_modelo = $m;
        $encontrado_flux = true;
    }
}

// 3. BUSCAR EL MOTOR LLM ACTIVO EN LA BD 
$stmt_llm = $pdo->query("SELECT nombre_archivo FROM modelos_ia WHERE activo = 1 AND motor = 'ollama' AND categoria = 'sys_llm' LIMIT 1");
$llm_model = $stmt_llm->fetchColumn() ?: 'llama3:latest'; 

// 4. EL PROMPT MAESTRO (Solo pedimos que devuelva el ID del modelo)
$system_prompt = "You are an expert AI routing system.
Analyze the USER PROMPT and match it with the best model from the MODELS LIST based on its 'Specialty'.

MODELS LIST:
{$contexto_modelos}

USER PROMPT: \"{$idea}\"

RULES:
1. Select the single best 'ID' from the list that matches the user's request.
2. Determine the best proportion based on the subject: '1024x1024' (square/logos/products), '896x1152' (portraits/vertical), or '1152x896' (landscapes/cinematic/horizontal).
3. Output ONLY a valid JSON object. No markdown, no greetings, no explanations.

EXAMPLE OUTPUT:
{
  \"id_modelo\": 5,
  \"proporcion\": \"1024x1024\"
}";

// 5. PETICIÓN A OLLAMA
$ch = curl_init("http://" . LLM_IP . ":" . LLM_PORT . "/api/generate");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15); 
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => $llm_model,
    'prompt' => $system_prompt,
    'stream' => false,
    'format' => 'json',
    'options' => ['temperature' => 0.0] // 0 Creatividad. Obediencia absoluta.
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 6. EXTRACCIÓN DEL ID 
$id_elegido = null;
$proporcion_elegida = "1024x1024";
$raw_json = "";

if ($http_code === 200 && $res) {
    $llm_response = json_decode($res, true);
    if (isset($llm_response['response'])) {
        $raw_json = $llm_response['response'];
        // Usamos Regex para ignorar texto basura y capturar solo las llaves {}
        if (preg_match('/\{[\s\S]*\}/', $raw_json, $matches)) {
            $datos_json = json_decode($matches[0], true);
            if (isset($datos_json['id_modelo'])) $id_elegido = intval($datos_json['id_modelo']);
            if (isset($datos_json['proporcion'])) $proporcion_elegida = $datos_json['proporcion'];
        }
    }
}

// 7. BÚSQUEDA DEL MODELO EN LA BD Y RECONSTRUCCIÓN EXACTA PARA EL JAVASCRIPT
$modelo_final = $fallback_modelo;
$uso_fallback = true;

if ($id_elegido !== null) {
    foreach ($modelos_db as $m) {
        if ($m['id'] == $id_elegido) {
            $modelo_final = $m;
            $uso_fallback = false;
            break;
        }
    }
}

$cat_map = ['sd15' => '[SD15]', 'sdxl' => '[SDXL]', 'flux' => '[NATURAL_IMAGE]', 'video' => '[VIDEO]'];
$cat_frontend = $cat_map[strtolower($modelo_final['categoria'])] ?? '[NATURAL_IMAGE]';

// Recreamos exactamente la estructura que usaba tu JSON antiguo
$reglas = [
    "categoria" => $cat_frontend,
    "modelo_keyword" => strtolower($modelo_final['categoria']), // <- ¡Esta era la llave que rompía el JS!
    "modelo_exacto" => $modelo_final['nombre_archivo'],
    "modelo_id" => $modelo_final['id'], // Añadida por seguridad extra
    "proporcion" => $proporcion_elegida
];

// 8. LOG ESPÍA (DEBUG)
$debug_info = "--- NUEVA CONSULTA AUTO-ARQUITECTO (V3) ---\n";
$debug_info .= "FECHA: " . date("Y-m-d H:i:s") . "\n";
$debug_info .= "IDEA USUARIO: $idea\n\n";
$debug_info .= "RESPUESTA CRUDA DEL LLM:\n$raw_json\n\n";
if ($uso_fallback) {
    $debug_info .= "⚠ ERROR. SE USÓ FALLBACK. EL LLM NO DEVOLVIÓ UN ID VÁLIDO.\n";
} else {
    $debug_info .= "✅ ID $id_elegido MAPEADO CORRECTAMENTE AL MODELO: " . $modelo_final['nombre_archivo'] . "\n";
}
$debug_info .= print_r($reglas, true) . "\n=====================================\n\n";
@file_put_contents(__DIR__ . '/../debug_arquitecto.txt', $debug_info, FILE_APPEND);

// 9. ENVÍO DE ORDEN AL FRONTEND
echo json_encode([
    'success' => true,
    'reglas' => $reglas
]);
exit;