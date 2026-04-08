<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Obtener métricas simples con PDO
$kpis = [];
$ultimos = [];

try {
    $sql = "SELECT 
        (SELECT COUNT(*) FROM users) as usuarios_totales,
        (SELECT COUNT(*) FROM pets) as pacientes_totales,
        (SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()) as consultas_hoy,
        (SELECT COUNT(*) FROM appointments WHERE status = 'PENDIENTE') as citas_pendientes";
    $stmt = $conn->query($sql);
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $sql2 = "SELECT p.name, pt.name as especie, p.created_at 
             FROM pets p 
             LEFT JOIN pet_types pt ON p.type_id = pt.id 
             ORDER BY p.created_at DESC 
             LIMIT 5";
    $stmt2 = $conn->query($sql2);
    $ultimos = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $kpis = ['usuarios_totales' => 0, 'pacientes_totales' => 0, 'consultas_hoy' => 0, 'citas_pendientes' => 0];
    $ultimos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Métricas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8fafc;
        }
        body {
            background-color: var(--gray-bg);
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1200px;
            margin: 10px auto 0;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 28px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: linear-gradient(145deg, #ffffff 0%, #f9fbfd 100%);
            border-radius: 28px;
            padding: 24px 20px;
            text-align: center;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02), 0 1px 2px rgba(0,0,0,0.03);
            border: 1px solid #eef2f8;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            line-height: 1.2;
        }
        .stat-label {
            color: #5b6e8c;
            margin-top: 12px;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        .stat-icon {
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 12px;
        }
        h3 {
            font-size: 1.3rem;
            color: var(--primary-dark);
            margin: 20px 0 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
        }
        th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
        }
        .btn {
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-secondary {
            background: #eef2f8;
            color: var(--primary-dark);
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .actions-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        @media (max-width: 640px) {
            .stats-grid { gap: 16px; }
            .card { padding: 20px; }
        }
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
            <h1><i class="fas fa-chart-line"></i> Dashboard de Métricas</h1>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo $kpis['usuarios_totales'] ?? 0; ?></div><div class="stat-label">Usuarios totales</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-paw"></i></div><div class="stat-value"><?php echo $kpis['pacientes_totales'] ?? 0; ?></div><div class="stat-label">Pacientes totales</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-stethoscope"></i></div><div class="stat-value"><?php echo $kpis['consultas_hoy'] ?? 0; ?></div><div class="stat-label">Consultas hoy</div></div>
                <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div class="stat-value"><?php echo $kpis['citas_pendientes'] ?? 0; ?></div><div class="stat-label">Citas pendientes</div></div>
            </div>

            <h3><i class="fas fa-dog"></i> Últimos pacientes registrados</h3>
            <table>
                <thead><tr><th>Nombre</th><th>Especie</th><th>Fecha</th></tr></thead>
                <tbody>
                    <?php if (!empty($ultimos)): ?>
                        <?php foreach ($ultimos as $p): ?>
                        <tr><td><?php echo htmlspecialchars($p['name']); ?></td><td><?php echo htmlspecialchars($p['especie'] ?? 'N/A'); ?></td><td><?php echo date('d/m/Y', strtotime($p['created_at'])); ?></td></tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="no-data">No hay registros</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="actions-group">
                <a href="daily_report.php" class="btn btn-primary"><i class="fas fa-file-alt"></i> Reporte Diario</a>
                <a href="statistics_dashboard.php" class="btn btn-secondary"><i class="fas fa-chart-pie"></i> Estadísticas avanzadas</a>
                <a href="welcome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
