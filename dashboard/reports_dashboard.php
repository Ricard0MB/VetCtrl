<?php
// reports_dashboard.php - VERSIÓN CORREGIDA Y SIMPLIFICADA (CON PDO) + CSS PROFESIONAL
session_start();

// Verificar sesión
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

// Verificar permisos
$user_role = $_SESSION['role_name'] ?? '';
if (!in_array($user_role, ['Veterinario', 'admin'])) {
    header("Location: welcome.php");
    exit;
}

// Conectar a BD mediante PDO
require_once '../includes/config.php'; // $conn debe ser un objeto PDO

// Determinar sección
$section = $_GET['section'] ?? 'daily';
$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Obtener reporte diario simple con PDO
function obtenerReporteDiarioSimple($conn, $fecha) {
    $reporte = [];
    
    // Resumen del día
    $sql = "SELECT 
        (SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = :fecha1) as consultas,
        (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = :fecha2) as citas,
        (SELECT COUNT(DISTINCT pet_id) FROM consultations WHERE DATE(consultation_date) = :fecha3) as pacientes";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':fecha1', $fecha);
    $stmt->bindValue(':fecha2', $fecha);
    $stmt->bindValue(':fecha3', $fecha);
    $stmt->execute();
    $reporte['resumen'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    return $reporte;
}

$datos = obtenerReporteDiarioSimple($conn, $fecha);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes del Sistema - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8fafc;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }
        body {
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1200px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 32px;
            padding: 28px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid #eef2f8;
            margin-bottom: 25px;
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .tab {
            padding: 10px 24px;
            background: white;
            border-radius: 40px;
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 600;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }
        .tab:hover {
            background: var(--primary-light);
            color: white;
            border-color: var(--primary-light);
            transform: translateY(-2px);
        }
        .tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        .filter-box {
            background: #f9fbfd;
            padding: 20px;
            border-radius: 24px;
            margin-bottom: 25px;
            border: 1px solid #eef2f8;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 15px;
        }
        .filter-form label {
            font-weight: 600;
            color: var(--primary-dark);
            margin-right: 8px;
        }
        .filter-form input {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
        }
        .filter-form input:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn {
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
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
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin: 30px 0;
        }
        .resumen-card {
            background: linear-gradient(145deg, #ffffff 0%, #f9fbfd 100%);
            border-radius: 28px;
            padding: 24px;
            text-align: center;
            transition: all 0.2s;
            border: 1px solid #eef2f8;
        }
        .resumen-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }
        .resumen-card h3 {
            font-size: 1.2rem;
            margin-bottom: 12px;
            color: var(--primary-dark);
        }
        .resumen-card .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            background: #f9fbfd;
            border-radius: 28px;
            color: #5b6e8c;
            margin: 20px 0;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            background: #eef2f8;
            color: var(--primary-dark);
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .back-link:hover {
            background: #e2e8f0;
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .card { padding: 20px; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .resumen-grid { gap: 16px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Reportes del Sistema</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-chart-bar"></i> Reportes del Sistema</h1>
            <p style="color: #5b6e8c; margin-bottom: 20px;">Visualiza la actividad del sistema de forma rápida.</p>
            
            <!-- Pestañas -->
            <div class="tabs">
                <a href="?section=daily" class="tab <?php echo $section == 'daily' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-day"></i> Reporte Diario
                </a>
                <a href="metrics_dashboard.php" class="tab">
                    <i class="fas fa-chart-line"></i> Dashboard Avanzado
                </a>
            </div>
            
            <!-- Filtro de fecha -->
            <div class="filter-box">
                <form method="GET" class="filter-form">
                    <input type="hidden" name="section" value="daily">
                    <div>
                        <label><i class="fas fa-calendar-alt"></i> Fecha:</label>
                        <input type="date" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> Generar Reporte</button>
                </form>
            </div>
            
            <!-- Resumen -->
            <div class="resumen-grid">
                <div class="resumen-card">
                    <h3><i class="fas fa-stethoscope"></i> Consultas</h3>
                    <div class="stat-number"><?php echo $datos['resumen']['consultas'] ?? 0; ?></div>
                    <p>Realizadas el <?php echo date('d/m/Y', strtotime($fecha)); ?></p>
                </div>
                <div class="resumen-card">
                    <h3><i class="fas fa-calendar-check"></i> Citas</h3>
                    <div class="stat-number"><?php echo $datos['resumen']['citas'] ?? 0; ?></div>
                    <p>Programadas</p>
                </div>
                <div class="resumen-card">
                    <h3><i class="fas fa-paw"></i> Pacientes</h3>
                    <div class="stat-number"><?php echo $datos['resumen']['pacientes'] ?? 0; ?></div>
                    <p>Atendidos</p>
                </div>
            </div>
            
            <!-- Mensaje si no hay datos -->
            <?php if (($datos['resumen']['consultas'] ?? 0) == 0): ?>
                <div class="no-data">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px; display: block;"></i>
                    <p>No hay actividad registrada para esta fecha.</p>
                </div>
            <?php endif; ?>
            
            <!-- Enlace volver -->
            <div style="margin-top: 25px; text-align: center;">
                <a href="welcome.php" class="back-link"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
