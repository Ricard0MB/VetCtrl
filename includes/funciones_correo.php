<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

require_once __DIR__ . '/../vendor/autoload.php';

function enviarCorreoSendGrid($destinatario, $asunto, $cuerpoHTML, $cuerpoPlano = '') {
    $apiKey = getenv('SENDGRID_API_KEY');
    if (empty($apiKey)) {
        return "Error: SENDGRID_API_KEY no configurada";
    }

    $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: 'no-reply@tudominio.com';
    $fromName  = getenv('SENDGRID_FROM_NAME') ?: 'VetCtrl';

    $client = new Client([
        'timeout' => 10.0,
        'connect_timeout' => 5.0,
    ]);

    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $destinatario]],
                'subject' => $asunto,
            ]
        ],
        'from' => ['email' => $fromEmail, 'name' => $fromName],
        'content' => [
            [
                'type' => 'text/html',
                'value' => $cuerpoHTML,
            ]
        ]
    ];
    if (!empty($cuerpoPlano)) {
        $data['content'][] = [
            'type' => 'text/plain',
            'value' => $cuerpoPlano,
        ];
    }

    try {
        $response = $client->post('https://api.sendgrid.com/v3/mail/send', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => $data,
        ]);
        return $response->getStatusCode() === 202 ? true : "Error HTTP: " . $response->getStatusCode();
    } catch (GuzzleException $e) {
        $errorMsg = "Error Guzzle: " . $e->getMessage();
        error_log($errorMsg);
        return $errorMsg;
    }
}

// (Opcional) Mantener la función PHPMailer por compatibilidad, pero no se usará
function enviarCorreoPHPMailer(...) { ... }
?>
