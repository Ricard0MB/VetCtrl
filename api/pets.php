<?php
// Configurar cabeceras para permitir peticiones desde la App (Ionic)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Ajusta la ruta a tu archivo de conexión (ej: ../config/database.php)
// Si tu conexión está en otro lado, cambia esta ruta.
include '../config/database.php'; 

$response = [];

// Verificar que nos llegue el ID del dueño (ej: ?owner_id=1)
if (isset($_GET['owner_id']) && !empty($_GET['owner_id'])) {
    $owner_id = intval($_GET['owner_id']);

    // Consulta SQL con JOIN para traer el nombre de la especie y raza
    $query = "SELECT p.*, pt.name as type_name, b.name as breed_name 
              FROM pets p
              LEFT JOIN pet_types pt ON p.type_id = pt.id
              LEFT JOIN breeds b ON p.breed_id = b.id
              WHERE p.owner_id = $owner_id
              ORDER BY p.created_at DESC";
              
    $resultado = mysqli_query($conn, $query);

    if ($resultado) {
        $mascotas = [];
        while ($fila = mysqli_fetch_assoc($resultado)) {
            $mascotas[] = $fila;
        }
        $response['success'] = true;
        $response['data'] = $mascotas;
    } else {
        $response['success'] = false;
        $response['message'] = 'Error en la consulta: ' . mysqli_error($conn);
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Falta el parámetro owner_id';
}

// Devolver el resultado en JSON
echo json_encode($response);
?>
