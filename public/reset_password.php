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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
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
        body::before {
            content: "🐾";
            font-size: 120px;
            opacity: 0.05;
            position: absolute;
            bottom: 20px;
            left: 20px;
            pointer-events: none;
            transform: rotate(-15deg);
        }
        body::after {
            content: "🐾";
            font-size: 180px;
            opacity: 0.05;
            position: absolute;
            top: 20px;
            right: 20px;
            pointer-events: none;
            transform: rotate(15deg);
        }
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
        .container:hover {
            transform: translateY(-3px);
        }
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
        h1::before {
            content: "🐕";
            font-size: 2rem;
        }
        h1::after {
            content: "🐈";
            font-size: 2rem;
        }
        .alert {
            padding: 12px 18px;
            border-radius: 40px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            text-align: center;
            background: #f8f9fa;
            border-left: 5px solid;
        }
        .alert-danger {
            background-color: #ffebee;
            border-left-color: #d32f2f;
            color: #b71c1c;
        }
        .alert-success {
            background-color: #e8f5e9;
            border-left-color: #2e7d32;
            color: #1b5e20;
        }
        form {
            margin-top: 10px;
        }
        label {
            display: block;
            text-align: left;
            font-weight: 600;
            margin: 20px 0 8px 5px;
            color: #1b5e20;
            font-size: 0.9rem;
        }
        input[type="password"] {
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
        input[type="password"]:focus {
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
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2e7d32;
            text-decoration: none;
            font-size: 0.9rem;
            border-bottom: 1px dashed #2e7d32;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #1b5e20;
            border-bottom: 1px solid #1b5e20;
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
        footer p {
            margin: 0;
        }
        @media (max-width: 550px) {
            .container {
                padding: 30px 20px;
            }
            h1 {
                font-size: 1.6rem;
            }
        }
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
        <a href="index.php" class="back-link">← Volver al inicio de sesión</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
