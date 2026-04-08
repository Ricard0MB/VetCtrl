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

// Solo veterinario y admin pueden acceder
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$error = '';
$success = '';
$type_name = '';

try {
    $stmtTypes = $conn->query("SELECT name, created_at FROM pet_types ORDER BY name ASC");
    $types = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar especies: " . $e->getMessage();
    $types = [];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $type_name = trim($_POST['type_name'] ?? '');
    if (empty($type_name)) {
        $error = "Ingrese el nombre de la especie.";
    } else {
        try {
            $sql = "INSERT INTO pet_types (name, attendant_id) VALUES (:name, :attendant_id)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':name', $type_name);
            $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            require_once '../includes/bitacora_function.php';
            $action = "Nueva especie registrada: $type_name";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

            $success = "Especie '$type_name' registrada correctamente.";
            $type_name = '';

            $stmtTypes = $conn->query("SELECT name, created_at FROM pet_types ORDER BY name ASC");
            $types = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                $error = "La especie '$type_name' ya existe.";
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
    <title>Registro de Especies - VetCtrl</title>
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
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
        }
        h1, h2 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        h2 {
            font-size: 1.3rem;
            margin-top: 30px;
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
        .alert-success { background: #e0f2e9; color: #1e7b4a; border-left-color: #1e7b4a; }
        .alert-danger { background: #fee7e7; color: #b91c1c; border-left-color: #b91c1c; }
        label {
            display: block;
            margin: 18px 0 6px;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
        }
        input:focus {
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
            margin-top: 15px;
            transition: 0.2s;
        }
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
            margin-top: 15px;
        }
        th {
            background: var(--primary-dark);
            color: white;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #eef2f8;
        }
        hr {
            margin: 30px 0;
            border: none;
            border-top: 2px solid #eef2f8;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Registro de Especies</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-cat"></i> Registrar Especie</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="type_name">Nombre de la especie:</label>
            <input type="text" name="type_name" id="type_name" value="<?php echo htmlspecialchars($type_name); ?>" required placeholder="Ej: Perro, Gato, Ave">
            <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar</button>
        </form>

        <hr>

        <h2><i class="fas fa-list"></i> Especies registradas (<?php echo count($types); ?>)</h2>
        <?php if (empty($types)): ?>
            <p>No hay especies registradas.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Especie</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($types as $t): ?>
                        <tr><td><?php echo htmlspecialchars($t['name']); ?></td><td><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
