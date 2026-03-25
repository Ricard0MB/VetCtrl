<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$role_name = $_SESSION['role_name'] ?? '';
// Solo veterinarios y admin pueden eliminar mascotas
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pet_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($pet_id <= 0) {
    header("Location: search_pet_owner.php?error=invalid_pet");
    exit;
}

// Verificar confirmación
if (!isset($_GET['confirm']) || $_GET['confirm'] != 1) {
    // Si no hay confirmación, redirigir al perfil con advertencia
    header("Location: pet_profile.php?id=$pet_id&error=confirm_required");
    exit;
}

$message = '';
$error = false;

try {
    // Obtener nombre de la mascota para bitácora
    $stmt = $conn->prepare("SELECT name FROM pets WHERE id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pet) {
        header("Location: search_pet_owner.php?error=not_found");
        exit;
    }
    $pet_name = $pet['name'];

    // Iniciar transacción
    $conn->beginTransaction();

    // 1. Eliminar citas asociadas
    $stmt = $conn->prepare("DELETE FROM appointments WHERE pet_id = :pet_id");
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $deleted_appointments = $stmt->rowCount();

    // 2. Eliminar vacunas asociadas
    $stmt = $conn->prepare("DELETE FROM vaccines WHERE pet_id = :pet_id");
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $deleted_vaccines = $stmt->rowCount();

    // 3. Eliminar tratamientos asociados
    $stmt = $conn->prepare("DELETE FROM treatments WHERE pet_id = :pet_id");
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $deleted_treatments = $stmt->rowCount();

    // 4. Eliminar consultas asociadas
    $stmt = $conn->prepare("DELETE FROM consultations WHERE pet_id = :pet_id");
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $deleted_consultations = $stmt->rowCount();

    // 5. Finalmente eliminar la mascota
    $stmt = $conn->prepare("DELETE FROM pets WHERE id = :id");
    $stmt->bindValue(':id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();

    // Commit de la transacción
    $conn->commit();

    // Registrar en bitácora
    require_once '../includes/bitacora_function.php';
    $action = "Mascota eliminada: $pet_name (ID: $pet_id) - Registros eliminados: Citas: $deleted_appointments, Vacunas: $deleted_vaccines, Tratamientos: $deleted_treatments, Consultas: $deleted_consultations";
    log_to_bitacora($conn, $action, $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);

    // Redirigir con mensaje de éxito
    $message = urlencode("Mascota '$pet_name' eliminada correctamente junto con todos sus registros asociados.");
    header("Location: search_pet_owner.php?success=" . $message);
    exit;

} catch (PDOException $e) {
    // Rollback en caso de error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $error_msg = "Error al eliminar la mascota: " . $e->getMessage();
    $message = urlencode($error_msg);
    header("Location: pet_profile.php?id=$pet_id&error=" . $message);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminando mascota...</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; text-align: center; }
        .container { max-width: 500px; margin: 100px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #40916c; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        p { color: #6c757d; }
    </style>
    <meta http-equiv="refresh" content="2;url=search_pet_owner.php?success=<?php echo urlencode("Mascota eliminada correctamente."); ?>">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container">
        <h1><i class="fas fa-trash-alt"></i> Eliminando mascota...</h1>
        <div class="spinner"></div>
        <p>Por favor, espera mientras se eliminan todos los registros asociados.</p>
        <p>Serás redirigido automáticamente.</p>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
