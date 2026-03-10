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

// Solo veterinario y admin pueden editar consultas (según matriz)
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$consultation = null;
$pets = [];
$error = '';
$consultation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($consultation_id <= 0) {
    header("Location: consultation_history.php?error=invalid");
    exit;
}

// Obtener lista de mascotas (todas, para selección)
$sql_pets = "SELECT id, name FROM pets ORDER BY name ASC";
$result_pets = $conn->query($sql_pets);
while ($row = $result_pets->fetch_assoc()) {
    $pets[] = $row;
}

// Obtener datos de la consulta
$sql = "SELECT * FROM consultations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $consultation_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 1) {
    $consultation = $result->fetch_assoc();
    // Verificar que el usuario pueda editar (attendant_id debe coincidir o ser admin)
    if ($role_name !== 'admin' && $consultation['attendant_id'] != $user_id) {
        header("Location: consultation_history.php?error=unauthorized");
        exit;
    }
} else {
    header("Location: consultation_history.php?error=notfound");
    exit;
}
$stmt->close();

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $consultation_date = $_POST['consultation_date'] ?? '';
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($pet_id <= 0 || empty($consultation_date) || empty($diagnosis)) {
        $error = "Los campos mascota, fecha y diagnóstico son obligatorios.";
    } else {
        $timestamp = strtotime($consultation_date);
        if (!$timestamp) {
            $error = "Fecha inválida.";
        } else {
            $formatted_date = date('Y-m-d H:i:s', $timestamp);
            $sql_update = "UPDATE consultations SET pet_id = ?, consultation_date = ?, diagnosis = ?, treatment = ?, notes = ? WHERE id = ?";
            $stmt = $conn->prepare($sql_update);
            $stmt->bind_param("issssi", $pet_id, $formatted_date, $diagnosis, $treatment, $notes, $consultation_id);
            if ($stmt->execute()) {
                require_once '../includes/bitacora_function.php';
                $action = "Consulta #$consultation_id actualizada";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                
                header("Location: consultation_view.php?id=$consultation_id&success=updated");
                exit;
            } else {
                $error = "Error al actualizar: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Consulta - VetCtrl</title>
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
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-secondary:hover { background: #5a6268; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="consultation_history.php">Consultas</a> <span>›</span>
        <span>Editar Consulta #<?php echo $consultation_id; ?></span>
    </div>

    <div class="container">
        <h1><i class="fas fa-edit"></i> Editar Consulta #<?php echo $consultation_id; ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="pet_id">Mascota:</label>
            <select name="pet_id" id="pet_id" required>
                <option value="">Seleccione una mascota</option>
                <?php foreach ($pets as $pet): ?>
                    <option value="<?php echo $pet['id']; ?>" <?php echo ($pet['id'] == $consultation['pet_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pet['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="consultation_date">Fecha y hora:</label>
            <input type="datetime-local" name="consultation_date" id="consultation_date" value="<?php echo date('Y-m-d\TH:i', strtotime($consultation['consultation_date'])); ?>" required>

            <label for="diagnosis">Diagnóstico:</label>
            <textarea name="diagnosis" id="diagnosis" rows="4" required><?php echo htmlspecialchars($consultation['diagnosis']); ?></textarea>

            <label for="treatment">Tratamiento:</label>
            <textarea name="treatment" id="treatment" rows="4"><?php echo htmlspecialchars($consultation['treatment'] ?? ''); ?></textarea>

            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="4"><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios</button>
        </form>

        <a href="consultation_history.php" class="btn btn-secondary" style="margin-top:20px;"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>