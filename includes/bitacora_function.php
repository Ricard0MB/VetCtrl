<?php
// ../includes/bitacora_function.php

// NOTA: Este archivo asume que la conexión a la base de datos ($conn) ya ha sido establecida
// y que la sesión ($_SESSION) está iniciada en el script que lo incluye.

/**
 * Registra una acción en la tabla 'db_bitacora' (versión con parámetros explícitos).
 *
 * @param mysqli $conn Objeto de conexión a la base de datos.
 * @param string $action Descripción de la acción realizada (ej: "Mascota registrada").
 * @param string $username Nombre de usuario que realiza la acción.
 * @param int $role_id ID del rol del usuario.
 * @return bool True si el registro fue exitoso, False en caso contrario.
 */
function log_to_bitacora($conn, $action, $username, $role_id) {
    // === USANDO EL NOMBRE DE TABLA SOLICITADO: db_bitacora ===
    $sql = "INSERT INTO db_bitacora (role_id, username, action, timestamp) 
            VALUES (?, ?, ?, CURRENT_TIMESTAMP())";
    
    // Preparar la consulta para la seguridad (Previene Inyección SQL)
    if ($stmt = $conn->prepare($sql)) {
        
        // Vincula los parámetros a la sentencia preparada:
        // "i" para integer (role_id)
        // "s" para string (username)
        // "s" para string (action)
        $stmt->bind_param("iss", $role_id, $username, $action);
        
        // Ejecutar la sentencia
        if ($stmt->execute()) {
            // Éxito en el registro
            $stmt->close();
            return true;
        } else {
            // Error en la ejecución de la sentencia
            error_log("Error al ejecutar la sentencia de bitácora: " . $stmt->error);
            $stmt->close();
            return false;
        }
    } else {
        // Error en la preparación de la sentencia (ej: error de sintaxis en el SQL)
        error_log("Error al preparar la sentencia de bitácora: " . $conn->error);
        return false;
    }
}

/**
 * Registra una acción en la bitácora del sistema (versión simplificada)
 * 
 * @param string $action Descripción de la acción realizada
 * @param int|null $user_id ID del usuario que realiza la acción (opcional, usa el de sesión por defecto)
 * @return bool True si se registró correctamente, false en caso de error
 */
function register_log($action, $user_id = null) {
    global $conn; // Asegúrate de que $conn esté disponible
    
    // Si no se proporciona user_id, usar el de la sesión
    if ($user_id === null) {
        // No iniciar sesión si ya está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $user_id = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'Sistema';
        $role_id = $_SESSION['role_id'] ?? 0;
    } else {
        // Si se proporciona user_id, obtener nombre de usuario y rol
        $user_query = $conn->prepare("SELECT username, role_id FROM users WHERE id = ?");
        $user_query->bind_param("i", $user_id);
        $user_query->execute();
        $user_result = $user_query->get_result();
        
        if ($user_result->num_rows > 0) {
            $user_data = $user_result->fetch_assoc();
            $username = $user_data['username'];
            $role_id = $user_data['role_id'];
        } else {
            $username = 'Usuario desconocido';
            $role_id = 0;
        }
        $user_query->close();
    }
    
    try {
        // Preparar la consulta
        $sql = "INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            error_log("Error preparando consulta de bitácora: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("iss", $role_id, $username, $action);
        
        if ($stmt->execute()) {
            $stmt->close();
            return true;
        } else {
            error_log("Error ejecutando consulta de bitácora: " . $stmt->error);
            $stmt->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Excepción en register_log: " . $e->getMessage());
        return false;
    }
}

/**
 * Función alternativa para compatibilidad (alias de register_log)
 */
function log_action($action, $user_id = null) {
    return register_log($action, $user_id);
}

/**
 * Versión mejorada que usa log_to_bitacora internamente para consistencia
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