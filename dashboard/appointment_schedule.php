<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Admin y veterinario también pueden agendar citas (para cualquier mascota)
if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pets = [];
$vets = []; // Lista de veterinarios
$error = '';
$success = '';

// Obtener lista de veterinarios (usuarios con rol Veterinario)
// Ajusta el role_id según tu base de datos. Por ejemplo, si 'Veterinario' tiene role_id = 2
$vet_role_id = 2; // CAMBIA ESTO SI ES NECESARIO
$sql_vets = "SELECT id, username, CONCAT('Dr(a). ', username) AS display_name FROM users WHERE role_id = ? ORDER BY username ASC";
$stmt_vets = $conn->prepare($sql_vets);
$stmt_vets->bind_param("i", $vet_role_id);
$stmt_vets->execute();
$result_vets = $stmt_vets->get_result();
while ($row = $result_vets->fetch_assoc()) {
    $vets[] = $row;
}
$stmt_vets->close();

// Si no hay veterinarios, mostrar mensaje de error
if (empty($vets)) {
    $error = "No hay veterinarios registrados en el sistema. Por favor, contacte al administrador.";
}

// Cargar mascotas según el rol
if ($role_name === 'Propietario') {
    // Propietario: solo sus mascotas
    $sql_pets = "SELECT id, name FROM pets WHERE owner_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql_pets);
    $stmt->bind_param("i", $user_id);
} else {
    // Veterinario/admin: todas las mascotas
    $sql_pets = "SELECT id, name FROM pets ORDER BY name ASC";
    $stmt = $conn->prepare($sql_pets);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pets[] = $row;
}
$stmt->close();

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $vet_id = intval($_POST['vet_id'] ?? 0); // Veterinario seleccionado
    $appointment_date = $_POST['appointment_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($pet_id <= 0 || $vet_id <= 0 || empty($appointment_date) || empty($reason)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        $timestamp = strtotime($appointment_date);
        if (!$timestamp || $timestamp <= time()) {
            $error = "La fecha y hora deben ser futuras.";
        } else {
            $formatted_date = date('Y-m-d H:i:s', $timestamp);
            
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
            
            // Verificar que el veterinario seleccionado exista y sea veterinario (opcional, pero seguro)
            if (empty($error)) {
                $check_vet = $conn->prepare("SELECT id FROM users WHERE id = ? AND role_id = ?");
                $check_vet->bind_param("ii", $vet_id, $vet_role_id);
                $check_vet->execute();
                $check_vet->store_result();
                if ($check_vet->num_rows == 0) {
                    $error = "El veterinario seleccionado no es válido.";
                }
                $check_vet->close();
            }
            
            if (empty($error)) {
                // Insertar cita. attendant_id será el veterinario seleccionado
                $sql_insert = "INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) VALUES (?, ?, ?, ?, 'PENDIENTE')";
                $stmt = $conn->prepare($sql_insert);
                $stmt->bind_param("iiss", $pet_id, $vet_id, $formatted_date, $reason);
                if ($stmt->execute()) {
                    require_once '../includes/bitacora_function.php';
                    $action = "Cita agendada para mascota ID $pet_id con veterinario ID $vet_id el $formatted_date";
                    log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                    
                    $success = "Cita agendada correctamente.";
                    $_POST = [];
                } else {
                    $error = "Error al agendar: " . $stmt->error;
                }
                $stmt->close();
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
    <title>Agendar Cita - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 600px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; box-sizing: border-box; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { display: block; width: 100%; padding: 12px; background: #40916c; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-align: center; text-decoration: none; margin-top: 10px; }
        .btn-secondary:hover { background: #5a6268; }
        .no-pets { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Agendar Cita</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-calendar-plus"></i> Agendar Nueva Cita</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (empty($pets)): ?>
            <div class="no-pets">
                <p>No hay mascotas disponibles para agendar citas.</p>
                <?php if ($role_name === 'Propietario'): ?>
                    <a href="pet_register.php" class="btn btn-secondary">Registrar Mascota</a>
                <?php endif; ?>
            </div>
        <?php elseif (empty($vets)): ?>
            <div class="no-pets">
                <p>No hay veterinarios disponibles. Contacte al administrador.</p>
            </div>
        <?php else: ?>
            <form method="post">
                <label for="pet_id">Mascota:</label>
                <select name="pet_id" id="pet_id" required>
                    <option value="">Seleccione una mascota</option>
                    <?php foreach ($pets as $pet): ?>
                        <option value="<?php echo $pet['id']; ?>" <?php echo (isset($_POST['pet_id']) && $_POST['pet_id'] == $pet['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pet['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="vet_id">Veterinario:</label>
                <select name="vet_id" id="vet_id" required>
                    <option value="">Seleccione un veterinario</option>
                    <?php foreach ($vets as $vet): ?>
                        <option value="<?php echo $vet['id']; ?>" <?php echo (isset($_POST['vet_id']) && $_POST['vet_id'] == $vet['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vet['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="appointment_date">Fecha y hora:</label>
                <input type="datetime-local" name="appointment_date" id="appointment_date" required min="<?php echo date('Y-m-d\TH:i'); ?>" value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">

                <label for="reason">Motivo:</label>
                <textarea name="reason" id="reason" rows="4" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>

                <button type="submit" class="btn"><i class="fas fa-save"></i> Agendar Cita</button>
            </form>
            <a href="appointment_list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Ver citas</a>
        <?php endif; ?>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>