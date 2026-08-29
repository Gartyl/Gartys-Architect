<?php
// ==============================================================================
// --- API PRESETS: Gestión de Presets en Base de Datos ---
// ==============================================================================
require_once __DIR__ . '/../db.php'; 

global $pdo; 

// Si recibimos una petición GET, devolvemos la lista de presets
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $presets_data = [];
    
    try {
        $stmt = $pdo->query("SELECT * FROM presets_personales ORDER BY nombre ASC");
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formateamos para mantener la estructura JSON original
        foreach ($resultados as $row) {
            $presets_data[$row['nombre']] = [
                'categoria' => $row['categoria'] ?? '', // <-- AÑADIDO
                'prompt' => $row['prompt'],
                'prompt_negativo' => $row['prompt_negativo'],
                'modelo' => $row['modelo_id'],
                'width' => (int)$row['width'],
                'height' => (int)$row['height'],
                'formato' => $row['formato'],
                'steps' => (int)$row['steps'],
                'cfg' => (float)$row['cfg'],
                'sampler' => $row['sampler'],
                'scheduler' => $row['scheduler'],
                'seed' => (int)$row['seed'],
                'flow_shift' => $row['flow_shift'],
                'loras' => json_decode($row['loras_json'], true) ?: []
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($presets_data, JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([]); 
        exit;
    }
}

// Si recibimos una petición POST, leemos los datos
$datos = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($datos['action'])) {
    
    header('Content-Type: application/json');
    
    if ($datos['action'] === 'save') {
        try {
            $nombre = $datos['name'];
            $config = $datos['config'];
            
            $categoria = $config['categoria'] ?? ''; // <-- AÑADIDO
            $prompt = $config['prompt'] ?? '';
            $prompt_negativo = $config['prompt_negativo'] ?? '';
            $modelo = !empty($config['modelo']) ? $config['modelo'] : null;
            $width = $config['width'] ?? 1024;
            $height = $config['height'] ?? 1024;
            $formato = $config['formato'] ?? 'PNG';
            $steps = $config['steps'] ?? 30;
            $cfg = $config['cfg'] ?? 5.0;
            $sampler = $config['sampler'] ?? 'euler';
            $scheduler = $config['scheduler'] ?? 'beta';
            $seed = $config['seed'] ?? -1;
            $flow_shift = $config['flow_shift'] ?? 'Auto';
            $loras_json = json_encode($config['loras'] ?? []);

            // Comprobamos si el preset ya existe (Hacemos UPDATE) o es nuevo (Hacemos INSERT)
            $check = $pdo->prepare("SELECT id FROM presets_personales WHERE nombre = :nombre");
            $check->execute([':nombre' => $nombre]);
            $existe = $check->fetchColumn();

            if ($existe) {
                $sql = "UPDATE presets_personales SET 
                        categoria = :categoria, prompt = :prompt, prompt_negativo = :prompt_negativo, modelo_id = :modelo, 
                        width = :width, height = :height, formato = :formato, steps = :steps, 
                        cfg = :cfg, sampler = :sampler, scheduler = :scheduler, seed = :seed, 
                        flow_shift = :flow_shift, loras_json = :loras_json 
                        WHERE nombre = :nombre";
            } else {
                $sql = "INSERT INTO presets_personales 
                        (nombre, categoria, prompt, prompt_negativo, modelo_id, width, height, formato, steps, cfg, sampler, scheduler, seed, flow_shift, loras_json) 
                        VALUES 
                        (:nombre, :categoria, :prompt, :prompt_negativo, :modelo, :width, :height, :formato, :steps, :cfg, :sampler, :scheduler, :seed, :flow_shift, :loras_json)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $nombre,
                ':categoria' => $categoria, // <-- AÑADIDO
                ':prompt' => $prompt,
                ':prompt_negativo' => $prompt_negativo,
                ':modelo' => $modelo,
                ':width' => $width,
                ':height' => $height,
                ':formato' => $formato,
                ':steps' => $steps,
                ':cfg' => $cfg,
                ':sampler' => $sampler,
                ':scheduler' => $scheduler,
                ':seed' => $seed,
                ':flow_shift' => $flow_shift,
                ':loras_json' => $loras_json
            ]);

            echo json_encode(['status' => 'ok']);
            
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        
    } elseif ($datos['action'] === 'delete') {
        try {
            $nombre = $datos['name'];
            $stmt = $pdo->prepare("DELETE FROM presets_personales WHERE nombre = :nombre");
            $stmt->execute([':nombre' => $nombre]);
            
            echo json_encode(['status' => 'ok']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    exit;
}
?>