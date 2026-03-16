<?php
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once '../includes/bitacora_function.php';

$error = '';
$user_input_value = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $user_input_value = trim($_POST["user_input"] ?? '');
    $password_input = $_POST["password"] ?? '';
    
    if (empty($user_input_value) || empty($password_input)) {
        $error = "Por favor, complete ambos campos.";
    } else {
        try {
            // Usamos marcadores ? y pasamos dos veces el mismo valor al execute
            $sql = "
                SELECT 
                    u.id, 
                    u.username, 
                    u.email,
                    u.password, 
                    u.role_id, 
                    r.name as role_name
                FROM users u
                INNER JOIN roles r ON u.role_id = r.id
                WHERE u.username = ? OR u.email = ?
            ";
            
            $stmt = $conn->prepare($sql);
            // Ejecutamos con un array que contiene el mismo valor dos veces
            if ($stmt->execute([$user_input_value, $user_input_value])) {
                if ($stmt->rowCount() == 1) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $hashed_password = $row['password'];

                    if (password_verify($password_input, $hashed_password)) {
                        
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $row['id'];
                        $_SESSION["username"] = $row['username'];
                        $_SESSION["email"] = $row['email'];
                        $_SESSION["role_id"] = $row['role_id'];
                        $_SESSION["role_name"] = $row['role_name'];

                        if (function_exists('log_to_bitacora')) {
                            $action_log = "Inicio de sesión exitoso. Usuario: '{$row['username']}' (Rol: {$row['role_name']})";
                            log_to_bitacora($conn, $action_log, $row['username'], $row['role_id']);
                        }

                        // Redirigir al dashboard
                        header("Location: ../dashboard/welcome.php");
                        exit;

                    } else {
                        $error = "Usuario/Correo o contraseña incorrectos.";
                        if (function_exists('log_to_bitacora')) {
                            log_to_bitacora($conn, "Intento fallido para: '{$user_input_value}' (contraseña incorrecta)", 0, 0);
                        }
                    }
                } else {
                    $error = "Usuario/Correo o contraseña incorrectos.";
                    if (function_exists('log_to_bitacora')) {
                        log_to_bitacora($conn, "Intento fallido: Usuario/Correo '{$user_input_value}' no encontrado", 0, 0);
                    }
                }
            } else {
                $error = "Error al ejecutar la consulta.";
            }
            $stmt = null;
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
            error_log("PDO Error en login: " . $e->getMessage());
        }
    }
    
    // No cerrar $conn aquí porque aún puede ser usado en redirección
    $_SESSION['login_error'] = $error;
    header("Location: ../index.php");
    exit();
    
} else {
    header("Location: ../index.php");
    exit();
}
?>
