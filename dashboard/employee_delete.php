<?php
session_start();
require_once '../includes/config.php'; // $conn debe ser un objeto PDO

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

// Funciones de consulta usando PDO
function getConsultations($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM consultations WHERE consultation_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getAppointments($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getVaccines($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM vaccines WHERE vaccine_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getTreatments($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM treatments WHERE treatment_date BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getNewPets($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM pets WHERE created_at BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return $stmt->fetchColumn();
}

function getNewUsers($conn, $start, $end) {
    $sql = "SELECT COUNT(*) FROM users WHERE created_at BETWEEN :start AND :end";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':start', $start);
    $stmt->bindValue(':end', $end);
    $stmt->execute();
    return $stmt->fetchColumn();
}

// Obtener datos
$daily_stats = [
    'consultations' => getConsultations($conn, $start_datetime, $end_datetime),
    'appointments'  => getAppointments($conn, $start_datetime, $end_datetime),
    'vaccines'      => getVaccines($conn, $start_datetime, $end_datetime),
    'treatments'    => getTreatments($conn, $start_datetime, $end_datetime),
    'new_pets'      => getNewPets($conn, $start_datetime, $end_datetime),
    'new_users'     => getNewUsers($conn, $start_datetime, $end_datetime)
];

// No es necesario cerrar la conexión explícitamente
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Diario - VetCtrl</title>
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
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight:600; margin-bottom:5px; color:#1b4332; }
        .filter-group input, .filter-group select { padding:8px; border:1px solid #ccc; border-radius:4px; min-width:150px; }
        .btn { padding:10px 20px; border:none; border-radius:6px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#40916c; color:white; }
        .btn-primary:hover { background:#2d6a4f; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-secondary:hover { background:#5a6268; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap:20px; margin-bottom:30px; }
        .stat-card { background:#f8f9fa; padding:20px; border-radius:8px; text-align:center; border-left:4px solid #40916c; }
        .stat-value { font-size:2rem; font-weight:bold; color:#1b4332; }
        .stat-label { color:#6c757d; margin-top:5px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Reporte Diario</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-file-alt"></i> Reporte de Actividad Diaria</h1>

            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label>Fecha inicio</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group">
                    <label>Fecha fin</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-group">
                    <label>Tipo</label>
                    <select name="report_type">
                        <option value="daily" <?php echo $report_type=='daily'?'selected':''; ?>>Diario</option>
                        <option value="range" <?php echo $report_type=='range'?'selected':''; ?>>Rango</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Generar</button>
                </div>
            </form>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['consultations']; ?></div><div class="stat-label">Consultas</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['appointments']; ?></div><div class="stat-label">Citas</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['vaccines']; ?></div><div class="stat-label">Vacunas</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['treatments']; ?></div><div class="stat-label">Tratamientos</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['new_pets']; ?></div><div class="stat-label">Nuevas mascotas</div></div>
                <div class="stat-card"><div class="stat-value"><?php echo $daily_stats['new_users']; ?></div><div class="stat-label">Nuevos usuarios</div></div>
            </div>

            <a href="welcome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
