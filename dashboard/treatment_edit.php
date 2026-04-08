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

// Solo veterinario y admin pueden editar tratamientos
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
$pet_name = '';
$error = '';

try {
    // Obtener datos del tratamiento junto con nombre de la mascota
    $sql = "SELECT t.*, p.name AS pet_name 
            FROM treatments t
            JOIN pets p ON t.pet_id = p.id
            WHERE t.id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $treatment_id, PDO::PARAM_INT);
    $stmt->execute();
    $treatment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$treatment) {
        header("Location: treatment_history.php?error=not_found");
        exit;
    }

    // Verificar que el usuario sea el creador o admin
    if ($role_name !== 'admin' && $treatment['attendant_id'] != $user_id) {
        header("Location: treatment_history.php?error=unauthorized");
        exit;
    }

    $pet_name = $treatment['pet_name'];
} catch (PDOException $e) {
    $error = "Error al cargar los datos: " . $e->getMessage();
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $title = trim($_POST['title'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $medication_details = trim($_POST['medication_details'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'ACTIVO';

    // Validación de campos obligatorios
    if (empty($title) || empty($start_date) || empty($medication_details)) {
        $error = "Título, fecha de inicio y detalles de medicación son obligatorios.";
    } else {
        // Validación de fechas
        $start_timestamp = strtotime($start_date);
        if ($start_timestamp === false) {
            $error = "La fecha de inicio no es válida.";
        } elseif ($end_date !== null) {
            $end_timestamp = strtotime($end_date);
            if ($end_timestamp === false) {
                $error = "La fecha de fin no es válida.";
            } elseif ($end_timestamp < $start_timestamp) {
                $error = "La fecha de fin no puede ser anterior a la fecha de inicio.";
            }
        }
    }

    if (empty($error)) {
        try {
            // Actualizar tratamiento
            $sql = "UPDATE treatments 
                    SET title = :title, 
                        treatment_name = :treatment_name,
                        start_date = :start_date,
                        end_date = :end_date,
                        diagnosis = :diagnosis,
                        medication_details = :medication_details,
                        notes = :notes,
                        status = :status
                    WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':title', $title);
            $stmt->bindValue(':treatment_name', $title);
            $stmt->bindValue(':start_date', $start_date);
            $stmt->bindValue(':end_date', $end_date);
            $stmt->bindValue(':diagnosis', $diagnosis);
            $stmt->bindValue(':medication_details', $medication_details);
            $stmt->bindValue(':notes', $notes);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':id', $treatment_id, PDO::PARAM_INT);
            $stmt->execute();

            require_once '../includes/bitacora_function.php';
            $action = "Tratamiento #$treatment_id actualizado: $title";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

            header("Location: treatment_history.php?success=updated");
            exit;
        } catch (PDOException $e) {
            $error = "Error al actualizar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Tratamiento - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
        }
        body {
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 800px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #dc3545;
        }
        label {
            display: block;
            margin: 18px 0 6px;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        .btn {
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
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
        <span>Editar Tratamiento #<?php echo $treatment_id; ?></span>
    </div>

    <div class="container">
        <h1><i class="fas fa-edit"></i> Editar Tratamiento</h1>
        <p><strong>Paciente:</strong> <?php echo htmlspecialchars($pet_name); ?></p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="title">Título del tratamiento:</label>
            <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($treatment['title'] ?? ''); ?>" required>

            <label for="start_date">Fecha de inicio:</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($treatment['start_date'] ?? ''); ?>" required>

            <label for="end_date">Fecha estimada de fin (opcional):</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($treatment['end_date'] ?? ''); ?>">

            <label for="diagnosis">Diagnóstico asociado:</label>
            <textarea name="diagnosis" id="diagnosis" rows="3"><?php echo htmlspecialchars($treatment['diagnosis'] ?? ''); ?></textarea>

            <label for="medication_details">Detalles de medicación:</label>
            <textarea name="medication_details" id="medication_details" rows="4" required><?php echo htmlspecialchars($treatment['medication_details'] ?? ''); ?></textarea>

            <label for="notes">Notas adicionales:</label>
            <textarea name="notes" id="notes" rows="4"><?php echo htmlspecialchars($treatment['notes'] ?? ''); ?></textarea>

            <label for="status">Estado:</label>
            <select name="status" id="status">
                <option value="ACTIVO" <?php echo ($treatment['status'] ?? '') == 'ACTIVO' ? 'selected' : ''; ?>>Activo</option>
                <option value="COMPLETADO" <?php echo ($treatment['status'] ?? '') == 'COMPLETADO' ? 'selected' : ''; ?>>Completado</option>
                <option value="PAUSADO" <?php echo ($treatment['status'] ?? '') == 'PAUSADO' ? 'selected' : ''; ?>>Pausado</option>
            </select>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                <a href="treatment_history.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Cancelar</a>
            </div>
        </form>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
