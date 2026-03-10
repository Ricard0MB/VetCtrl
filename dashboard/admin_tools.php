<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

// Solo admin puede acceder
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
        // Simulación
        $mensaje = "Caché limpiada correctamente (simulado).";
        $tipo_mensaje = 'success';
    } elseif ($accion === 'optimizar_bd') {
        $mensaje = "Base de datos optimizada (simulado).";
        $tipo_mensaje = 'success';
    } elseif ($accion === 'respaldar_bd') {
        $mensaje = "Backup creado correctamente (simulado).";
        $tipo_mensaje = 'success';
    }
}

// Obtener información básica del sistema
$stats = [
    'total_veterinarios' => 0,
    'total_propietarios' => 0,
    'total_pacientes' => 0,
    'citas_hoy' => 0
];

$sql = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role_id = 1) as total_veterinarios,
    (SELECT COUNT(*) FROM users WHERE role_id = 3) as total_propietarios,
    (SELECT COUNT(*) FROM pets) as total_pacientes,
    (SELECT COUNT(*) FROM appointments WHERE status = 'PENDIENTE' AND DATE(appointment_date) = CURDATE()) as citas_hoy";
$result = $conn->query($sql);
if ($row = $result->fetch_assoc()) {
    $stats = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Herramientas de Administración - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 1200px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; border-left: 4px solid #40916c; }
        .stat-value { font-size: 2rem; font-weight: bold; color: #1b4332; }
        .stat-label { color: #6c757d; margin-top: 5px; }
        .tools-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 20px; }
        .tool { background: #f8f9fa; border-radius: 8px; padding: 20px; text-align: center; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #40916c; color: white; }
        .btn-primary:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .module-links { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .module-link { background: #e9ecef; padding: 10px 15px; border-radius: 6px; text-decoration: none; color: #1b4332; font-weight: 600; }
        .module-link:hover { background: #dee2e6; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Herramientas Admin</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-tools"></i> Herramientas de Administración</h1>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <i class="fas fa-<?php echo $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_veterinarios']; ?></div>
                    <div class="stat-label">Veterinarios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_propietarios']; ?></div>
                    <div class="stat-label">Propietarios</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_pacientes']; ?></div>
                    <div class="stat-label">Pacientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['citas_hoy']; ?></div>
                    <div class="stat-label">Citas Pendientes Hoy</div>
                </div>
            </div>

            <h3 style="margin-top:30px;">Acciones rápidas</h3>
            <div class="tools-grid">
                <div class="tool">
                    <i class="fas fa-broom fa-2x" style="color:#40916c;"></i>
                    <h4>Limpiar Caché</h4>
                    <form method="post">
                        <input type="hidden" name="accion" value="limpiar_cache">
                        <button type="submit" class="btn btn-primary">Ejecutar</button>
                    </form>
                </div>
                <div class="tool">
                    <i class="fas fa-database fa-2x" style="color:#40916c;"></i>
                    <h4>Optimizar BD</h4>
                    <form method="post">
                        <input type="hidden" name="accion" value="optimizar_bd">
                        <button type="submit" class="btn btn-primary">Ejecutar</button>
                    </form>
                </div>
                <div class="tool">
                    <i class="fas fa-hdd fa-2x" style="color:#40916c;"></i>
                    <h4>Respaldar BD</h4>
                    <form method="post">
                        <input type="hidden" name="accion" value="respaldar_bd">
                        <button type="submit" class="btn btn-primary">Ejecutar</button>
                    </form>
                </div>
            </div>

            <h3 style="margin-top:30px;">Módulos de administración</h3>
            <div class="module-links">
                <a href="employee_list.php" class="module-link"><i class="fas fa-users-cog"></i> Empleados</a>
                <a href="log_viewer.php" class="module-link"><i class="fas fa-history"></i> Bitácora</a>
                <a href="backup_system.php" class="module-link"><i class="fas fa-database"></i> Backups</a>
                <a href="system_settings.php" class="module-link"><i class="fas fa-cog"></i> Configuración</a>
                <a href="statistics_dashboard.php" class="module-link"><i class="fas fa-chart-line"></i> Estadísticas</a>
                <a href="daily_report.php" class="module-link"><i class="fas fa-file-alt"></i> Reporte Diario</a>
            </div>

            <div style="margin-top:20px;">
                <a href="welcome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>