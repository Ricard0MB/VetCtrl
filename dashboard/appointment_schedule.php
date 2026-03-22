<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn debe ser un objeto PDO

$username = $_SESSION["username"] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Admin y veterinario también pueden agendar citas (para cualquier mascota)
if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pets = [];
$vets = [];
$error = '';
$success = '';

try {
    // Obtener el ID del rol "Veterinario" de la tabla roles
    $stmt_role = $conn->prepare("SELECT id FROM roles WHERE name = :role_name");
    $stmt_role->bindValue(':role_name', 'Veterinario');
    $stmt_role->execute();
    $vet_role = $stmt_role->fetch(PDO::FETCH_ASSOC);
    
    if (!$vet_role) {
        $error = "El rol 'Veterinario' no está configurado en el sistema.";
    } else {
        $vet_role_id = $vet_role['id'];
        
        // Obtener lista de veterinarios (usuarios con el rol "Veterinario")
        // También puedes incluir a los administradores si lo deseas, pero aquí solo veterinarios
        $sql_vets = "SELECT id, username, CONCAT('Dr(a). ', username) AS display_name 
                     FROM users 
                     WHERE role_id = :role_id 
                     ORDER BY username ASC";
        $stmt_vets = $conn->prepare($sql_vets);
        $stmt_vets->bindValue(':role_id', $vet_role_id, PDO::PARAM_INT);
        $stmt_vets->execute();
        $vets = $stmt_vets->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($vets)) {
            $error = "No hay veterinarios registrados en el sistema. Por favor, contacte al administrador.";
        }
    }

    // Cargar mascotas según el rol
    if ($role_name === 'Propietario') {
        // Propietario: solo sus mascotas
        $sql_pets = "SELECT id, name FROM pets WHERE owner_id = :owner_id ORDER BY name ASC";
        $stmt_pets = $conn->prepare($sql_pets);
        $stmt_pets->bindValue(':owner_id', $user_id, PDO::PARAM_INT);
    } else {
        // Veterinario/admin: todas las mascotas
        $sql_pets = "SELECT id, name FROM pets ORDER BY name ASC";
        $stmt_pets = $conn->prepare($sql_pets);
    }
    $stmt_pets->execute();
    $pets = $stmt_pets->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $vet_id = intval($_POST['vet_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($pet_id <= 0 || $vet_id <= 0 || empty($appointment_date) || empty($reason)) {
        $error = "Todos los campos son obligatorios.";
    } else {
        $timestamp = strtotime($appointment_date);
        if (!$timestamp || $timestamp <= time()) {
            $error = "La fecha y hora deben ser futuras.";
        } else {
            $formatted_date = date('Y-m-d H:i:s', $timestamp);
            
            try {
                // Si es propietario, verificar que la mascota le pertenezca
                if ($role_name === 'Propietario') {
                    $check_sql = "SELECT id FROM pets WHERE id = :pet_id AND owner_id = :owner_id";
                    $check_stmt = $conn->prepare($check_sql);
                    $check_stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                    $check_stmt->bindValue(':owner_id', $user_id, PDO::PARAM_INT);
                    $check_stmt->execute();
                    if ($check_stmt->rowCount() == 0) {
                        $error = "La mascota seleccionada no le pertenece.";
                    }
                }
                
                // Verificar que el veterinario seleccionado exista y tenga rol Veterinario
                if (empty($error)) {
                    $check_vet_sql = "SELECT id FROM users WHERE id = :vet_id AND role_id = :role_id";
                    $check_vet_stmt = $conn->prepare($check_vet_sql);
                    $check_vet_stmt->bindValue(':vet_id', $vet_id, PDO::PARAM_INT);
                    $check_vet_stmt->bindValue(':role_id', $vet_role_id, PDO::PARAM_INT);
                    $check_vet_stmt->execute();
                    if ($check_vet_stmt->rowCount() == 0) {
                        $error = "El veterinario seleccionado no es válido.";
                    }
                }
                
                if (empty($error)) {
                    // Insertar cita
                    $insert_sql = "INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) 
                                   VALUES (:pet_id, :vet_id, :date, :reason, 'PENDIENTE')";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                    $insert_stmt->bindValue(':vet_id', $vet_id, PDO::PARAM_INT);
                    $insert_stmt->bindValue(':date', $formatted_date, PDO::PARAM_STR);
                    $insert_stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
                    
                    if ($insert_stmt->execute()) {
                        require_once '../includes/bitacora_function.php';
                        $action = "Cita agendada para mascota ID $pet_id con veterinario ID $vet_id el $formatted_date";
                        log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                        
                        $success = "Cita agendada correctamente.";
                        $_POST = []; // Limpiar formulario
                    } else {
                        $errorInfo = $insert_stmt->errorInfo();
                        $error = "Error al agendar: " . ($errorInfo[2] ?? 'Error desconocido');
                    }
                }
            } catch (PDOException $e) {
                $error = "Error de base de datos: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agendar Cita - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
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
        select, input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; box-sizing: border-box; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { display: block; width: 100%; padding: 12px; background: #40916c; color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; margin-top: 20px; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-align: center; text-decoration: none; margin-top: 10px; }
        .btn-secondary:hover { background: #5a6268; }
        .no-pets { background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Agendar Cita</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-calendar-plus"></i> Agendar Nueva Cita</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (empty($pets)): ?>
            <div class="no-pets">
                <p>No hay mascotas disponibles para agendar citas.</p>
                <?php if ($role_name === 'Propietario'): ?>
                    <a href="pet_register.php" class="btn btn-secondary">Registrar Mascota</a>
                <?php endif; ?>
            </div>
        <?php elseif (empty($vets)): ?>
            <div class="no-pets">
                <p>No hay veterinarios disponibles. Contacte al administrador.</p>
            </div>
        <?php else: ?>
            <form method="post">
                <label for="pet_id">Mascota:</label>
                <select name="pet_id" id="pet_id" required>
                    <option value="">Seleccione una mascota</option>
                    <?php foreach ($pets as $pet): ?>
                        <option value="<?php echo $pet['id']; ?>" <?php echo (isset($_POST['pet_id']) && $_POST['pet_id'] == $pet['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pet['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="vet_id">Veterinario:</label>
                <select name="vet_id" id="vet_id" required>
                    <option value="">Seleccione un veterinario</option>
                    <?php foreach ($vets as $vet): ?>
                        <option value="<?php echo $vet['id']; ?>" <?php echo (isset($_POST['vet_id']) && $_POST['vet_id'] == $vet['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vet['display_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="appointment_date">Fecha y hora:</label>
                <input type="datetime-local" name="appointment_date" id="appointment_date" required min="<?php echo date('Y-m-d\TH:i'); ?>" value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">

                <label for="reason">Motivo:</label>
                <textarea name="reason" id="reason" rows="4" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>

                <button type="submit" class="btn"><i class="fas fa-save"></i> Agendar Cita</button>
            </form>
            <a href="appointment_list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Ver citas</a>
        <?php endif; ?>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
