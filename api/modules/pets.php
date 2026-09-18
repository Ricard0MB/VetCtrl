<?php
// api/modules/pets.php
// Módulo CRUD de mascotas — compatible con Render (lee id/action de query o segmentos)

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
// Acepta /pets/123  Y  index.php?resource=pets&id=123
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // ==========================================================
    // GET /api/pets — Listar mascotas (según rol)
    // ==========================================================
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();
        $p = getPagination();

        $search  = trim($_GET['q'] ?? '');
        $species = (int)($_GET['species'] ?? 0);
        $owner   = (int)($_GET['owner'] ?? 0);

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

        if ($user['role_name'] === 'Propietario') {
            $sql .= " AND p.owner_id = :owner_id";
            $params[':owner_id'] = $user['id'];
        } elseif ($user['role_name'] === 'Veterinario') {
            $sql .= " AND p.attendant_id = :attendant_id";
            $params[':attendant_id'] = $user['id'];
        }

        if ($search !== '') {
            $sql .= " AND (p.name LIKE :s OR pt.name LIKE :s OR b.name LIKE :s OR u.username LIKE :s)";
            $params[':s'] = "%$search%";
        }
        if ($species > 0) {
            $sql .= " AND p.type_id = :species";
            $params[':species'] = $species;
        }
        if ($owner > 0 && $user['role_name'] !== 'Propietario') {
            $sql .= " AND p.owner_id = :owner_filter";
            $params[':owner_filter'] = $owner;
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $p['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $p['offset'], PDO::PARAM_INT);
        $stmt->execute();
        $pets = $stmt->fetchAll();

        jsonSuccess([
            'pets'  => $pets,
            'page'  => $p['page'],
            'limit' => $p['limit'],
            'total' => count($pets),
        ], 'Listado de mascotas');
        break;

    // ==========================================================
    // GET /api/pets/search?q=... — Búsqueda
    // ==========================================================
    case $method === 'GET' && $action === 'search':
        $user = requireAuth();
        $db = getDB();

        $q = trim($_GET['q'] ?? '');
        if ($q === '') jsonError('Falta parámetro q', 400);

        $sql = "SELECT 
                    p.id, p.name, pt.name AS species_name, b.name AS breed_name,
                    p.date_of_birth, p.gender,
                    u.username AS owner_name, u.ci AS owner_ci, u.id AS owner_id
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds   b  ON p.breed_id = b.id
                INNER JOIN users   u  ON p.owner_id = u.id
                WHERE (p.name LIKE :q OR pt.name LIKE :q OR b.name LIKE :q 
                       OR u.username LIKE :q OR u.ci LIKE :q)";
        $params = [':q' => "%$q%"];

        if ($user['role_name'] === 'Propietario') {
            $sql .= " AND p.owner_id = :owner_id";
            $params[':owner_id'] = $user['id'];
        }

        $sql .= " ORDER BY p.name ASC LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Resultados de búsqueda');
        break;

    // ==========================================================
    // GET /api/pets/{id} — Detalle con historial completo
    // ==========================================================
    case $method === 'GET' && is_numeric($action):
        $user = requireAuth();
        $db = getDB();
        $petId = (int)$action;

        $stmt = $db->prepare("
            SELECT p.*, 
                   pt.name AS species_name, 
                   b.name  AS breed_name,
                   u.username AS owner_name, u.email AS owner_email,
                   u.ci AS owner_ci, u.phone AS owner_phone, u.address AS owner_address
            FROM pets p
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            LEFT JOIN breeds   b  ON p.breed_id = b.id
            LEFT JOIN users    u  ON p.owner_id = u.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $petId]);
        $pet = $stmt->fetch();

        if (!$pet) jsonError('Mascota no encontrada', 404);

        if ($user['role_name'] === 'Propietario' && (int)$pet['owner_id'] !== (int)$user['id']) {
            jsonError('No tienes permiso para ver esta mascota', 403);
        }

        $stmt = $db->prepare("
            SELECT c.id, c.consultation_date, c.reason, c.diagnosis, c.treatment, c.notes,
                   u.username AS vet_name
            FROM consultations c
            LEFT JOIN users u ON c.attendant_id = u.id
            WHERE c.pet_id = :id
            ORDER BY c.consultation_date DESC
        ");
        $stmt->execute([':id' => $petId]);
        $consultations = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT v.id, v.application_date, v.next_due_date, v.lote_number, v.notes,
                   vt.name AS vaccine_name, vt.species_target
            FROM vaccines v
            LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
            WHERE v.pet_id = :id
            ORDER BY v.application_date DESC
        ");
        $stmt->execute([':id' => $petId]);
        $vaccines = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT t.id, t.title, t.treatment_name, t.start_date, t.end_date,
                   t.diagnosis, t.medication_details, t.notes, t.status, t.created_at
            FROM treatments t
            WHERE t.pet_id = :id
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([':id' => $petId]);
        $treatments = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT a.id, a.appointment_date, a.reason, a.status,
                   u.username AS vet_name
            FROM appointments a
            LEFT JOIN users u ON a.attendant_id = u.id
            WHERE a.pet_id = :id
            ORDER BY a.appointment_date DESC
        ");
        $stmt->execute([':id' => $petId]);
        $appointments = $stmt->fetchAll();

        jsonSuccess([
            'pet'           => $pet,
            'consultations' => $consultations,
            'vaccines'      => $vaccines,
            'treatments'    => $treatments,
            'appointments'  => $appointments,
        ], 'Detalle de mascota');
        break;

    // ==========================================================
    // POST /api/pets — Crear mascota
    // ==========================================================
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Veterinario', 'admin', 'Propietario']);
        $db = getDB();
        $body = getJsonBody();

        $name    = trim($body['name'] ?? '');
        $typeId  = (int)($body['type_id'] ?? 0);
        $breedId = !empty($body['breed_id']) ? (int)$body['breed_id'] : null;
        $dob     = !empty($body['date_of_birth']) ? $body['date_of_birth'] : null;
        $gender  = $body['gender'] ?? null;
        $medical = $body['medical_history'] ?? null;

        if ($name === '' || $typeId === 0) {
            jsonError('Nombre y especie son obligatorios', 422);
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            jsonError('El nombre debe tener entre 2 y 50 caracteres', 422);
        }
        if (!preg_match('/[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/u', $name)) {
            jsonError('El nombre debe contener al menos una letra', 422);
        }

        if ($user['role_name'] === 'Propietario') {
            $ownerId = (int)$user['id'];
        } else {
            $ownerId = (int)($body['owner_id'] ?? 0);
            if ($ownerId <= 0) jsonError('Debe especificar owner_id', 422);
        }

        $stmt = $db->prepare("SELECT id FROM pet_types WHERE id = :id");
        $stmt->execute([':id' => $typeId]);
        if (!$stmt->fetch()) jsonError('Especie no válida', 422);

        if ($breedId !== null) {
            $stmt = $db->prepare("SELECT id FROM breeds WHERE id = :id AND type_id = :t");
            $stmt->execute([':id' => $breedId, ':t' => $typeId]);
            if (!$stmt->fetch()) jsonError('Raza no válida para esa especie', 422);
        }

        $stmt = $db->prepare("
            INSERT INTO pets (owner_id, attendant_id, name, type_id, breed_id, date_of_birth, gender, medical_history, created_at)
            VALUES (:owner_id, :attendant_id, :name, :type_id, :breed_id, :dob, :gender, :medical, NOW())
        ");
        $stmt->execute([
            ':owner_id'     => $ownerId,
            ':attendant_id' => (int)$user['id'],
            ':name'         => $name,
            ':type_id'      => $typeId,
            ':breed_id'     => $breedId,
            ':dob'          => $dob,
            ':gender'       => $gender,
            ':medical'      => $medical,
        ]);
        $newId = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Nueva mascota registrada vía API: '$name' (ID $newId)",
           ]);

        jsonSuccess(['id' => $newId], 'Mascota registrada', 201);
        break;

    // ==========================================================
    // PUT /api/pets/{id} — Actualizar
    // ==========================================================
    case $method === 'PUT' && is_numeric($action):
        $user = requireAuth();
        $db = getDB();
        $petId = (int)$action;
        $body = getJsonBody();

        $stmt = $db->prepare("SELECT owner_id FROM pets WHERE id = :id");
        $stmt->execute([':id' => $petId]);
        $pet = $stmt->fetch();
        if (!$pet) jsonError('Mascota no encontrada', 404);

        if ($user['role_name'] === 'Propietario' && (int)$pet['owner_id'] !== (int)$user['id']) {
            jsonError('No tienes permiso para editar esta mascota', 403);
        }

        $fields = [];
        $params = [':id' => $petId];
        $allowed = ['name', 'type_id', 'breed_id', 'date_of_birth', 'gender', 'medical_history'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Nada que actualizar', 422);

        $sql = "UPDATE pets SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Mascota ID $petId actualizada vía API",
           ]);

        jsonSuccess(null, 'Mascota actualizada');
        break;

    // ==========================================================
    // DELETE /api/pets/{id} — Eliminar con cascada manual
    // ==========================================================
    case $method === 'DELETE' && is_numeric($action):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $petId = (int)$action;

        $stmt = $db->prepare("SELECT name FROM pets WHERE id = :id");
        $stmt->execute([':id' => $petId]);
        $pet = $stmt->fetch();
        if (!$pet) jsonError('Mascota no encontrada', 404);

        try {
            $db->beginTransaction();

            $db->prepare("DELETE FROM consultations WHERE pet_id = :id")->execute([':id' => $petId]);
            $db->prepare("DELETE FROM appointments  WHERE pet_id = :id")->execute([':id' => $petId]);
            $db->prepare("DELETE FROM vaccines      WHERE pet_id = :id")->execute([':id' => $petId]);
            $db->prepare("DELETE FROM treatments    WHERE pet_id = :id")->execute([':id' => $petId]);

            $db->prepare("DELETE FROM pets WHERE id = :id")->execute([':id' => $petId]);

            $db->commit();

            $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
               ->execute([
                   ':r' => $user['role_id'],
                   ':u' => $user['username'],
                   ':a' => "Mascota '{$pet['name']}' (ID $petId) eliminada vía API",
               ]);

            jsonSuccess(null, 'Mascota eliminada correctamente');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonError('Error al eliminar: ' . $e->getMessage(), 500);
        }
        break;

    // ==========================================================
    // Fallback
    // ==========================================================
    default:
        jsonError('Ruta de pets no encontrada. Método: ' . $method . ' acción: ' . ($action ?? 'null'), 404);
}
