<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}

require_json_method('POST');

$data = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($data)) {
    json_response(['ok' => false, 'error' => 'JSON inválido'], 400);
}

$username = trim((string) ($data['username'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$password2 = (string) ($data['password_confirm'] ?? $password);

if ($username === '' || $email === '' || $password === '') {
    json_response(['ok' => false, 'error' => 'Usuario, correo y contraseña son obligatorios'], 400);
}

if (!preg_match('/^[a-zA-Z0-9._-]{2,64}$/', $username)) {
    json_response([
        'ok' => false,
        'error' => 'El usuario debe tener entre 2 y 64 caracteres (letras, números, punto, guion o guion bajo)',
    ], 400);
}

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 128) {
    json_response(['ok' => false, 'error' => 'Correo electrónico no válido'], 400);
}

if ($password !== $password2) {
    json_response(['ok' => false, 'error' => 'Las contraseñas no coinciden'], 400);
}

if (strlen($password) < 8) {
    json_response(['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres'], 400);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
try {
    $ins = db()->prepare(
        'INSERT INTO usuarios (username, email, password_hash, perfil_id, activo) VALUES (?,?,?,?,1)'
    );
    $ins->execute([$username, $email, $hash, PERFIL_RESIDENTE]);
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        json_response(['ok' => false, 'error' => 'Ese usuario o correo ya está registrado'], 409);
    }
    throw $e;
}

json_response(['ok' => true, 'id' => (int) db()->lastInsertId(), 'mensaje' => 'Cuenta creada. Ya puede entrar al sistema.']);
