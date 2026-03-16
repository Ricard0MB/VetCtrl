<?php
// includes/bitacora_function.php

// NOTA: Este archivo asume que la conexión a la base de datos ($conn) ya ha sido establecida
// y que la sesión ($_SESSION) está iniciada en el script que lo incluye.

/**
 * Registra una acción en la tabla 'db_bitacora' (versión PDO).
 *
 * @param PDO $conn Objeto de conexión a la base de datos (PDO).
 * @param string $action Descripción de la acción realizada.
 * @param string $username Nombre de usuario que realiza la acción.
 * @param int $role_id ID del rol del usuario.
 * @return bool True si el registro fue exitoso, False en caso contrario.
 */
function log_to_bitacora($conn, $action, $username = 'Sistema', $role_id = 0) {
    try {
        $sql = "INSERT INTO db_bitacora (role_id, username, action, timestamp) 
                VALUES (:role_id, :username, :action, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':role_id', $role_id, PDO::PARAM_INT);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error en log_to_bitacora: " . $e->getMessage());
        return false;
    }
}

/**
 * Registra una acción en la bitácora del sistema (versión simplificada que usa sesión automáticamente).
 * 
 * @param string $action Descripción de la acción realizada.
 * @param int|null $user_id ID del usuario que realiza la acción (opcional, si no se proporciona se obtiene de la sesión).
 * @return bool True si se registró correctamente, false en caso de error.
 */
function register_log($action, $user_id = null) {
    global $conn;
    
    // Iniciar sesión si no está iniciada (solo lectura)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if ($user_id === null) {
        // Tomar datos de la sesión actual
        $username = $_SESSION['username'] ?? 'Sistema';
        $role_id = $_SESSION['role_id'] ?? 0;
        return log_to_bitacora($conn, $action, $username, $role_id);
    } else {
        // Obtener nombre de usuario y rol desde la base de datos
        try {
            $stmt = $conn->prepare("SELECT username, role_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return log_to_bitacora($conn, $action, $row['username'], $row['role_id']);
            } else {
                error_log("register_log: usuario ID $user_id no encontrado");
                return false;
            }
        } catch (PDOException $e) {
            error_log("Error en register_log al consultar usuario: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Función alternativa para compatibilidad (alias de register_log).
 */
function log_action($action, $user_id = null) {
    return register_log($action, $user_id);
}

/**
 * Versión que usa log_to_bitacora internamente, basada en sesión (compatible con la anterior).
 */
function register_log_v2($action) {
    global $conn;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $username = $_SESSION['username'] ?? 'Sistema';
    $role_id = $_SESSION['role_id'] ?? 0;
    
    return log_to_bitacora($conn, $action, $username, $role_id);
}
?>
