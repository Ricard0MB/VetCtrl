<?php
require_once __DIR__ . '/../includes/config.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$error = '';
$show_form = false;

if (empty($token) || empty($email)) {
    $error = 'Enlace inválido.';
} else {
    // Buscar token válido y no expirado
    $sql = "SELECT pr.id AS reset_id, pr.expires_at, u.id AS user_id 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND u.email = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('ss', $token, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = 'Token inválido o ya utilizado.';
        } else {
            $expires_at = $row['expires_at'];
            if (strtotime($expires_at) < time()) {
                $error = 'El enlace ha expirado.';
            } else {
                $show_form = true;
                $reset_id = $row['reset_id'];
                $user_id = $row['user_id'];
            }
        }
    } else {
        $error = 'Error interno al validar el token.';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Restablecer contraseña - VetCtrl</title>
    <link rel="stylesheet" href="css/style_l.css">
    <link rel="stylesheet" href="css/auth.css">
</head>
<body>
    <div class="auth-box">
        <h2>Restablecer contraseña</h2>

        <?php if ($error): ?>
            <p class="message-error"><?php echo htmlspecialchars($error); ?></p>
            <p><a href="forgot_password.php">Solicitar nuevo enlace</a></p>
        <?php endif; ?>

        <?php if ($show_form): ?>
            <form action="reset_password_submit.php" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" required minlength="6">

                <label for="password_confirm">Confirmar nueva contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="6">

                <button type="submit">Actualizar contraseña</button>
            </form>
        <?php endif; ?>

        <p class="auth-link"><a href="../index.php">← Volver al inicio</a></p>
    </div>
</body>
</html>