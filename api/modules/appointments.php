<?php
// api/modules/appointments.php
// Módulo CRUD de citas

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // ==========================================================
    // GET /api/appointments — Listar citas (según rol)
    // ==========================================================
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();

        $sql = "SELECT 
                    a.id, a.appointment_date, a.reason, a.status,
                    a.pet_id, a.attendant_id, a.vet_id, a.created_at,
                    p.name AS pet_name,
                    pt.name AS species_name,
                    u.username AS vet_name,
                    owner.username AS owner_name,
                    owner.id AS owner_id
                FROM appointments a
                LEFT JOIN pets p ON a.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN users u ON a.attendant_id = u.id
                LEFT JOIN users owner ON p.owner_id = owner.id
                WHERE 1=1";
        $params = [];

        // Filtrado por rol
        if ($user['role_name'] === 'Propietario') {
            $sql .= " AND p.owner_id = :owner_id";
            $params[':owner_id'] = $user['id'];
        } elseif ($user['role_name'] === 'Veterinario') {
            $sql .= " AND a.attendant_id = :attendant_id";
            $params[':attendant_id'] = $user['id'];
        }

        // Filtros opcionales
        if (!empty($_GET['pet_id'])) {
            $sql .= " AND a.pet_id = :pet_id";
            $params[':pet_id'] = (int)$_GET['pet_id'];
        }
        if (!empty($_GET['status'])) {
            $sql .= " AND a.status = :status";
            $params[':status'] = $_GET['status'];
        }

        $sql .= " ORDER BY a.appointment_date DESC LIMIT 100";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Listado de citas');
        break;

    // ==========================================================
    // GET /api/appointments/{id} — Detalle
    // ==========================================================
    case $method === 'GET' && is_numeric($action):
        $user = requireAuth();
        $db = getDB();
        $apptId = (int)$action;

        $stmt = $db->prepare("
            SELECT a.*,
                   p.name AS pet_name, p.date_of_birth, p.gender,
                   pt.name AS species_name,
                   b.name AS breed_name,
                   owner.username AS owner_name, owner.email AS owner_email,
                   owner.phone AS owner_phone, owner.ci AS owner_ci,
                   vet.username AS vet_name
            FROM appointments a
            LEFT JOIN pets p ON a.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            LEFT JOIN breeds b ON p.breed_id = b.id
            LEFT JOIN users owner ON p.owner_id = owner.id
            LEFT JOIN users vet ON a.attendant_id = vet.id
            WHERE a.id = :id
        ");
        $stmt->execute([':id' => $apptId]);
        $appt = $stmt->fetch();

        if (!$appt) jsonError('Cita no encontrada', 404);
        jsonSuccess($appt, 'Detalle de cita');
        break;

    // ==========================================================
    // POST /api/appointments — Crear cita
    // ==========================================================
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Propietario', 'Veterinario', 'admin']);
        $db = getDB();
        $body = getJsonBody();

        $petId  = (int)($body['pet_id'] ?? 0);
        $date   = trim($body['appointment_date'] ?? '');
        $reason = trim($body['reason'] ?? '');
        $vetId  = !empty($body['vet_id']) ? (int)$body['vet_id'] : (int)$user['id'];

        if ($petId === 0 || $date === '' || $reason === '') {
            jsonError('Mascota, fecha y motivo son obligatorios', 422);
        }

        // Validar fecha futura
        $ts = strtotime($date);
        if (!$ts) jsonError('Fecha inválida', 422);
        if ($ts <= time()) jsonError('La fecha debe ser futura', 422);

        // Validar que la mascota existe
        $stmt = $db->prepare("SELECT owner_id FROM pets WHERE id = :id");
        $stmt->execute([':id' => $petId]);
        $pet = $stmt->fetch();
        if (!$pet) jsonError('Mascota no encontrada', 404);

        // Propietario solo puede agendar para sus mascotas
        if ($user['role_name'] === 'Propietario' && (int)$pet['owner_id'] !== (int)$user['id']) {
            jsonError('No puedes agendar para una mascota que no es tuya', 403);
        }

        // Validar horario laboral (opcional, según tu config)
        $stmt = $db->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('horario_apertura', 'horario_cierre', 'dias_trabajo')");
        $config = [];
        while ($row = $stmt->fetch()) $config[$row['config_key']] = $row['config_value'];

        $open = $config['horario_apertura'] ?? '08:00';
        $close = $config['horario_cierre'] ?? '18:00';
        $hour = date('H:i', $ts);
        if ($hour < $open || $hour > $close) {
            jsonError("La cita debe estar dentro del horario laboral ($open - $close)", 422);
        }

        $formatted = date('Y-m-d H:i:s', $ts);

        // Insertar
        $stmt = $db->prepare("
            INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status, created_at)
            VALUES (:pet_id, :attendant_id, :date, :reason, 'PENDIENTE', NOW())
        ");
        $stmt->execute([
            ':pet_id' => $petId,
            ':attendant_id' => $vetId,
            ':date' => $formatted,
            ':reason' => $reason,
        ]);
        $newId = (int)$db->lastInsertId();

        // Bitácora
        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Cita agendada vía API: ID $newId para mascota $petId el $formatted",
           ]);

        jsonSuccess([
            'id' => $newId,
            'appointment_date' => $formatted,
        ], 'Cita agendada correctamente', 201);
        break;

    // ==========================================================
    // PUT /api/appointments/{id} — Actualizar cita
    // ==========================================================
    case $method === 'PUT' && is_numeric($action):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $apptId = (int)$action;
        $body = getJsonBody();

        $stmt = $db->prepare("SELECT id FROM appointments WHERE id = :id");
        $stmt->execute([':id' => $apptId]);
        if (!$stmt->fetch()) jsonError('Cita no encontrada', 404);

        $fields = [];
        $params = [':id' => $apptId];
        $allowed = ['pet_id', 'appointment_date', 'reason', 'status', 'attendant_id'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Nada que actualizar', 422);

        $sql = "UPDATE appointments SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Cita #$apptId actualizada vía API",
           ]);

        jsonSuccess(null, 'Cita actualizada');
        break;

    // ==========================================================
    // POST /api/appointments/{id}/cancel
    // ==========================================================
    case $method === 'POST' && $action === 'cancel':
        // Estructura: /appointments/{id}/cancel  → segments = ['appointments', '5', 'cancel']
        $user = requireAuth();
        $db = getDB();
        $apptId = isset($segments[1]) ? (int)$segments[1] : (int)($_GET['id'] ?? 0);

        if ($apptId <= 0) jsonError('ID de cita inválido', 422);

        $stmt = $db->prepare("SELECT a.id, p.owner_id FROM appointments a JOIN pets p ON a.pet_id = p.id WHERE a.id = :id");
        $stmt->execute([':id' => $apptId]);
        $appt = $stmt->fetch();
        if (!$appt) jsonError('Cita no encontrada', 404);

        if ($user['role_name'] === 'Propietario' && (int)$appt['owner_id'] !== (int)$user['id']) {
            jsonError('No tienes permiso para cancelar esta cita', 403);
        }

        $db->prepare("UPDATE appointments SET status = 'CANCELADA' WHERE id = :id")
           ->execute([':id' => $apptId]);

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Cita #$apptId cancelada vía API",
           ]);

        jsonSuccess(null, 'Cita cancelada');
        break;

    // ==========================================================
    // DELETE /api/appointments/{id}
    // ==========================================================
    case $method === 'DELETE' && is_numeric($action):
        $user = requireRole(['admin']);
        $db = getDB();
        $apptId = (int)$action;

        $stmt = $db->prepare("DELETE FROM appointments WHERE id = :id");
        $stmt->execute([':id' => $apptId]);

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Cita #$apptId eliminada vía API",
           ]);

        jsonSuccess(null, 'Cita eliminada');
        break;

    // ==========================================================
    // Fallback
    // ==========================================================
    default:
        jsonError('Ruta de appointments no encontrada. Método: ' . $method . ' acción: ' . ($action ?? 'null'), 404);
}
