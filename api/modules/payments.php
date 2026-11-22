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

// --- 1. Obtener tasa BCV desde Cotizave (CORREGIDO) ---
$ch = curl_init('https://api.cotizave.com/v2/fx/VE/rates/ve-bcv'); // URL correcta
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . getenv('COTIZAVE_API_KEY'),
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$curl_error_cotizave = curl_error($ch);
$http_code_cotizave = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false || $curl_error_cotizave || $http_code_cotizave !== 200) {
    jsonError('Error al conectar con Cotizave. Código HTTP: ' . $http_code_cotizave . '. Error: ' . $curl_error_cotizave, 500);
}

$data = json_decode($res, true);
$tasa_bcv = $data['mid'] ?? 0; // El campo correcto es 'mid'

if ($tasa_bcv <= 0) {
    jsonError('No se pudo obtener la tasa BCV. Respuesta de Cotizave: ' . $res, 500);
}

$monto_ves = $monto_usd * $tasa_bcv;

// --- 2. Crear link de pago en WayuPay (CORREGIDO) ---
$wayuPayload = [
    'amount' => ['value' => $monto_usd, 'currency' => 'USD'],
    'product_name' => 'Servicio Veterinario',
    'product_description' => 'Cita #' . $cita_id,
];
$payloadJson = json_encode($wayuPayload);

// Generar firma HMAC-SHA256 usando la clave secreta
$secretKey = getenv('WAYU_SECRET_KEY');
$publicKey = getenv('WAYU_PUBLIC_KEY'); // Necesitas la clave pública también

if (!$secretKey || !$publicKey) {
    jsonError('Faltan las claves de WayuPay en las variables de entorno.', 500);
}

$signature = hash_hmac('sha256', $payloadJson, $secretKey);

$ch = curl_init('https://api.wayu.app/v1/checkout'); // URL base correcta; el endpoint puede variar, revisa la documentación.
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-public-key: ' . $publicKey,
    'x-signature: ' . $signature
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$wayuRes = curl_exec($ch);
$http_code_wayu = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error_wayu = curl_error($ch);
curl_close($ch);

// --- 3. Validar la respuesta de WayuPay ---
if ($wayuRes === false || $curl_error_wayu) {
    jsonError('Error de conexión con WayuPay: ' . $curl_error_wayu, 500);
}

$wayuData = json_decode($wayuRes, true);

if ($http_code_wayu < 200 || $http_code_wayu >= 300) {
    $msj_error = $wayuData['message'] ?? $wayuData['error'] ?? $wayuRes;
    jsonError('WayuPay rechazó la petición (HTTP ' . $http_code_wayu . '): ' . $msj_error, 500);
}

// El SDK oficial usa 'generatePaymentLink' y 'transactionId'
$payment_link = $wayuData['generatePaymentLink'] ?? $wayuData['payment_link'] ?? $wayuData['url'] ?? $wayuData['link'] ?? null;
$transaction_id = $wayuData['transactionId'] ?? $wayuData['transaction_id'] ?? $wayuData['id'] ?? null;

if (!$payment_link) {
    jsonError('WayuPay no devolvió un link válido. Respuesta: ' . json_encode($wayuData), 500);
}

// --- 4. Guardar en la BD ---
try {
    $stmt = $db->prepare("INSERT INTO payments (cita_id, monto_usd, monto_ves, tasa_bcv, estado, id_transaccion_pasarela) VALUES (?, ?, ?, ?, 'pending', ?)");
    $stmt->execute([$cita_id, $monto_usd, $monto_ves, $tasa_bcv, $transaction_id]);
} catch (Exception $e) {
    error_log("Error al guardar pago en BD: " . $e->getMessage());
}

// --- 5. Devolver éxito ---
jsonSuccess([
    'payment_link' => $payment_link,
    'transaction_id' => $transaction_id
]);
