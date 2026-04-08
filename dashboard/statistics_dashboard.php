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

// Calcular rango según período
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

// Aquí irían las consultas reales para estadísticas (se mantienen los valores 0 como placeholder)
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8fafc;
        }
        body {
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1400px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 32px;
            padding: 28px 32px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .filter-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 30px;
            background: #f9fbfd;
            padding: 20px;
            border-radius: 24px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--primary-dark);
            margin-bottom: 6px;
        }
        .filter-group input, .filter-group select {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
            min-width: 150px;
        }
        .filter-group input:focus, .filter-group select:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn {
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #eef2f8;
            color: var(--primary-dark);
        }
        .btn-secondary:hover {
            background: #e2e8f0;
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
            padding: 24px;
            text-align: center;
            border: 1px solid #eef2f8;
            transition: all 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        .stat-label {
            color: #5b6e8c;
            margin-top: 8px;
            font-weight: 500;
        }
        .chart-container {
            margin: 30px 0;
            height: 350px;
        }
        .info-note {
            text-align: center;
            color: #6c757d;
            padding: 20px;
            background: #f9fbfd;
            border-radius: 24px;
            margin-top: 20px;
        }
        @media (max-width: 768px) {
            .card { padding: 20px; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
        }
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
                    <select name="period" id="periodSelect">
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
                <div class="filter-group" style="flex-direction: row; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Actualizar</button>
                    <a href="statistics_dashboard.php" class="btn btn-secondary"><i class="fas fa-undo-alt"></i> Reiniciar</a>
                </div>
            </form>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value">0</div><div class="stat-label"><i class="fas fa-stethoscope"></i> Consultas</div></div>
                <div class="stat-card"><div class="stat-value">0</div><div class="stat-label"><i class="fas fa-calendar-check"></i> Citas</div></div>
                <div class="stat-card"><div class="stat-value">0</div><div class="stat-label"><i class="fas fa-syringe"></i> Vacunas</div></div>
                <div class="stat-card"><div class="stat-value">0</div><div class="stat-label"><i class="fas fa-pills"></i> Tratamientos</div></div>
            </div>

            <div class="chart-container">
                <canvas id="myChart"></canvas>
            </div>

            <div class="info-note">
                <i class="fas fa-chart-simple"></i> Los datos estadísticos completos se implementarán en la siguiente fase.
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const periodSelect = document.getElementById('periodSelect');
            const customDates = document.getElementById('custom_dates');
            const customDates2 = document.getElementById('custom_dates2');
            periodSelect.addEventListener('change', function() {
                const isCustom = this.value === 'custom';
                customDates.style.display = isCustom ? 'block' : 'none';
                customDates2.style.display = isCustom ? 'block' : 'none';
            });
            
            var ctx = document.getElementById('myChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Consultas', 'Citas', 'Vacunas', 'Tratamientos'],
                    datasets: [{
                        label: 'Actividades',
                        data: [0,0,0,0],
                        backgroundColor: '#40916c',
                        borderRadius: 8,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: ${ctx.raw}` } }
                    }
                }
            });
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
