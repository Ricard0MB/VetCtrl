<?php
session_start();
require_once '../includes/config.php';

$role_name = $_SESSION['role_name'] ?? '';
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

date_default_timezone_set('America/Caracas');

$default_date = date('Y-m-d');
$start_date = $_GET['start_date'] ?? $default_date;
$end_date = $_GET['end_date'] ?? $default_date;
$report_type = $_GET['report_type'] ?? 'daily';

if ($start_date > $end_date) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

$start_datetime = $start_date . ' 00:00:00';
$end_datetime = $end_date . ' 23:59:59';

// Funciones corregidas
function getConsultations($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM consultations WHERE consultation_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getAppointments($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getVaccines($conn, $start, $end) {
    // Columna corregida: application_date
    $sql = "SELECT COUNT(*) FROM vaccines WHERE application_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getTreatments($conn, $start, $end) {
    // Columna corregida: created_at
    $sql = "SELECT COUNT(*) FROM treatments WHERE created_at BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getNewPets($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM pets WHERE created_at BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function getNewUsers($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM users WHERE created_at BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

$daily_stats = [
    'consultations' => getConsultations($conn, $start_datetime, $end_datetime),
    'appointments'  => getAppointments($conn, $start_datetime, $end_datetime),
    'vaccines'      => getVaccines($conn, $start_datetime, $end_datetime),
    'treatments'    => getTreatments($conn, $start_datetime, $end_datetime),
    'new_pets'      => getNewPets($conn, $start_datetime, $end_datetime),
    'new_users'     => getNewUsers($conn, $start_datetime, $end_datetime)
];

// Preparar datos para gráficas
$chart_labels = ['Consultas', 'Citas', 'Vacunas', 'Tratamientos', 'Nuevas Mascotas', 'Nuevos Usuarios'];
$chart_values = array_values($daily_stats);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Actividad - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .breadcrumb { max-width: 1200px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1200px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 1.8rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .filter-form { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem; align-items: flex-end; background: #f8faf8; padding: 1rem; border-radius: 12px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--vet-dark); margin-bottom: 0.2rem; }
        .filter-group input, .filter-group select { padding: 0.5rem; border: 1px solid #d0d8d0; border-radius: 8px; font-family: inherit; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: 0.2s; background: var(--vet-primary); color: white; }
        .btn:hover { background: var(--vet-dark); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: #f8faf8; padding: 1rem; border-radius: 12px; text-align: center; border-left: 4px solid var(--vet-primary); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--vet-dark); }
        .stat-label { color: #6c757d; font-size: 0.85rem; }
        .chart-container { max-width: 800px; margin: 2rem auto; }
        canvas { max-height: 400px; width: 100%; }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; margin-top: 1rem; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } .stats-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb"><a href="welcome.php">Inicio</a> <span>›</span> <span>Reporte de Actividad</span></div>
    <div class="container">
        <h1><i class="fas fa-chart-line"></i> Reporte de Actividad</h1>
        <form method="get" class="filter-form">
            <div class="filter-group"><label>Fecha inicio</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"></div>
            <div class="filter-group"><label>Fecha fin</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"></div>
            <div class="filter-group"><label>Tipo</label><select name="report_type"><option value="daily" <?php echo $report_type=='daily'?'selected':''; ?>>Diario</option><option value="range" <?php echo $report_type=='range'?'selected':''; ?>>Rango</option></select></div>
            <div class="filter-group"><button type="submit" class="btn"><i class="fas fa-sync-alt"></i> Generar</button></div>
        </form>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['consultations']; ?></div><div class="stat-label">Consultas</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['appointments']; ?></div><div class="stat-label">Citas</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['vaccines']; ?></div><div class="stat-label">Vacunas</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['treatments']; ?></div><div class="stat-label">Tratamientos</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['new_pets']; ?></div><div class="stat-label">Nuevas mascotas</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['new_users']; ?></div><div class="stat-label">Nuevos usuarios</div></div>
        </div>
        <div class="chart-container"><canvas id="activityChart"></canvas></div>
        <a href="welcome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    </div>
    <script>
        const ctx = document.getElementById('activityChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: { labels: <?php echo json_encode($chart_labels); ?>, datasets: [{ label: 'Cantidad', data: <?php echo json_encode($chart_values); ?>, backgroundColor: '#40916c', borderRadius: 8 }] },
            options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, grid: { color: '#e2e8e2' } }, x: { grid: { display: false } } }, plugins: { legend: { position: 'top' } } }
        });
    </script>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
