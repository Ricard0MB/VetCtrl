<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo veterinario y admin pueden eliminar tratamientos
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$treatment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($treatment_id <= 0) {
    header("Location: treatment_history.php?error=invalid_id");
    exit;
}

$treatment = null;
$error = '';

try {
    // Verificar que el tratamiento existe y pertenece al usuario (o admin)
    $sql = "SELECT id, title FROM treatments WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $treatment_id, PDO::PARAM_INT);
    $stmt->execute();
    $treatment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$treatment) {
        header("Location: treatment_history.php?error=not_found");
        exit;
    }

    // Si no es admin, verificar que el attendant_id coincida
    if ($role_name !== 'admin') {
        $sql = "SELECT id FROM treatments WHERE id = :id AND attendant_id = :attendant_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $treatment_id, PDO::PARAM_INT);
        $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        if (!$stmt->fetch()) {
            header("Location: treatment_history.php?error=unauthorized");
            exit;
        }
    }

    // Procesar confirmación
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        // Eliminar el tratamiento
        $sql = "DELETE FROM treatments WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $treatment_id, PDO::PARAM_INT);
        $stmt->execute();

        require_once '../includes/bitacora_function.php';
        $action = "Tratamiento #$treatment_id eliminado: " . $treatment['title'];
        log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

        header("Location: treatment_history.php?success=deleted");
        exit;
    }

} catch (PDOException $e) {
    $error = "Error al procesar la eliminación: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Tratamiento - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 600px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); text-align: center; }
        h1 { color: #dc3545; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; text-align: left; }
        .alert i { font-size: 1.4rem; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .alert-warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
        .btn-group { display: flex; gap: 15px; justify-content: center; margin-top: 30px; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        @media (max-width: 768px) {
            .btn-group { flex-direction: column; }
            .btn-group .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="treatment_history.php">Tratamientos</a> <span>›</span>
        <span>Eliminar Tratamiento</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-trash-alt"></i> Eliminar Tratamiento</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-circle"></i>
                ¿Estás seguro de que deseas eliminar el tratamiento <strong>"<?php echo htmlspecialchars($treatment['title']); ?>"</strong>? Esta acción no se puede deshacer.
            </div>
            <div class="btn-group">
                <a href="treatment_delete.php?id=<?php echo $treatment_id; ?>&confirm=yes" class="btn btn-danger"><i class="fas fa-check"></i> Sí, eliminar</a>
                <a href="treatment_history.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
