<?php
// Archivo donde se guardarán físicamente tus presets (ahora protegido en la carpeta data)
// Añadimos '/../' para salir de la carpeta 'modulos' y volver a la raíz antes de entrar a 'data'
$archivo_json = __DIR__ . '/../data/presets_personales.json';

// Si recibimos una petición GET, devolvemos la lista de presets
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($archivo_json)) {
        header('Content-Type: application/json');
        echo file_get_contents($archivo_json);
    } else {
        echo json_encode([]);
    }
    exit;
}

// Si recibimos una petición POST, leemos los datos
$datos = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($datos['action'])) {
    // Leemos el archivo actual (si existe)
    $presets = file_exists($archivo_json) ? json_decode(file_get_contents($archivo_json), true) : [];

    if ($datos['action'] === 'save') {
        // Guardamos o sobrescribimos el preset
        $presets[$datos['name']] = $datos['config'];
        file_put_contents($archivo_json, json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['status' => 'ok']);
        
    } elseif ($datos['action'] === 'delete') {
        // Borramos el preset
        if (isset($presets[$datos['name']])) {
            unset($presets[$datos['name']]);
            file_put_contents($archivo_json, json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        echo json_encode(['status' => 'ok']);
    }
    exit;
}
?>