<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';
require_once '../includes/bitacora_function.php';

$username = $_SESSION["username"] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

$pet_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($pet_id <= 0) {
    header("Location: search_pet_owner.php?error=invalid");
    exit;
}

// Obtener datos de la mascota
$sql = "SELECT p.*, pt.name AS species_name, b.name AS breed_name, u.username AS owner_name 
        FROM pets p
        LEFT JOIN pet_types pt ON p.type_id = pt.id
        LEFT JOIN breeds b ON p.breed_id = b.id
        LEFT JOIN users u ON p.owner_id = u.id
        WHERE p.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $pet_id);
$stmt->execute();
$result = $stmt->get_result();
$pet = $result->fetch_assoc();
$stmt->close();

if (!$pet) {
    header("Location: search_pet_owner.php?error=notfound");
    exit;
}

// Verificar permisos: propietario solo puede editar sus mascotas, admin/vet todas
if ($role_name === 'Propietario' && $pet['owner_id'] != $user_id) {
    header("Location: search_pet_owner.php?error=unauthorized");
    exit;
}

// Obtener especies y razas para selects
$pet_types = $conn->query("SELECT id, name FROM pet_types ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$breeds = $conn->query("SELECT id, name, type_id FROM breeds ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $type_id = intval($_POST['type_id']);
    $breed_id = intval($_POST['breed_id']) ?: null;
    $dob = $_POST['dob'] ?: null;
    $gender = $_POST['gender'] ?: null;
    $medical_history = trim($_POST['medical_history']) ?: null;

    if (empty($name) || $type_id == 0) {
        $error = "El nombre y la especie son obligatorios.";
    } else {
        $sql_update = "UPDATE pets SET name=?, type_id=?, breed_id=?, date_of_birth=?, gender=?, medical_history=? WHERE id=?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("siisssi", $name, $type_id, $breed_id, $dob, $gender, $medical_history, $pet_id);
        if ($stmt->execute()) {
            // Registrar cambios si los hubo
            $changes = [];
            if ($name != $pet['name']) $changes[] = "nombre: '{$pet['name']}' → '$name'";
            if ($type_id != $pet['type_id']) $changes[] = "especie ID cambió";
            if ($breed_id != $pet['breed_id']) $changes[] = "raza cambió";
            if ($dob != $pet['date_of_birth']) $changes[] = "fecha nacimiento";
            if ($gender != $pet['gender']) $changes[] = "género";
            if ($medical_history != $pet['medical_history']) $changes[] = "historial médico";
            if (!empty($changes)) {
                $action = "Mascota ID $pet_id actualizada: " . implode(', ', $changes);
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
            }
            header("Location: pet_profile.php?id=$pet_id&success=updated");
            exit;
        } else {
            $error = "Error al actualizar: " . $stmt->error;
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
    <title>Editar Mascota - VetCtrl</title>
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
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        input, select, textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        input:focus, select:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-secondary:hover { background: #5a6268; }
    </style>
    <script>
        const allBreeds = <?php echo json_encode($breeds); ?>;
        function filterBreeds() {
            const typeSelect = document.getElementById('type_id');
            const breedSelect = document.getElementById('breed_id');
            const selectedType = typeSelect.value;
            breedSelect.innerHTML = '<option value="">-- Sin raza --</option>';
            if (selectedType) {
                const filtered = allBreeds.filter(b => b.type_id == selectedType);
                filtered.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.name;
                    if (b.id == <?php echo json_encode($pet['breed_id']); ?>) opt.selected = true;
                    breedSelect.appendChild(opt);
                });
            }
        }
        document.addEventListener('DOMContentLoaded', filterBreeds);
    </script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="pet_profile.php?id=<?php echo $pet_id; ?>"><?php echo htmlspecialchars($pet['name']); ?></a> <span>›</span>
        <span>Editar</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-edit"></i> Editar <?php echo htmlspecialchars($pet['name']); ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="name">Nombre:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($pet['name']); ?>" required>

            <label for="type_id">Especie:</label>
            <select name="type_id" id="type_id" onchange="filterBreeds()" required>
                <option value="">Seleccione...</option>
                <?php foreach ($pet_types as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo ($type['id'] == $pet['type_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="breed_id">Raza:</label>
            <select name="breed_id" id="breed_id">
                <option value="">-- Sin raza --</option>
            </select>

            <label for="dob">Fecha de nacimiento:</label>
            <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($pet['date_of_birth']); ?>">

            <label for="gender">Género:</label>
            <select name="gender" id="gender">
                <option value="">-- Seleccione --</option>
                <option value="Macho" <?php echo ($pet['gender'] == 'Macho') ? 'selected' : ''; ?>>Macho</option>
                <option value="Hembra" <?php echo ($pet['gender'] == 'Hembra') ? 'selected' : ''; ?>>Hembra</option>
                <option value="N/D" <?php echo ($pet['gender'] == 'N/D') ? 'selected' : ''; ?>>N/D</option>
            </select>

            <label for="medical_history">Historial médico:</label>
            <textarea name="medical_history" id="medical_history" rows="4"><?php echo htmlspecialchars($pet['medical_history'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios</button>
        </form>

        <a href="pet_profile.php?id=<?php echo $pet_id; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Perfil</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>