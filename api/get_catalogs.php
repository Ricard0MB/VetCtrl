<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

require_once __DIR__ . '/../includes/config.php';

try {
    $types = $conn->query("SELECT id, name FROM pet_types ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $breeds = $conn->query("SELECT id, pet_type_id, name FROM breeds ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'pet_types' => $types,
            'breeds' => $breeds
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar catálogos: ' . $e->getMessage()]);
}
?>
