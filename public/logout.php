<?php
require_once '../includes/config.php'; 
require_once '../includes/bitacora_function.php';

session_start();

// Verificar que hay una sesión activa antes de registrar el cierre
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $username = $_SESSION['username'] ?? 'Usuario Desconocido';
    $role_id = $_SESSION['role_id'] ?? 2; 

    $action = "Cierre de sesión exitoso. Usuario: '{$username}'.";
    log_to_bitacora($conn, $action, $username, $role_id);
    
    // Con PDO no existe $conn->close(), se asigna null para liberar (opcional)
    $conn = null;
}

// Destruir la sesión
$_SESSION = array();
session_destroy();

// Redirigir a la página de inicio (raíz del proyecto)
header("location: ../index.php");
exit();
?>
