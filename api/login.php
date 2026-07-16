<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        $response['success'] = false;
        $response['message'] = 'Usuario y contraseña son requeridos';
        echo json_encode($response);
        exit;
    }

    try {
        $query = "SELECT id, username, email, password, role_id, first_name, last_name, status FROM users WHERE username = :login OR email = :login";
        $stmt = $conn->prepare($query);
        $stmt->execute(['login' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $response['success'] = false;
            $response['message'] = 'Usuario no encontrado';
            echo json_encode($response);
            exit;
        }

        if ($user['status'] !== 'active') {
            $response['success'] = false;
            $response['message'] = 'Usuario inactivo o suspendido';
            echo json_encode($response);
            exit;
        }

        if (password_verify($password, $user['password'])) {
            unset($user['password']);
            $response['success'] = true;
            $response['data'] = $user;
        } else {
            $response['success'] = false;
            $response['message'] = 'Contraseña incorrecta';
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error en el servidor: ' . $e->getMessage();
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Método no permitido. Use POST.';
}

echo json_encode($response);
?>
