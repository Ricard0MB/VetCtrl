<?php
require_once '../includes/config.php'; 
require_once '../includes/bitacora_function.php';

session_start();

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $username = $_SESSION['username'] ?? 'Usuario Desconocido';
    $role_id = $_SESSION['role_id'] ?? 2; 

    $action = "Cierre de sesión exitoso. Usuario: '{$username}'.";
    log_to_bitacora($conn, $action, $username, $role_id);
    
    $conn->close();
}

$_SESSION = array();
session_destroy();

// Redirigir a la página de inicio (raíz del proyecto)
header("location: ../index.php");
exit();
?>