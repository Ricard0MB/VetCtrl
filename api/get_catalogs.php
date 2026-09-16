<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/config.php';

try {
    $types  = $conn->query("SELECT id, name FROM pet_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $breeds = $conn->query("SELECT id, type_id, name FROM breeds ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $roles  = $conn->query("SELECT id, name FROM roles ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $vaccine_types = $conn->query("SELECT id, name, species_target FROM vaccine_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'pet_types'     => $types,
            'breeds'        => $breeds,
            'roles'         => $roles,
            'vaccine_types' => $vaccine_types,
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar catálogos: ' . $e->getMessage()]);
}
