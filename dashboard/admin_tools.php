<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

if (($_SESSION['role_name'] ?? '') !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

require_once '../includes/config.php';

$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'limpiar_cache') {
        $mensaje = "Caché limpiada correctamente (simulado).";
        $tipo_mensaje = 'success';
    } elseif ($accion === 'optimizar_bd') {
        $mensaje = "Base de datos optimizada (simulado).";
        $tipo_mensaje = 'success';
    }
}

$stats = [
    'total_veterinarios' => 0,
    'total_propietarios' => 0,
    'total_pacientes' => 0,
    'citas_hoy' => 0
];

try {
    $sql = "SELECT 
        (SELECT COUNT(*) FROM users WHERE role_id = 1) as total_veterinarios,
        (SELECT COUNT(*) FROM users WHERE role_id = 3) as total_propietarios,
        (SELECT COUNT(*) FROM pets) as total_pacientes,
        (SELECT COUNT(*) FROM appointments WHERE status = 'PENDIENTE' AND DATE(appointment_date) = CURDATE()) as citas_hoy";
    $stmt = $conn->query($sql);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats = $row;
    }
} catch (PDOException $e) {
    $mensaje = "Error al obtener estadísticas: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Herramientas de Administración - VetCtrl</title>
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
            --vet-light: #74c69d;
            --vet-bg: #f4f7f9;
            --vet-card: #ffffff;
            --shadow-md: 0 8px 20px rgba(0,0,0,0.05);
            --radius-lg: 16px;
        }
        .breadcrumb { max-width: 1200px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1200px; margin: 1.5rem auto; padding: 0 1.5rem; }
        .card { background: var(--vet-card); border-radius: var(--radius-lg); padding: 1.8rem; box-shadow: var(--shadow-md); margin-bottom: 2rem; }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-light); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 1.2rem; margin-bottom: 2rem; }
        .stat-card { background: #f8faf8; border-radius: 14px; padding: 1.2rem; text-align: center; border-left: 4px solid var(--vet-primary); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--vet-dark); }
        .stat-label { color: var(--vet-text-light); margin-top: 0.3rem; font-size: 0.85rem; }
        .tools-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 1.2rem; margin: 1.5rem 0; }
        .tool { background: #f8faf8; border-radius: 14px; padding: 1.5rem; text-align: center; transition: transform 0.2s; }
        .tool:hover { transform: translateY(-3px); background: #ffffff; box-shadow: var(--shadow-md); }
        .btn { padding: 0.6rem 1.2rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.85rem; transition: 0.2s; }
        .btn-primary { background: var(--vet-primary); color: white; }
        .btn-primary:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .module-links { display: flex; flex-wrap: wrap; gap: 0.8rem; margin: 1.5rem 0 1rem; }
        .module-link { background: #eef2ee; padding: 0.6rem 1rem; border-radius: 10px; text-decoration: none; color: var(--vet-dark); font-weight: 500; font-size: 0.85rem; transition: 0.2s; }
        .module-link:hover { background: var(--vet-primary); color: white; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1.2rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        @media (max-width: 768px) { .container { padding: 0 1rem; } .card { padding: 1.2rem; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span> <span>Herramientas Admin</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-tools"></i> Herramientas de Administración</h1>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $stats['total_veterinarios']; ?></div><div class="stat-label">Veterinarios</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $stats['total_propietarios']; ?></div><div class="stat-label">Propietarios</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $stats['total_pacientes']; ?></div><div class="stat-label">Pacientes</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $stats['citas_hoy']; ?></div><div class="stat-label">Citas Pendientes Hoy</div></div>
            </div>

            <h3 style="margin: 1.2rem 0 0.8rem;">Acciones rápidas</h3>
            <div class="tools-grid">
                <div class="tool">
                    <i class="fas fa-broom fa-2x" style="color: var(--vet-primary);"></i>
                    <h4 style="margin: 0.8rem 0;">Limpiar Caché</h4>
                    <form method="post">
                        <input type="hidden" name="accion" value="limpiar_cache">
                        <button type="submit" class="btn btn-primary">Ejecutar</button>
                    </form>
                </div>
                <div class="tool">
                    <i class="fas fa-database fa-2x" style="color: var(--vet-primary);"></i>
                    <h4 style="margin: 0.8rem 0;">Optimizar BD</h4>
                    <form method="post">
                        <input type="hidden" name="accion" value="optimizar_bd">
                        <button type="submit" class="btn btn-primary">Ejecutar</button>
                    </form>
                </div>
                <div class="tool">
                    <i class="fas fa-hdd fa-2x" style="color: var(--vet-primary);"></i>
                    <h4 style="margin: 0.8rem 0;">Respaldar BD</h4>
                    <a href="backup_system.php" class="btn btn-primary">Ir a Backup</a>
                </div>
            </div>

            <h3 style="margin: 1.2rem 0 0.8rem;">Módulos de administración</h3>
            <div class="module-links">
                <a href="employee_list.php" class="module-link"><i class="fas fa-users-cog"></i> Empleados</a>
                <a href="log_viewer.php" class="module-link"><i class="fas fa-history"></i> Bitácora</a>
                <a href="backup_system.php" class="module-link"><i class="fas fa-database"></i> Backups</a>
                <a href="system_settings.php" class="module-link"><i class="fas fa-cog"></i> Configuración</a>
                <a href="statistics_dashboard.php" class="module-link"><i class="fas fa-chart-line"></i> Estadísticas</a>
                <a href="daily_report.php" class="module-link"><i class="fas fa-file-alt"></i> Reporte Diario</a>
            </div>

            <div style="margin-top: 1.5rem;">
                <a href="welcome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
