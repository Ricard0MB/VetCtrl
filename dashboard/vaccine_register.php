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

// Solo veterinario y admin pueden registrar vacunas
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pet_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($pet_id <= 0) {
    header("Location: search_pet_owner.php?error=invalid");
    exit;
}

// Obtener nombre de la mascota
$sql = "SELECT name FROM pets WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$result = $stmt->get_result();
$pet = $result->fetch_assoc();
$stmt->close();

if (!$pet) {
    header("Location: search_pet_owner.php?error=notfound");
    exit;
}

// Obtener tipos de vacunas
$vaccine_types = $conn->query("SELECT id, name FROM vaccine_types ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $vaccine_type_id = intval($_POST['vaccine_type_id']);
    $application_date = $_POST['application_date'];
    $lote_number = trim($_POST['lote_number']) ?: null;
    $next_due_date = $_POST['next_due_date'] ?: null;
    $notes = trim($_POST['notes']) ?: null;

    if ($vaccine_type_id == 0 || empty($application_date)) {
        $error = "Seleccione la vacuna y la fecha de aplicación.";
    } else {
        $sql = "INSERT INTO vaccines (pet_id, attendant_id, vaccine_type_id, application_date, next_due_date, lote_number, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiissss", $pet_id, $user_id, $vaccine_type_id, $application_date, $next_due_date, $lote_number, $notes);
        if ($stmt->execute()) {
            require_once '../includes/bitacora_function.php';
            $action = "Vacuna aplicada a mascota $pet_id";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
            header("Location: pet_profile.php?id=$pet_id&success=vaccine_added");
            exit;
        } else {
            $error = "Error al registrar: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Aplicar Vacuna - VetCtrl</title>
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
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        select, input, textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; box-sizing: border-box; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; width: 100%; display: inline-block; text-align: center; text-decoration: none; box-sizing: border-box; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        /* Aseguramos que el enlace también ocupe todo el ancho */
        .btn-block { width: 100%; display: block; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="pet_profile.php?id=<?php echo $pet_id; ?>"><?php echo htmlspecialchars($pet['name']); ?></a> <span>›</span>
        <span>Aplicar Vacuna</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-syringe"></i> Aplicar Vacuna a <?php echo htmlspecialchars($pet['name']); ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="vaccine_type_id">Tipo de vacuna:</label>
            <select name="vaccine_type_id" id="vaccine_type_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($vaccine_types as $vt): ?>
                    <option value="<?php echo $vt['id']; ?>" <?php echo (isset($_POST['vaccine_type_id']) && $_POST['vaccine_type_id'] == $vt['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($vt['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="application_date">Fecha de aplicación:</label>
            <input type="date" name="application_date" id="application_date" value="<?php echo htmlspecialchars($_POST['application_date'] ?? date('Y-m-d')); ?>" required>

            <label for="lote_number">Número de lote (opcional):</label>
            <input type="text" name="lote_number" id="lote_number" value="<?php echo htmlspecialchars($_POST['lote_number'] ?? ''); ?>">

            <label for="next_due_date">Próxima dosis (opcional):</label>
            <input type="date" name="next_due_date" id="next_due_date" value="<?php echo htmlspecialchars($_POST['next_due_date'] ?? ''); ?>" min="<?php echo date('Y-m-d'); ?>">

            <label for="notes">Notas (opcional):</label>
            <textarea name="notes" id="notes" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Registrar Vacuna</button>
        </form>

        <!-- Enlace modificado para que tenga el mismo ancho que el botón -->
        <a href="pet_profile.php?id=<?php echo $pet_id; ?>" class="btn btn-secondary" style="width: 100%; display: block; box-sizing: border-box; text-align: center; margin-top: 20px;">
            <i class="fas fa-arrow-left"></i> Volver al Perfil
        </a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>