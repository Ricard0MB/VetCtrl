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

// Obtener tasa BCV (desde Cotizave)
$ch = curl_init('https://api.cotizave.com/v2/fx/VE/rates/ve-bcv');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-API-Key: ' . getenv('COTIZAVE_KEY')]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);
$data = json_decode($res, true);
$tasa_bcv = $data['rate'] ?? 0;
$monto_ves = $monto_usd * $tasa_bcv;

// Crear link de pago en Wayu Pay (usando cURL)
$wayuPayload = [
    'amount' => ['value' => $monto_usd, 'currency' => 'USD'],
    'product_name' => 'Servicio Veterinario',
    'product_description' => 'Cita #' . $cita_id,
    // Si es marketplace, agregar 'merchant_id' => $merchant_id
];
$ch = curl_init('https://api.wayu.com/v1/checkout'); // Endpoint ficticio, consultar doc oficial
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wayuPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . getenv('WAYU_SECRET_KEY'),
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$wayuRes = curl_exec($ch);
curl_close($ch);
$wayuData = json_decode($wayuRes, true);

$payment_link = $wayuData['payment_link'] ?? null;
$transaction_id = $wayuData['transaction_id'] ?? null;

// Guardar en la BD
$stmt = $db->prepare("INSERT INTO payments (cita_id, monto_usd, monto_ves, tasa_bcv, estado, id_transaccion_pasarela) VALUES (?, ?, ?, ?, 'pending', ?)");
$stmt->execute([$cita_id, $monto_usd, $monto_ves, $tasa_bcv, $transaction_id]);

jsonSuccess(['payment_link' => $payment_link, 'transaction_id' => $transaction_id]);
