<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

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

// Obtener especies existentes
$types = $conn->query("SELECT name, created_at FROM pet_types ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $type_name = trim($_POST['type_name'] ?? '');
    if (empty($type_name)) {
        $error = "Ingrese el nombre de la especie.";
    } else {
        $sql = "INSERT INTO pet_types (name, attendant_id) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $type_name, $user_id);
        if ($stmt->execute()) {
            require_once '../includes/bitacora_function.php';
            $action = "Nueva especie registrada: $type_name";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
            $success = "Especie '$type_name' registrada correctamente.";
            $type_name = '';
            // Recargar lista
            $types = $conn->query("SELECT name, created_at FROM pet_types ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
        } else {
            if ($conn->errno == 1062) {
                $error = "La especie '$type_name' ya existe.";
            } else {
                $error = "Error al registrar: " . $stmt->error;
            }
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
    <title>Registro de Especies - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 600px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        input { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        input:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; }
        .btn:hover { background: #2d6a4f; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #40916c; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
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
            <button type="submit" class="btn" style="margin-top:15px;"><i class="fas fa-save"></i> Guardar</button>
        </form>

        <hr style="margin:30px 0;">

        <h2>Especies registradas (<?php echo count($types); ?>)</h2>
        <?php if (empty($types)): ?>
            <p>No hay especies registradas.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Especie</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php foreach ($types as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>