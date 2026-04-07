<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $st = db()->query(
        'SELECT i.id, i.codigo, i.nombre, i.descripcion, i.categoria_id, c.nombre AS categoria_nombre,
                i.unidad_medida, i.stock_actual, i.stock_minimo, i.ubicacion, i.activo, i.creado_en
         FROM insumos i
         LEFT JOIN categorias_insumo c ON c.id = i.categoria_id
         ORDER BY i.codigo'
    );
    json_response(['ok' => true, 'insumos' => $st->fetchAll()]);
}

if ($method === 'POST') {
    require_json_method('POST');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $codigo = trim((string) ($data['codigo'] ?? ''));
    $nombre = trim((string) ($data['nombre'] ?? ''));
    if ($codigo === '' || $nombre === '') {
        json_response(['ok' => false, 'error' => 'Código y nombre requeridos'], 400);
    }
    $cat = $data['categoria_id'] ?? null;
    $cat = $cat === null || $cat === '' ? null : (int) $cat;
    $ins = db()->prepare(
        'INSERT INTO insumos (codigo, nombre, descripcion, categoria_id, unidad_medida, stock_actual, stock_minimo, ubicacion, activo)
         VALUES (?,?,?,?,?,?,?,?,?)'
    );
    try {
        $ins->execute([
            $codigo,
            $nombre,
            trim((string) ($data['descripcion'] ?? '')) ?: null,
            $cat,
            trim((string) ($data['unidad_medida'] ?? 'unidad')) ?: 'unidad',
            (float) ($data['stock_actual'] ?? 0),
            (float) ($data['stock_minimo'] ?? 0),
            trim((string) ($data['ubicacion'] ?? '')) ?: null,
            (int) (($data['activo'] ?? true) ? 1 : 0),
        ]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            json_response(['ok' => false, 'error' => 'Código de insumo duplicado'], 409);
        }
        throw $e;
    }
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

if ($method === 'PATCH') {
    require_json_method('PATCH');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data) || empty($data['id'])) {
        json_response(['ok' => false, 'error' => 'id requerido'], 400);
    }
    $id = (int) $data['id'];
    $fields = [];
    $params = [];
    $map = [
        'codigo' => 'codigo',
        'nombre' => 'nombre',
        'descripcion' => 'descripcion',
        'unidad_medida' => 'unidad_medida',
        'stock_actual' => 'stock_actual',
        'stock_minimo' => 'stock_minimo',
        'ubicacion' => 'ubicacion',
    ];
    foreach ($map as $key => $col) {
        if (array_key_exists($key, $data)) {
            $fields[] = "$col = ?";
            $params[] = $data[$key];
        }
    }
    if (array_key_exists('categoria_id', $data)) {
        $fields[] = 'categoria_id = ?';
        $v = $data['categoria_id'];
        $params[] = $v === null || $v === '' ? null : (int) $v;
    }
    if (array_key_exists('activo', $data)) {
        $fields[] = 'activo = ?';
        $params[] = (int) ((bool) $data['activo']);
    }
    if ($fields === []) {
        json_response(['ok' => false, 'error' => 'Nada que actualizar'], 400);
    }
    $params[] = $id;
    try {
        db()->prepare('UPDATE insumos SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            json_response(['ok' => false, 'error' => 'Código duplicado'], 409);
        }
        throw $e;
    }
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    require_json_method('DELETE');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data) || empty($data['id'])) {
        json_response(['ok' => false, 'error' => 'id requerido'], 400);
    }
    db()->prepare('DELETE FROM insumos WHERE id = ?')->execute([(int) $data['id']]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
