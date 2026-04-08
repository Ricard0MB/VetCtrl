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

// Solo veterinario y admin pueden registrar tipos de vacuna
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$error = '';
$success = '';
$species_options = ['Canino', 'Felino', 'Ave', 'Roedor', 'Reptil', 'Otros'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $species_target = $_POST['species_target'] ?? '';
    $description = !empty($_POST['description']) ? trim($_POST['description']) : null;

    if (empty($name) || empty($species_target)) {
        $error = "El nombre y la especie objetivo son obligatorios.";
    } else {
        try {
            $sql = "INSERT INTO vaccine_types (name, description, species_target, attendant_id) 
                    VALUES (:name, :description, :species_target, :attendant_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':description', $description);
            $stmt->bindValue(':species_target', $species_target);
            $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            require_once '../includes/bitacora_function.php';
            $action = "Nuevo tipo de vacuna registrado: $name ($species_target)";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

            $success = "Tipo de vacuna registrado correctamente.";
            $_POST = [];
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "Ya existe un tipo de vacuna con ese nombre.";
            } else {
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Tipo de Vacuna - VetCtrl</title>
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
        .alert-success {
            background: #e0f2e9;
            color: #1e7b4a;
            border-left-color: #1e7b4a;
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
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
            font-size: 0.9rem;
        }
        input:focus, select:focus, textarea:focus {
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
        <span>Registrar Tipo de Vacuna</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-vial"></i> Registrar Tipo de Vacuna</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="name">Nombre de la vacuna:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>

            <label for="species_target">Especie objetivo:</label>
            <select name="species_target" id="species_target" required>
                <option value="">Seleccione...</option>
                <?php foreach ($species_options as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo (isset($_POST['species_target']) && $_POST['species_target'] == $opt) ? 'selected' : ''; ?>>
                        <?php echo $opt; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="description">Descripción / Componentes (opcional):</label>
            <textarea name="description" id="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar</button>
        </form>

        <a href="vaccine_types_list.php" class="btn-secondary"><i class="fas fa-list"></i> Ver tipos registrados</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
