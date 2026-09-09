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

$data = json_decode(file_get_contents('php://input'), true);

$pet_id = $data['pet_id'] ?? null;
$user_id = $data['user_id'] ?? null;
$appointment_date = $data['appointment_date'] ?? null;
$reason = trim($data['reason'] ?? '');

if (!$pet_id || !$user_id || !$appointment_date || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios para agendar la cita.']);
    exit();
}

try {
    $sql = "INSERT INTO appointments (pet_id, user_id, appointment_date, reason, status, created_at) 
            VALUES (:pet_id, :user_id, :appointment_date, :reason, 'pending', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':pet_id' => $pet_id,
        ':user_id' => $user_id,
        ':appointment_date' => $appointment_date,
        ':reason' => $reason
    ]);

    echo json_encode(['success' => true, 'message' => 'Cita agendada con éxito.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al agendar cita: ' . $e->getMessage()]);
}
?>
