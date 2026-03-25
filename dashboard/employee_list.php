<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
// Solo admin puede acceder (según matriz: gestión de empleados solo admin)
if ($role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$role_filter = $_GET['role'] ?? '';

$where_clause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where_clause .= " AND (u.first_name LIKE :search1 OR u.last_name LIKE :search2 OR u.email LIKE :search3 OR u.username LIKE :search4 OR u.ci LIKE :search5)";
    $params[':search1'] = "%$search%";
    $params[':search2'] = "%$search%";
    $params[':search3'] = "%$search%";
    $params[':search4'] = "%$search%";
    $params[':search5'] = "%$search%";
}
if (!empty($status) && $status !== 'all') {
    $where_clause .= " AND u.status = :status";
    $params[':status'] = $status;
}
if (!empty($role_filter) && $role_filter !== 'all') {
    $where_clause .= " AND u.role_id = :role_id";
    $params[':role_id'] = $role_filter;
}

$sql = "SELECT u.id, u.ci, u.first_name, u.last_name, u.email, u.phone, 
               u.address, u.position, u.role_id, u.username, u.status, 
               u.created_at, r.name as role_name
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        $where_clause
        ORDER BY u.first_name, u.last_name";

try {
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener roles para filtro
    $roles_list = [];
    $roles_query = $conn->query("SELECT id, name FROM roles ORDER BY id");
    $roles_list = $roles_query->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Manejo de errores: podrías guardar en log y mostrar mensaje genérico
    $employees = [];
    $roles_list = [];
    // Opcional: establecer un mensaje de error para mostrar al usuario
    $error_message = "Error al cargar los datos: " . $e->getMessage();
}

// No es necesario cerrar la conexión explícitamente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Empleados - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1400px;
            margin: 10px auto 0;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.95rem;
        }
        .breadcrumb a {
            color: #40916c;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #6c757d;
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .btn-new {
            background: #40916c;
            color: white;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-new:hover {
            background: #2d6a4f;
        }
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 5px;
            font-weight: 600;
            color: #1b4332;
        }
        .filter-group input,
        .filter-group select {
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .filter-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        .filter-btn.search {
            background: #40916c;
            color: white;
        }
        .filter-btn.reset {
            background: #6c757d;
            color: white;
        }
        .table-container {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        .employees-table {
            width: 100%;
            border-collapse: collapse;
        }
        .employees-table th {
            background: #e8f5e9;
            padding: 15px;
            text-align: left;
            color: #1b4332;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        .employees-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        .employees-table tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        .status-inactive {
            background: #fff3cd;
            color: #856404;
        }
        .status-suspended {
            background: #f8d7da;
            color: #842029;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .btn-action {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-edit {
            background: #0dcaf0;
            color: white;
        }
        .btn-view {
            background: #6c757d;
            color: white;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-action:hover {
            opacity: 0.9;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .counter {
            margin-top: 15px;
            color: #666;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .employees-table {
                display: block;
                overflow-x: auto;
            }
            .header-actions {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Gestión de Empleados</span>
    </div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-users-cog"></i> Gestión de Empleados</h1>
            <p>Administración del personal de la clínica</p>
            <div class="header-actions">
                <a href="employee_register.php" class="btn-new">
                    <i class="fas fa-user-plus"></i> Nuevo Empleado
                </a>
                <div class="counter">Total: <strong><?php echo count($employees); ?></strong></div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="filters-container">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label for="search">Buscar:</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Nombre, CI, email...">
                </div>
                <div class="filter-group">
                    <label for="status">Estado:</label>
                    <select id="status" name="status">
                        <option value="all">Todos</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                        <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>Suspendido</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="role">Rol:</label>
                    <select id="role" name="role">
                        <option value="all">Todos</option>
                        <?php foreach ($roles_list as $role): ?>
                            <option value="<?php echo $role['id']; ?>" <?php echo $role_filter == $role['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($role['name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="filter-btn search"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="employee_list.php" class="filter-btn reset"><i class="fas fa-undo"></i> Limpiar</a>
                </div>
            </form>
        </div>

        <!-- Tabla -->
        <div class="table-container">
            <?php if (empty($employees)): ?>
                <div class="no-results">
                    <i class="fas fa-user-slash" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <h3>No se encontraron empleados</h3>
                    <p>Prueba con otros filtros o registra un nuevo empleado.</p>
                    <a href="employee_register.php" class="btn-new" style="margin-top: 15px;">Registrar Empleado</a>
                </div>
            <?php else: ?>
                <table class="employees-table">
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
                                <td><?php echo $emp['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($emp['ci'] ?? 'N/A'); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')); ?></strong><br>
                                    <small>Usuario: <?php echo htmlspecialchars($emp['username'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($emp['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($emp['position'] ?? ''); ?></td>
                                <td><span style="color: #40916c;"><?php echo htmlspecialchars($emp['role_name'] ?? ''); ?></span></td>
                                <td>
                                    <span class="status-badge status-<?php echo $emp['status']; ?>">
                                        <?php echo ucfirst($emp['status'] ?? ''); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($emp['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="employee_view.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-view"><i class="fas fa-eye"></i></a>
                                        <a href="employee_edit.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i></a>
                                        <a href="employee_delete.php?id=<?php echo $emp['id']; ?>" class="btn-action btn-delete" onclick="return confirm('¿Eliminar este empleado?')"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
