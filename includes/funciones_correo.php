<?php
// funciones_correo.php
function enviarCorreoSendGrid($destinatario, $asunto, $cuerpoHTML, $cuerpoPlano = '') {
    // Intentar obtener la clave de API (primero desde SMTP_PASS, luego SENDGRID_API_KEY)
    $apiKey = getenv('SMTP_PASS') ?: getenv('SENDGRID_API_KEY');
    if (empty($apiKey)) {
        return "Error: API Key no configurada";
    }

    $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: 'no-reply@tudominio.com';
    $fromName  = getenv('SENDGRID_FROM_NAME') ?: 'VetCtrl';

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

    $jsonData = json_encode($data);
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonData)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return "Error cURL: " . $curlError;
    }
    
    if ($httpCode === 202) {
        return true;
    }
    
    return "Error HTTP: $httpCode - Respuesta: $response";
}
