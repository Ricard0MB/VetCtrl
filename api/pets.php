<?php
// Configurar cabeceras para permitir peticiones desde la App (Ionic)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// 🔥 Incluir tu archivo de configuración (está en la carpeta "includes")
// __DIR__ es la carpeta donde está este archivo (/api)
// Subimos un nivel y entramos a includes/config.php
require_once __DIR__ . '/../includes/config.php';

$response = [];

// Verificar que nos llegue el ID del dueño (ej: ?owner_id=1)
if (isset($_GET['owner_id']) && !empty($_GET['owner_id'])) {
    $owner_id = intval($_GET['owner_id']);

    try {
        // 🔥 Usamos PDO (la variable $conn viene de tu config.php)
        $query = "SELECT p.*, pt.name as type_name, b.name as breed_name 
                  FROM pets p
                  LEFT JOIN pet_types pt ON p.type_id = pt.id
                  LEFT JOIN breeds b ON p.breed_id = b.id
                  WHERE p.owner_id = :owner_id
                  ORDER BY p.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute(['owner_id' => $owner_id]);
        $mascotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response['success'] = true;
        $response['data'] = $mascotas;
        $response['count'] = count($mascotas);

    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error en la consulta: ' . $e->getMessage();
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Falta el parámetro owner_id. Ejemplo: ?owner_id=1';
}

// Devolver el resultado en JSON
echo json_encode($response);
?>
