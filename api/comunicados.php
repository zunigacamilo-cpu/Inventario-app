<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    require_login();
    $limite = isset($_GET['limite']) ? max(1, min(100, (int) $_GET['limite'])) : 50;
    $st = db()->prepare(
        'SELECT c.id, c.titulo, c.cuerpo, c.creado_en, u.username AS autor_username
         FROM comunicados c
         JOIN usuarios u ON u.id = c.creado_por_id
         ORDER BY c.creado_en DESC
         LIMIT ' . (int) $limite
    );
    $st->execute();
    json_response(['ok' => true, 'comunicados' => $st->fetchAll()]);
}

if ($method === 'POST') {
    $u = require_admin();
    require_json_method('POST');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $titulo = trim((string) ($data['titulo'] ?? ''));
    $cuerpo = trim((string) ($data['cuerpo'] ?? ''));
    if ($titulo === '' || $cuerpo === '') {
        json_response(['ok' => false, 'error' => 'Título y cuerpo son obligatorios'], 400);
    }
    $ins = db()->prepare(
        'INSERT INTO comunicados (titulo, cuerpo, creado_por_id) VALUES (?,?,?)'
    );
    $ins->execute([
        mb_substr($titulo, 0, 200),
        $cuerpo,
        $u['id'],
    ]);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
