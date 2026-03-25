<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo veterinario y admin pueden registrar tratamientos
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
$pet = null;
try {
    $sql = "SELECT name FROM pets WHERE id = :pet_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar mascota: " . $e->getMessage());
}

if (!$pet) {
    header("Location: search_pet_owner.php?error=notfound");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title'] ?? '');
    $start_date = $_POST['start_date'] ?? '';
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $medication_details = trim($_POST['medication_details'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($title) || empty($start_date) || empty($medication_details)) {
        $error = "Título, fecha de inicio y detalles de medicación son obligatorios.";
    } else {
        // Validar fechas
        $today = date('Y-m-d');
        $start_timestamp = strtotime($start_date);
        if ($start_timestamp === false) {
            $error = "La fecha de inicio no es válida.";
        } elseif ($start_date < $today) {
            $error = "La fecha de inicio no puede ser anterior a la fecha actual.";
        } elseif ($end_date !== null) {
            $end_timestamp = strtotime($end_date);
            if ($end_timestamp === false) {
                $error = "La fecha de fin no es válida.";
            } elseif ($end_timestamp < $start_timestamp) {
                $error = "La fecha de fin no puede ser anterior a la fecha de inicio.";
            }
        }
        
        if (empty($error)) {
            try {
                // Incluimos también el campo treatment_name con el mismo valor que title
                $sql = "INSERT INTO treatments (pet_id, attendant_id, title, treatment_name, start_date, end_date, diagnosis, medication_details, notes) 
                        VALUES (:pet_id, :attendant_id, :title, :treatment_name, :start_date, :end_date, :diagnosis, :medication_details, :notes)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
                $stmt->bindValue(':title', $title);
                $stmt->bindValue(':treatment_name', $title); // Usamos el título como treatment_name
                $stmt->bindValue(':start_date', $start_date);
                $stmt->bindValue(':end_date', $end_date);
                $stmt->bindValue(':diagnosis', $diagnosis);
                $stmt->bindValue(':medication_details', $medication_details);
                $stmt->bindValue(':notes', $notes);
                $stmt->execute();

                require_once '../includes/bitacora_function.php';
                $action = "Tratamiento registrado para mascota $pet_id: $title";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

                header("Location: pet_profile.php?id=$pet_id&success=treatment_added");
                exit;
            } catch (PDOException $e) {
                $error = "Error al registrar: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Tratamiento - VetCtrl</title>
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
        input, textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; width: 100%; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-secondary:hover { background: #5a6268; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="pet_profile.php?id=<?php echo $pet_id; ?>"><?php echo htmlspecialchars($pet['name']); ?></a> <span>›</span>
        <span>Registrar Tratamiento</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-prescription-bottle-alt"></i> Registrar Tratamiento para <?php echo htmlspecialchars($pet['name']); ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="title">Título del tratamiento:</label>
            <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>

            <label for="start_date">Fecha de inicio:</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? date('Y-m-d')); ?>" required>

            <label for="end_date">Fecha estimada de fin (opcional):</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">

            <label for="diagnosis">Diagnóstico asociado:</label>
            <textarea name="diagnosis" id="diagnosis" rows="3"><?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?></textarea>

            <label for="medication_details">Detalles de medicación:</label>
            <textarea name="medication_details" id="medication_details" rows="4" required><?php echo htmlspecialchars($_POST['medication_details'] ?? ''); ?></textarea>

            <label for="notes">Notas adicionales:</label>
            <textarea name="notes" id="notes" rows="4"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Registrar Tratamiento</button>
        </form>

        <a href="pet_profile.php?id=<?php echo $pet_id; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Perfil</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
