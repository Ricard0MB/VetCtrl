<?php
// api/modules/payments_webhook.php
require_once __DIR__ . '/../core/db.php';

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$secret = getenv('WAYU_WEBHOOK_SECRET');

$expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}

$payload = json_decode($rawBody, true);
$transaction_id = $payload['transaction_id'] ?? null;
$status = $payload['status'] ?? '';

if ($status === 'succeeded' && $transaction_id) {
    $db = getDB();
    try {
        $db->beginTransaction();

        // 1. Marcar el pago como completado
        $stmt = $db->prepare("UPDATE payments SET estado = 'completed', fecha_confirmacion = NOW() WHERE id_transaccion_pasarela = ?");
        $stmt->execute([$transaction_id]);

        // 2. Buscar a qué cita/servicio corresponde ese pago
        $stmtFind = $db->prepare("SELECT cita_id, tipo_servicio, referencia_servicio_id FROM payments WHERE id_transaccion_pasarela = ? LIMIT 1");
        $stmtFind->execute([$transaction_id]);
        $payment = $stmtFind->fetch(PDO::FETCH_ASSOC);

        if ($payment) {
            // Si es una cita (usando tu columna actual cita_id)
            if (!empty($payment['cita_id'])) {
                // ⚠️ Cambia 'appointments' por el nombre real de tu tabla de citas si se llama 'citas'
                $stmtCita = $db->prepare("UPDATE appointments SET status = 'PAGADA' WHERE id = ?");
                $stmtCita->execute([$payment['cita_id']]);
            }
            // Si en el futuro pagas vacuna o tratamiento directo:
            elseif ($payment['tipo_servicio'] === 'vacuna' && !empty($payment['referencia_servicio_id'])) {
                $stmtVac = $db->prepare("UPDATE vaccines SET status = 'PAGADA' WHERE id = ?");
                $stmtVac->execute([$payment['referencia_servicio_id']]);
            }
            elseif ($payment['tipo_servicio'] === 'tratamiento' && !empty($payment['referencia_servicio_id'])) {
                $stmtTrat = $db->prepare("UPDATE treatments SET status = 'PAGADA' WHERE id = ?");
                $stmtTrat->execute([$payment['referencia_servicio_id']]);
            }
        }

        $db->commit();
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error en webhook WayuPay: " . $e->getMessage());
        http_response_code(500);
        exit('Database error');
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
