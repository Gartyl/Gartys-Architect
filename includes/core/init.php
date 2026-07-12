<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
session_start();

// ==============================================================================
// --- PASO CERO: BLINDAJE Y AUTO-CONFIGURACIÓN DE ARRANQUE ---
// ==============================================================================
// Si no existe config.php (primer arranque o post-actualización), lo creamos desde la muestra
$ruta_config = __DIR__ . '/../../config.php';
$ruta_sample = __DIR__ . '/../../config.sample.php';

if (!file_exists($ruta_config)) {
    if (file_exists($ruta_sample)) {
        copy($ruta_sample, $ruta_config);
    } else {
        // Al no estar cargado aún el sistema i18n, damos un error bilingüe directo
		$msg_err = function_exists('__') ? __('err_missing_config_sample') : "Critical startup error: The config.sample.php template file was not found.";
		die("<h3 style='color:red;'>" . $msg_err . "</h3>");
    }
}
// ==============================================================================

require_once __DIR__ . '/../../config.php'; // 1º Ahora sí se carga de forma 100% segura
require_once __DIR__ . '/../../db.php';     // 2º Conectamos a la base de datos sabiendo el modo

// ==============================================================================
// --- DETECCIÓN DE ONBOARDING (PRIMER ARRANQUE) ---
// ==============================================================================
$necesita_onboarding = false;

// Si la cookie NO existe, comprobamos la base de datos
if (!isset($_COOKIE['garty_onboarding_done'])) {
    try {
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM modelos_ia");
        if ($stmt_count->fetchColumn() == 0) {
            $necesita_onboarding = true;
        }
    } catch (Exception $e) {}
}

// ==============================================================================
// --- NUEVO: ASIGNACIÓN DE SESIÓN LOCAL AUTOMÁTICA Y SEGURA (PROTEGIDA) ---
// ==============================================================================
if (defined('APP_MODE') && APP_MODE === 'local') {
    try {
        $stmt = $pdo->query("SELECT id, nick, rol, license_key FROM usuarios LIMIT 1");
        $user_local = $stmt->fetch();
        
        if ($user_local) {
            $_SESSION['user_id'] = $user_local['id'];
            $_SESSION['nick']    = $user_local['nick'];
            $_SESSION['estado']  = 'activo';
            
            // --- VALIDACIÓN DE NIVEL 2: SERVIDOR CON FALLBACK OFFLINE ---
            if ($user_local['rol'] === 'pro') {
                $license = trim($user_local['license_key'] ?? '');
                
                if (!empty($license) && strlen($license) > 15) {
                    
                    // Intentamos conectar con Lemon Squeezy en el arranque
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api.lemonsqueezy.com/v1/licenses/validate",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query(['license_key' => $license]),
                        CURLOPT_HTTPHEADER => ["Accept: application/json"],
                        CURLOPT_TIMEOUT => 3, // ¡VITAL! Si en 3 segundos no hay internet, aborta la conexión
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_SSL_VERIFYHOST => false,
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);
                    //curl_close($curl);  DEPRECATED

                    if ($err) {
                        // MODO OFFLINE: Falló la conexión (no hay internet). 
                        // Le damos el beneficio de la duda y mantenemos su modo Pro local.
                        $_SESSION['rol'] = 'pro';
                    } else {
                        // MODO ONLINE: Hay respuesta de Lemon Squeezy. Vamos a leerla.
                        $data = json_decode($response, true);
                        if (isset($data['valid']) && $data['valid'] === true) {
                            $_SESSION['rol'] = 'pro'; // Licencia 100% legal confirmada
                        } else {
                            // CAZADO: Lemon Squeezy confirma que la licencia es inventada, falsa o desactivada
                            $_SESSION['rol'] = 'free';
                            
                            // OPCIONAL: Podrías hacer un UPDATE a la base de datos aquí para 
                            // devolverlo a 'free' permanentemente hasta que ponga una clave real.
                            $pdo->query("UPDATE usuarios SET rol = 'free' WHERE id = 1");
                        }
                    }
                } else {
                    // Si ha tocado la BBDD a mano dejando la clave vacía o muy corta
                    $_SESSION['rol'] = 'free';
                }
            } else {
                $_SESSION['rol'] = 'free';
            }
            
        } else {
            $_SESSION['user_id'] = 0;
            $_SESSION['nick']    = 'Invitado';
            $_SESSION['rol']     = 'free'; 
            $_SESSION['estado']  = 'activo';
        }
    } catch (Exception $e) {
        $_SESSION['user_id'] = 0;
        $_SESSION['nick']    = 'Invitado';
        $_SESSION['rol']     = 'free';
        $_SESSION['estado']  = 'activo';
    }
}

// Comprobación estándar del sistema
$pagina_actual = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $pagina_actual !== 'login.php' && $pagina_actual !== 'registro.php') {
    header("Location: login.php");
    exit();
}

// ==============================================================================
// --- MÓDULO i18n: SISTEMA DE IDIOMAS PERSISTENTE Y DINÁMICO ---
// ==============================================================================
// 1. Detectar si el usuario quiere cambiar de idioma (por la URL o un botón futuro)
if (isset($_GET['lang'])) {
    $nuevo_idioma = preg_replace('/[^a-zA-Z]/', '', $_GET['lang']); // Limpieza de seguridad
    $_SESSION['lang'] = $nuevo_idioma;
    // Guardamos una Cookie en el navegador que durará 1 año
    setcookie('user_lang', $nuevo_idioma, time() + (86400 * 365), "/"); 
}

// 2. Definir idioma actual: Sesión -> Cookie -> Detección del Navegador
if (isset($_SESSION['lang'])) {
    $lang = $_SESSION['lang'];
} elseif (isset($_COOKIE['user_lang'])) {
    $lang = $_COOKIE['user_lang'];
    $_SESSION['lang'] = $lang; // Sincronizamos la sesión actual
} else {
    // NUEVO: Detectar el idioma principal del navegador del usuario de forma automática
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2); // Extrae 'fr', 'it', 'en', 'es'...
    } else {
        $lang = 'en'; // Si el navegador oculta el dato, asumimos inglés
    }
}

// 3. Cargar el diccionario correspondiente con sistema de Fallback (Cascada)
// Usamos DIRECTORY_SEPARATOR para máxima compatibilidad entre SO
$lang_file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $lang . '.php';

if (!file_exists($lang_file)) {
    // Si el usuario es italiano pero no tienes "it.php", cae al Inglés
    $lang = 'en';
    $lang_file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'en.php';
    
    // Si por algún motivo se borró el fichero inglés, el seguro de vida es el Castellano
    if (!file_exists($lang_file)) {
        $lang = 'es';
        $lang_file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'es.php';
    }
}

// Guardamos el idioma final real en la sesión (por si hubo que hacer fallback)
$_SESSION['lang'] = $lang;

if (file_exists($lang_file)) {
    $diccionario = include $lang_file;
} else {
    // Escudo de titanio final: Si ni siquiera existe el es.php, creamos un array vacío
    // para que la app no explote por completo (aunque se vean las claves)
    $diccionario = []; 
}

// 4. La Función Mágica Traductora
if (!function_exists('__')) {
    function __($clave) {
        global $diccionario;
        return $diccionario[$clave] ?? $clave; 
    }
}
// ==============================================================================

// ¡LA VACUNA CONTRA EL ERROR 502! Suelta la llave de la sesión una vez procesado el idioma
session_write_close();

// --- SISTEMA UNIFICADO DE ROLES (Distribución y SaaS) ---
$user_rol = $_SESSION['rol'] ?? 'free'; 
$is_admin = ($user_rol === 'admin');
// Un usuario "Pro" es quien tiene la licencia 'pro', pero el 'admin' también hereda todos los derechos Pro
$is_pro   = ($user_rol === 'pro' || $user_rol === 'admin');

$mensajes_pendientes = [];

// Solo buscamos mensajes o marcamos como leídos si el usuario YA está logueado
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    
    if (isset($_POST['action']) && $_POST['action'] === 'marcar_leido') {
        $msg_id = intval($_POST['msg_id']);
        try {
            $stmt_upd = $pdo->prepare("UPDATE mensajes_admin SET leido = 1 WHERE id = ? AND user_id = ?");
            $stmt_upd->execute([$msg_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false]);
        }
        exit();
    }

    try {
        $pdo->query("SELECT id FROM mensajes_admin LIMIT 1"); 
        $stmt_msg = $pdo->prepare("SELECT id, mensaje, fecha FROM mensajes_admin WHERE user_id = ? AND leido = 0 ORDER BY fecha ASC");
        $stmt_msg->execute([$_SESSION['user_id']]);
        $mensajes_pendientes = $stmt_msg->fetchAll();
    } catch (Exception $e) { }
}
?>