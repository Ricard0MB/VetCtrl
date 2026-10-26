<?php
// api/modules/auth.php
// Módulo de autenticación

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
// Soportar /auth/login Y index.php?resource=auth&action=login
$action   = $segments[1] ?? $_GET['action'] ?? null;

switch ($action) {

    // ============================================
    // POST /api/auth/login
    // ============================================
    case 'login':
        if ($method !== 'POST') jsonError('Método no permitido', 405);

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
                   u.first_name, u.last_name, u.status,
                   u.phone, u.address, u.ci
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

        jsonSuccess([
            'token' => $token,
            'user'  => $user,
        ], 'Inicio de sesión exitoso');
        break;

    // ============================================
    // POST /api/auth/logout
    // ============================================
    case 'logout':
        if ($method !== 'POST') jsonError('Método no permitido', 405);
        $token = getBearerToken();
        if ($token) {
            $db = getDB();
            $stmt = $db->prepare("DELETE FROM api_tokens WHERE token = :t");
            $stmt->execute([':t' => $token]);
        }
        jsonSuccess(null, 'Sesión cerrada');
        break;

    // ============================================
    // GET /api/auth/me
    // ============================================
    case 'me':
        if ($method !== 'GET') jsonError('Método no permitido', 405);
        $user = requireAuth();
        
        // Traer todos los datos para el perfil
        $db = getDB();
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.role_id, 
                   r.name AS role_name,
                   u.first_name, u.last_name, u.phone, u.address, u.ci, u.status
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $user['id']]);
        $fullUser = $stmt->fetch();
        
        jsonSuccess($fullUser, 'Usuario actual');
        break;

    // ============================================
    // POST /api/auth/register
    // ============================================
    case 'register':
        if ($method !== 'POST') jsonError('Método no permitido', 405);

        $body = getJsonBody();
        $username = trim($body['username'] ?? '');
        $email    = trim($body['email'] ?? '');
        $password = trim($body['password'] ?? '');
        $confirm  = trim($body['confirm_password'] ?? '');

        $errors = [];
        if (empty($username) || empty($email) || empty($password) || empty($confirm)) $errors[] = 'Todos los campos son obligatorios';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
        if ($password !== $confirm) $errors[] = 'Las contraseñas no coinciden';
        if (strlen($password) < 6) $errors[] = 'La contraseña debe tener al menos 6 caracteres';
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = 'El usuario solo puede contener letras, números y guiones bajos';
        if ($errors) jsonError(implode('. ', $errors), 422);

        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
        $stmt->execute([':u' => $username, ':e' => $email]);
        if ($stmt->fetch()) jsonError('Usuario o email ya registrado', 409);

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password, role_id, status, created_at)
            VALUES (:u, :e, :p, 2, 'active', NOW())
        ");
        $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hash]);
        $newId = (int)$db->lastInsertId();

        $token = generateToken($newId);

        jsonSuccess([
            'token' => $token,
            'user'  => [
                'id'        => $newId,
                'username'  => $username,
                'email'     => $email,
                'role_id'   => 2, // 🔥 CORREGIDO: Ahora dice 2 (Propietario) en vez de 3
                'role_name' => 'Propietario',
                'first_name'=> null,
                'last_name' => null,
                'phone'     => null,
                'address'   => null,
            ],
        ], 'Registro exitoso', 201);
        break;

    // ============================================
    // PUT /api/auth/profile — Actualizar perfil
    // ============================================
    case 'profile':
        if ($method !== 'PUT' && $method !== 'POST') jsonError('Método no permitido', 405);

        $user = requireAuth();
        $db = getDB();
        $body = getJsonBody();

        $firstName = trim($body['first_name'] ?? '');
        $lastName  = trim($body['last_name'] ?? '');
        $email     = trim($body['email'] ?? '');
        $phone     = trim($body['phone'] ?? '');
        $address   = trim($body['address'] ?? '');
        $newPassword = $body['new_password'] ?? null;

        if (empty($email)) {
            jsonError('El email es obligatorio', 422);
        }

        // Validar email único
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :e AND id != :id");
        $stmt->execute([':e' => $email, ':id' => $user['id']]);
        if ($stmt->fetch()) jsonError('El email ya está en uso por otro usuario', 409);

        if ($newPassword && strlen($newPassword) >= 6) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET first_name=:fn, last_name=:ln, email=:e, phone=:p, address=:a, password=:pass WHERE id=:id");
            $stmt->execute([':fn'=>$firstName, ':ln'=>$lastName, ':e'=>$email, ':p'=>$phone, ':a'=>$address, ':pass'=>$hash, ':id'=>$user['id']]);
        } elseif ($newPassword) {
            jsonError('La nueva contraseña debe tener al menos 6 caracteres', 422);
        } else {
            $stmt = $db->prepare("UPDATE users SET first_name=:fn, last_name=:ln, email=:e, phone=:p, address=:a WHERE id=:id");
            $stmt->execute([':fn'=>$firstName, ':ln'=>$lastName, ':e'=>$email, ':p'=>$phone, ':a'=>$address, ':id'=>$user['id']]);
        }

        // Devolver el usuario actualizado
        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.role_id, u.first_name, u.last_name, u.phone, u.address, u.ci, r.name AS role_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $user['id']]);
        $updatedUser = $stmt->fetch();

        jsonSuccess($updatedUser, 'Perfil actualizado correctamente');
        break;

    default:
        jsonError('Acción de auth no encontrada: ' . ($action ?? '(vacío)'), 404);
}
