<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

const PERFIL_ADMIN = 1;
const PERFIL_RESIDENTE = 2;
const PERFIL_SUPERVISOR = 3;

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'perfil_id' => (int) $_SESSION['perfil_id'],
        'perfil_nombre' => (string) ($_SESSION['perfil_nombre'] ?? ''),
    ];
}

/** Datos de usuario expuestos al front (permisos calculados en servidor). */
function user_public_payload(?array $u): ?array
{
    if ($u === null) {
        return null;
    }
    $pid = (int) $u['perfil_id'];
    return [
        'id' => (int) $u['id'],
        'username' => (string) $u['username'],
        'perfil_id' => $pid,
        'perfil_nombre' => (string) ($u['perfil_nombre'] ?? ''),
        'es_admin' => $pid === PERFIL_ADMIN,
        'es_supervisor' => $pid === PERFIL_SUPERVISOR,
        'puede_gestionar_inventario' => $pid === PERFIL_ADMIN || $pid === PERFIL_SUPERVISOR,
        'puede_gestionar_reservas' => $pid === PERFIL_ADMIN || $pid === PERFIL_SUPERVISOR,
        'puede_listar_usuarios' => $pid === PERFIL_ADMIN || $pid === PERFIL_SUPERVISOR,
    ];
}

function require_login(): array
{
    $u = current_user();
    if ($u === null) {
        json_response(['ok' => false, 'error' => 'No autenticado'], 401);
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['perfil_id'] !== PERFIL_ADMIN) {
        json_response(['ok' => false, 'error' => 'Solo el administrador puede realizar esta acción'], 403);
    }
    return $u;
}

function require_admin_or_supervisor(): array
{
    $u = require_login();
    if ($u['perfil_id'] !== PERFIL_ADMIN && $u['perfil_id'] !== PERFIL_SUPERVISOR) {
        json_response(['ok' => false, 'error' => 'Acción no permitida para este perfil'], 403);
    }
    return $u;
}

function load_user_into_session(int $userId): void
{
    $st = db()->prepare(
        'SELECT u.id, u.username, u.perfil_id, p.nombre AS perfil_nombre
         FROM usuarios u
         JOIN perfiles p ON p.id = u.perfil_id
         WHERE u.id = ? AND u.activo = 1'
    );
    $st->execute([$userId]);
    $row = $st->fetch();
    if (!$row) {
        json_response(['ok' => false, 'error' => 'Usuario no válido'], 401);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['perfil_id'] = (int) $row['perfil_id'];
    $_SESSION['perfil_nombre'] = $row['perfil_nombre'];
}
