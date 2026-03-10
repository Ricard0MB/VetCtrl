<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? 'Propietario';
$user_id = $_SESSION['user_id'] ?? 0;

$appointment_id = intval($_GET['id'] ?? 0);
if ($appointment_id <= 0) {
    header("Location: appointment_list.php");
    exit;
}

// Obtener datos de la cita
$sql = "SELECT 
            a.*,
            p.id as pet_id, p.name as pet_name, p.date_of_birth,
            pt.name as pet_type,
            b.name as breed_name,
            u_owner.id as owner_id, u_owner.username as owner_name, u_owner.email as owner_email, u_owner.phone as owner_phone,
            u_vet.username as vet_name, u_vet.email as vet_email
        FROM appointments a
        LEFT JOIN pets p ON a.pet_id = p.id
        LEFT JOIN pet_types pt ON p.type_id = pt.id
        LEFT JOIN breeds b ON p.breed_id = b.id
        LEFT JOIN users u_owner ON p.owner_id = u_owner.id
        LEFT JOIN users u_vet ON a.attendant_id = u_vet.id
        WHERE a.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    header("Location: appointment_list.php?error=notfound");
    exit;
}

// Verificar permisos: propietario solo ve sus citas
if ($role_name === 'Propietario' && $appointment['attendant_id'] != $user_id) {
    header("Location: appointment_list.php?error=unauthorized");
    exit;
}

// Registrar en bitácora si se imprime
if (isset($_GET['action']) && $_GET['action'] === 'print') {
    require_once '../includes/bitacora_function.php';
    $action = "Comprobante de cita #$appointment_id impreso";
    log_to_bitacora($conn, $action, $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Cita - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 1000px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1000px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .receipt-header { background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%); color: white; padding: 30px; text-align: center; }
        .receipt-body { padding: 30px; }
        .section { margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px dashed #ccc; }
        .section-title { color: #1b4332; font-size: 1.5rem; font-weight: bold; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px,1fr)); gap: 15px; }
        .info-item { margin-bottom: 12px; }
        .info-label { font-weight: bold; color: #555; margin-bottom: 4px; }
        .info-value { color: #222; padding: 8px; background: #f9f9f9; border-radius: 5px; border-left: 4px solid #40916c; }
        .status { display: inline-block; padding: 5px 15px; border-radius: 20px; font-weight: bold; }
        .status-PENDIENTE { background: #fff3cd; color: #856404; }
        .status-COMPLETADA { background: #d1e7dd; color: #0f5132; }
        .status-CANCELADA { background: #f8d7da; color: #842029; }
        .receipt-footer { background: #f1f1f1; padding: 20px; text-align: center; border-top: 2px solid #ccc; }
        .btn { display: inline-block; padding: 12px 25px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; margin: 5px; }
        .btn-primary { background: #40916c; color: white; }
        .btn-primary:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        @media print { .btn, .breadcrumb, .navbar, footer { display: none !important; } body { padding-top: 0; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb no-print">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="appointment_list.php">Citas</a> <span>›</span>
        <span>Comprobante</span>
    </div>

    <div class="container">
        <div class="receipt-header">
            <h1>COMPROBANTE DE CITA</h1>
            <p>Clínica Veterinaria VetCtrl</p>
        </div>

        <div class="receipt-body">
            <div class="section">
                <h2 class="section-title"><i class="fas fa-calendar-check"></i> Información de la Cita</h2>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Número:</span> <span class="info-value">VC-<?php echo str_pad($appointment['id'], 6, '0', STR_PAD_LEFT); ?></span></div>
                    <div class="info-item"><span class="info-label">Fecha:</span> <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($appointment['appointment_date'])); ?></span></div>
                    <div class="info-item"><span class="info-label">Estado:</span> <span class="info-value"><span class="status status-<?php echo $appointment['status']; ?>"><?php echo $appointment['status']; ?></span></span></div>
                    <div class="info-item"><span class="info-label">Motivo:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['reason']); ?></span></div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-paw"></i> Paciente</h2>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Nombre:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['pet_name']); ?></span></div>
                    <div class="info-item"><span class="info-label">Especie/Raza:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['pet_type'] ?? 'N/A'); ?> <?php if ($appointment['breed_name']) echo '/ ' . htmlspecialchars($appointment['breed_name']); ?></span></div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-user"></i> Dueño</h2>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">Nombre:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['owner_name']); ?></span></div>
                    <div class="info-item"><span class="info-label">Email:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['owner_email']); ?></span></div>
                    <div class="info-item"><span class="info-label">Teléfono:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['owner_phone'] ?? 'N/A'); ?></span></div>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title"><i class="fas fa-user-md"></i> Veterinario</h2>
                <div class="info-item"><span class="info-label">Nombre:</span> <span class="info-value"><?php echo htmlspecialchars($appointment['vet_name'] ?? 'Por asignar'); ?></span></div>
            </div>
        </div>

        <div class="receipt-footer">
            <p>Comprobante generado el <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>

        <div class="btn-container no-print" style="padding:20px; text-align:center;">
            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
            <a href="appointment_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>