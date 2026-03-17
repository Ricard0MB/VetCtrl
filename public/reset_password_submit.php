<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Validaciones básicas
if (empty($token) || empty($email) || empty($password) || empty($password_confirm)) {
    header('Location: ../public/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('Todos los campos son obligatorios'));
    exit;
}
if ($password !== $password_confirm) {
    header('Location: ../public/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('Las contraseñas no coinciden'));
    exit;
}
if (strlen($password) < 8) {
    header('Location: ../public/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('La contraseña debe tener al menos 8 caracteres'));
    exit;
}

try {
    // Buscar el usuario por token y email (la tabla users tiene reset_token y reset_expires)
    $sql = "SELECT id, reset_expires FROM users WHERE reset_token = :token AND email = :email LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: ../public/reset_password.php?error=' . urlencode('Token inválido o ya utilizado'));
        exit;
    }

    // Verificar expiración
    if (strtotime($user['reset_expires']) < time()) {
        header('Location: ../public/reset_password.php?error=' . urlencode('El enlace ha expirado'));
        exit;
    }

    // Actualizar la contraseña y limpiar los campos de reset
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $update = "UPDATE users SET password = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id";
    $stmtUpdate = $conn->prepare($update);
    $stmtUpdate->bindValue(':hash', $hash);
    $stmtUpdate->bindValue(':id', $user['id'], PDO::PARAM_INT);
    $stmtUpdate->execute();

    // Redirigir al login con mensaje de éxito
    header('Location: ../public/index.php?msg=' . urlencode('Contraseña actualizada. Por favor, inicia sesión.'));
    exit;

} catch (PDOException $e) {
    // Log del error (opcional)
    error_log("Error en reset_password_submit: " . $e->getMessage());
    header('Location: ../public/reset_password.php?error=' . urlencode('Error interno del servidor'));
    exit;
}
?>
