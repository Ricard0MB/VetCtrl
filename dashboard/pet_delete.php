<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: ../index.php?error=access_denied");
    exit;
}

require_once '../includes/config.php';
require_once '../includes/bitacora_function.php'; // si existe, si no, comenta

$pet_id = isset($_GET['id']) && is_numeric($_GET['id']) ? intval($_GET['id']) : 0;
$confirm = isset($_GET['confirm']) && $_GET['confirm'] == 1;

if (!$pet_id) {
    header("Location: search_pet_owner.php?error=invalidid");
    exit;
}

if (!$confirm) {
    header("Location: pet_profile.php?id=$pet_id&error=confirm_required");
    exit;
}

try {
    // Verificar que la mascota existe
    $stmt = $conn->prepare("SELECT name FROM pets WHERE id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pet) {
        header("Location: search_pet_owner.php?error=pet_not_found");
        exit;
    }

    // ===== INICIAR TRANSACCIÓN =====
    $conn->beginTransaction();

    // Eliminar registros dependientes en orden (para respetar claves foráneas)
    // Primero las tablas que tienen FK hacia pets
    $dependent_tables = [
        'consultations' => 'pet_id',
        'appointments'  => 'pet_id',
        'vaccines'      => 'pet_id',
        'treatments'    => 'pet_id',
        // Si tienes otras tablas con FK a pets, agrégalas aquí
    ];

    $deleted_counts = [];

    foreach ($dependent_tables as $table => $fk_column) {
        try {
            // Verificar si la tabla existe
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() == 0) continue;

            // Verificar si la columna existe (opcional)
            $col_check = $conn->query("SHOW COLUMNS FROM $table LIKE '$fk_column'");
            if ($col_check->rowCount() == 0) continue;

            $stmt = $conn->prepare("DELETE FROM $table WHERE $fk_column = :id");
            $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
            $stmt->execute();
            $deleted_counts[$table] = $stmt->rowCount();
        } catch (PDOException $e) {
            // Si hay error, hacemos rollback y lanzamos excepción
            $conn->rollBack();
            throw new PDOException("Error eliminando registros de $table: " . $e->getMessage());
        }
    }

    // Finalmente eliminar la mascota
    $stmt = $conn->prepare("DELETE FROM pets WHERE id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();

    // Commit de la transacción
    $conn->commit();

    // Registrar en bitácora con detalle de cuántos registros se eliminaron
    $username = $_SESSION['username'] ?? 'Usuario';
    $details = [];
    foreach ($deleted_counts as $table => $count) {
        if ($count > 0) {
            $details[] = "$count en $table";
        }
    }
    $detail_str = empty($details) ? "ningún registro dependiente" : implode(', ', $details);
    $action = "Mascota eliminada: {$pet['name']} (ID $pet_id) - Registros eliminados: $detail_str";
    if (function_exists('log_to_bitacora')) {
        log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
    } else {
        error_log("Eliminación de mascota: $action por $username");
    }

    // Redirigir con mensaje de éxito
    $msg = urlencode("Mascota {$pet['name']} eliminada correctamente junto con todos sus registros asociados.");
    header("Location: pet_list.php?msg=" . $msg);
    exit;

} catch (PDOException $e) {
    // Asegurar rollback si la transacción sigue activa
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error al eliminar mascota ID $pet_id: " . $e->getMessage());
    $error_detail = urlencode("Error interno: " . $e->getMessage());
    header("Location: pet_profile.php?id=$pet_id&error=$error_detail");
    exit;
}
?>
