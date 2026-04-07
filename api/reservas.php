<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

function salon_anticipacion_horas(): int
{
    $st = db()->query('SELECT anticipacion_horas FROM salon_config WHERE id = 1 LIMIT 1');
    $row = $st->fetch();
    return $row ? (int) $row['anticipacion_horas'] : 48;
}

function parse_datetime(string $raw): ?DateTimeImmutable
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace('T', ' ', $raw);
    try {
        return new DateTimeImmutable($raw);
    } catch (Exception $e) {
        return null;
    }
}

function hay_solapamiento(DateTimeImmutable $inicio, DateTimeImmutable $fin, ?int $excluirId): bool
{
    $sql = 'SELECT 1 FROM reservas_salon
            WHERE estado IN (\'pendiente\',\'confirmada\')
              AND fin > ? AND inicio < ?
            LIMIT 1';
    $params = [$inicio->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s')];
    if ($excluirId !== null) {
        $sql = 'SELECT 1 FROM reservas_salon
                WHERE estado IN (\'pendiente\',\'confirmada\')
                  AND id <> ?
                  AND fin > ? AND inicio < ?
                LIMIT 1';
        $params = [$excluirId, $inicio->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s')];
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (bool) $st->fetch();
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    require_login();
    $desde = isset($_GET['desde']) ? parse_datetime((string) $_GET['desde']) : null;
    $hasta = isset($_GET['hasta']) ? parse_datetime((string) $_GET['hasta']) : null;
    if ($desde === null) {
        $desde = new DateTimeImmutable('today');
    }
    if ($hasta === null) {
        $hasta = $desde->modify('+62 days');
    }
    $sql = 'SELECT r.id, r.usuario_id, r.inicio, r.fin, r.motivo, r.estado, r.notas_admin, r.creado_en,
                   u.username AS usuario_username
            FROM reservas_salon r
            JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.fin >= ? AND r.inicio <= ?
            ORDER BY r.inicio ASC, r.id ASC';
    $st = db()->prepare($sql);
    $st->execute([$desde->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s')]);
    json_response([
        'ok' => true,
        'reservas' => $st->fetchAll(),
        'anticipacion_horas' => salon_anticipacion_horas(),
    ]);
}

if ($method === 'POST') {
    $u = require_login();
    require_json_method('POST');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $inicio = parse_datetime((string) ($data['inicio'] ?? ''));
    $fin = parse_datetime((string) ($data['fin'] ?? ''));
    $motivo = trim((string) ($data['motivo'] ?? ''));
    if ($inicio === null || $fin === null) {
        json_response(['ok' => false, 'error' => 'inicio y fin son obligatorios (formato fecha y hora)'], 400);
    }
    if ($fin <= $inicio) {
        json_response(['ok' => false, 'error' => 'La hora de fin debe ser posterior al inicio'], 400);
    }
    if ($motivo === '') {
        json_response(['ok' => false, 'error' => 'Indique el motivo o tipo de evento'], 400);
    }
    $ahora = new DateTimeImmutable('now');
    $minInicio = $ahora->modify('+' . salon_anticipacion_horas() . ' hours');
    if ($inicio < $minInicio) {
        json_response([
            'ok' => false,
            'error' => 'Debe reservar con al menos ' . salon_anticipacion_horas() . ' horas de anticipación',
        ], 422);
    }
    if ($inicio < $ahora) {
        json_response(['ok' => false, 'error' => 'No se pueden crear reservas en el pasado'], 400);
    }
    if (hay_solapamiento($inicio, $fin, null)) {
        json_response(['ok' => false, 'error' => 'Ya existe una reserva pendiente o confirmada en ese horario'], 409);
    }
    $ins = db()->prepare(
        'INSERT INTO reservas_salon (usuario_id, inicio, fin, motivo, estado)
         VALUES (?,?,?,?, \'pendiente\')'
    );
    $ins->execute([
        $u['id'],
        $inicio->format('Y-m-d H:i:s'),
        $fin->format('Y-m-d H:i:s'),
        mb_substr($motivo, 0, 500),
    ]);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

if ($method === 'PATCH') {
    $u = require_login();
    require_json_method('PATCH');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data) || empty($data['id'])) {
        json_response(['ok' => false, 'error' => 'id requerido'], 400);
    }
    $id = (int) $data['id'];
    $st = db()->prepare('SELECT id, usuario_id, estado, inicio, fin FROM reservas_salon WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Reserva no encontrada'], 404);
    }
    $esAdmin = $u['perfil_id'] === PERFIL_ADMIN;
    if ($esAdmin) {
        if (empty($data['estado'])) {
            json_response(['ok' => false, 'error' => 'estado requerido'], 400);
        }
        $nuevo = (string) $data['estado'];
        if (!in_array($nuevo, ['confirmada', 'rechazada', 'cancelada', 'pendiente'], true)) {
            json_response(['ok' => false, 'error' => 'Estado no válido'], 400);
        }
        if ($nuevo === 'confirmada' || $nuevo === 'pendiente') {
            $ini = new DateTimeImmutable((string) $row['inicio']);
            $f = new DateTimeImmutable((string) $row['fin']);
            if (hay_solapamiento($ini, $f, $id)) {
                json_response(['ok' => false, 'error' => 'No se puede dejar en ese estado: hay solape con otra reserva activa'], 409);
            }
        }
        $sets = ['estado = ?'];
        $params = [$nuevo];
        if (array_key_exists('notas_admin', $data)) {
            $t = trim((string) $data['notas_admin']);
            $sets[] = 'notas_admin = ?';
            $params[] = $t === '' ? null : mb_substr($t, 0, 500);
        }
        $params[] = $id;
        db()->prepare('UPDATE reservas_salon SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
        json_response(['ok' => true]);
    }
    // Residente o supervisor: solo cancelar la propia si está pendiente
    if ((int) $row['usuario_id'] !== $u['id']) {
        json_response(['ok' => false, 'error' => 'Solo puede modificar sus propias reservas'], 403);
    }
    if ($row['estado'] !== 'pendiente') {
        json_response(['ok' => false, 'error' => 'Solo puede cancelar reservas pendientes de aprobación'], 400);
    }
    $accion = (string) ($data['estado'] ?? '');
    if ($accion !== 'cancelada') {
        json_response(['ok' => false, 'error' => 'Solo se admite cancelar (estado cancelada)'], 400);
    }
    db()->prepare('UPDATE reservas_salon SET estado = \'cancelada\' WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
