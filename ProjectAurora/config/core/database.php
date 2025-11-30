<?php
// config/core/database.php

// 1. Función para cargar variables del archivo .env
$envFile = __DIR__ . '/../../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorar comentarios
        if (strpos(trim($line), '#') === 0) continue;
        
        // Separar nombre y valor
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Eliminar comillas si existen
            $value = trim($value, '"\'');
            
            // Guardar en variables de entorno y superglobales
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// 2. Obtener credenciales MySQL
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = $_ENV['DB_NAME'] ?? 'project_aurora_db';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    if (!class_exists('I18n')) {
        $i18nPath = __DIR__ . '/../../includes/logic/i18n_server.php';
        if (file_exists($i18nPath)) {
            require_once $i18nPath;
            require_once __DIR__ . '/../helpers/utilities.php'; 
            $lang = detect_browser_language() ?? 'es-latam';
            I18n::load($lang);
        }
    }
    $errorMsg = function_exists('translation') ? translation('global.error_connection') : 'Error de conexión a base de datos';
    die(json_encode(['success' => false, 'message' => $errorMsg]));
}

// 3. Configuración Redis
$redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
$redisPort = $_ENV['REDIS_PORT'] ?? 6379;
$redisPass = $_ENV['REDIS_PASS'] ?? null;

try {
    $redis = new Redis();
    $redis->connect($redisHost, $redisPort);
    if ($redisPass) {
        $redis->auth($redisPass);
    }
} catch (Exception $e) {
    // Fallback o log error
    error_log("Redis Connection Error: " . $e->getMessage());
}
?>