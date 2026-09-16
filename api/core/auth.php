<?php
// api/core/auth.php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/response.php';

function getAuthHeader(): ?string {
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        return $h['Authorization'] ?? $h['authorization'] ?? null;
    }
    return $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
}

function getBearerToken(): ?string {
    $h = getAuthHeader();
    if (!$h) return null;
    if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return trim($m[1]);
    return null;
}

function requireAuth(): array {
    $token = getBearerToken();
    if (!$token) jsonError('Token no proporcionado', 401);

    $db = getDB();
    $stmt = $db->prepare("
        SELECT u.id, u.username, u.email, u.role_id, u.status,
               r.name AS role_name
        FROM api_tokens t
        JOIN users u ON t.user_id = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE t.token = :token AND t.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();

    if (!$user) jsonError('Token inválido o expirado', 401);
    if (($user['status'] ?? 'active') !== 'active') {
        jsonError('Usuario inactivo o suspendido', 403);
    }
    return $user;
}

function requireRole(array $allowedRoles): array {
    $user = requireAuth();
    if (!in_array($user['role_name'], $allowedRoles, true)) {
        jsonError('No tienes permisos para esta acción', 403);
    }
    return $user;
}

function generateToken(int $userId, int $ttlHours = 24 * 7): string {
    $token = bin2hex(random_bytes(32));
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO api_tokens (user_id, token, expires_at)
        VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL :ttl HOUR))
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':ttl', $ttlHours, PDO::PARAM_INT);
    $stmt->execute();
    return $token;
}
