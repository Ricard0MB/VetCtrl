<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/funciones_correo.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico inválido.";
    } else {
        // Buscar usuario por email
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Generar token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Guardar token
            $update = $conn->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
            $update->bindValue(':token', $token);
            $update->bindValue(':expires', $expires);
            $update->bindValue(':id', $user['id'], PDO::PARAM_INT);
            $update->execute();

            // Enviar correo
            $resetLink = "http://localhost/VetCtrl/public/reset_password.php?token=" . $token;
            $asunto = "Recuperación de contraseña - VetCtrl";
            $mensajeHTML = "<h2>Recupera tu contraseña</h2><p>Haz clic en el siguiente enlace para restablecer tu contraseña:</p><p><a href='$resetLink'>$resetLink</a></p><p>Este enlace expirará en 1 hora.</p>";
            $mensajePlano = "Recupera tu contraseña\n\nHaz clic en este enlace: $resetLink\n\nExpira en 1 hora.";

            $envio = enviarCorreoPHPMailer($email, $asunto, $mensajeHTML, $mensajePlano);
            if ($envio === true) {
                $success = "Se ha enviado un enlace de recuperación a tu correo.";
            } else {
                error_log("Error enviando correo: $envio");
                $error = "Ocurrió un error al enviar el correo. Intenta más tarde.";
            }
        } else {
            // Por seguridad, no revelamos si el email existe
            $success = "Si el correo está registrado, recibirás un enlace.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Recuperar contraseña</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <h1>Recuperar contraseña</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <form method="post">
            <label>Email:</label>
            <input type="email" name="email" required>
            <button type="submit">Enviar enlace</button>
            <a href="login.php">Volver</a>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
