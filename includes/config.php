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
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_json_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
    }
}
