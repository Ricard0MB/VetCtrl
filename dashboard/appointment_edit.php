<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo roles permitidos
if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($appointment_id <= 0) {
    header("Location: appointment_list.php");
    exit;
}

// Cargar datos de la cita
$sql = "SELECT * FROM appointments WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();
$stmt->close();

if (!$appointment) {
    header("Location: appointment_list.php?error=notfound");
    exit;
}

// Verificar permisos: propietario solo puede editar sus propias citas
if ($role_name === 'Propietario' && $appointment['attendant_id'] != $user_id) {
    header("Location: appointment_list.php?error=unauthorized");
    exit;
}

// Cargar mascotas según rol (para el select)
if ($role_name === 'Propietario') {
    $sql_pets = "SELECT id, name FROM pets WHERE owner_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql_pets);
    $stmt->bind_param("i", $user_id);
} else {
    $sql_pets = "SELECT id, name FROM pets ORDER BY name ASC";
    $stmt = $conn->prepare($sql_pets);
}
$stmt->execute();
$pets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error = '';
$success = '';

// Procesar POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['cancel_appointment'])) {
        // Cancelar cita
        $sql = "UPDATE appointments SET status = 'CANCELADA' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $appointment_id);
        if ($stmt->execute()) {
            require_once '../includes/bitacora_function.php';
            $action = "Cita #$appointment_id cancelada";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
            header("Location: appointment_list.php?msg=cancelled");
            exit;
        } else {
            $error = "Error al cancelar: " . $stmt->error;
        }
        $stmt->close();
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
                
                // Si es propietario, verificar que la mascota le pertenezca
                if ($role_name === 'Propietario') {
                    $check = $conn->prepare("SELECT id FROM pets WHERE id = ? AND owner_id = ?");
                    $check->bind_param("ii", $pet_id, $user_id);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows == 0) {
                        $error = "La mascota seleccionada no le pertenece.";
                    }
                    $check->close();
                }
                
                if (empty($error)) {
                    $sql = "UPDATE appointments SET pet_id = ?, appointment_date = ?, reason = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("issi", $pet_id, $formatted, $reason, $appointment_id);
                    if ($stmt->execute()) {
                        require_once '../includes/bitacora_function.php';
                        $action = "Cita #$appointment_id actualizada";
                        log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                        $success = "Cita actualizada correctamente.";
                        // Actualizar datos locales
                        $appointment['pet_id'] = $pet_id;
                        $appointment['appointment_date'] = $formatted;
                        $appointment['reason'] = $reason;
                    } else {
                        $error = "Error al actualizar: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cita - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 800px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .form-actions { display: flex; gap: 15px; margin-top: 30px; }
        .btn { padding: 12px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: #40916c; color: white; }
        .btn-primary:hover { background: #2d6a4f; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
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