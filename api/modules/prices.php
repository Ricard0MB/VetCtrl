<?php
// api/modules/prices.php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$user = requireAuth();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// Helper para verificar si el usuario autenticado es administrador
// En roles: 1 = Veterinario, 2 = Propietario, 3 = admin
function esAdmin(array $user): bool {
    $roleId = intval($user['role_id'] ?? $user['id_rol'] ?? 0);
    $roleName = strtolower(trim((string)($user['role'] ?? $user['rol'] ?? $user['role_name'] ?? '')));

    return ($roleId === 3) || in_array($roleName, ['admin', 'administrador'], true);
}

// GET: Cualquier usuario autenticado puede ver los precios vigentes
if ($method === 'GET') {
    try {
        $stmt = $db->query("SELECT id, service_key, label, price_usd FROM service_prices ORDER BY id ASC");
        $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonSuccess($prices);
    } catch (Exception $e) {
        jsonError('Error al obtener las tarifas: ' . $e->getMessage(), 500);
    }
}

// POST / PUT: Solo el administrador puede actualizar los precios
if ($method === 'POST' || $method === 'PUT') {
    if (!esAdmin($user)) {
        jsonError('No tienes permisos para modificar tarifas.', 403);
    }

    $body = getJsonBody();
    $items = $body['prices'] ?? null;

    if (!is_array($items) || empty($items)) {
        jsonError('Datos de tarifas inválidos.', 400);
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE service_prices SET price_usd = ? WHERE service_key = ?");

        foreach ($items as $item) {
            $key = trim((string)($item['service_key'] ?? ''));
            $price = filter_var($item['price_usd'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($key === '' || $price === false || $price <= 0) {
                throw new Exception("El precio para '{$key}' debe ser un número mayor a 0.");
            }

            $stmt->execute([round($price, 2), $key]);
        }

        $db->commit();
        jsonSuccess(['message' => 'Precios actualizados correctamente.']);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        jsonError($e->getMessage(), 400);
    }
}

jsonError('Método no permitido.', 405);
