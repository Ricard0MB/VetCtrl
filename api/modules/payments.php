<?php
// api/modules/payments.php
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$user = requireAuth();
$db = getDB();
$body = getJsonBody();

$cita_id = $body['cita_id'] ?? null;
$monto_usd = $body['monto_usd'] ?? 0;

// 1. Obtener tasa BCV (desde Cotizave)
$ch = curl_init('https://api.cotizave.com/v2/fx/VE/rates/ve-bcv');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . getenv('COTIZAVE_KEY')]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$curl_error_cotizave = curl_error($ch);
curl_close($ch);

if ($res === false || $curl_error_cotizave) {
    jsonError('Error al conectar con Cotizave: ' . $curl_error_cotizave, 500);
}

$data = json_decode($res, true);
// Dependiendo de Cotizave, la tasa puede venir en 'rate' o en 'data.rate'
$tasa_bcv = $data['rate'] ?? $data['data']['rate'] ?? 0;

if ($tasa_bcv <= 0) {
    jsonError('La tasa BCV obtenida es inválida (0). Revisa tu COTIZAVE_KEY.', 500);
}

$monto_ves = $monto_usd * $tasa_bcv;

// 2. Crear link de pago en Wayu Pay
$wayuPayload = [
    'amount' => ['value' => $monto_usd, 'currency' => 'USD'],
    'product_name' => 'Servicio Veterinario',
    'product_description' => 'Cita #' . $cita_id,
];

// ⚠️ IMPORTANTE: Esta URL es un EJEMPLO. Debes consultar la documentación oficial de Wayu Pay
// y colocar aquí el endpoint real para crear intenciones de pago.
$ch = curl_init('https://api.wayu.com/v1/checkout'); 
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wayuPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . getenv('WAYU_SECRET_KEY'),
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$wayuRes = curl_exec($ch);
$http_code_wayu = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_wayu = curl_error($ch);
curl_close($ch);

// 3. Validar la respuesta de Wayu Pay
if ($wayuRes === false || $curl_error_wayu) {
    jsonError('Error de conexión con Wayu Pay: ' . $curl_error_wayu, 500);
}

$wayuData = json_decode($wayuRes, true);

// Si Wayu Pay devuelve un error HTTP (ej. 400, 401, 404, 500)
if ($http_code_wayu < 200 || $http_code_wayu >= 300) {
    $msj_error = $wayuData['message'] ?? $wayuData['error'] ?? $wayuRes;
    jsonError('Wayu Pay rechazó la petición (HTTP ' . $http_code_wayu . '): ' . $msj_error, 500);
}

// Extraer el link de pago (el nombre del campo depende de la doc de Wayu)
$payment_link = $wayuData['payment_link'] ?? $wayuData['url'] ?? $wayuData['link'] ?? null;
$transaction_id = $wayuData['transaction_id'] ?? $wayuData['id'] ?? null;

if (!$payment_link) {
    // Si no encontramos el link, devolvemos la respuesta completa para debug
    jsonError('Wayu Pay no devolvió un link válido. Respuesta: ' . json_encode($wayuData), 500);
}

// 4. Guardar en la BD
try {
    $stmt = $db->prepare("INSERT INTO payments (cita_id, monto_usd, monto_ves, tasa_bcv, estado, id_transaccion_pasarela) VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([$cita_id, $monto_usd, $monto_ves, $tasa_bcv, $transaction_id]);
} catch (Exception $e) {
    error_log("Error al guardar pago en BD: " . $e->getMessage());
    // Podrías decidir devolver un error aquí, o dejar pasar el pago para que el webhook lo cree después
}

// 5. Devolver éxito al frontend
jsonSuccess([
    'payment_link' => $payment_link, 
    'transaction_id' => $transaction_id
]);
