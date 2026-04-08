<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$username = $_SESSION["username"] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo veterinarios y administradores pueden registrar vacunas
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pets = [];
$message = '';

try {
    if ($role_name === 'Propietario') {
        $sql = "SELECT p.id, p.name, pt.name AS species_name, b.name AS breed_name 
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                WHERE p.owner_id = :user_id 
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    } else {
        $sql = "SELECT p.id, p.name, pt.name AS species_name, b.name AS breed_name 
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
    }

    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pets)) {
        $message = "<div class='alert alert-info'><i class='fas fa-info-circle'></i> No hay mascotas registradas. <a href='pet_register.php'>Registrar primera mascota</a></div>";
    }
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar pacientes: " . htmlspecialchars($e->getMessage()) . "</div>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_pet_id = $_POST['pet_id'] ?? '';
    if (is_numeric($selected_pet_id) && $selected_pet_id > 0) {
        header("Location: vaccine_register.php?id=" . $selected_pet_id);
        exit;
    } else {
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Por favor, seleccione un paciente válido.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Paciente para Vacuna - VetCtrl</title>
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
        .dashboard-container {
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 500px;
            text-align: center;
            border: 1px solid #eef2f8;
        }
        h1 {
            color: var(--primary-dark);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h2 {
            font-size: 1.2rem;
            color: var(--primary);
            border-bottom: 2px solid var(--accent);
            padding-bottom: 15px;
            margin-bottom: 20px;
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
        .alert-info {
            background: #e0f2fe;
            color: #0369a1;
            border-left-color: #0ea5e9;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #dc3545;
        }
        select, .btn-primary {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 1rem;
            transition: 0.2s;
        }
        select:focus, .btn-primary:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .back-link {
            display: block;
            margin-bottom: 20px;
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        label {
            font-weight: 600;
            display: block;
            text-align: left;
            margin-top: 10px;
            color: var(--primary-dark);
        }
        @media (max-width: 640px) {
            .main-content { padding: 20px; margin: 15px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-syringe"></i> Registrar Vacuna</h1>
            <h2>Paso 1: Seleccione el Paciente</h2>

            <a href="welcome.php" class="back-link"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>

            <?php echo $message; ?>

            <?php if (empty($pets) && empty($message)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aún no hay pacientes registrados. <a href="pet_register.php" style="color: #0369a1; font-weight: bold;">Registra un paciente</a> primero.
                </div>
            <?php elseif (!empty($pets)): ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <label for="pet_id">Seleccionar Paciente:</label>
                    <select id="pet_id" name="pet_id" required>
                        <option value="">-- Elija una Mascota --</option>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?php echo $pet['id']; ?>">
                                <?php echo htmlspecialchars($pet['name']) . " (" . htmlspecialchars($pet['species_name'] ?? 'Desconocida') . " - " . htmlspecialchars($pet['breed_name'] ?? 'Sin raza') . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-primary">
                        <i class="fas fa-arrow-right"></i> Continuar al Registro de Vacuna
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
