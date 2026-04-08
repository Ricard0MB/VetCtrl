<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_name'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';

$where = "WHERE 1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (u.first_name LIKE :s1 OR u.last_name LIKE :s2 OR u.email LIKE :s3 OR u.username LIKE :s4 OR u.ci LIKE :s5)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
    $params[':s4'] = "%$search%";
    $params[':s5'] = "%$search%";
}
if (!empty($status) && $status !== 'all') {
    $where .= " AND u.status = :status";
    $params[':status'] = $status;
}
if (!empty($role_filter) && $role_filter !== 'all') {
    $where .= " AND u.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}

$employees = [];
$roles_list = [];
$error_message = '';

try {
    $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id $where ORDER BY u.first_name, u.last_name");
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $roles_list = $conn->query("SELECT id, name FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error al cargar los datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados - VetCtrl</title>
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
        .breadcrumb { max-width: 1400px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1400px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 1.8rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .header-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .btn-new { background: var(--vet-primary); color: white; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-new:hover { background: var(--vet-dark); }
        .filter-form { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; align-items: flex-end; background: #f8faf8; padding: 1rem; border-radius: 12px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--vet-dark); margin-bottom: 0.2rem; }
        .filter-group input, .filter-group select { padding: 0.5rem; border: 1px solid #d0d8d0; border-radius: 8px; font-family: inherit; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-primary { background: var(--vet-primary); color: white; }
        .btn-primary:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; color: white; text-decoration: none; display: inline-flex; align-items: center; gap: 0.3rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8faf8; padding: 0.9rem; text-align: left; color: var(--vet-dark); border-bottom: 2px solid #dee6de; font-weight: 600; }
        td { padding: 0.8rem; border-bottom: 1px solid #eef2ee; vertical-align: middle; }
        tr:hover td { background: #fafdfa; }
        .status-badge { display: inline-block; padding: 0.2rem 0.7rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-inactive { background: #fff3cd; color: #856404; }
        .status-suspended { background: #f8d7da; color: #842029; }
        .btn-action { padding: 0.3rem 0.6rem; border-radius: 6px; text-decoration: none; font-size: 0.75rem; display: inline-flex; align-items: center; gap: 0.3rem; transition: 0.2s; margin: 0 0.2rem; }
        .btn-view { background: #6c757d; color: white; }
        .btn-edit { background: #0dcaf0; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .no-results { text-align: center; padding: 2rem; color: #6c757d; }
        .counter { font-weight: 600; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } .filter-form { flex-direction: column; } table { font-size: 0.75rem; } th, td { padding: 0.5rem; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span> <span>Gestión de Empleados</span>
    </div>
    <div class="container">
        <h1><i class="fas fa-users-cog"></i> Gestión de Empleados</h1>
        <div class="header-actions">
            <a href="employee_register.php" class="btn-new"><i class="fas fa-user-plus"></i> Nuevo Empleado</a>
            <div>Total: <strong class="counter"><?php echo count($employees); ?></strong></div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label>Buscar</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nombre, CI, email">
            </div>
            <div class="filter-group">
                <label>Estado</label>
                <select name="status">
                    <option value="all">Todos</option>
                    <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Activo</option>
                    <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                    <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspendido</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Rol</label>
                <select name="role">
                    <option value="all">Todos</option>
                    <?php foreach ($roles_list as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $role_filter == $r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrar</button>
            </div>
            <div class="filter-group">
                <a href="employee_list.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Limpiar</a>
            </div>
        </form>

        <?php if (empty($employees)): ?>
            <div class="no-results">
                <i class="fas fa-user-slash fa-2x" style="margin-bottom: 0.5rem; display: block;"></i>
                <p>No se encontraron empleados.</p>
                <a href="employee_register.php" class="btn-new" style="margin-top: 1rem;">Registrar Empleado</a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cédula</th>
                            <th>Nombre Completo</th>
                            <th>Email</th>
                            <th>Cargo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($emp['id'] ?? '')); ?></td>
                            <td><strong><?php echo htmlspecialchars((string)($emp['ci'] ?? 'N/A')); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars((string)($emp['first_name'] ?? '') . ' ' . (string)($emp['last_name'] ?? '')); ?></strong><br>
                                <small>Usuario: <?php echo htmlspecialchars((string)($emp['username'] ?? '')); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars((string)($emp['email'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($emp['position'] ?? '')); ?></td>
                            <td><span style="color: var(--vet-primary);"><?php echo htmlspecialchars((string)($emp['role_name'] ?? '')); ?></span></td>
                            <td><span class="status-badge status-<?php echo htmlspecialchars((string)($emp['status'] ?? 'inactive')); ?>"><?php echo ucfirst(htmlspecialchars((string)($emp['status'] ?? 'inactive'))); ?></span></td>
                            <td><?php echo date('d/m/Y', strtotime($emp['created_at'] ?? 'now')); ?></td>
                            <td>
                                <a href="employee_view.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-view"><i class="fas fa-eye"></i></a>
                                <a href="employee_edit.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                                <a href="employee_delete.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-delete" onclick="return confirm('¿Eliminar este empleado?')"><i class="fas fa-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
