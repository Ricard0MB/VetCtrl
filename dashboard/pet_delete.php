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
require_once '../includes/bitacora_function.php'; // si existe, para log

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

    // Verificar si hay registros dependientes
    // Consultas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM consultations WHERE pet_id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Vacunas (asumiendo tabla vaccinations con pet_id)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM vaccinations WHERE pet_id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $vaccinations = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Citas (appointments)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE pet_id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Tratamientos (si hay tabla treatment_records)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM treatment_records WHERE pet_id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $treatments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($consultations > 0 || $vaccinations > 0 || $appointments > 0 || $treatments > 0) {
        $msg = "No se puede eliminar la mascota porque tiene registros asociados: ";
        $details = [];
        if ($consultations > 0) $details[] = "$consultations consulta(s)";
        if ($vaccinations > 0) $details[] = "$vaccinations vacuna(s)";
        if ($appointments > 0) $details[] = "$appointments cita(s)";
        if ($treatments > 0) $details[] = "$treatments tratamiento(s)";
        $msg .= implode(', ', $details);
        header("Location: pet_profile.php?id=$pet_id&error=" . urlencode($msg));
        exit;
    }

    // Iniciar transacción
    $conn->beginTransaction();

    // Eliminar mascota (las tablas dependientes ya fueron verificadas como vacías)
    $stmt = $conn->prepare("DELETE FROM pets WHERE id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();

    // Registrar en bitácora
    $username = $_SESSION['username'] ?? 'Usuario';
    $action = "Mascota eliminada: {$pet['name']} (ID $pet_id)";
    if (function_exists('log_to_bitacora')) {
        log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
    }

    $conn->commit();

    // Redirigir con mensaje de éxito
    header("Location: search_pet_owner.php?msg=" . urlencode("Mascota {$pet['name']} eliminada correctamente."));
    exit;

} catch (PDOException $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    error_log("Error al eliminar mascota $pet_id: " . $e->getMessage());
    header("Location: pet_profile.php?id=$pet_id&error=" . urlencode("Error interno al eliminar. Contacta al administrador."));
    exit;
}
?>
