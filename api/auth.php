<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET' && ($_GET['action'] ?? '') === 'me') {
    $u = current_user();
    if ($u === null) {
        json_response(['ok' => true, 'user' => null]);
    }
    json_response(['ok' => true, 'user' => user_public_payload($u)]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'login') {
    require_json_method('POST');
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
    }
    $user = trim((string) ($data['username'] ?? ''));
    $pass = (string) ($data['password'] ?? '');
    if ($user === '' || $pass === '') {
        json_response(['ok' => false, 'error' => 'Usuario y contraseña requeridos'], 400);
    }
    $st = db()->prepare(
        'SELECT u.id, u.password_hash, u.activo FROM usuarios u WHERE u.username = ?'
    );
    $st->execute([$user]);
    $row = $st->fetch();
    if (!$row || !(int) $row['activo'] || !password_verify($pass, $row['password_hash'])) {
        json_response(['ok' => false, 'error' => 'Credenciales incorrectas o usuario inactivo'], 401);
    }
    load_user_into_session((int) $row['id']);
    json_response(['ok' => true, 'user' => user_public_payload(current_user())]);
}

if ($method === 'POST' && ($_GET['action'] ?? '') === 'logout') {
    require_json_method('POST');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'Acción no válida'], 404);
