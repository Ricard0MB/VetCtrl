<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/bitacora_function.php';

unset($_SESSION['registration_error']);
unset($_SESSION['registration_success']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $password = trim($_POST["password"] ?? '');
    $confirm_password = trim($_POST["confirm_password"] ?? '');

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

    try {
        // Verificar si el usuario ya existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['registration_error'] = "El nombre de usuario ya está en uso. Por favor, elige otro.";
            header("Location: ../public/register.php");
            exit();
        }

        // Verificar si el email ya existe
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $_SESSION['registration_error'] = "El correo electrónico ya está en uso. Por favor, utiliza otro.";
            header("Location: ../public/register.php");
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insertar nuevo usuario (role_id = 2 por defecto = Propietario)
        $sql = "INSERT INTO users (username, email, password, role_id, created_at) VALUES (?, ?, ?, 2, NOW())";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$username, $email, $hashed_password])) {
            if (function_exists('log_to_bitacora')) {
                log_to_bitacora($conn, "Nuevo usuario registrado: '{$username}'", $username, 2);
            }
            $_SESSION['registration_success'] = "¡Registro exitoso! Ahora puedes iniciar sesión.";
            header("Location: ../index.php");
            exit();
        } else {
            $_SESSION['registration_error'] = "Error al registrar usuario.";
            header("Location: ../public/register.php");
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['registration_error'] = "Error de base de datos: " . $e->getMessage();
        header("Location: ../public/register.php");
        exit();
    }
} else {
    header("Location: ../public/register.php");
    exit();
}
?>
