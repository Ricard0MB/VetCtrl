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

$email = trim($_POST['email']);
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../public/forgot_password.php?error=' . urlencode('Correo inválido'));
    exit;
}

// Verificar si el usuario existe
$sql = "SELECT id, email FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    error_log("Error preparando consulta: " . $conn->error);
    header('Location: ../public/forgot_password.php?error=' . urlencode('Error interno'));
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: ../public/forgot_password.php?msg=' . urlencode('Si existe una cuenta asociada, recibirás un correo con el enlace de recuperación.'));
    exit;
}

$user_id = $user['id'];
$token = bin2hex(random_bytes(16));
$expires_at = date('Y-m-d H:i:s', time() + 3600);
$created_at = date('Y-m-d H:i:s');

$insert = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($insert);
if (!$stmt) {
    error_log("Error preparando insert: " . $conn->error);
    header('Location: ../public/forgot_password.php?error=' . urlencode('Error interno'));
    exit;
}
$stmt->bind_param('isss', $user_id, $token, $expires_at, $created_at);
if (!$stmt->execute()) {
    error_log("Error ejecutando insert: " . $stmt->error);
    $stmt->close();
    header('Location: ../public/forgot_password.php?error=' . urlencode('Error al crear el token'));
    exit;
}
$stmt->close();

// Construir enlace de restablecimiento (absoluto)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']); // /auth o /vetctrl/auth
$baseDir = rtrim(str_replace('/auth', '', $scriptDir), '/'); // vacío o /vetctrl
$resetLink = $protocol . '://' . $host . $baseDir . '/public/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);

$subject = 'Restablecer contraseña';
$message = "Hola,\n\nSe solicitó restablecer la contraseña para tu cuenta. Haz clic en el siguiente enlace o pégalo en tu navegador:\n\n" . $resetLink . "\n\nEste enlace expirará en 1 hora. Si no solicitaste este restablecimiento, puedes ignorar este correo.\n\nSaludos.";

$sent = send_mail($email, $subject, $message);

if ($sent) {
    header('Location: ../public/forgot_password.php?msg=' . urlencode('Correo enviado. Revisa tu bandeja.'));
} else {
    header('Location: ../public/forgot_password.php?msg=' . urlencode('No se pudo enviar el correo automáticamente. Copia el enlace de abajo para restaurar tu contraseña:') . '&link=' . urlencode($resetLink));
}
?>