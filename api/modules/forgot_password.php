<?php
// api/modules/forgot_password.php

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';

$method = getMethod();
if ($method !== 'POST') jsonError('Método no permitido', 405);

$body = getJsonBody();
$email = trim($body['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('Correo inválido', 400);
}

try {
    $db = getDB();
    
    // Verificar si el usuario existe
    $sql = "SELECT id, email FROM users WHERE email = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Por seguridad, no revelamos si el correo existe o no.
        jsonSuccess(['message' => 'Si existe una cuenta asociada, recibirás un correo con el enlace de recuperación.']);
        exit;
    }

    $user_id = $user['id'];
    $token = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', time() + 3600);
    $created_at = date('Y-m-d H:i:s');

    // Insertar token en password_resets
    $insert = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)";
    $stmtInsert = $db->prepare($insert);
    $stmtInsert->execute([$user_id, $token, $expires_at, $created_at]);

    // Construir enlace (Ajusta esto a la URL real de tu página web)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    // Como estamos en la API, la ruta base suele ser diferente. 
    // Aquí asumo que tu web está en la raíz del dominio de Render.
    $resetLink = "https://vetctrl.onrender.com/public/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);

    $subject = 'Restablecer contraseña - VetCtrl';
    $message = "Hola,\n\nSe solicitó restablecer la contraseña para tu cuenta en VetCtrl. Haz clic en el siguiente enlace o pégalo en tu navegador:\n\n" . $resetLink . "\n\nEste enlace expirará en 1 hora. Si no solicitaste este restablecimiento, puedes ignorar este correo.\n\nSaludos.";

    // --- Lógica de envío con SendGrid (tu código) ---
    $apiKey = getenv('SMTP_PASS') ?: getenv('SENDGRID_API_KEY');
    if (empty($apiKey)) {
        jsonError('Error de configuración: API Key de SendGrid no encontrada.', 500);
    }

    $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: 'no-reply@tudominio.com';
    $fromName  = getenv('SENDGRID_FROM_NAME') ?: 'VetCtrl';

    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $email]],
                'subject' => $subject,
            ]
        ],
        'from' => ['email' => $fromEmail, 'name' => $fromName],
        'content' => [
            [
                'type' => 'text/plain',
                'value' => $message,
            ]
        ]
    ];

    $jsonData = json_encode($data);
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 202) {
        jsonSuccess(['message' => 'Correo enviado. Revisa tu bandeja de entrada o spam.']);
    } else {
        error_log("SendGrid Error: HTTP $httpCode - $response");
        jsonError('No se pudo enviar el correo automáticamente. Intenta de nuevo más tarde.', 500);
    }

} catch (Exception $e) {
    error_log("PDO Error en forgot_password: " . $e->getMessage());
    jsonError('Error interno en la base de datos'), 500); 
}
