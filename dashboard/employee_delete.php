<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_name'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$employee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$employee_id || $employee_id <= 0) {
    header("Location: employee_list.php?error=invalid_id");
    exit;
}

try {
    // Verificar que el empleado existe
    $check = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE id = :id");
    $check->bindValue(':id', $employee_id, PDO::PARAM_INT);
    $check->execute();
    $employee = $check->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        header("Location: employee_list.php?error=not_found");
        exit;
    }

    // Eliminar (no usar DELETE si hay restricciones de integridad, pero asumimos que se puede)
    $delete = $conn->prepare("DELETE FROM users WHERE id = :id");
    $delete->bindValue(':id', $employee_id, PDO::PARAM_INT);
    if ($delete->execute()) {
        require_once '../includes/bitacora_function.php';
        log_to_bitacora($conn, "Empleado eliminado: {$employee['first_name']} {$employee['last_name']} (ID $employee_id)", $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);
        header("Location: employee_list.php?msg=deleted");
    } else {
        header("Location: employee_list.php?error=delete_failed");
    }
} catch (PDOException $e) {
    header("Location: employee_list.php?error=db_error");
}
exit;
?>
