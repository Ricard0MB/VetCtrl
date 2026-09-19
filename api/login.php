<?php
// api/login.php
// Wrapper de compatibilidad: redirige al módulo de auth nuevo

require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/router.php';
require_once __DIR__ . '/core/auth.php';

handleCors();

if (getMethod() !== 'POST') {
    jsonError('Método no permitido. Use POST.', 405);
}

$body = getJsonBody();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if (empty($username) || empty($password)) {
    jsonError('Usuario/Email y contraseña son obligatorios', 400);
}

$db = getDB();
$stmt = $db->prepare("
    SELECT u.id, u.username, u.email, u.password, u.role_id,
           r.name AS role_name,
           u.first_name, u.last_name, u.status
    FROM users u
    INNER JOIN roles r ON u.role_id = r.id
    WHERE u.username = :u OR u.email = :e
    LIMIT 1
");
$stmt->execute([':u' => $username, ':e' => $username]);
$user = $stmt->fetch();

if (!$user) jsonError('Usuario/Email no encontrado', 401);
if (($user['status'] ?? 'active') !== 'active') jsonError('Usuario inactivo o suspendido', 403);
if (!password_verify($password, $user['password'])) jsonError('Contraseña incorrecta', 401);

$token = generateToken((int)$user['id']);
unset($user['password']);

// ⚠️ Formato NUEVO (con token + user):
jsonSuccess([
    'token' => $token,
    'user'  => $user,
], 'Inicio de sesión exitoso');
