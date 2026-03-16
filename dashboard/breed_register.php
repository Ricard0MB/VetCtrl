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

// Solo veterinario y admin pueden acceder (según matriz)
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$error = '';
$success = '';
$breed_name = '';
$selected_type_id = 0;
$pet_types = [];
$current_breeds = [];

try {
    // Cargar especies
    $stmtTypes = $conn->query("SELECT id, name FROM pet_types ORDER BY name ASC");
    while ($row = $stmtTypes->fetch(PDO::FETCH_ASSOC)) {
        $pet_types[] = $row;
    }

    // Cargar razas existentes
    $sql_breeds = "SELECT b.name AS breed_name, pt.name AS type_name, b.created_at 
                   FROM breeds b 
                   JOIN pet_types pt ON b.type_id = pt.id
                   ORDER BY pt.name, b.name ASC";
    $stmtBreeds = $conn->query($sql_breeds);
    while ($row = $stmtBreeds->fetch(PDO::FETCH_ASSOC)) {
        $current_breeds[] = $row;
    }

    // Procesar formulario
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $breed_name = trim($_POST['breed_name'] ?? '');
        $selected_type_id = intval($_POST['type_id'] ?? 0);

        if (empty($breed_name) || $selected_type_id == 0) {
            $error = "Debe ingresar el nombre de la raza y seleccionar una especie.";
        } else {
            try {
                $sql_insert = "INSERT INTO breeds (type_id, name, attendant_id) VALUES (:type_id, :name, :attendant_id)";
                $stmtInsert = $conn->prepare($sql_insert);
                $stmtInsert->bindValue(':type_id', $selected_type_id, PDO::PARAM_INT);
                $stmtInsert->bindValue(':name', $breed_name, PDO::PARAM_STR);
                $stmtInsert->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
                $stmtInsert->execute();

                require_once '../includes/bitacora_function.php';
                $action = "Nueva raza registrada: $breed_name para especie ID $selected_type_id";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

                $success = "Raza '$breed_name' registrada correctamente.";
                $breed_name = '';
                $selected_type_id = 0;

                // Recargar lista de razas
                $current_breeds = [];
                $stmtBreeds = $conn->query($sql_breeds);
                while ($row = $stmtBreeds->fetch(PDO::FETCH_ASSOC)) {
                    $current_breeds[] = $row;
                }

            } catch (PDOException $e) {
                // Código 1062: Duplicate entry
                if ($e->errorInfo[1] == 1062) {
                    $error = "La raza '$breed_name' ya existe para esta especie.";
                } else {
                    $error = "Error al registrar: " . $e->getMessage();
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
}

// No es necesario cerrar la conexión explícitamente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Razas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 900px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 900px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #1b4332; }
        select, input[type="text"] { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        select:focus, input:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; }
        .btn:hover { background: #2d6a4f; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #40916c; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Registro de Razas</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-dna"></i> Registro de Razas</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (empty($pet_types)): ?>
            <div class="alert alert-danger">No hay especies registradas. <a href="pet_type_register.php">Registre una especie primero</a>.</div>
        <?php else: ?>
            <form method="post">
                <div class="form-group">
                    <label for="type_id">Especie:</label>
                    <select name="type_id" id="type_id" required>
                        <option value="">Seleccione una especie</option>
                        <?php foreach ($pet_types as $type): ?>
                            <option value="<?php echo $type['id']; ?>" <?php echo ($selected_type_id == $type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="breed_name">Nombre de la raza:</label>
                    <input type="text" name="breed_name" id="breed_name" value="<?php echo htmlspecialchars($breed_name); ?>" required placeholder="Ej: Labrador, Siames">
                </div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Raza</button>
            </form>
        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <h2>Razas registradas (<?php echo count($current_breeds); ?>)</h2>
        <?php if (empty($current_breeds)): ?>
            <p>No hay razas registradas.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Especie</th><th>Raza</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($current_breeds as $b): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($b['breed_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($b['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
