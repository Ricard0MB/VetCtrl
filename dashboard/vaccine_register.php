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

$pet = null;
$vaccine_types = [];
$error = '';

try {
    $sql = "SELECT name FROM pets WHERE id = :pet_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        header("Location: search_pet_owner.php?error=notfound");
        exit;
    }

    $stmtTypes = $conn->query("SELECT id, name FROM vaccine_types ORDER BY name");
    $vaccine_types = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $vaccine_type_id = intval($_POST['vaccine_type_id'] ?? 0);
    $application_date = $_POST['application_date'] ?? '';
    $lote_number = !empty($_POST['lote_number']) ? trim($_POST['lote_number']) : null;
    $next_due_date = !empty($_POST['next_due_date']) ? $_POST['next_due_date'] : null;
    $notes = !empty($_POST['notes']) ? trim($_POST['notes']) : null;

    if ($vaccine_type_id == 0 || empty($application_date)) {
        $error = "Seleccione la vacuna y la fecha de aplicación.";
    } else {
        try {
            $sql = "INSERT INTO vaccines (pet_id, attendant_id, vaccine_type_id, application_date, next_due_date, lote_number, notes) 
                    VALUES (:pet_id, :attendant_id, :vaccine_type_id, :application_date, :next_due_date, :lote_number, :notes)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
            $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':vaccine_type_id', $vaccine_type_id, PDO::PARAM_INT);
            $stmt->bindValue(':application_date', $application_date);
            $stmt->bindValue(':next_due_date', $next_due_date);
            $stmt->bindValue(':lote_number', $lote_number);
            $stmt->bindValue(':notes', $notes);
            $stmt->execute();

            require_once '../includes/bitacora_function.php';
            $action = "Vacuna aplicada a mascota $pet_id";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

            header("Location: pet_profile.php?id=$pet_id&success=vaccine_added");
            exit;
        } catch (PDOException $e) {
            $error = "Error al registrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplicar Vacuna - VetCtrl</title>
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
            max-width: 600px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .container {
            max-width: 600px;
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
        select, input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        select:focus, input:focus, textarea:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: white;
            width: 100%;
            margin-top: 25px;
            transition: 0.2s;
        }
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #eef2f8;
            color: var(--primary-dark);
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            border-radius: 40px;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        @media (max-width: 640px) {
            .container { padding: 20px; margin: 15px; }
        }
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

        <a href="pet_profile.php?id=<?php echo $pet_id; ?>" class="btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Perfil</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
