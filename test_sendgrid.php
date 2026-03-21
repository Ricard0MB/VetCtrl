<?php
require_once __DIR__ . '/vendor/autoload.php';
use GuzzleHttp\Client;

$apiKey = getenv('SENDGRID_API_KEY'); // o escribe la clave directamente para prueba
$fromEmail = 'tu-correo-verificado@tudominio.com';
$toEmail = 'destinatario@example.com';
$subject = 'Prueba SendGrid';
$html = '<p>Hola, esto es una prueba.</p>';

$client = new Client(['timeout' => 10]);
try {
    $response = $client->post('https://api.sendgrid.com/v3/mail/send', [
        'headers' => [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ],
        'json' => [
            'personalizations' => [[
                'to' => [['email' => $toEmail]],
                'subject' => $subject,
            ]],
            'from' => ['email' => $fromEmail, 'name' => 'VetCtrl'],
            'content' => [['type' => 'text/html', 'value' => $html]],
        ],
    ]);
    echo "Envío exitoso. Código: " . $response->getStatusCode();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
