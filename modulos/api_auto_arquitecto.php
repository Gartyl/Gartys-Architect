<?php
// ==============================================================================
// --- AUTO-ARQUITECTO: MOTOR HÍBRIDO DE REGLAS BASADO EN JSON ---
// ==============================================================================
if (!defined('COMFY_URL')) exit;

global $pdo;

$idea = strtolower(trim($_POST['idea'] ?? ''));
if (empty($idea)) {
    echo json_encode(['error' => __('err_falta_idea') ?? 'La idea está vacía.']);
    exit;
}

// 1. Ruta del archivo JSON (se creará en la raíz del proyecto)
$archivo_reglas = __DIR__ . '/../architect_templates.json';

// Si no existe, creamos la plantilla maestra con tus categorías
if (!file_exists($archivo_reglas)) {
    $reglas_default = [
        "reglas" => [
            [
                "id" => "poster_publicitario",
                "keywords" => ["texto", "cartel", "poster", "publicidad", "rotulo", "portada", "tipografia"],
                "accion" => ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "sd3.5", "proporcion" => "896x1152"]
            ],
            [
                "id" => "retrato_fotografia",
                "keywords" => ["foto", "retrato", "fotografia", "realista", "persona", "lente", "camara", "macro", "fotorealismo"],
                "accion" => ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "flux", "proporcion" => "896x1152"]
            ],
            [
                "id" => "paisaje",
                "keywords" => ["paisaje", "naturaleza", "montaña", "ciudad", "calle", "bosque", "panoramica"],
                "accion" => ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "flux", "proporcion" => "1152x896"]
            ],
            [
                "id" => "producto",
                "keywords" => ["producto", "comercial", "bodegon", "frasco", "botella", "caja", "render 3d"],
                "accion" => ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "flux", "proporcion" => "1024x1024"]
            ],
            [
                "id" => "arquitectura_interiorismo",
                "keywords" => ["arquitectura", "interiorismo", "casa", "edificio", "habitacion", "salon", "cocina"],
                "accion" => ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "flux", "proporcion" => "1152x896"]
            ],
            [
                "id" => "anime_ilustracion",
                "keywords" => ["anime", "manga", "ilustracion", "dibujo", "comic", "2d", "pintura", "arte"],
                "accion" => ["categoria" => "[SDXL]", "modelo_keyword" => "chroma", "proporcion" => "1152x896"]
            ],
            [
                "id" => "pixel_art",
                "keywords" => ["pixel art", "16 bits", "8 bits", "retro game", "videojuego retro"],
                "accion" => ["categoria" => "[SDXL]", "modelo_keyword" => "chroma", "proporcion" => "1024x1024"]
            ],
            [
                "id" => "ropa_moda",
                "keywords" => ["ropa", "vestido", "moda", "outfit", "prenda", "textil", "camiseta"],
                "accion" => ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "flux", "proporcion" => "896x1152", "activar_redux" => true]
            ]
        ],
        "default" => [
            "categoria" => "[NATURAL_IMAGE]",
            "modelo_keyword" => "flux",
            "proporcion" => "1024x1024"
        ]
    ];
    file_put_contents($archivo_reglas, json_encode($reglas_default, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 2. Cargamos el JSON
$json_data = json_decode(file_get_contents($archivo_reglas), true);
$reglas = $json_data['reglas'] ?? [];
$accion_elegida = $json_data['default'] ?? ["categoria" => "[NATURAL_IMAGE]", "modelo_keyword" => "flux", "proporcion" => "1024x1024"];

// 3. Motor de matching por palabras clave (Ultrarrápido)
foreach ($reglas as $regla) {
    foreach ($regla['keywords'] as $keyword) {
        if (strpos($idea, strtolower($keyword)) !== false) {
            $accion_elegida = $regla['accion'];
            break 2; // Detenemos en la primera coincidencia
        }
    }
}

// 4. Buscar el modelo exacto en la base de datos
$keyword_modelo = $accion_elegida['modelo_keyword'];
$stmt = $pdo->prepare("SELECT nombre_archivo, categoria FROM modelos_ia WHERE activo = 1 AND LOWER(nombre_archivo) LIKE :keyword LIMIT 1");
$stmt->execute([':keyword' => '%' . strtolower($keyword_modelo) . '%']);
$modelo_real = $stmt->fetch(PDO::FETCH_ASSOC);

if ($modelo_real) {
    $accion_elegida['modelo_exacto'] = $modelo_real['nombre_archivo'];
    
    // Auto-ajustamos la categoría por si el modelo está en otro cajón en la BD
    if ($modelo_real['categoria'] === 'sd15') $accion_elegida['categoria'] = '[SD15]';
    elseif ($modelo_real['categoria'] === 'sdxl') $accion_elegida['categoria'] = '[SDXL]';
    elseif ($modelo_real['categoria'] === 'flux') $accion_elegida['categoria'] = '[NATURAL_IMAGE]';
}

echo json_encode([
    'success' => true,
    'reglas' => $accion_elegida
]);
exit;