<?php
// api/modules/vaccine_types.php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['id'] ?? null;

switch (true) {

    // GET /api/vaccine-types — Listar
    case $method === 'GET' && ($action === null || $action === ''):
        requireAuth();
        $db = getDB();
        $stmt = $db->query("
            SELECT id, name, description, species_target, attendant_id, created_at 
            FROM vaccine_types 
            ORDER BY name ASC
        ");
        jsonSuccess($stmt->fetchAll(), 'Tipos de vacuna');
        break;

    // POST /api/vaccine-types — Crear
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

    default:
        jsonError('Ruta de vaccine-types no encontrada', 404);
}
