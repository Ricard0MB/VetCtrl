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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Tratamiento - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --danger: #dc3545;
            --danger-dark: #b91c1c;
        }
        body {
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 600px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
            text-align: center;
        }
        h1 {
            color: var(--danger);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: left;
            border-left: 5px solid;
        }
        .alert-danger {
            background: #fee7e7;
            color: var(--danger-dark);
            border-left-color: var(--danger);
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background: var(--danger-dark);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #eef2f8;
            color: var(--primary-dark);
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        @media (max-width: 640px) {
            .container { padding: 20px; margin: 15px; }
            .btn-group { flex-direction: column; }
            .btn-group .btn { width: 100%; justify-content: center; }
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
