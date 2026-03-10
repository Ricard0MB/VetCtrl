<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';

if (empty($token) || empty($email) || empty($password) || empty($password_confirm)) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('Todos los campos son obligatorios'));
    exit;
}

if ($password !== $password_confirm) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('Las contraseñas no coinciden'));
    exit;
}

if (strlen($password) < 6) {
    header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email) . '&error=' . urlencode('La contraseña debe tener al menos 6 caracteres'));
    exit;
}

// Validar token nuevamente y obtener user_id
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
        header('Location: reset_password.php?error=' . urlencode('Token inválido o ya utilizado'));
        exit;
    }
    if (strtotime($row['expires_at']) < time()) {
        header('Location: reset_password.php?error=' . urlencode('El enlace ha expirado'));
        exit;
    }
    $user_id = $row['user_id'];
    $reset_id = $row['reset_id'];
} else {
    header('Location: reset_password.php?error=' . urlencode('Error interno al validar el token'));
    exit;
}

// Actualizar contraseña
$hashed = password_hash($password, PASSWORD_DEFAULT);
$update = "UPDATE users SET password = ? WHERE id = ?";
if ($stmt = $conn->prepare($update)) {
    $stmt->bind_param('si', $hashed, $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        header('Location: reset_password.php?error=' . urlencode('Error al actualizar la contraseña'));
        exit;
    }
    $stmt->close();
} else {
    header('Location: reset_password.php?error=' . urlencode('Error interno al actualizar'));
    exit;
}

// Eliminar el token usado (por seguridad)
$del = "DELETE FROM password_resets WHERE id = ?";
if ($stmt = $conn->prepare($del)) {
    $stmt->bind_param('i', $reset_id);
    $stmt->execute();
    $stmt->close();
}

// Redirigir al login con mensaje de éxito
header('Location: index.php?msg=' . urlencode('Contraseña actualizada. Por favor, inicia sesión.'));
exit;
?>