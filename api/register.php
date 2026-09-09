<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/config.php';

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit();
}

$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');

if (empty($username) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
    $stmt->execute([':u' => $username, ':e' => $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El usuario o correo ya está registrado.']);
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, email, password, role_id, status, created_at) VALUES (:u, :e, :p, 2, 'active', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':u' => $username, ':e' => $email, ':p' => $hashed_password]);

    echo json_encode(['success' => true, 'message' => 'Usuario registrado exitosamente.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de BD: ' . $e->getMessage()]);
}
?>
