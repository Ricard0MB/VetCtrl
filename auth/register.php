<?php
session_start();

require_once '../includes/config.php'; // conexión PDO
require_once '../includes/functions.php';
require_once '../includes/bitacora_function.php';

// Limpiar mensajes anteriores
unset($_SESSION['registration_error']);
unset($_SESSION['registration_success']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $confirm_password = trim($_POST["confirm_password"] ?? '');

    // Validaciones básicas
    $errors = [];
    
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "Por favor, completa todos los campos.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Por favor, ingresa un correo electrónico válido.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres.";
    }
    
    if (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {
        $errors[] = "El nombre de usuario solo puede contener letras, números y guiones bajos.";
    }
    
    if (!empty($errors)) {
        $_SESSION['registration_error'] = implode("<br>", $errors);
        header("Location: ../public/register.php");
        exit();
    }

    // Verificar si el usuario ya existe (con PDO)
    $sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql_check);
    $stmt->execute([$username, $email]);
    if ($stmt->rowCount() > 0) {
        // Determinar cuál está duplicado para mensaje específico
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        // Podríamos hacer otra consulta para saber si es username o email, pero simplificamos
        // Vamos a verificar por separado para dar mensaje más preciso
        $stmt_username = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_username->execute([$username]);
        if ($stmt_username->rowCount() > 0) {
            $_SESSION['registration_error'] = "El nombre de usuario ya está en uso. Por favor, elige otro.";
        } else {
            $_SESSION['registration_error'] = "El correo electrónico ya está en uso. Por favor, utiliza otro.";
        }
        header("Location: ../public/register.php");
        exit();
    }

    // Hashear la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insertar nuevo usuario (role_id = 2 por defecto = Propietario)
    $sql = "INSERT INTO users (username, email, password, role_id, created_at) VALUES (?, ?, ?, 2, NOW())";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute([$username, $email, $hashed_password])) {
        // Registrar en bitácora
        if (function_exists('log_to_bitacora')) {
            $action_log = "Nuevo usuario registrado: '{$username}'";
            log_to_bitacora($conn, $action_log, $username, 2);
        }
        
        $_SESSION['registration_success'] = "¡Registro exitoso! Ahora puedes iniciar sesión.";
        // No cerrar $conn porque puede usarse después, pero al final del script se cierra automáticamente
        header("Location: ../index.php");
        exit();
    } else {
        // Si hay error en execute, se puede obtener el mensaje mediante errorInfo()
        $errorInfo = $stmt->errorInfo();
        $_SESSION['registration_error'] = "Error al registrar usuario: " . $errorInfo[2];
        header("Location: ../public/register.php");
        exit();
    }
} else {
    // Si no es POST, redirigir al formulario
    header("Location: ../public/register.php");
    exit();
}
?>
