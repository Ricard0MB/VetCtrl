<?php
// public/forgot_password.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Olvidé mi contraseña</title>
    <link rel="stylesheet" href="css/style_l.css">
    <link rel="stylesheet" href="css/auth.css">
    <style>
        .container { max-width: 480px; margin: 60px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
        label, input, button { display:block; width:100%; }
        input { padding: 10px; margin: 10px 0 16px; }
        .info { font-size:0.95em; color:#555; }
    </style>
</head>
<body>
    <div class="container">
        <h2>¿Olvidaste tu contraseña?</h2>
        <p class="info">Introduce tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>

        <?php if (isset($_GET['msg'])): ?>
            <p class="message-success"><?php echo htmlspecialchars($_GET['msg']); ?></p>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <p class="message-error"><?php echo htmlspecialchars($_GET['error']); ?></p>
        <?php endif; ?>

        <?php if (isset($_GET['link'])): ?>
            <p class="message-error">En entorno local no se pudo enviar el correo. Usa este enlace para probar:</p>
            <p><a href="<?php echo htmlspecialchars($_GET['link']); ?>"><?php echo htmlspecialchars($_GET['link']); ?></a></p>
        <?php endif; ?>

        <form action="../auth/forgot_password_submit.php" method="post">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Enviar enlace de recuperación</button>
        </form>

        <p class="auth-link"><a href="../index.php">← Volver al inicio</a></p>
    </div>
</body>
</html>