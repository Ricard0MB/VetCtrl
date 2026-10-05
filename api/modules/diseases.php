<?php
// api/modules/diseases.php
// Módulo de enfermedades filtradas por especie

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // ==========================================================
    // GET /api/diseases — Listar enfermedades (con filtro por especie)
    // ==========================================================
    case $method === 'GET' && ($action === null || $action === ''):
        $user = requireAuth();
        $db = getDB();

        $species = trim($_GET['species'] ?? '');
        $search  = trim($_GET['q'] ?? '');

        $sql = "SELECT id, name, species_target, description, symptoms, treatment_recommended, created_at
                FROM diseases
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
        jsonSuccess($stmt->fetchAll(), 'Listado de enfermedades');
        break;

    // ==========================================================
    // GET /api/diseases/{id} — Detalle
    // ==========================================================
    case $method === 'GET' && is_numeric($action):
        $user = requireAuth();
        $db = getDB();
        $id = (int)$action;

        $stmt = $db->prepare("SELECT * FROM diseases WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $disease = $stmt->fetch();
        if (!$disease) jsonError('Enfermedad no encontrada', 404);

        jsonSuccess($disease, 'Detalle de enfermedad');
        break;

    // ==========================================================
    // POST /api/diseases — Crear enfermedad (solo vet/admin)
    // ==========================================================
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $body = getJsonBody();

        $name = trim($body['name'] ?? '');
        $species = trim($body['species_target'] ?? 'General');
        $description = $body['description'] ?? null;
        $symptoms = $body['symptoms'] ?? null;
        $treatment = $body['treatment_recommended'] ?? null;

        if ($name === '') jsonError('El nombre es obligatorio', 422);

        $stmt = $db->prepare("
            INSERT INTO diseases (name, species_target, description, symptoms, treatment_recommended, attendant_id)
            VALUES (:name, :species, :description, :symptoms, :treatment, :attendant_id)
        ");
        $stmt->execute([
            ':name' => $name,
            ':species' => $species,
            ':description' => $description,
            ':symptoms' => $symptoms,
            ':treatment' => $treatment,
            ':attendant_id' => $user['id'],
        ]);
        $newId = (int)$db->lastInsertId();

        jsonSuccess(['id' => $newId], 'Enfermedad registrada', 201);
        break;

    // ==========================================================
    // PUT /api/diseases/{id} — Actualizar
    // ==========================================================
    case $method === 'PUT' && is_numeric($action):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $id = (int)$action;
        $body = getJsonBody();

        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'species_target', 'description', 'symptoms', 'treatment_recommended'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Nada que actualizar', 422);

        $sql = "UPDATE diseases SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonSuccess(null, 'Enfermedad actualizada');
        break;

    // ==========================================================
    // DELETE /api/diseases/{id}
    // ==========================================================
    case $method === 'DELETE' && is_numeric($action):
        $user = requireRole(['admin']);
        $db = getDB();
        $id = (int)$action;

        $stmt = $db->prepare("DELETE FROM diseases WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonSuccess(null, 'Enfermedad eliminada');
        break;

    default:
        jsonError('Ruta de diseases no encontrada', 404);
}
