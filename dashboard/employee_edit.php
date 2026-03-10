<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
$username = $_SESSION['username'] ?? 'Usuario';

// Solo admin puede editar empleados (según matriz)
if ($role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$employee_id = intval($_GET['id'] ?? 0);
if ($employee_id <= 0) {
    header("Location: employee_list.php");
    exit;
}

// Obtener datos actuales del empleado
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if (!$employee) {
    header("Location: employee_list.php");
    exit;
}

// Obtener lista de roles
$roles = $conn->query("SELECT id, name FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$error = '';
$success = '';

// Procesar actualización
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ci = trim($_POST['ci']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $position = trim($_POST['position']);
    $role_id = intval($_POST['role_id']);
    $status = $_POST['status'];

    if (empty($first_name) || empty($email) || empty($position)) {
        $error = "Nombres, email y cargo son obligatorios.";
    } else {
        // Verificar email único (excepto el mismo)
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->bind_param("si", $email, $employee_id);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) {
            $error = "El email ya está registrado por otro usuario.";
            $check->close();
        } else {
            $check->close();
            $update = $conn->prepare("UPDATE users SET ci=?, first_name=?, last_name=?, email=?, phone=?, address=?, position=?, role_id=?, status=? WHERE id=?");
            $update->bind_param("sssssssisi", $ci, $first_name, $last_name, $email, $phone, $address, $position, $role_id, $status, $employee_id);
            if ($update->execute()) {
                require_once '../includes/bitacora_function.php';
                $action = "Empleado ID $employee_id actualizado";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                
                $success = "Empleado actualizado correctamente.";
                // Actualizar datos locales
                $employee = array_merge($employee, [
                    'ci' => $ci, 'first_name' => $first_name, 'last_name' => $last_name,
                    'email' => $email, 'phone' => $phone, 'address' => $address,
                    'position' => $position, 'role_id' => $role_id, 'status' => $status
                ]);
            } else {
                $error = "Error al actualizar: " . $update->error;
            }
            $update->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 800px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #1b4332; }
        input, select, textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        input:focus, select:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #5a6268; }
        .form-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; }
        @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="employee_list.php">Empleados</a> <span>›</span>
        <span>Editar Empleado</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-user-edit"></i> Editar Empleado</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="form-row">
                <div class="form-group">
                    <label for="ci">Cédula:</label>
                    <input type="text" name="ci" id="ci" value="<?php echo htmlspecialchars($employee['ci'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="role_id">Rol:</label>
                    <select name="role_id" id="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo ($role['id'] == $employee['role_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Nombres:</label>
                    <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Apellidos:</label>
                    <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($employee['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Teléfono:</label>
                    <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="position">Cargo:</label>
                    <input type="text" name="position" id="position" value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="status">Estado:</label>
                    <select name="status" id="status" required>
                        <option value="active" <?php echo ($employee['status'] == 'active') ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactive" <?php echo ($employee['status'] == 'inactive') ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="suspended" <?php echo ($employee['status'] == 'suspended') ? 'selected' : ''; ?>>Suspendido</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="address">Dirección:</label>
                <textarea name="address" id="address" rows="3"><?php echo htmlspecialchars($employee['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-footer">
                <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios</button>
                <a href="employee_view.php?id=<?php echo $employee_id; ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> Ver Perfil</a>
                <a href="employee_list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Lista</a>
            </div>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>