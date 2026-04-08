<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$error = $success = '';
$breed_name = '';
$selected_type_id = 0;
$pet_types = $current_breeds = [];

try {
    $stmtTypes = $conn->query("SELECT id, name FROM pet_types ORDER BY name");
    while ($row = $stmtTypes->fetch(PDO::FETCH_ASSOC)) $pet_types[] = $row;

    $sql_breeds = "SELECT b.name AS breed_name, pt.name AS type_name, b.created_at FROM breeds b JOIN pet_types pt ON b.type_id = pt.id ORDER BY pt.name, b.name";
    $stmtBreeds = $conn->query($sql_breeds);
    while ($row = $stmtBreeds->fetch(PDO::FETCH_ASSOC)) $current_breeds[] = $row;

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $breed_name = trim($_POST['breed_name'] ?? '');
        $selected_type_id = intval($_POST['type_id'] ?? 0);
        if (empty($breed_name) || $selected_type_id == 0) $error = "Debe ingresar el nombre de la raza y seleccionar una especie.";
        else {
            $sql_insert = "INSERT INTO breeds (type_id, name, attendant_id) VALUES (:type_id, :name, :attendant_id)";
            $stmtInsert = $conn->prepare($sql_insert);
            $stmtInsert->bindValue(':type_id', $selected_type_id);
            $stmtInsert->bindValue(':name', $breed_name);
            $stmtInsert->bindValue(':attendant_id', $user_id);
            if ($stmtInsert->execute()) {
                require_once '../includes/bitacora_function.php';
                log_to_bitacora($conn, "Nueva raza registrada: $breed_name", $username, $_SESSION['role_id'] ?? 0);
                $success = "Raza '$breed_name' registrada correctamente.";
                $breed_name = ''; $selected_type_id = 0;
                $current_breeds = [];
                $stmtBreeds = $conn->query($sql_breeds);
                while ($row = $stmtBreeds->fetch(PDO::FETCH_ASSOC)) $current_breeds[] = $row;
            } else $error = "Error al registrar.";
        }
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Razas - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7f9; padding-top: 72px; }
        :root { --vet-dark: #1b4332; --vet-primary: #40916c; --shadow-md: 0 8px 20px rgba(0,0,0,0.05); --radius-lg: 16px; }
        .breadcrumb { max-width: 900px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .container { max-width: 900px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.4rem; font-weight: 600; color: var(--vet-dark); }
        select, input[type="text"] { width: 100%; padding: 0.7rem; border: 1px solid #d0d8d0; border-radius: 10px; font-family: inherit; }
        select:focus, input:focus { border-color: var(--vet-primary); outline: none; box-shadow: 0 0 0 2px rgba(64,145,108,0.2); }
        .btn { background: var(--vet-primary); color: white; padding: 0.7rem 1.2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: var(--vet-dark); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8faf8; padding: 0.8rem; text-align: left; color: var(--vet-dark); border-bottom: 2px solid #dee6de; }
        td { padding: 0.8rem; border-bottom: 1px solid #eef2ee; }
        tr:hover td { background: #fafdfa; }
        hr { margin: 1.5rem 0; border-color: #e2e8e2; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb"><a href="welcome.php">Inicio</a> <span>›</span> <span>Registro de Razas</span></div>
    <div class="container">
        <h1><i class="fas fa-dna"></i> Registro de Razas</h1>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if (empty($pet_types)): ?>
            <div class="alert alert-danger">No hay especies registradas. <a href="pet_type_register.php">Registre una especie primero</a>.</div>
        <?php else: ?>
            <form method="post">
                <div class="form-group"><label for="type_id">Especie:</label><select name="type_id" id="type_id" required><option value="">Seleccione...</option><?php foreach ($pet_types as $type): ?><option value="<?php echo $type['id']; ?>" <?php echo ($selected_type_id==$type['id'])?'selected':''; ?>><?php echo htmlspecialchars($type['name']); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="breed_name">Nombre de la raza:</label><input type="text" name="breed_name" id="breed_name" value="<?php echo htmlspecialchars($breed_name); ?>" required placeholder="Ej: Labrador, Siames"></div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Raza</button>
            </form>
        <?php endif; ?>
        <hr>
        <h2>Razas registradas (<?php echo count($current_breeds); ?>)</h2>
        <?php if (empty($current_breeds)): ?><p>No hay razas registradas.</p><?php else: ?>
            <div style="overflow-x: auto;"><table><thead><tr><th>Especie</th><th>Raza</th><th>Fecha</th></tr></thead><tbody><?php foreach ($current_breeds as $b): ?><tr><td><?php echo htmlspecialchars($b['type_name']); ?></td><td><?php echo htmlspecialchars($b['breed_name']); ?></td><td><?php echo date('d/m/Y', strtotime($b['created_at'])); ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
