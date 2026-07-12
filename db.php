<?php
/**
 * db.php - Conexión a la Base de Datos
 * Sistema dual: SQLite (Portable) / MariaDB (Servidor)
 */

// ==============================================================================
// --- PASO 1: BLINDAJE DE CONFIGURACIÓN Y AUTOCREACIÓN ---
// ==============================================================================
// Aunque init.php ya lo comprueba, mantenemos este escudo por si algún archivo
// independiente (como scripts AJAX) llama directamente a db.php.
if (!file_exists(__DIR__ . '/config.php')) {
    if (file_exists(__DIR__ . '/config.sample.php')) {
        copy(__DIR__ . '/config.sample.php', __DIR__ . '/config.php');
    } else {
        // PRECAUCIÓN TÉCNICA: Comprobamos si la función __() existe en este milisegundo,
        // ya que al arrancar db.php es posible que el sistema i18n aún no se haya cargado.
        $msg_err = function_exists('__') ? __('err_missing_config_sample') : "Error crítico de arranque: No se encuentra el fichero plantilla config.sample.php.";
        die("<h3 style='color:red;'>" . $msg_err . "</h3>");
    }
}

// Aseguramos que la configuración esté cargada para leer APP_MODE
if (!defined('APP_MODE')) {
    require_once __DIR__ . '/config.php';
}

if (defined('APP_MODE') && APP_MODE === 'local') {
	
    // ==============================================================================
    // --- MODO LOCAL (CLIENTE FINAL) -> USA SQLITE PORTABLE ---
    // ==============================================================================
    // El archivo de la base de datos vivirá en la misma carpeta que este archivo de código
    // Nota: Si db.php está en la raíz, __DIR__ es perfecto. 
    // (Si estuviera dentro de una subcarpeta como /includes/db.php, deberías usar: dirname(__DIR__) . '/database.sqlite')
    $sqlite_path = __DIR__ . '/data/database.sqlite';
    $dsn = "sqlite:" . $sqlite_path;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, null, null, $options);
        // Activar soporte para claves foráneas en SQLite (por si las usas)
        $pdo->exec('PRAGMA foreign_keys = ON;');
        
        // Enseñar a SQLite a entender la función RAND() de MySQL
        $pdo->sqliteCreateFunction('RAND', function() {
            return mt_rand() / mt_getrandmax();
        }, 0);
        
    } catch (\PDOException $e) {
        die("<h3 style='color:red;'>Error crítico: No se encuentra o no se puede abrir el archivo database.sqlite local.</h3><p>Detalle técnico: " . $e->getMessage() . "</p>");
    }

} else {
    // ==============================================================================
    // --- MODO SERVIDOR (TU ENTORNO DE DESARROLLO) -> USA MYSQL ---
    // ==============================================================================
    $host = '127.0.0.1'; 
    $port = '3306';      
    $db   = 'ia_prompts';
    $user = 'root';      
    $pass = ''; // Tu contraseña de MariaDB
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // EL PARCHE: Fuerza a MariaDB a usar el set de caracteres y la colación correctos
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die("Error de conexión a la base de datos MariaDB: " . $e->getMessage());
    }
}

?>