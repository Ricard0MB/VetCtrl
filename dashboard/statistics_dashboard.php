<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

$role_name = $_SESSION['role_name'] ?? '';
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

date_default_timezone_set('America/Caracas');

// Parámetros
$period = $_GET['period'] ?? 'month';
$start_date_input = $_GET['start_date'] ?? '';
$end_date_input = $_GET['end_date'] ?? '';

// Calcular rango según período (función similar a la original, simplificada)
function calculateDateRange($period, $custom_start, $custom_end) {
    $today = new DateTime();
    $start = clone $today; $end = clone $today;
    switch ($period) {
        case 'today': $start->setTime(0,0,0); $end->setTime(23,59,59); break;
        case 'yesterday': $start->modify('-1 day')->setTime(0,0,0); $end->modify('-1 day')->setTime(23,59,59); break;
        case 'week': $start->modify('monday this week')->setTime(0,0,0); $end->modify('sunday this week')->setTime(23,59,59); break;
        case 'month': $start->modify('first day of this month')->setTime(0,0,0); $end->modify('last day of this month')->setTime(23,59,59); break;
        case 'year': $start->setDate($start->format('Y'),1,1)->setTime(0,0,0); $end->setDate($end->format('Y'),12,31)->setTime(23,59,59); break;
        case 'custom':
            if ($custom_start && $custom_end) {
                $start = new DateTime($custom_start); $end = new DateTime($custom_end);
                $start->setTime(0,0,0); $end->setTime(23,59,59);
            } break;
    }
    return ['start'=>$start->format('Y-m-d H:i:s'), 'end'=>$end->format('Y-m-d H:i:s'), 'start_date'=>$start->format('Y-m-d'), 'end_date'=>$end->format('Y-m-d')];
}
$range = calculateDateRange($period, $start_date_input, $end_date_input);
$start_date = $range['start'];
$end_date = $range['end'];

// Aquí irían todas las consultas para estadísticas (por brevedad omito, pero se deben incluir las del original)
// Nota: cuando se implementen, deben usarse métodos PDO (prepare, execute, fetch, etc.)

// No es necesario cerrar la conexión explícitamente con PDO
// $conn = null; // Opcional
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 1400px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1400px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight:600; margin-bottom:5px; color:#1b4332; }
        .filter-group input, .filter-group select { padding:8px; border:1px solid #ccc; border-radius:4px; min-width:150px; }
        .btn { padding:8px 15px; border:none; border-radius:4px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#40916c; color:white; }
        .btn-primary:hover { background:#2d6a4f; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-secondary:hover { background:#5a6268; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(250px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background: #f8f9fa; padding:20px; border-radius:8px; text-align:center; border-left:4px solid #40916c; }
        .stat-value { font-size:2rem; font-weight:bold; color:#1b4332; }
        .chart-container { height:300px; margin:20px 0; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Dashboard de Estadísticas</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-chart-line"></i> Dashboard de Estadísticas</h1>

            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label>Período</label>
                    <select name="period">
                        <option value="today" <?php echo $period=='today'?'selected':''; ?>>Hoy</option>
                        <option value="yesterday" <?php echo $period=='yesterday'?'selected':''; ?>>Ayer</option>
                        <option value="week" <?php echo $period=='week'?'selected':''; ?>>Esta semana</option>
                        <option value="month" <?php echo $period=='month'?'selected':''; ?>>Este mes</option>
                        <option value="year" <?php echo $period=='year'?'selected':''; ?>>Este año</option>
                        <option value="custom" <?php echo $period=='custom'?'selected':''; ?>>Personalizado</option>
                    </select>
                </div>
                <div class="filter-group" id="custom_dates" style="<?php echo $period!='custom'?'display:none;':''; ?>">
                    <label>Desde</label>
                    <input type="date" name="start_date" value="<?php echo $range['start_date']; ?>">
                </div>
                <div class="filter-group" id="custom_dates2" style="<?php echo $period!='custom'?'display:none;':''; ?>">
                    <label>Hasta</label>
                    <input type="date" name="end_date" value="<?php echo $range['end_date']; ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="statistics_dashboard.php" class="btn btn-secondary">Reiniciar</a>
                </div>
            </form>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Consultas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Citas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Vacunas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Tratamientos</div>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="myChart"></canvas>
            </div>

            <p style="color:#6c757d;">Los datos estadísticos completos se implementarán en la siguiente fase.</p>
        </div>
    </div>

    <script>
        // Aquí irían los gráficos con Chart.js (usando datos PHP)
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('myChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Consultas', 'Citas', 'Vacunas', 'Tratamientos'],
                    datasets: [{
                        label: 'Actividades',
                        data: [0,0,0,0],
                        backgroundColor: '#40916c'
                    }]
                }
            });
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
