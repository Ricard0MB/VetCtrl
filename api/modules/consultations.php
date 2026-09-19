<?php
// api/modules/consultations.php
// Módulo CRUD de consultas médicas

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // GET /api/consultations — Listar (según rol)
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();

        $sql = "SELECT 
                    c.id, c.consultation_date, c.reason, c.diagnosis, c.treatment, c.notes,
                    c.pet_id, c.attendant_id, c.created_at,
                    p.name AS pet_name,
                    pt.name AS species_name,
                    u.username AS vet_name
                FROM consultations c
                LEFT JOIN pets p ON c.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN users u ON c.attendant_id = u.id
                WHERE 1=1";
        $params = [];

        if ($user['role_name'] === 'Propietario') {
            $sql .= " AND p.owner_id = :owner_id";
            $params[':owner_id'] = $user['id'];
        } elseif ($user['role_name'] === 'Veterinario') {
            $sql .= " AND c.attendant_id = :attendant_id";
            $params[':attendant_id'] = $user['id'];
        }

        if (!empty($_GET['pet_id'])) {
            $sql .= " AND c.pet_id = :pet_id";
            $params[':pet_id'] = (int)$_GET['pet_id'];
        }

        $sql .= " ORDER BY c.consultation_date DESC LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Listado de consultas');
        break;

    // GET /api/consultations/{id} — Detalle
    case $method === 'GET' && is_numeric($action):
        $user = requireAuth();
        $db = getDB();
        $cId = (int)$action;

        $stmt = $db->prepare("
            SELECT c.*, p.name AS pet_name, pt.name AS species_name,
                   b.name AS breed_name, u.username AS vet_name,
                   p.owner_id
            FROM consultations c
            LEFT JOIN pets p ON c.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            LEFT JOIN breeds b ON p.breed_id = b.id
            LEFT JOIN users u ON c.attendant_id = u.id
            WHERE c.id = :id
        ");
        $stmt->execute([':id' => $cId]);
        $c = $stmt->fetch();

        if (!$c) jsonError('Consulta no encontrada', 404);

        if ($user['role_name'] === 'Propietario' && (int)$c['owner_id'] !== (int)$user['id']) {
            jsonError('No tienes permiso para ver esta consulta', 403);
        }

        jsonSuccess($c, 'Detalle de consulta');
        break;

    // POST /api/consultations — Crear
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $body = getJsonBody();

        $petId  = (int)($body['pet_id'] ?? 0);
        $date   = trim($body['consultation_date'] ?? '');
        $reason = trim($body['reason'] ?? '');
        $diagnosis = trim($body['diagnosis'] ?? '');
        $treatment = $body['treatment'] ?? null;
        $notes  = $body['notes'] ?? null;

        if ($petId === 0 || $date === '' || $diagnosis === '') {
            jsonError('Mascota, fecha y diagnóstico son obligatorios', 422);
        }

        $ts = strtotime($date);
        if (!$ts) jsonError('Fecha inválida', 422);

        // Validar mascota
        $stmt = $db->prepare("SELECT id FROM pets WHERE id = :id");
        $stmt->execute([':id' => $petId]);
        if (!$stmt->fetch()) jsonError('Mascota no encontrada', 404);

        $formatted = date('Y-m-d H:i:s', $ts);

        $stmt = $db->prepare("
            INSERT INTO consultations (pet_id, attendant_id, consultation_date, reason, diagnosis, treatment, notes, created_at)
            VALUES (:pet_id, :attendant_id, :date, :reason, :diagnosis, :treatment, :notes, NOW())
        ");
        $stmt->execute([
            ':pet_id' => $petId,
            ':attendant_id' => $user['id'],
            ':date' => $formatted,
            ':reason' => $reason,
            ':diagnosis' => $diagnosis,
            ':treatment' => $treatment,
            ':notes' => $notes,
        ]);
        $newId = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Consulta #$newId creada vía API para mascota $petId",
           ]);

        jsonSuccess(['id' => $newId], 'Consulta registrada', 201);
        break;

    // PUT /api/consultations/{id} — Actualizar
    case $method === 'PUT' && is_numeric($action):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $cId = (int)$action;
        $body = getJsonBody();

        $stmt = $db->prepare("SELECT id, attendant_id FROM consultations WHERE id = :id");
        $stmt->execute([':id' => $cId]);
        $c = $stmt->fetch();
        if (!$c) jsonError('Consulta no encontrada', 404);

        if ($user['role_name'] === 'Veterinario' && (int)$c['attendant_id'] !== (int)$user['id']) {
            jsonError('No puedes editar consultas de otros veterinarios', 403);
        }

        $fields = [];
        $params = [':id' => $cId];
        $allowed = ['consultation_date', 'reason', 'diagnosis', 'treatment', 'notes'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Nada que actualizar', 422);

        $sql = "UPDATE consultations SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Consulta #$cId actualizada vía API",
           ]);

        jsonSuccess(null, 'Consulta actualizada');
        break;

    default:
        jsonError('Ruta de consultations no encontrada. Método: ' . $method . ' acción: ' . ($action ?? 'null'), 404);
}
