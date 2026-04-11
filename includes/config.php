<?php
declare(strict_types=1);

/**
 * Configuración de BD — lee variables de entorno inyectadas por Docker.
 * Los valores por defecto coinciden con el .env del proyecto.
 */
const DB_CHARSET = 'utf8mb4';

session_name('INVAPPSESSID');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        // Docker inyecta estas variables; fuera de Docker usa los valores del .env
        $host   = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'db';
        $port   = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
        $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'inventario_app';
        $user   = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'inventario_user';
        $pass   = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'inventario_pass';

        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($data, $flags);
    // #region agent log
    if ($json === false) {
        $logPath = dirname(__DIR__) . '/.cursor/debug-736b24.log';
        $logLine = json_encode([
            'sessionId' => '736b24',
            'location' => 'includes/config.php:json_response',
            'message' => 'json_encode returned false',
            'data' => [
                'http_code' => $code,
                'json_last_error' => json_last_error(),
                'json_last_error_msg' => json_last_error_msg(),
                'top_level_keys' => array_keys($data),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
            'hypothesisId' => 'C',
        ], JSON_UNESCAPED_UNICODE) . "\n";
        @file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
        http_response_code(500);
        $json = json_encode(
            ['ok' => false, 'error' => 'Error interno al generar la respuesta JSON'],
            JSON_UNESCAPED_UNICODE
        );
    }
    // #endregion
    echo $json !== false ? $json : '{"ok":false,"error":"Error interno"}';
    exit;
}

function require_json_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
    }
}
