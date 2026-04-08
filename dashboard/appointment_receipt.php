<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? 'Propietario';
$user_id = $_SESSION['user_id'] ?? 0;

$appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$appointment_id || $appointment_id <= 0) {
    header("Location: appointment_list.php");
    exit;
}

try {
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
            WHERE a.id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $appointment_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment || ($role_name === 'Propietario' && $appointment['attendant_id'] != $user_id)) {
        header("Location: appointment_list.php?error=unauthorized");
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Cita - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7f9;
            padding-top: 72px;
        }
        :root { --vet-dark: #1b4332; --vet-primary: #40916c; --shadow-md: 0 8px 20px rgba(0,0,0,0.05); --radius-lg: 16px; }
        .breadcrumb { max-width: 1000px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .container { max-width: 1000px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-md); }
        .receipt-header { background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%); color: white; padding: 1.5rem; text-align: center; }
        .receipt-body { padding: 2rem; }
        .section { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px dashed #ccc; }
        .section-title { color: var(--vet-dark); font-size: 1.2rem; font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px,1fr)); gap: 1rem; }
        .info-item { margin-bottom: 0.8rem; }
        .info-label { font-weight: 600; color: var(--vet-dark); margin-bottom: 0.2rem; font-size: 0.8rem; }
        .info-value { background: #f8faf8; padding: 0.5rem; border-radius: 8px; border-left: 3px solid var(--vet-primary); }
        .status { display: inline-block; padding: 0.2rem 0.8rem; border-radius: 20px; font-weight: 600; font-size: 0.75rem; }
        .status-PENDIENTE { background: #fff3cd; color: #856404; }
        .receipt-footer { background: #f8faf8; padding: 1rem; text-align: center; font-size: 0.75rem; color: #6c757d; }
        .btn-container { padding: 1rem; text-align: center; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; margin: 0.2rem; transition: 0.2s; }
        .btn-primary { background: var(--vet-primary); color: white; }
        .btn-primary:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; color: white; }
        @media print { .breadcrumb, .btn-container, .navbar, footer { display: none !important; } body { padding-top: 0; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb no-print"><a href="welcome.php">Inicio</a> <span>›</span> <a href="appointment_list.php">Citas</a> <span>›</span> <span>Comprobante</span></div>
    <div class="container">
        <div class="receipt-header"><h1>COMPROBANTE DE CITA</h1><p>Clínica Veterinaria VetCtrl</p></div>
        <div class="receipt-body">
            <div class="section"><div class="section-title"><i class="fas fa-calendar-check"></i> Información de la Cita</div><div class="info-grid"><div class="info-item"><div class="info-label">Número:</div><div class="info-value">VC-<?php echo str_pad($appointment['id'], 6, '0', STR_PAD_LEFT); ?></div></div><div class="info-item"><div class="info-label">Fecha:</div><div class="info-value"><?php echo date('d/m/Y H:i', strtotime($appointment['appointment_date'])); ?></div></div><div class="info-item"><div class="info-label">Estado:</div><div class="info-value"><span class="status status-<?php echo $appointment['status']; ?>"><?php echo $appointment['status']; ?></span></div></div><div class="info-item"><div class="info-label">Motivo:</div><div class="info-value"><?php echo htmlspecialchars($appointment['reason']); ?></div></div></div></div>
            <div class="section"><div class="section-title"><i class="fas fa-paw"></i> Paciente</div><div class="info-grid"><div class="info-item"><div class="info-label">Nombre:</div><div class="info-value"><?php echo htmlspecialchars($appointment['pet_name']); ?></div></div><div class="info-item"><div class="info-label">Especie/Raza:</div><div class="info-value"><?php echo htmlspecialchars($appointment['pet_type'] ?? 'N/A'); ?> <?php if ($appointment['breed_name']) echo '/ ' . htmlspecialchars($appointment['breed_name']); ?></div></div></div></div>
            <div class="section"><div class="section-title"><i class="fas fa-user"></i> Dueño</div><div class="info-grid"><div class="info-item"><div class="info-label">Nombre:</div><div class="info-value"><?php echo htmlspecialchars($appointment['owner_name']); ?></div></div><div class="info-item"><div class="info-label">Email:</div><div class="info-value"><?php echo htmlspecialchars($appointment['owner_email']); ?></div></div><div class="info-item"><div class="info-label">Teléfono:</div><div class="info-value"><?php echo htmlspecialchars($appointment['owner_phone'] ?? 'N/A'); ?></div></div></div></div>
            <div class="section"><div class="section-title"><i class="fas fa-user-md"></i> Veterinario</div><div class="info-item"><div class="info-label">Nombre:</div><div class="info-value"><?php echo htmlspecialchars($appointment['vet_name'] ?? 'Por asignar'); ?></div></div></div>
        </div>
        <div class="receipt-footer"><p>Comprobante generado el <?php echo date('d/m/Y H:i:s'); ?></p></div>
        <div class="btn-container no-print"><button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button><a href="appointment_list.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a></div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
