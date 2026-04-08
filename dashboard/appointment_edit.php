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

if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($appointment_id <= 0) {
    header("Location: appointment_list.php");
    exit;
}

$error = '';
$success = '';
$appointment = null;
$pets = [];

try {
    // Cargar datos de la cita
    $sql = "SELECT * FROM appointments WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $appointment_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        header("Location: appointment_list.php?error=notfound");
        exit;
    }

    if ($role_name === 'Propietario' && $appointment['attendant_id'] != $user_id) {
        header("Location: appointment_list.php?error=unauthorized");
        exit;
    }

    // Cargar mascotas
    if ($role_name === 'Propietario') {
        $sql_pets = "SELECT id, name FROM pets WHERE owner_id = :owner_id ORDER BY name ASC";
        $stmtPets = $conn->prepare($sql_pets);
        $stmtPets->bindValue(':owner_id', $user_id, PDO::PARAM_INT);
    } else {
        $sql_pets = "SELECT id, name FROM pets ORDER BY name ASC";
        $stmtPets = $conn->prepare($sql_pets);
    }
    $stmtPets->execute();
    $pets = $stmtPets->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_POST['cancel_appointment'])) {
            $sqlCancel = "UPDATE appointments SET status = 'CANCELADA' WHERE id = :id";
            $stmtCancel = $conn->prepare($sqlCancel);
            $stmtCancel->bindValue(':id', $appointment_id, PDO::PARAM_INT);
            if ($stmtCancel->execute()) {
                require_once '../includes/bitacora_function.php';
                log_to_bitacora($conn, "Cita #$appointment_id cancelada", $username, $_SESSION['role_id'] ?? 0);
                header("Location: appointment_list.php?msg=cancelled");
                exit;
            } else {
                $error = "Error al cancelar.";
            }
        } elseif (isset($_POST['save_changes'])) {
            $pet_id = intval($_POST['pet_id']);
            $appointment_date = $_POST['appointment_date'];
            $reason = trim($_POST['reason']);

            if ($pet_id <= 0 || empty($appointment_date) || empty($reason)) {
                $error = "Todos los campos son obligatorios.";
            } else {
                $timestamp = strtotime($appointment_date);
                if (!$timestamp || $timestamp <= time()) {
                    $error = "La fecha debe ser futura.";
                } else {
                    $formatted = date('Y-m-d H:i:s', $timestamp);
                    if ($role_name === 'Propietario') {
                        $checkSql = "SELECT id FROM pets WHERE id = :pet_id AND owner_id = :owner_id";
                        $checkStmt = $conn->prepare($checkSql);
                        $checkStmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                        $checkStmt->bindValue(':owner_id', $user_id, PDO::PARAM_INT);
                        $checkStmt->execute();
                        if ($checkStmt->rowCount() == 0) {
                            $error = "La mascota seleccionada no le pertenece.";
                        }
                    }
                    if (empty($error)) {
                        $updateSql = "UPDATE appointments SET pet_id = :pet_id, appointment_date = :date, reason = :reason WHERE id = :id";
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                        $updateStmt->bindValue(':date', $formatted, PDO::PARAM_STR);
                        $updateStmt->bindValue(':reason', $reason, PDO::PARAM_STR);
                        $updateStmt->bindValue(':id', $appointment_id, PDO::PARAM_INT);
                        if ($updateStmt->execute()) {
                            require_once '../includes/bitacora_function.php';
                            log_to_bitacora($conn, "Cita #$appointment_id actualizada", $username, $_SESSION['role_id'] ?? 0);
                            $success = "Cita actualizada correctamente.";
                            $appointment['pet_id'] = $pet_id;
                            $appointment['appointment_date'] = $formatted;
                            $appointment['reason'] = $reason;
                        } else {
                            $error = "Error al actualizar.";
                        }
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cita - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7f9;
            color: #1e2f2a;
            padding-top: 72px;
        }
        :root {
            --vet-dark: #1b4332;
            --vet-primary: #40916c;
            --shadow-md: 0 8px 20px rgba(0,0,0,0.05);
            --radius-lg: 16px;
        }
        .breadcrumb { max-width: 800px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1.2rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 1rem 0 0.4rem; font-weight: 600; color: var(--vet-dark); }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 0.7rem; border: 1px solid #d0d8d0; border-radius: 10px; font-family: inherit; transition: 0.2s; }
        select:focus, input:focus, textarea:focus { border-color: var(--vet-primary); outline: none; box-shadow: 0 0 0 2px rgba(64,145,108,0.2); }
        .form-actions { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn { padding: 0.7rem 1.2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: 0.2s; }
        .btn-primary { background: var(--vet-primary); color: white; }
        .btn-primary:hover { background: var(--vet-dark); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1.2rem; } .form-actions { flex-direction: column; } .btn { justify-content: center; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="appointment_list.php">Citas</a> <span>›</span>
        <span>Editar Cita #<?php echo $appointment_id; ?></span>
    </div>

    <div class="container">
        <h1><i class="fas fa-edit"></i> Editar Cita</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="pet_id">Mascota:</label>
            <select name="pet_id" id="pet_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($pets as $pet): ?>
                    <option value="<?php echo $pet['id']; ?>" <?php echo ($pet['id'] == $appointment['pet_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pet['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="appointment_date">Fecha y hora:</label>
            <input type="datetime-local" name="appointment_date" id="appointment_date" value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_date'])); ?>" required min="<?php echo date('Y-m-d\TH:i'); ?>">

            <label for="reason">Motivo:</label>
            <textarea name="reason" id="reason" rows="4" required><?php echo htmlspecialchars($appointment['reason']); ?></textarea>

            <div class="form-actions">
                <button type="submit" name="save_changes" class="btn btn-primary"><i class="fas fa-save"></i> Guardar</button>
                <button type="submit" name="cancel_appointment" class="btn btn-danger" onclick="return confirm('¿Cancelar esta cita?');"><i class="fas fa-times"></i> Cancelar Cita</button>
                <a href="appointment_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </form>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
