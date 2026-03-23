<?php
session_start();
set_time_limit(120);
require_once '../includes/config.php';

$error = '';
$success = '';
$baseUrl = getenv('SITE_URL') ?: 'https://vetctrl.onrender.com'; // Ajusta si es necesario

// ------------------------------------------------------------------
// Función para enviar correo usando la API de SendGrid con cURL
// ------------------------------------------------------------------
function enviarCorreoSendGrid($destinatario, $asunto, $cuerpoHTML, $cuerpoPlano = '') {
    $apiKey = getenv('SMTP_PASS') ?: getenv('SENDGRID_API_KEY');
    if (empty($apiKey)) {
        return "Error: Clave de API no encontrada (SMTP_PASS o SENDGRID_API_KEY no definida)";
    }

    $fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: 'no-reply@vetctrl.com';
    $fromName  = getenv('SENDGRID_FROM_NAME') ?: 'VetCtrl';

    // Construir el array de contenido respetando el orden: text/plain primero, luego text/html
    $content = [];
    if (!empty($cuerpoPlano)) {
        $content[] = ['type' => 'text/plain', 'value' => $cuerpoPlano];
    }
    $content[] = ['type' => 'text/html', 'value' => $cuerpoHTML];

    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $destinatario]],
                'subject' => $asunto,
            ]
        ],
        'from' => ['email' => $fromEmail, 'name' => $fromName],
        'content' => $content,
    ];

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
    
    if ($curlError) return "Error cURL: " . $curlError;
    if ($httpCode === 202) return true;
    return "Error HTTP $httpCode - Respuesta: " . $response;
}
// ------------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico inválido.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $update = $conn->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
            $update->bindValue(':token', $token);
            $update->bindValue(':expires', $expires);
            $update->bindValue(':id', $user['id'], PDO::PARAM_INT);
            $update->execute();

            $resetLink = $baseUrl . "/public/reset_password.php?token=" . $token . "&email=" . urlencode($email);
            $asunto = "Recuperación de contraseña - VetCtrl";
            $mensajeHTML = "<h2>Recupera tu contraseña</h2><p>Haz clic en el siguiente enlace para restablecer tu contraseña:</p><p><a href='$resetLink'>$resetLink</a></p><p>Este enlace expirará en 1 hora.</p>";
            $mensajePlano = "Recupera tu contraseña\n\nHaz clic en este enlace: $resetLink\n\nExpira en 1 hora.";

            $envio = enviarCorreoSendGrid($email, $asunto, $mensajeHTML, $mensajePlano);
            if ($envio === true) {
                $success = "Se ha enviado un enlace de recuperación a tu correo.";
            } else {
                $error = "Error al enviar: " . $envio;
                error_log("Error enviando correo: $envio");
            }
        } else {
            $success = "Si el correo está registrado, recibirás un enlace.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Recuperar contraseña | Clínica Veterinaria</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
        }
        body::before { content: "🐾"; font-size: 120px; opacity: 0.05; position: absolute; bottom: 20px; left: 20px; pointer-events: none; transform: rotate(-15deg); }
        body::after { content: "🐾"; font-size: 180px; opacity: 0.05; position: absolute; top: 20px; right: 20px; pointer-events: none; transform: rotate(15deg); }
        .container {
            background: white;
            border-radius: 28px;
            box-shadow: 0 20px 35px -10px rgba(0, 0, 0, 0.15);
            padding: 40px 35px;
            width: 100%;
            max-width: 480px;
            text-align: center;
            transition: transform 0.2s ease;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(76, 175, 80, 0.2);
        }
        .container:hover { transform: translateY(-3px); }
        h1 {
            color: #2e7d32;
            font-size: 1.9rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        h1::before { content: "🐕"; font-size: 2rem; }
        h1::after { content: "🐈"; font-size: 2rem; }
        .alert {
            padding: 12px 18px;
            border-radius: 40px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            text-align: center;
            background: #f8f9fa;
            border-left: 5px solid;
        }
        .alert-danger { background-color: #ffebee; border-left-color: #d32f2f; color: #b71c1c; }
        .alert-success { background-color: #e8f5e9; border-left-color: #2e7d32; color: #1b5e20; }
        form { margin-top: 10px; }
        label {
            display: block;
            text-align: left;
            font-weight: 600;
            margin: 20px 0 8px 5px;
            color: #1b5e20;
            font-size: 0.9rem;
        }
        input[type="email"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e0e0e0;
            border-radius: 50px;
            font-size: 1rem;
            transition: all 0.2s ease;
            background-color: #fefefe;
            outline: none;
            font-family: inherit;
        }
        input[type="email"]:focus {
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
            background-color: #ffffff;
        }
        button {
            background: linear-gradient(95deg, #2e7d32, #4caf50);
            color: white;
            border: none;
            padding: 14px 20px;
            width: 100%;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 50px;
            cursor: pointer;
            margin-top: 30px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            font-family: inherit;
        }
        button:hover {
            background: linear-gradient(95deg, #1b5e20, #388e3c);
            transform: scale(0.98);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        /* Estilo del enlace de retorno modificado (sin flecha, negrita, sin subrayado) */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2c6e49;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8rem;
            color: #2e7d32;
            background: rgba(255, 255, 255, 0.7);
            padding: 10px 20px;
            border-radius: 40px;
            backdrop-filter: blur(4px);
            width: auto;
            z-index: 2;
        }
        footer p { margin: 0; }
        @media (max-width: 550px) {
            .container { padding: 30px 20px; }
            h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Recuperar contraseña</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post">
            <label>Correo electrónico</label>
            <input type="email" name="email" required placeholder="tuemail@ejemplo.com">
            <button type="submit">Enviar enlace de recuperación</button>
        </form>
        <a href="../index.php" class="back-link">Volver al Inicio de Sesión</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
