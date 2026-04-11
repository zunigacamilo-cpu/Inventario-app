<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$tipos = ['complemento', 'sustituto', 'componente_de', 'incompatible', 'asociado'];

if ($method === 'GET') {
    $insumoId = isset($_GET['insumo_id']) ? (int) $_GET['insumo_id'] : 0;
    if ($insumoId > 0) {
        $st = db()->prepare(
            'SELECT r.id, r.insumo_origen_id, r.insumo_destino_id, r.tipo_relacion, r.cantidad_referencia, r.notas, r.creado_en,
                    o.codigo AS origen_codigo, o.nombre AS origen_nombre,
                    d.codigo AS destino_codigo, d.nombre AS destino_nombre
             FROM relaciones_insumo r
             JOIN insumos o ON o.id = r.insumo_origen_id
             JOIN insumos d ON d.id = r.insumo_destino_id
             WHERE r.insumo_origen_id = ? OR r.insumo_destino_id = ?
             ORDER BY r.id'
        );
        $st->execute([$insumoId, $insumoId]);
    } else {
        $st = db()->query(
            'SELECT r.id, r.insumo_origen_id, r.insumo_destino_id, r.tipo_relacion, r.cantidad_referencia, r.notas, r.creado_en,
                    o.codigo AS origen_codigo, o.nombre AS origen_nombre,
                    d.codigo AS destino_codigo, d.nombre AS destino_nombre
             FROM relaciones_insumo r
             JOIN insumos o ON o.id = r.insumo_origen_id
             JOIN insumos d ON d.id = r.insumo_destino_id
             ORDER BY r.id'
        );
    }
    json_response(['ok' => true, 'relaciones' => $st->fetchAll(), 'tipos_validos' => $tipos]);
}

if ($method === 'POST') {
    require_admin_or_supervisor();
    require_json_method('POST');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $origen = (int) ($data['insumo_origen_id'] ?? 0);
    $destino = (int) ($data['insumo_destino_id'] ?? 0);
    $tipo = (string) ($data['tipo_relacion'] ?? 'asociado');
    if ($origen < 1 || $destino < 1 || $origen === $destino) {
        json_response(['ok' => false, 'error' => 'Origen y destino deben ser insumos distintos'], 400);
    }
    if (!in_array($tipo, $tipos, true)) {
        json_response(['ok' => false, 'error' => 'tipo_relacion no válido'], 400);
    }
    $cant = $data['cantidad_referencia'] ?? null;
    $cant = $cant === null || $cant === '' ? null : (float) $cant;
    $notas = trim((string) ($data['notas'] ?? '')) ?: null;
    try {
        db()->prepare(
            'INSERT INTO relaciones_insumo (insumo_origen_id, insumo_destino_id, tipo_relacion, cantidad_referencia, notas)
             VALUES (?,?,?,?,?)'
        )->execute([$origen, $destino, $tipo, $cant, $notas]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            json_response(['ok' => false, 'error' => 'Ya existe esa relación (mismo par y tipo)'], 409);
        }
        if ((int) $e->errorInfo[1] === 1452) {
            json_response(['ok' => false, 'error' => 'Insumo origen o destino no existe'], 400);
        }
        throw $e;
    }
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

if ($method === 'DELETE') {
    require_admin_or_supervisor();
    require_json_method('DELETE');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data) || empty($data['id'])) {
        json_response(['ok' => false, 'error' => 'id requerido'], 400);
    }
    db()->prepare('DELETE FROM relaciones_insumo WHERE id = ?')->execute([(int) $data['id']]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
