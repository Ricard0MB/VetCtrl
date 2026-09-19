<?php
// api/modules/owners.php
// Módulo de propietarios (dueños de mascotas)

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // GET /api/owners — Listar dueños (admin/vet ven todos, propietario solo se ve a sí mismo)
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();

        $sql = "SELECT 
                    u.id, u.username, u.email, u.ci,
                    u.first_name, u.last_name, u.phone, u.address,
                    u.status, u.created_at,
                    (SELECT COUNT(*) FROM pets WHERE owner_id = u.id) AS pets_count
                FROM users u
                WHERE u.role_id = 2";
        $params = [];

        if ($user['role_name'] === 'Propietario') {
            $sql .= " AND u.id = :id";
            $params[':id'] = $user['id'];
        } else {
            // Filtros opcionales para admin/vet
            if (!empty($_GET['q'])) {
                $sql .= " AND (u.username LIKE :q OR u.email LIKE :q 
                          OR u.ci LIKE :q OR u.first_name LIKE :q OR u.last_name LIKE :q)";
                $params[':q'] = '%' . $_GET['q'] . '%';
            }
            if (!empty($_GET['status'])) {
                $sql .= " AND u.status = :status";
                $params[':status'] = $_GET['status'];
            }
        }

        $sql .= " ORDER BY u.first_name ASC, u.last_name ASC LIMIT 200";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Listado de propietarios');
        break;

    // GET /api/owners/{id} — Detalle de un dueño con sus mascotas
    case $method === 'GET' && is_numeric($action):
        $user = requireAuth();
        $db = getDB();
        $ownerId = (int)$action;

        // Propietario solo puede verse a sí mismo
        if ($user['role_name'] === 'Propietario' && $ownerId !== (int)$user['id']) {
            jsonError('No tienes permiso para ver este propietario', 403);
        }

        $stmt = $db->prepare("
            SELECT u.id, u.username, u.email, u.ci,
                   u.first_name, u.last_name, u.phone, u.address,
                   u.status, u.created_at
            FROM users u
            WHERE u.id = :id AND u.role_id = 2
        ");
        $stmt->execute([':id' => $ownerId]);
        $owner = $stmt->fetch();

        if (!$owner) jsonError('Propietario no encontrado', 404);

        // Mascotas del dueño
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.date_of_birth, p.gender,
                   pt.name AS species_name,
                   b.name AS breed_name
            FROM pets p
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            LEFT JOIN breeds b ON p.breed_id = b.id
            WHERE p.owner_id = :id
            ORDER BY p.name ASC
        ");
        $stmt->execute([':id' => $ownerId]);
        $pets = $stmt->fetchAll();

        jsonSuccess([
            'owner' => $owner,
            'pets'  => $pets,
        ], 'Detalle del propietario');
        break;

    default:
        jsonError('Ruta de owners no encontrada', 404);
}
