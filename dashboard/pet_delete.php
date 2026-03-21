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
require_once '../includes/bitacora_function.php'; // si no existe, comenta esta línea

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

    // ===== VERIFICAR REGISTROS DEPENDIENTES =====
    // Ajusta las tablas según tu esquema real
    $dependent_tables = [
        'consultations' => 'pet_id',
        'vaccinations' => 'pet_id',
        'appointments' => 'pet_id',
        'treatment_records' => 'pet_id',
        // Agrega aquí cualquier otra tabla que tenga pet_id como FK
    ];

    $has_dependencies = false;
    $dependencies_list = [];

    foreach ($dependent_tables as $table => $fk_column) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM $table WHERE $fk_column = :id");
        $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($count > 0) {
            $has_dependencies = true;
            $dependencies_list[] = "$count registro(s) en $table";
        }
    }

    if ($has_dependencies) {
        $msg = "No se puede eliminar la mascota porque tiene registros asociados: " . implode(', ', $dependencies_list);
        header("Location: pet_profile.php?id=$pet_id&error=" . urlencode($msg));
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Eliminar mascota
    $stmt = $conn->prepare("DELETE FROM pets WHERE id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();

    // Registrar en bitácora
    $username = $_SESSION['username'] ?? 'Usuario';
    $action = "Mascota eliminada: {$pet['name']} (ID $pet_id)";
    if (function_exists('log_to_bitacora')) {
        log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
    } else {
        // Si no existe la función, al menos escribimos en log de errores
        error_log("Eliminación de mascota: $action por $username");
    }

    $conn->commit();

    // Redirigir con mensaje de éxito
    header("Location: search_pet_owner.php?msg=" . urlencode("Mascota {$pet['name']} eliminada correctamente."));
    exit;

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    // Registrar el error completo en el log del servidor
    error_log("Error al eliminar mascota ID $pet_id: " . $e->getMessage());
    error_log("Código SQL: " . $e->getCode());
    
    // Opcional: Mostrar el error real en la URL para depuración (solo temporal)
    $error_detail = urlencode("Error: " . $e->getMessage());
    header("Location: pet_profile.php?id=$pet_id&error=$error_detail");
    exit;
}
?>
