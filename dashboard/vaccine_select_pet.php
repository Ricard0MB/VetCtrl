<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

// Variables de sesión
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
    // Construir consulta según rol
    if ($role_name === 'Propietario') {
        // Propietarios ven solo sus mascotas
        $sql = "SELECT p.id, p.name, pt.name AS species_name, b.name AS breed_name 
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                WHERE p.owner_id = :user_id 
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    } else {
        // Veterinarios y administradores ven todas las mascotas
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
        $message = "<p class='error-message'>No hay mascotas registradas en el sistema. <a href='pet_register.php'>Registrar primera mascota</a></p>";
    }
} catch (PDOException $e) {
    $message = "<p class='error-message'>Error al cargar pacientes: " . htmlspecialchars($e->getMessage()) . "</p>";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_pet_id = $_POST['pet_id'] ?? '';

    if (is_numeric($selected_pet_id) && $selected_pet_id > 0) {
        // Redirige a la página de registro de vacuna
        header("Location: vaccine_register.php?id=" . $selected_pet_id);
        exit;
    } else {
        $message = "<p class='error-message'>Por favor, seleccione un paciente válido.</p>";
    }
}

// No es necesario cerrar la conexión explícitamente con PDO
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Paciente para Vacuna</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <style>
        body {
            padding-top: 60px; 
            background-color: #f4f4f4;
        }
        .dashboard-container {
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .main-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: #1b4332;
            margin-bottom: 5px;
        }
        h2 {
            font-size: 1.2em;
            color: #2d6a4f;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        select, .btn-primary {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
            cursor: pointer;
        }
        .btn-primary {
            background-color: #40916c;
            color: white;
            border: none;
            transition: background-color 0.3s ease;
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: #2d6a4f;
        }
        .error-message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
            background-color: #f8d7da;
            color: #721c24;
            text-align: center;
        }
        .main-content a {
            color: #40916c;
            text-decoration: none;
            font-weight: 500;
            display: block;
            margin-bottom: 10px;
        }
        .main-content a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="dashboard-container">
        <div class="main-content">
            <h1>Registrar Vacuna 💉</h1>
            <h2>Paso 1: Seleccione el Paciente</h2>

            <p><a href="welcome.php">← Volver al Dashboard</a></p>

            <?php echo $message; ?>

            <?php if (empty($pets) && empty($message)): ?>
                <p class='error-message'>Aún no hay pacientes registrados. <a href="pet_register.php">Registra un paciente</a> primero.</p>
            <?php elseif (!empty($pets)): ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <label for="pet_id" style="font-weight: 600; display: block; text-align: left; margin-top: 10px;">Seleccionar Paciente:</label>
                    <select id="pet_id" name="pet_id" required>
                        <option value="">-- Elija una Mascota --</option>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?php echo $pet['id']; ?>">
                                <?php echo htmlspecialchars($pet['name']) . " (" . htmlspecialchars($pet['species_name'] ?? 'Desconocida') . " - " . htmlspecialchars($pet['breed_name'] ?? 'Sin raza') . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-primary">
                        Continuar al Registro de Vacuna
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
