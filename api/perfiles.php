<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

require_login();

$st = db()->query('SELECT id, nombre, descripcion FROM perfiles ORDER BY id');
json_response(['ok' => true, 'perfiles' => $st->fetchAll()]);
