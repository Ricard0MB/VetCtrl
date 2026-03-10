<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if ($role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$employee_id = intval($_GET['id'] ?? 0);
if ($employee_id <= 0) {
    header("Location: employee_list.php");
    exit;
}

// No permitir eliminarse a sí mismo
if ($employee_id == ($_SESSION['user_id'] ?? 0)) {
    header("Location: employee_list.php?error=self_delete");
    exit;
}

$sql = "SELECT first_name, last_name, username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    header("Location: employee_list.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm_text'] === 'ELIMINAR') {
    $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
    $delete->bind_param("i", $employee_id);
    if ($delete->execute()) {
        require_once '../includes/bitacora_function.php';
        $action = "Empleado eliminado: " . $employee['first_name'] . " " . $employee['last_name'];
        log_to_bitacora($conn, $action, $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);
        header("Location: employee_list.php?msg=deleted");
        exit;
    } else {
        $error = "Error al eliminar: " . $delete->error;
    }
    $delete->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Eliminar Empleado - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 800px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .warning-box { background: #fff3cd; border-left: 5px solid #ffc107; padding: 20px; margin-bottom: 20px; color: #856404; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .form-group { margin-bottom: 20px; }
        input[type="text"] { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="employee_list.php">Empleados</a> <span>›</span>
        <span>Eliminar</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-trash-alt"></i> Eliminar Empleado</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡Advertencia!</strong> Estás a punto de eliminar permanentemente a <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong> (usuario: <?php echo htmlspecialchars($employee['username']); ?>). Esta acción no se puede deshacer.
            </div>

            <form method="post">
                <p>Para confirmar, escribe <strong>ELIMINAR</strong> en el campo:</p>
                <div class="form-group">
                    <input type="text" name="confirm_text" id="confirm_text" autocomplete="off" required>
                </div>
                <input type="hidden" name="confirm" value="1">
                <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> Confirmar eliminación</button>
                <a href="employee_list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            </form>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>