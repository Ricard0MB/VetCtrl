<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !in_array($_SESSION['role_name'] ?? '', ['admin', 'Veterinario'])) {
    header("Location: ../index.php");
    exit;
}

$employee_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$employee_id || $employee_id <= 0) {
    header("Location: employee_list.php");
    exit;
}

try {
    $stmt = $conn->prepare("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = :id");
    $stmt->bindValue(':id', $employee_id, PDO::PARAM_INT);
    $stmt->execute();
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$employee) {
        header("Location: employee_list.php");
        exit;
    }
} catch (PDOException $e) {
    die("Error al cargar empleado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de Empleado - VetCtrl</title>
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
        :root { --vet-dark: #1b4332; --vet-primary: #40916c; --shadow-md: 0 8px 20px rgba(0,0,0,0.05); --radius-lg: 16px; }
        .breadcrumb { max-width: 1000px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .container { max-width: 1000px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        .profile-header { display: flex; align-items: center; gap: 1.5rem; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid #e2e8e2; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: white; }
        .profile-info h2 { font-size: 1.8rem; color: var(--vet-dark); }
        .profile-info .position { color: var(--vet-primary); font-weight: 500; margin: 0.2rem 0; }
        .status-badge { display: inline-block; padding: 0.2rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-inactive { background: #fff3cd; color: #856404; }
        .status-suspended { background: #f8d7da; color: #842029; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .info-section { background: #f8faf8; padding: 1.2rem; border-radius: 12px; border-left: 4px solid var(--vet-primary); }
        .info-section h3 { margin-bottom: 1rem; color: var(--vet-dark); font-size: 1.1rem; }
        .info-item { margin-bottom: 0.8rem; }
        .info-label { font-weight: 600; color: #495057; font-size: 0.8rem; display: block; margin-bottom: 0.2rem; }
        .info-value { background: white; padding: 0.5rem; border-radius: 8px; border: 1px solid #dee2e6; font-size: 0.85rem; }
        .btn-group { display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background: var(--vet-primary); color: white; }
        .btn-primary:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } .profile-header { flex-direction: column; text-align: center; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb"><a href="welcome.php">Inicio</a> <span>›</span> <a href="employee_list.php">Empleados</a> <span>›</span> <span>Detalles</span></div>
    <div class="container">
        <div class="profile-header">
            <div class="avatar"><?php echo strtoupper(substr($employee['first_name']??'E',0,1).substr($employee['last_name']??'M',0,1)); ?></div>
            <div class="profile-info"><h2><?php echo htmlspecialchars($employee['first_name'].' '.$employee['last_name']); ?></h2><div class="position"><?php echo htmlspecialchars($employee['position']); ?></div><div><span class="status-badge status-<?php echo $employee['status']; ?>"><?php echo ucfirst($employee['status']); ?></span> <span style="margin-left:0.5rem;"><?php echo htmlspecialchars($employee['role_name']); ?></span></div></div>
        </div>
        <div class="info-grid">
            <div class="info-section"><h3>📝 Información Personal</h3><div class="info-item"><div class="info-label">Cédula</div><div class="info-value"><?php echo htmlspecialchars($employee['ci']??'No especificada'); ?></div></div><div class="info-item"><div class="info-label">Email</div><div class="info-value"><?php echo htmlspecialchars($employee['email']); ?></div></div><div class="info-item"><div class="info-label">Teléfono</div><div class="info-value"><?php echo htmlspecialchars($employee['phone']??'No especificado'); ?></div></div><div class="info-item"><div class="info-label">Dirección</div><div class="info-value"><?php echo htmlspecialchars($employee['address']??'No especificada'); ?></div></div></div>
            <div class="info-section"><h3>💼 Información Laboral</h3><div class="info-item"><div class="info-label">Cargo</div><div class="info-value"><?php echo htmlspecialchars($employee['position']); ?></div></div><div class="info-item"><div class="info-label">Rol en Sistema</div><div class="info-value"><?php echo htmlspecialchars($employee['role_name']); ?> (ID: <?php echo $employee['role_id']; ?>)</div></div><div class="info-item"><div class="info-label">Estado</div><div class="info-value"><span class="status-badge status-<?php echo $employee['status']; ?>"><?php echo ucfirst($employee['status']); ?></span></div></div></div>
            <div class="info-section"><h3>🔑 Acceso</h3><div class="info-item"><div class="info-label">Usuario</div><div class="info-value"><?php echo htmlspecialchars($employee['username']); ?></div></div><div class="info-item"><div class="info-label">Fecha Registro</div><div class="info-value"><?php echo date('d/m/Y H:i', strtotime($employee['created_at'])); ?></div></div><div class="info-item"><div class="info-label">ID Usuario</div><div class="info-value"><?php echo $employee['id']; ?></div></div></div>
        </div>
        <div class="btn-group"><a href="employee_edit.php?id=<?php echo $employee_id; ?>" class="btn btn-primary"><i class="fas fa-edit"></i> Editar</a><a href="employee_list.php" class="btn btn-secondary"><i class="fas fa-list"></i> Volver a Lista</a><?php if ($_SESSION['role_name'] === 'admin'): ?><a href="employee_delete.php?id=<?php echo $employee_id; ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este empleado?')"><i class="fas fa-trash"></i> Eliminar</a><?php endif; ?></div>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
