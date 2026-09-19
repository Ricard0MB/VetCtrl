<?php
// api/modules/vaccines.php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // GET /api/vaccines — Listar (con filtros)
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();

        $sql = "SELECT 
                    v.id, v.application_date, v.next_due_date, v.lote_number, v.notes,
                    v.pet_id, v.attendant_id, v.created_at,
                    p.name AS pet_name,
                    pt.name AS species_name,
                    vt.name AS vaccine_name,
                    vt.species_target
                FROM vaccines v
                LEFT JOIN pets p ON v.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
                WHERE 1=1";
        $params = [];

        if ($user['role_name'] === 'Propietario') {
            $sql .= " AND p.owner_id = :owner_id";
            $params[':owner_id'] = $user['id'];
        } elseif ($user['role_name'] === 'Veterinario') {
            $sql .= " AND v.attendant_id = :attendant_id";
            $params[':attendant_id'] = $user['id'];
        }

        if (!empty($_GET['pet_id'])) {
            $sql .= " AND v.pet_id = :pet_id";
            $params[':pet_id'] = (int)$_GET['pet_id'];
        }

        // Filtro especial: alertas (vencidas o próximas 60 días)
        if (!empty($_GET['alerts']) && $_GET['alerts'] === '1') {
            $sql .= " AND v.next_due_date IS NOT NULL 
                      AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
                      AND v.next_due_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)";
        }

        $sql .= " ORDER BY v.application_date DESC LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Listado de vacunas');
        break;

    // POST /api/vaccines — Registrar vacuna
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $body = getJsonBody();

        $petId = (int)($body['pet_id'] ?? 0);
        $typeId = (int)($body['vaccine_type_id'] ?? 0);
        $appDate = trim($body['application_date'] ?? '');
        $nextDue = !empty($body['next_due_date']) ? $body['next_due_date'] : null;
        $lote = $body['lote_number'] ?? null;
        $notes = $body['notes'] ?? null;

        if ($petId === 0 || $typeId === 0 || $appDate === '') {
            jsonError('Mascota, tipo de vacuna y fecha son obligatorios', 422);
        }

        $ts = strtotime($appDate);
        if (!$ts) jsonError('Fecha de aplicación inválida', 422);

        $stmt = $db->prepare("SELECT id FROM pets WHERE id = :id");
        $stmt->execute([':id' => $petId]);
        if (!$stmt->fetch()) jsonError('Mascota no encontrada', 404);

        $stmt = $db->prepare("SELECT id FROM vaccine_types WHERE id = :id");
        $stmt->execute([':id' => $typeId]);
        if (!$stmt->fetch()) jsonError('Tipo de vacuna no válido', 422);

        $stmt = $db->prepare("
            INSERT INTO vaccines (pet_id, attendant_id, vaccine_type_id, application_date, next_due_date, lote_number, notes, created_at)
            VALUES (:pet_id, :attendant_id, :type_id, :app_date, :next_due, :lote, :notes, NOW())
        ");
        $stmt->execute([
            ':pet_id' => $petId,
            ':attendant_id' => $user['id'],
            ':type_id' => $typeId,
            ':app_date' => date('Y-m-d', $ts),
            ':next_due' => $nextDue,
            ':lote' => $lote,
            ':notes' => $notes,
        ]);
        $newId = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Vacuna #$newId aplicada a mascota $petId vía API",
           ]);

        jsonSuccess(['id' => $newId], 'Vacuna registrada', 201);
        break;

    default:
        jsonError('Ruta de vaccines no encontrada', 404);
}
