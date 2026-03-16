<?php
// reports_dashboard.php - VERSIÓN CORREGIDA Y SIMPLIFICADA (CON PDO)
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
    <title>Reportes del Sistema</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        .container { padding: 20px; max-width: 1200px; margin: auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab { padding: 10px 20px; background: #f8f9fa; border-radius: 4px; text-decoration: none; color: #333; }
        .tab.active { background: #40916c; color: white; }
        .resumen-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0; }
        .resumen-card { background: white; padding: 20px; border-radius: 6px; text-align: center; }
        @media (max-width: 600px) {
            .resumen-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>📋 Reportes del Sistema</h1>
            <p>Visualiza la actividad del sistema</p>
        </div>
        
        <!-- Pestañas -->
        <div class="tabs">
            <a href="?section=daily" class="tab <?php echo $section == 'daily' ? 'active' : ''; ?>">
                📅 Reporte Diario
            </a>
            <a href="metrics_dashboard.php" class="tab">
                📊 Dashboard Avanzado
            </a>
        </div>
        
        <!-- Filtro de fecha -->
        <div style="background: white; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
            <form method="GET">
                <input type="hidden" name="section" value="daily">
                <label>Fecha:</label>
                <input type="date" name="fecha" value="<?php echo htmlspecialchars($fecha); ?>" 
                       style="padding: 8px; margin: 0 10px; border: 1px solid #ddd; border-radius: 4px;">
                <button type="submit" style="padding: 8px 15px; background: #40916c; color: white; border: none; border-radius: 4px;">
                    🔍 Generar Reporte
                </button>
            </form>
        </div>
        
        <!-- Resumen -->
        <div class="resumen-grid">
            <div class="resumen-card">
                <h3>🩺 Consultas</h3>
                <div style="font-size: 32px; font-weight: bold; color: #40916c;">
                    <?php echo $datos['resumen']['consultas'] ?? 0; ?>
                </div>
                <p>Realizadas el <?php echo date('d/m/Y', strtotime($fecha)); ?></p>
            </div>
            
            <div class="resumen-card">
                <h3>📅 Citas</h3>
                <div style="font-size: 32px; font-weight: bold; color: #40916c;">
                    <?php echo $datos['resumen']['citas'] ?? 0; ?>
                </div>
                <p>Programadas</p>
            </div>
            
            <div class="resumen-card">
                <h3>🐾 Pacientes</h3>
                <div style="font-size: 32px; font-weight: bold; color: #40916c;">
                    <?php echo $datos['resumen']['pacientes'] ?? 0; ?>
                </div>
                <p>Atendidos</p>
            </div>
        </div>
        
        <!-- Mensaje si no hay datos -->
        <?php if (($datos['resumen']['consultas'] ?? 0) == 0): ?>
            <div style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 6px; margin: 20px 0;">
                <p style="color: #666; font-size: 18px;">
                    📝 No hay actividad registrada para esta fecha
                </p>
            </div>
        <?php endif; ?>
        
        <!-- Enlaces -->
        <div style="margin-top: 30px; text-align: center;">
            <a href="welcome.php" style="padding: 10px 20px; background: #6c757d; color: white; border-radius: 4px; text-decoration: none;">
                ← Volver al Dashboard
            </a>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
<?php
// No es necesario cerrar la conexión explícitamente con PDO
?>
