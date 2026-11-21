<?php
// api/modules/payments_webhook.php
$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$secret = getenv('WAYU_WEBHOOK_SECRET');

$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$payload = json_decode($rawBody, true);
$transaction_id = $payload['transaction_id'];
$status = $payload['status']; // 'succeeded'

if ($status === 'succeeded') {
    $db = getDB();
    $stmt = $db->prepare("UPDATE payments SET estado = 'completed', fecha_confirmacion = NOW() WHERE id_transaccion_pasarela = ?");
    $stmt->execute([$transaction_id]);
    // Si es pago de cita, actualizar estado de la cita
    // ...
}
http_response_code(200);
echo json_encode(['received' => true]);
