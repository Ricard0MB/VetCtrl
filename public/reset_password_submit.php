<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Validaciones
if (empty($token) || empty($email) || empty($password) || empty($password_confirm)) {
    $error = 'Todos los campos son obligatorios.';
} elseif ($password !== $password_confirm) {
    $error = 'Las contraseñas no coinciden.';
} elseif (strlen($password) < 8) {
    $error = 'La contraseña debe tener al menos 8 caracteres.';
} else {
    try {
        $sql = "SELECT id, reset_expires FROM users WHERE reset_token = :token AND email = :email LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = 'Token inválido o ya utilizado.';
        } elseif (strtotime($user['reset_expires']) < time()) {
            $error = 'El enlace ha expirado.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $update = "UPDATE users SET password = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id";
            $stmtUpdate = $conn->prepare($update);
            $stmtUpdate->bindValue(':hash', $hash);
            $stmtUpdate->bindValue(':id', $user['id'], PDO::PARAM_INT);
            $stmtUpdate->execute();

            if ($stmtUpdate->rowCount() > 0) {
                $success = 'Contraseña actualizada correctamente. <a href="index.php">Iniciar sesión</a>';
            } else {
                $error = 'No se pudo actualizar la contraseña. Intenta de nuevo.';
            }
        }
    } catch (PDOException $e) {
        error_log("Error en reset_password_submit: " . $e->getMessage());
        $error = 'Error interno del servidor. Intenta más tarde.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Restablecer contraseña | Clínica Veterinaria</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Estilos idénticos a los de forgot_password.php */
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
        .alert-success a {
            color: #0d47a1;
            font-weight: bold;
            text-decoration: none;
            border-bottom: 1px dashed #0d47a1;
        }
        .alert-success a:hover {
            color: #002171;
            border-bottom: 1px solid #002171;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2e7d32;
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px dashed #2e7d32;
            transition: all 0.2s;
        }
        .back-link:hover { color: #1b5e20; border-bottom: 1px solid #1b5e20; }
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
        <h1>Restablecer contraseña</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <a href="reset_password.php?token=<?php echo urlencode($token); ?>&email=<?php echo urlencode($email); ?>" class="back-link">← Intentar de nuevo</a>
        <?php elseif ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <a href="index.php" class="back-link">← Ir al inicio de sesión</a>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
