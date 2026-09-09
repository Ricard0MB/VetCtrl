<?php
// Activar visualización de errores (QUITAR EN PRODUCCIÓN)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mail.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../public/forgot_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../public/forgot_password.php?error=' . urlencode('Correo inválido'));
    exit;
}

try {
    // Verificar si el usuario existe mediante PDO
    $sql = "SELECT id, email FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: ../public/forgot_password.php?msg=' . urlencode('Si existe una cuenta asociada, recibirás un correo con el enlace de recuperación.'));
        exit;
    }

    $user_id = $user['id'];
    $token = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', time() + 3600);
    $created_at = date('Y-m-d H:i:s');

    // Insertar token en password_resets usando PDO
    $insert = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($insert);
    $stmtInsert->execute([$user_id, $token, $expires_at, $created_at]);

    // Construir enlace de restablecimiento (absoluto)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $baseDir = rtrim(str_replace('/auth', '', $scriptDir), '/');
    $resetLink = $protocol . '://' . $host . $baseDir . '/public/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);

    $subject = 'Restablecer contraseña - VetCtrl';
    $message = "Hola,\n\nSe solicitó restablecer la contraseña para tu cuenta en VetCtrl. Haz clic en el siguiente enlace o pégalo en tu navegador:\n\n" . $resetLink . "\n\nEste enlace expirará en 1 hora. Si no solicitaste este restablecimiento, puedes ignorar este correo.\n\nSaludos.";

    $sent = send_mail($email, $subject, $message);

    if ($sent) {
        header('Location: ../public/forgot_password.php?msg=' . urlencode('Correo enviado. Revisa tu bandeja.'));
    } else {
        header('Location: ../public/forgot_password.php?msg=' . urlencode('No se pudo enviar el correo automáticamente. Copia el enlace de abajo para restaurar tu contraseña:') . '&link=' . urlencode($resetLink));
    }

} catch (PDOException $e) {
    error_log("PDO Error en forgot_password: " . $e->getMessage());
    header('Location: ../public/forgot_password.php?error=' . urlencode('Error interno en la base de datos'));
    exit;
}
?>
