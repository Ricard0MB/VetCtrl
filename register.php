<?php
session_start();

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/bitacora_function.php'; // Agregado

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

    // Verificar si el usuario ya existe
    $sql_check_username = "SELECT id FROM users WHERE username = ?";
    if ($stmt = $conn->prepare($sql_check_username)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['registration_error'] = "El nombre de usuario ya está en uso. Por favor, elige otro.";
            $stmt->close();
            header("Location: ../public/register.php");
            exit();
        }
        $stmt->close();
    }

    // Verificar si el email ya existe
    $sql_check_email = "SELECT id FROM users WHERE email = ?";
    if ($stmt = $conn->prepare($sql_check_email)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $_SESSION['registration_error'] = "El correo electrónico ya está en uso. Por favor, utiliza otro.";
            $stmt->close();
            header("Location: ../public/register.php");
            exit();
        }
        $stmt->close();
    }

    // Hashear la contraseña
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insertar nuevo usuario (role_id = 2 por defecto = Propietario)
    $sql = "INSERT INTO users (username, email, password, role_id, created_at) VALUES (?, ?, ?, 2, NOW())";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("sss", $username, $email, $hashed_password);

        if ($stmt->execute()) {
            // Registrar en bitácora
            if (function_exists('log_to_bitacora')) {
                $action_log = "Nuevo usuario registrado: '{$username}'";
                log_to_bitacora($conn, $action_log, $username, 2);
            }
            
            $_SESSION['registration_success'] = "¡Registro exitoso! Ahora puedes iniciar sesión.";
            $stmt->close();
            $conn->close();
            header("Location: ../index.php");
            exit();
        } else {
            $_SESSION['registration_error'] = "Error al registrar usuario: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['registration_error'] = "Error de preparación de la consulta: " . $conn->error;
    }

    $conn->close();
    header("Location: ../public/register.php");
    exit();
} else {
    // Si no es POST, redirigir al formulario
    header("Location: ../public/register.php");
    exit();
}
?>