<?php
// api/modules/prices.php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$user = requireAuth();
$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// GET: Cualquier usuario autenticado puede ver los precios vigentes
if ($method === 'GET') {
    $stmt = $db->query("SELECT id, service_key, label, price_usd FROM service_prices ORDER BY id ASC");
    $prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonSuccess($prices);
}

// POST / PUT: Solo el administrador puede actualizar los precios
if ($method === 'POST' || $method === 'PUT') {
    // Ajusta esta validación según cómo guardes el rol en tu $user (ej. 'admin' o role_id == 1)
    if (($user['role'] ?? '') !== 'admin') {
        jsonError('No tienes permisos para modificar tarifas.', 403);
    }

    $body = getJsonBody();
    $items = $body['prices'] ?? [];

    if (empty($items) || !is_array($items)) {
        jsonError('Datos de tarifas inválidos.', 400);
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE service_prices SET price_usd = ? WHERE service_key = ?");

        foreach ($items as $item) {
            $key = $item['service_key'] ?? '';
            $price = floatval($item['price_usd'] ?? 0);

            if ($price <= 0) {
                throw new Exception("El precio para '$key' debe ser mayor a 0.");
            }
            $stmt->execute([$price, $key]);
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
