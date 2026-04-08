<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_name'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$employee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$employee_id || $employee_id <= 0) {
    header("Location: employee_list.php");
    exit;
}

$employee = null;
$error = '';
$success = '';

try {
    $sql = "SELECT * FROM users WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        header("Location: employee_list.php");
        exit;
    }

    $roles = [];
    $stmtRoles = $conn->query("SELECT id, name FROM roles ORDER BY id");
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $ci = trim($_POST['ci'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $role_id = intval($_POST['role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($first_name) || empty($email) || empty($position)) {
            $error = "Nombres, email y cargo son obligatorios.";
        } else {
            $checkSql = "SELECT COUNT(*) FROM users WHERE email = :email AND id != :id";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindValue(':email', $email);
            $checkStmt->bindValue(':id', $employee_id, PDO::PARAM_INT);
            $checkStmt->execute();
            if ($checkStmt->fetchColumn() > 0) {
                $error = "El email ya está registrado por otro usuario.";
            } else {
                $updateSql = "UPDATE users SET ci = :ci, first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, address = :address, position = :position, role_id = :role_id, status = :status WHERE id = :id";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bindValue(':ci', $ci);
                $updateStmt->bindValue(':first_name', $first_name);
                $updateStmt->bindValue(':last_name', $last_name);
                $updateStmt->bindValue(':email', $email);
                $updateStmt->bindValue(':phone', $phone);
                $updateStmt->bindValue(':address', $address);
                $updateStmt->bindValue(':position', $position);
                $updateStmt->bindValue(':role_id', $role_id, PDO::PARAM_INT);
                $updateStmt->bindValue(':status', $status);
                $updateStmt->bindValue(':id', $employee_id, PDO::PARAM_INT);
                if ($updateStmt->execute()) {
                    require_once '../includes/bitacora_function.php';
                    log_to_bitacora($conn, "Empleado ID $employee_id actualizado", $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);
                    $success = "Empleado actualizado correctamente.";
                    $employee = array_merge($employee, compact('ci','first_name','last_name','email','phone','address','position','role_id','status'));
                } else {
                    $error = "Error al actualizar.";
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Empleado - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7f9;
            color: #1e2f2a;
            padding-top: 72px;
        }
        :root {
            --vet-dark: #1b4332;
            --vet-primary: #40916c;
            --shadow-md: 0 8px 20px rgba(0,0,0,0.05);
            --radius-lg: 16px;
        }
        .breadcrumb { max-width: 800px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .container { max-width: 800px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .form-group { margin-bottom: 1.2rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
        label { display: block; margin-bottom: 0.3rem; font-weight: 600; color: var(--vet-dark); font-size: 0.85rem; }
        input, select, textarea { width: 100%; padding: 0.7rem; border: 1px solid #d0d8d0; border-radius: 10px; font-family: inherit; }
        input:focus, select:focus, textarea:focus { border-color: var(--vet-primary); outline: none; box-shadow: 0 0 0 2px rgba(64,145,108,0.2); }
        .btn { padding: 0.7rem 1.2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; background: var(--vet-primary); color: white; }
        .btn:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; margin-left: 0.5rem; }
        .form-footer { display: flex; gap: 1rem; margin-top: 1.5rem; flex-wrap: wrap; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb"><a href="welcome.php">Inicio</a> <span>›</span> <a href="employee_list.php">Empleados</a> <span>›</span> <span>Editar</span></div>
    <div class="container">
        <h1><i class="fas fa-user-edit"></i> Editar Empleado</h1>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <form method="post">
            <div class="form-row">
                <div class="form-group"><label>Cédula</label><input type="text" name="ci" value="<?php echo htmlspecialchars((string)($employee['ci']??'')); ?>"></div>
                <div class="form-group"><label>Rol</label><select name="role_id"><?php foreach ($roles as $r): ?><option value="<?php echo $r['id']; ?>" <?php echo ($r['id']==($employee['role_id']??0))?'selected':''; ?>><?php echo htmlspecialchars($r['name']); ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Nombres *</label><input type="text" name="first_name" value="<?php echo htmlspecialchars((string)($employee['first_name']??'')); ?>" required></div>
                <div class="form-group"><label>Apellidos *</label><input type="text" name="last_name" value="<?php echo htmlspecialchars((string)($employee['last_name']??'')); ?>" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Email *</label><input type="email" name="email" value="<?php echo htmlspecialchars((string)($employee['email']??'')); ?>" required></div>
                <div class="form-group"><label>Teléfono</label><input type="text" name="phone" value="<?php echo htmlspecialchars((string)($employee['phone']??'')); ?>"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Cargo *</label><input type="text" name="position" value="<?php echo htmlspecialchars((string)($employee['position']??'')); ?>" required></div>
                <div class="form-group"><label>Estado</label><select name="status"><option value="active" <?php echo ($employee['status']??'')=='active'?'selected':''; ?>>Activo</option><option value="inactive" <?php echo ($employee['status']??'')=='inactive'?'selected':''; ?>>Inactivo</option><option value="suspended" <?php echo ($employee['status']??'')=='suspended'?'selected':''; ?>>Suspendido</option></select></div>
            </div>
            <div class="form-group"><label>Dirección</label><textarea name="address" rows="3"><?php echo htmlspecialchars((string)($employee['address']??'')); ?></textarea></div>
            <div class="form-footer"><button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios</button><a href="employee_view.php?id=<?php echo (int)$employee_id; ?>" class="btn btn-secondary"><i class="fas fa-eye"></i> Ver Perfil</a><a href="employee_list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Lista</a></div>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
