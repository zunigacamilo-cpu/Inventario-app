<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $st = db()->query('SELECT id, nombre, descripcion FROM categorias_insumo ORDER BY nombre');
    json_response(['ok' => true, 'categorias' => $st->fetchAll()]);
}

if ($method === 'POST') {
    require_admin();
    require_json_method('POST');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($nombre === '') {
        json_response(['ok' => false, 'error' => 'Nombre requerido'], 400);
    }
    $desc = trim((string) ($data['descripcion'] ?? ''));
    try {
        db()->prepare('INSERT INTO categorias_insumo (nombre, descripcion) VALUES (?,?)')->execute([$nombre, $desc ?: null]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            json_response(['ok' => false, 'error' => 'Categoría ya existe'], 409);
        }
        throw $e;
    }
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
