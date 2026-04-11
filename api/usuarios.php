<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    require_admin_or_supervisor();
    $st = db()->query(
        'SELECT u.id, u.username, u.email, u.perfil_id, p.nombre AS perfil_nombre, u.activo, u.creado_en
         FROM usuarios u
         JOIN perfiles p ON p.id = u.perfil_id
         ORDER BY u.id'
    );
    json_response(['ok' => true, 'usuarios' => $st->fetchAll()]);
}

if ($method === 'POST') {
    require_admin();
    require_json_method('POST');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $username = trim((string) ($data['username'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $password = (string) ($data['password'] ?? '');
    $perfilId = (int) ($data['perfil_id'] ?? 0);
    if ($username === '' || $email === '' || $password === '' || $perfilId < 1) {
        json_response(['ok' => false, 'error' => 'Datos incompletos'], 400);
    }
    $st = db()->prepare('SELECT id FROM perfiles WHERE id = ?');
    $st->execute([$perfilId]);
    if (!$st->fetch()) {
        json_response(['ok' => false, 'error' => 'Perfil no válido'], 400);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $ins = db()->prepare(
            'INSERT INTO usuarios (username, email, password_hash, perfil_id, activo) VALUES (?,?,?,?,1)'
        );
        $ins->execute([$username, $email, $hash, $perfilId]);
    } catch (PDOException $e) {
        if ((int) $e->errorInfo[1] === 1062) {
            json_response(['ok' => false, 'error' => 'Usuario o correo ya existen'], 409);
        }
        throw $e;
    }
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId()]);
}

if ($method === 'PATCH') {
    require_admin();
    require_json_method('PATCH');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data) || empty($data['id'])) {
        json_response(['ok' => false, 'error' => 'id requerido'], 400);
    }
    $id = (int) $data['id'];
    $fields = [];
    $params = [];
    if (array_key_exists('email', $data)) {
        $fields[] = 'email = ?';
        $params[] = trim((string) $data['email']);
    }
    if (array_key_exists('perfil_id', $data)) {
        $pid = (int) $data['perfil_id'];
        $st = db()->prepare('SELECT id FROM perfiles WHERE id = ?');
        $st->execute([$pid]);
        if (!$st->fetch()) {
            json_response(['ok' => false, 'error' => 'Perfil no válido'], 400);
        }
        $fields[] = 'perfil_id = ?';
        $params[] = $pid;
    }
    if (array_key_exists('activo', $data)) {
        $fields[] = 'activo = ?';
        $params[] = (int) ((bool) $data['activo']);
    }
    if (!empty($data['password'])) {
        $fields[] = 'password_hash = ?';
        $params[] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
    }
    if ($fields === []) {
        json_response(['ok' => false, 'error' => 'Nada que actualizar'], 400);
    }
    $params[] = $id;
    $sql = 'UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = ?';
    db()->prepare($sql)->execute($params);
    json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    require_admin();
    require_json_method('DELETE');
    $data = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($data) || empty($data['id'])) {
        json_response(['ok' => false, 'error' => 'id requerido'], 400);
    }
    $id = (int) $data['id'];
    if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
        json_response(['ok' => false, 'error' => 'No puede eliminarse a sí mismo'], 400);
    }
    db()->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
