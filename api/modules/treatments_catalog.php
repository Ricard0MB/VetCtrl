<?php
// api/modules/treatments_catalog.php
// Módulo de tratamientos predefinidos por especie

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // ==========================================================
    // GET /api/treatments-catalog — Listar
    // ==========================================================
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();

        $species = trim($_GET['species'] ?? '');
        $search  = trim($_GET['q'] ?? '');

        $sql = "SELECT id, name, species_target, description, medication, dosage, duration, created_at
                FROM treatments_catalog
                WHERE 1=1";
        $params = [];

        if ($species !== '') {
            $sql .= " AND species_target = :species";
            $params[':species'] = $species;
        }
        if ($search !== '') {
            $sql .= " AND (name LIKE :s1 OR description LIKE :s2)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
        }

        $sql .= " ORDER BY species_target ASC, name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Catálogo de tratamientos');
        break;

    // ==========================================================
    // POST /api/treatments-catalog — Crear (vet/admin)
    // ==========================================================
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $body = getJsonBody();

        $name = trim($body['name'] ?? '');
        $species = trim($body['species_target'] ?? 'General');
        $description = $body['description'] ?? null;
        $medication = $body['medication'] ?? null;
        $dosage = $body['dosage'] ?? null;
        $duration = $body['duration'] ?? null;

        if ($name === '') jsonError('El nombre es obligatorio', 422);

        $stmt = $db->prepare("
            INSERT INTO treatments_catalog (name, species_target, description, medication, dosage, duration, attendant_id)
            VALUES (:name, :species, :description, :medication, :dosage, :duration, :attendant_id)
        ");
        $stmt->execute([
            ':name' => $name,
            ':species' => $species,
            ':description' => $description,
            ':medication' => $medication,
            ':dosage' => $dosage,
            ':duration' => $duration,
            ':attendant_id' => $user['id'],
        ]);
        $newId = (int)$db->lastInsertId();

        jsonSuccess(['id' => $newId], 'Tratamiento registrado', 201);
        break;

    // ==========================================================
    // PUT /api/treatments-catalog/{id} — Actualizar
    // ==========================================================
    case $method === 'PUT' && is_numeric($action):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $id = (int)$action;
        $body = getJsonBody();

        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'species_target', 'description', 'medication', 'dosage', 'duration'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Nada que actualizar', 422);

        $sql = "UPDATE treatments_catalog SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonSuccess(null, 'Tratamiento actualizado');
        break;

    default:
        jsonError('Ruta de treatments-catalog no encontrada', 404);
}
