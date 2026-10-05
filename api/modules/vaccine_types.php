<?php
// api/modules/vaccine_types.php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // ==========================================================
    // GET /api/vaccine-types — Listar (con filtro por especie)
    // ==========================================================
    case $method === 'GET' && ($action === null || $action === ''):
        requireAuth();
        $db = getDB();

        // Filtrar por especie si viene el parámetro
        $species = trim($_GET['species'] ?? '');
        $search  = trim($_GET['q'] ?? '');

        $sql = "SELECT id, name, description, species_target, attendant_id, created_at 
                FROM vaccine_types";
        $where = [];
        $params = [];

        // Filtro por especie (incluye las generales)
        if ($species !== '') {
            $where[] = "(species_target = :species OR species_target = 'General')";
            $params[':species'] = $species;
        }

        // Filtro por búsqueda
        if ($search !== '') {
            $where[] = "(name LIKE :s1 OR description LIKE :s2)";
            $params[':s1'] = "%$search%";
            $params[':s2'] = "%$search%";
        }

        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }

        $sql .= " ORDER BY name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        jsonSuccess($stmt->fetchAll(), 'Tipos de vacuna');
        break;

    // ==========================================================
    // GET /api/vaccine-types/{id} — Detalle
    // ==========================================================
    case $method === 'GET' && is_numeric($action):
        requireAuth();
        $db = getDB();
        $id = (int)$action;

        $stmt = $db->prepare("SELECT * FROM vaccine_types WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $vt = $stmt->fetch();
        if (!$vt) jsonError('Tipo de vacuna no encontrado', 404);

        jsonSuccess($vt, 'Detalle del tipo de vacuna');
        break;

    // ==========================================================
    // POST /api/vaccine-types — Crear
    // ==========================================================
    case $method === 'POST' && ($action === null || $action === ''):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $body = getJsonBody();

        $name = trim($body['name'] ?? '');
        $species = trim($body['species_target'] ?? 'General');
        $desc = $body['description'] ?? null;

        if ($name === '') jsonError('El nombre es obligatorio', 422);

        $stmt = $db->prepare("SELECT id FROM vaccine_types WHERE name = :n");
        $stmt->execute([':n' => $name]);
        if ($stmt->fetch()) jsonError('Ya existe un tipo de vacuna con ese nombre', 409);

        $stmt = $db->prepare("
            INSERT INTO vaccine_types (name, description, species_target, attendant_id, created_at)
            VALUES (:n, :d, :s, :a, NOW())
        ");
        $stmt->execute([
            ':n' => $name,
            ':d' => $desc,
            ':s' => $species,
            ':a' => $user['id'],
        ]);
        $newId = (int)$db->lastInsertId();

        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Tipo de vacuna '$name' creado vía API (ID $newId)",
           ]);

        jsonSuccess(['id' => $newId], 'Tipo de vacuna creado', 201);
        break;

    // ==========================================================
    // PUT /api/vaccine-types/{id} — Actualizar
    // ==========================================================
    case $method === 'PUT' && is_numeric($action):
        $user = requireRole(['Veterinario', 'admin']);
        $db = getDB();
        $id = (int)$action;
        $body = getJsonBody();

        $stmt = $db->prepare("SELECT id FROM vaccine_types WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) jsonError('Tipo de vacuna no encontrado', 404);

        $fields = [];
        $params = [':id' => $id];
        $allowed = ['name', 'description', 'species_target'];

        foreach ($allowed as $f) {
            if (array_key_exists($f, $body)) {
                $fields[] = "$f = :$f";
                $params[":$f"] = $body[$f];
            }
        }
        if (empty($fields)) jsonError('Nada que actualizar', 422);

        $sql = "UPDATE vaccine_types SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        jsonSuccess(null, 'Tipo de vacuna actualizado');
        break;

    // ==========================================================
    // DELETE /api/vaccine-types/{id}
    // ==========================================================
    case $method === 'DELETE' && is_numeric($action):
        $user = requireRole(['admin']);
        $db = getDB();
        $id = (int)$action;

        $stmt = $db->prepare("DELETE FROM vaccine_types WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonSuccess(null, 'Tipo de vacuna eliminado');
        break;

    default:
        jsonError('Ruta de vaccine-types no encontrada', 404);
}
