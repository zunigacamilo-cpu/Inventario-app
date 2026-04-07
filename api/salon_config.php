<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $u = require_login();
    $st = db()->query('SELECT id, anticipacion_horas, texto_condiciones FROM salon_config WHERE id = 1 LIMIT 1');
    $row = $st->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Configuración del salón no inicializada. Importe sql/schema.sql o migration_salon.sql'], 500);
    }
    json_response(['ok' => true, 'config' => $row, 'es_admin' => $u['perfil_id'] === PERFIL_ADMIN]);
}

if ($method === 'PATCH') {
    require_admin();
    require_json_method('PATCH');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $fields = [];
    $params = [];
    if (array_key_exists('anticipacion_horas', $data)) {
        $h = (int) $data['anticipacion_horas'];
        if ($h < 1 || $h > 8760) {
            json_response(['ok' => false, 'error' => 'anticipacion_horas debe estar entre 1 y 8760'], 400);
        }
        $fields[] = 'anticipacion_horas = ?';
        $params[] = $h;
    }
    if (array_key_exists('texto_condiciones', $data)) {
        $fields[] = 'texto_condiciones = ?';
        $t = trim((string) $data['texto_condiciones']);
        $params[] = $t === '' ? null : $t;
    }
    if ($fields === []) {
        json_response(['ok' => false, 'error' => 'Nada que actualizar'], 400);
    }
    $params[] = 1;
    db()->prepare('UPDATE salon_config SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    $st = db()->query('SELECT id, anticipacion_horas, texto_condiciones FROM salon_config WHERE id = 1');
    json_response(['ok' => true, 'config' => $st->fetch()]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
