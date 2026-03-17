<?php
session_start();
require_once '../includes/config.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die("Token no proporcionado.");
}

$error = '';
$success = '';

// Buscar token válido
$stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = :token AND reset_expires > NOW()");
$stmt->bindValue(':token', $token);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Token inválido o expirado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } elseif ($password !== $confirm) {
        $error = "Las contraseñas no coinciden.";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id");
        $update->bindValue(':hash', $hash);
        $update->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $update->execute();

        $success = "Contraseña actualizada. <a href='login.php'>Iniciar sesión</a>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Restablecer contraseña</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <h1>Restablecer contraseña</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
        <?php if (!$success): ?>
        <form method="post">
            <label>Nueva contraseña:</label>
            <input type="password" name="password" required minlength="8">
            <label>Confirmar:</label>
            <input type="password" name="confirm_password" required minlength="8">
            <button type="submit">Cambiar contraseña</button>
        </form>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
