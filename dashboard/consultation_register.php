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

// Solo veterinario y admin pueden registrar consultas
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pets = [];
$error = '';
$success = '';
$preselected_pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;

// Cargar todas las mascotas (con dueño para referencia)
$sql_pets = "SELECT p.id, p.name, u.username as owner_name 
             FROM pets p 
             LEFT JOIN users u ON p.owner_id = u.id 
             ORDER BY p.name ASC";
$result = $conn->query($sql_pets);
while ($row = $result->fetch_assoc()) {
    $pets[] = $row;
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $consultation_date = $_POST['consultation_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
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
            $sql_insert = "INSERT INTO consultations (pet_id, attendant_id, consultation_date, reason, diagnosis, treatment, notes) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_insert);
            $stmt->bind_param("iisssss", $pet_id, $user_id, $formatted_date, $reason, $diagnosis, $treatment, $notes);
            if ($stmt->execute()) {
                $new_id = $stmt->insert_id;
                require_once '../includes/bitacora_function.php';
                $action = "Nueva consulta #$new_id registrada para mascota ID $pet_id";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                
                $success = "Consulta registrada correctamente.";
                // Limpiar POST
                $_POST = [];
            } else {
                $error = "Error al registrar: " . $stmt->error;
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
    <title>Registrar Consulta - VetCtrl</title>
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
        /* Unificamos todos los campos del formulario */
        select,
        input[type="text"],
        input[type="datetime-local"],
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: inherit;
        }
        select:focus,
        input:focus,
        textarea:focus {
            border-color: #40916c;
            outline: none;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            background: #40916c;
            color: white;
            width: 100%;
            margin-top: 10px;
        }
        .btn:hover {
            background: #2d6a4f;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Registrar Consulta</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-stethoscope"></i> Registrar Nueva Consulta</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="pet_id">Mascota:</label>
            <select name="pet_id" id="pet_id" required>
                <option value="">Seleccione una mascota</option>
                <?php foreach ($pets as $pet): ?>
                    <option value="<?php echo $pet['id']; ?>" <?php echo ($preselected_pet_id == $pet['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pet['name']); ?>
                        <?php if ($pet['owner_name']) echo ' (Dueño: ' . htmlspecialchars($pet['owner_name']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="consultation_date">Fecha y hora:</label>
            <input type="datetime-local" name="consultation_date" id="consultation_date" required value="<?php echo htmlspecialchars($_POST['consultation_date'] ?? date('Y-m-d\TH:i')); ?>">

            <label for="reason">Motivo (opcional):</label>
            <input type="text" name="reason" id="reason" value="<?php echo htmlspecialchars($_POST['reason'] ?? ''); ?>">

            <label for="diagnosis">Diagnóstico:</label>
            <textarea name="diagnosis" id="diagnosis" rows="4" required><?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?></textarea>

            <label for="treatment">Tratamiento:</label>
            <textarea name="treatment" id="treatment" rows="4"><?php echo htmlspecialchars($_POST['treatment'] ?? ''); ?></textarea>

            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="4"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Registrar Consulta</button>
        </form>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>