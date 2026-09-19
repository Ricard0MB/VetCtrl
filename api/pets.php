<?php
// api/pets.php
// Wrapper de compatibilidad: redirige al módulo de pets nuevo
// Soporta ?owner_id=X para compatibilidad con la app vieja

require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/auth.php';

handleCors();

$user = requireAuth(); // Requiere token válido
$db = getDB();

$ownerId = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;

$sql = "SELECT 
            p.id, p.name, p.date_of_birth, p.gender,
            p.medical_history, p.created_at,
            p.owner_id, p.attendant_id,
            pt.name AS species_name,
            b.name  AS breed_name,
            u.username AS owner_name,
            u.email    AS owner_email
        FROM pets p
        LEFT JOIN pet_types pt ON p.type_id = pt.id
        LEFT JOIN breeds   b  ON p.breed_id = b.id
        LEFT JOIN users    u  ON p.owner_id = u.id
        WHERE 1=1";
$params = [];

if ($ownerId > 0) {
    $sql .= " AND p.owner_id = :owner_id";
    $params[':owner_id'] = $ownerId;
} elseif ($user['role_name'] === 'Propietario') {
    $sql .= " AND p.owner_id = :owner_id";
    $params[':owner_id'] = $user['id'];
} elseif ($user['role_name'] === 'Veterinario') {
    $sql .= " AND p.attendant_id = :attendant_id";
    $params[':attendant_id'] = $user['id'];
}

$sql .= " ORDER BY p.created_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$pets = $stmt->fetchAll();

// ⚠️ Formato NUEVO compatible con el viejo:
// El viejo devolvía {success, data: [...], count}
// Pero también añadimos {species_name, breed_name} para que el HTML nuevo funcione
jsonSuccess($pets, 'Listado de mascotas');
