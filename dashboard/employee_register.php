<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if ($role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$roles = [];
$error = '';
$success = '';

try {
    // Obtener lista de roles
    $stmtRoles = $conn->query("SELECT id, name FROM roles ORDER BY id");
    $roles = $stmtRoles->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar roles: " . $e->getMessage();
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $ci = trim($_POST['ci'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $role_id = intval($_POST['role_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Validaciones
    if (empty($ci)) {
        $error = "La cédula es obligatoria.";
    } elseif (empty($first_name) || empty($email) || empty($username) || empty($password) || empty($position)) {
        $error = "Complete los campos obligatorios.";
    } elseif ($password !== $confirm) {
        $error = "Las contraseñas no coinciden.";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres.";
    } else {
        try {
            // Verificar cédula única
            $checkCi = $conn->prepare("SELECT id FROM users WHERE ci = :ci");
            $checkCi->bindValue(':ci', $ci);
            $checkCi->execute();
            if ($checkCi->fetch()) {
                $error = "La cédula ya está registrada.";
            } else {
                // Verificar email único
                $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = :email");
                $checkEmail->bindValue(':email', $email);
                $checkEmail->execute();
                if ($checkEmail->fetch()) {
                    $error = "El email ya está registrado.";
                } else {
                    // Verificar username único
                    $checkUser = $conn->prepare("SELECT id FROM users WHERE username = :username");
                    $checkUser->bindValue(':username', $username);
                    $checkUser->execute();
                    if ($checkUser->fetch()) {
                        $error = "El nombre de usuario ya existe.";
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO users (ci, first_name, last_name, email, phone, address, position, role_id, username, password, status, created_at) 
                                VALUES (:ci, :first_name, :last_name, :email, :phone, :address, :position, :role_id, :username, :password, 'active', NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindValue(':ci', $ci);
                        $stmt->bindValue(':first_name', $first_name);
                        $stmt->bindValue(':last_name', $last_name);
                        $stmt->bindValue(':email', $email);
                        $stmt->bindValue(':phone', $phone);
                        $stmt->bindValue(':address', $address);
                        $stmt->bindValue(':position', $position);
                        $stmt->bindValue(':role_id', $role_id, PDO::PARAM_INT);
                        $stmt->bindValue(':username', $username);
                        $stmt->bindValue(':password', $hashed);

                        if ($stmt->execute()) {
                            require_once '../includes/bitacora_function.php';
                            $action = "Nuevo empleado registrado: $first_name $last_name (CI: $ci)";
                            log_to_bitacora($conn, $action, $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);

                            $success = "Empleado registrado exitosamente.";
                            $_POST = []; // Limpiar formulario
                        } else {
                            $errorInfo = $stmt->errorInfo();
                            $error = "Error al registrar: " . ($errorInfo[2] ?? 'Error desconocido');
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Empleado - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 800px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
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
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #40916c; color: white; }
        .btn-primary:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .field-note { font-size: 0.85rem; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="employee_list.php">Empleados</a> <span>›</span>
        <span>Registrar</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-user-plus"></i> Registrar Nuevo Empleado</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-row">
                    <div class="form-group">
                        <label>Cédula *</label>
                        <input type="text" name="ci" value="<?php echo htmlspecialchars($_POST['ci'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Rol *</label>
                        <select name="role_id" required>
                            <option value="">Seleccione</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?php echo $r['id']; ?>" <?php echo (($_POST['role_id'] ?? '') == $r['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nombres *</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Apellidos *</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Usuario *</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Cargo *</label>
                        <input type="text" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contraseña *</label>
                        <input type="password" name="password" required>
                        <div class="field-note">Mínimo 8 caracteres</div>
                    </div>
                    <div class="form-group">
                        <label>Confirmar *</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Dirección</label>
                    <textarea name="address" rows="3"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Registrar</button>
                    <a href="employee_list.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
