<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Obtener métricas simples
$kpis = [];
$sql = "SELECT 
    (SELECT COUNT(*) FROM users) as usuarios_totales,
    (SELECT COUNT(*) FROM pets) as pacientes_totales,
    (SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()) as consultas_hoy,
    (SELECT COUNT(*) FROM appointments WHERE status = 'PENDIENTE') as citas_pendientes";
$result = $conn->query($sql);
$kpis = $result->fetch_assoc();

$ultimos = $conn->query("SELECT p.name, pt.name as especie, p.created_at FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id ORDER BY p.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard de Métricas - VetCtrl</title>
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
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background: #f8f9fa; border-radius: 8px; padding: 20px; text-align:center; border-left:4px solid #40916c; }
        .stat-value { font-size:2rem; font-weight:bold; color:#1b4332; }
        .stat-label { color:#6c757d; margin-top:5px; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th { background:#40916c; color:white; padding:12px; text-align:left; }
        td { padding:10px; border-bottom:1px solid #ddd; }
        .btn { padding:10px 20px; border:none; border-radius:6px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#40916c; color:white; }
        .btn-primary:hover { background:#2d6a4f; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-secondary:hover { background:#5a6268; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Dashboard de Métricas</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-chart-bar"></i> Dashboard de Métricas</h1>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $kpis['usuarios_totales']; ?></div>
                    <div class="stat-label">Usuarios totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $kpis['pacientes_totales']; ?></div>
                    <div class="stat-label">Pacientes totales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $kpis['consultas_hoy']; ?></div>
                    <div class="stat-label">Consultas hoy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $kpis['citas_pendientes']; ?></div>
                    <div class="stat-label">Citas pendientes</div>
                </div>
            </div>

            <h3>Últimos pacientes registrados</h3>
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Especie</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                    <?php if ($ultimos): ?>
                        <?php foreach ($ultimos as $p): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo htmlspecialchars($p['especie'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3">No hay registros</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div style="margin-top:20px;">
                <a href="daily_report.php" class="btn btn-primary">Reporte Diario</a>
                <a href="statistics_dashboard.php" class="btn btn-secondary">Estadísticas avanzadas</a>
                <a href="welcome.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>