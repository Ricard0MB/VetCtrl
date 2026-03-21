<?php
session_start();
require_once '../includes/config.php';

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$error = $_GET['error'] ?? '';
$success = '';

if (empty($token)) {
    die("Token no proporcionado.");
}

$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_expires > NOW()");
$stmt->bindValue(':token', $token);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Token inválido o expirado.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Restablecer contraseña | Clínica Veterinaria</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* (Incluir aquí los estilos verdes y decoraciones que ya tienes en forgot_password.php) */
        /* ... */
    </style>
</head>
<body>
    <div class="container">
        <h1>Restablecer contraseña</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post" action="reset_password_submit.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <label>Nueva contraseña:</label>
            <input type="password" name="password" required minlength="8">
            <label>Confirmar contraseña:</label>
            <input type="password" name="password_confirm" required minlength="8">
            <button type="submit">Cambiar contraseña</button>
        </form>
        <a href="login.php" class="back-link">← Volver al inicio de sesión</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
