<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

// Variables necesarias para la navbar
$username = $_SESSION["username"] ?? 'Veterinario'; 
$user_id = $_SESSION['user_id'] ?? 0;
$owner_id = $user_id; // ID del veterinario logueado

require_once '../includes/config.php'; // $conn es un objeto PDO

$pets = [];
$message = '';

// Procesar POST antes de cualquier salida (redirección)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_pet_id = $_POST['pet_id'] ?? '';

    if (is_numeric($selected_pet_id) && $selected_pet_id > 0) {
        // Redirigir a la página de registro de tratamiento usando el ID de la mascota
        header("Location: treatment_register.php?id=" . $selected_pet_id);
        exit;
    } else {
        $message = "<p class='error-message'>Por favor, seleccione un paciente válido.</p>";
        // Continuar para mostrar el formulario nuevamente
    }
}

// Consulta para obtener las mascotas registradas por este veterinario (aunque owner_id debería ser el dueño real, se mantiene la lógica original)
try {
    $sql = "SELECT 
                p.id, 
                p.name, 
                pt.name AS species_name, 
                b.name AS breed_name    
            FROM pets p
            LEFT JOIN pet_types pt ON p.type_id = pt.id 
            LEFT JOIN breeds b ON p.breed_id = b.id      
            WHERE p.owner_id = :owner_id 
            ORDER BY p.name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<p class='error-message'>Error al cargar pacientes: " . htmlspecialchars($e->getMessage()) . "</p>";
}
// No es necesario cerrar la conexión explícitamente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seleccionar Paciente para Tratamiento</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 60px; /* Espacio para la navbar fija */
        }
        .dashboard-container {
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .main-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        h1 {
            color: #1b4332;
            margin-bottom: 5px;
            text-align: center;
        }
        h2 {
            color: #2d6a4f;
            text-align: center;
            margin-top: 5px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            font-size: 1.2em;
        }
        select, button {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 1em;
        }
        select {
            height: 45px;
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
        .btn-primary {
            background-color: #40916c;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .btn-primary:hover {
            background-color: #2d6a4f;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-bottom: 20px;
            color: #2d6a4f;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    <div class="dashboard-container">
        <div class="main-content">
            <h1>Agregar Tratamiento 💊</h1>
            <h2>Paso 1: Seleccione el Paciente</h2>

            <p><a href="welcome.php" class="back-link">← Volver al Dashboard</a></p>

            <?php echo $message; ?>

            <?php if (empty($pets)): ?>
                <p class='error-message'>Aún no tienes pacientes registrados. Por favor, <a href="pet_register.php" style="color: #721c24; font-weight: bold;">registra un paciente</a> primero.</p>
            <?php else: ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <label for="pet_id" style="font-weight: 600; display: block; margin-bottom: 5px; color: #1b4332;">Seleccionar Paciente:</label>
                    <select id="pet_id" name="pet_id" required>
                        <option value="">-- Elija una Mascota --</option>
                        <?php foreach ($pets as $pet): ?>
                            <option value="<?php echo $pet['id']; ?>">
                                <?php 
                                    $species = htmlspecialchars($pet['species_name'] ?? 'Desconocida');
                                    $breed = htmlspecialchars($pet['breed_name'] ?? 'Sin Raza');
                                    echo htmlspecialchars($pet['name']) . " (" . $species . " - " . $breed . ")"; 
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn-primary" style="margin-top: 20px;">
                        Continuar al Registro de Tratamiento
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
