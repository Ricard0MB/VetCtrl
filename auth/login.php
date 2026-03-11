<?php
session_start();

// Inclusiones de archivos
require_once __DIR__ . '/../includes/conexion.php';
require_once '../includes/bitacora_function.php';

$error = '';
$user_input_value = '';

// Verifica si la solicitud es un envío de formulario (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Obtener y sanear la entrada del usuario (USANDO user_input como en index.php)
    $user_input_value = trim($_POST["user_input"] ?? '');
    $password_input = $_POST["password"] ?? '';
    
    // 1. Validar que los campos no estén vacíos
    if (empty($user_input_value) || empty($password_input)) {
        $error = "Por favor, complete ambos campos.";
    } else {
        // 2. Preparar la consulta SQL con JOIN a la tabla 'roles'
        //    Buscar TANTO por email como por username en UNA SOLA consulta
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
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("ss", $user_input_value, $user_input_value);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $row = $result->fetch_assoc();
                    $hashed_password = $row['password'];

                    // 3. Verificar la contraseña
                    if (password_verify($password_input, $hashed_password)) {
                        
                        // Contraseña correcta, iniciar sesión y guardar variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["user_id"] = $row['id'];
                        $_SESSION["username"] = $row['username'];
                        $_SESSION["email"] = $row['email'];
                        $_SESSION["role_id"] = $row['role_id'];
                        $_SESSION["role_name"] = $row['role_name'];

                        // --- REGISTRO EN BITÁCORA: Login Exitoso ---
                        if (function_exists('log_to_bitacora')) {
                            $action_log = "Inicio de sesión exitoso. Usuario: '{$row['username']}' (Rol: {$row['role_name']})";
                            log_to_bitacora($conn, $action_log, $row['username'], $row['role_id']);
                        }

                        $stmt->close();
                        $conn->close();

                        // Redirigir al dashboard CORRECTO
                        header("Location: ../dashboard/welcome.php");
                        exit;

                    } else {
                        // Mensaje genérico por seguridad (no especificar que la contraseña es incorrecta)
                        $error = "Usuario/Correo o contraseña incorrectos.";
                        if (function_exists('log_to_bitacora')) {
                            log_to_bitacora($conn, "Intento de inicio de sesión fallido para: '{$user_input_value}'.", 0, 0);
                        }
                    }
                } else {
                    // Mensaje genérico por seguridad
                    $error = "Usuario/Correo o contraseña incorrectos.";
                    if (function_exists('log_to_bitacora')) {
                        log_to_bitacora($conn, "Intento de inicio de sesión fallido: Usuario/Correo '{$user_input_value}' no encontrado.", 0, 0);
                    }
                }
                
                $result->free();
            } else {
                $error = "Error de ejecución: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error de preparación de la consulta: " . $conn->error;
        }
    }
    
    // Cierre de la conexión
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }

    // Si llegamos aquí, hubo un error
    $_SESSION['login_error'] = $error;
    // Redirigir de vuelta al formulario de login
    header("Location: ../index.php");
    exit();
} else {
    // Si no es POST, redirigir al formulario
    header("Location: ../index.php");
    exit();
}
?>
