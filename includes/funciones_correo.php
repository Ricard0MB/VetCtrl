<?php
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Envía un correo usando la API de SendGrid.
 *
 * @param string $destinatario Correo del destinatario.
 * @param string $asunto Asunto del mensaje.
 * @param string $cuerpoHTML Cuerpo en formato HTML.
 * @param string $cuerpoPlano Versión en texto plano (opcional).
 * @return true|string True si se envió, o mensaje de error.
 */
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
?>
