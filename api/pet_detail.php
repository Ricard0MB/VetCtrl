<?php
// api/pet_detail.php
// Wrapper de compatibilidad: redirige al módulo de pets nuevo

require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/auth.php';

handleCors();

$user = requireAuth();
$db = getDB();

$petId = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
if ($petId <= 0) jsonError('Falta pet_id', 400);

// Datos de la mascota
$stmt = $db->prepare("
    SELECT p.*, pt.name AS species_name, b.name AS breed_name,
           u.username AS owner_name, u.email AS owner_email
    FROM pets p
    LEFT JOIN pet_types pt ON p.type_id = pt.id
    LEFT JOIN breeds b ON p.breed_id = b.id
    LEFT JOIN users u ON p.owner_id = u.id
    WHERE p.id = :id
");
$stmt->execute([':id' => $petId]);
$pet = $stmt->fetch();

if (!$pet) jsonError('Mascota no encontrada', 404);
if ($user['role_name'] === 'Propietario' && (int)$pet['owner_id'] !== (int)$user['id']) {
    jsonError('No tienes permiso', 403);
}

// Historiales
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
           vt.name AS vaccine_name
    FROM vaccines v
    LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
    WHERE v.pet_id = :id
    ORDER BY v.application_date DESC
");
$stmt->execute([':id' => $petId]);
$vaccines = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT t.id, t.title, t.start_date, t.end_date, t.diagnosis, t.status
    FROM treatments t
    WHERE t.pet_id = :id
    ORDER BY t.created_at DESC
");
$stmt->execute([':id' => $petId]);
$treatments = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT a.id, a.appointment_date, a.reason, a.status, u.username AS vet_name
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
