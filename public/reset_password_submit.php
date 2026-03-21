<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Mostrar errores temporalmente para depurar (luego puedes quitarlo)
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Validaciones
if (empty($token) || empty($email) || empty($password) || empty($password_confirm)) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('Todos los campos son obligatorios'));
    exit;
}
if ($password !== $password_confirm) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('Las contraseñas no coinciden'));
    exit;
}
if (strlen($password) < 8) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('La contraseña debe tener al menos 8 caracteres'));
    exit;
}

try {
    // Buscar usuario por token y email
    $sql = "SELECT id, reset_expires FROM users WHERE reset_token = :token AND email = :email LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Location: reset_password.php?error=' . urlencode('Token inválido o ya utilizado'));
        exit;
    }

    // Verificar expiración
    if (strtotime($user['reset_expires']) < time()) {
        header('Location: reset_password.php?error=' . urlencode('El enlace ha expirado'));
        exit;
    }

    // Actualizar contraseña y limpiar token
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $update = "UPDATE users SET password = :hash, reset_token = NULL, reset_expires = NULL WHERE id = :id";
    $stmtUpdate = $conn->prepare($update);
    $stmtUpdate->bindValue(':hash', $hash);
    $stmtUpdate->bindValue(':id', $user['id'], PDO::PARAM_INT);
    $stmtUpdate->execute();

    // Verificar si se actualizó alguna fila
    if ($stmtUpdate->rowCount() === 0) {
        header('Location: reset_password.php?error=' . urlencode('No se pudo actualizar la contraseña. Intenta de nuevo.'));
        exit;
    }

    // Redirigir al login (login.php está en la misma carpeta public)
    header('Location: login.php?msg=' . urlencode('Contraseña actualizada correctamente. Inicia sesión.'));
    exit;

} catch (PDOException $e) {
    error_log("Error en reset_password_submit: " . $e->getMessage());
    header('Location: reset_password.php?error=' . urlencode('Error interno del servidor. Intenta más tarde.'));
    exit;
}
?>
