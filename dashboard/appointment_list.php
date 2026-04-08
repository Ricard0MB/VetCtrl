<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$appointments = [];
$error_message = '';
$success_message = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled') {
    $success_message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Cita cancelada exitosamente.</div>";
}

try {
    if ($role_name === 'Propietario') {
        $sql = "SELECT 
                    a.id, 
                    a.appointment_date, 
                    a.reason, 
                    a.status,
                    p.name AS pet_name,
                    u.username AS vet_name
                FROM appointments a 
                JOIN pets p ON a.pet_id = p.id 
                JOIN users u ON a.attendant_id = u.id
                WHERE p.owner_id = :user_id
                ORDER BY a.appointment_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    } else {
        $sql = "SELECT 
                    a.id, 
                    a.appointment_date, 
                    a.reason, 
                    a.status,
                    p.name AS pet_name,
                    u.username AS owner_name,
                    u2.username AS vet_name
                FROM appointments a 
                JOIN pets p ON a.pet_id = p.id
                LEFT JOIN users u ON p.owner_id = u.id
                LEFT JOIN users u2 ON a.attendant_id = u2.id
                ORDER BY a.appointment_date DESC";
        $stmt = $conn->prepare($sql);
    }
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al obtener las citas: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Citas - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
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
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1.2rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .btn-pdf { background: var(--vet-primary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin-bottom: 1rem; transition: 0.2s; }
        .btn-pdf:hover { background: var(--vet-dark); }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8faf8; padding: 0.9rem 0.8rem; text-align: left; color: var(--vet-dark); font-weight: 600; border-bottom: 2px solid #dee6de; }
        td { padding: 0.8rem; border-bottom: 1px solid #eef2ee; vertical-align: middle; }
        tr:hover td { background: #fafdfa; }
        .status-tag { padding: 0.2rem 0.7rem; border-radius: 20px; font-weight: 600; font-size: 0.75rem; display: inline-block; }
        .PENDIENTE { background: #fff3cd; color: #856404; }
        .COMPLETADA { background: #d1e7dd; color: #0f5132; }
        .CANCELADA { background: #f8d7da; color: #842029; }
        .btn-action { background: var(--vet-primary); color: white; padding: 0.3rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.75rem; font-weight: 500; transition: 0.2s; display: inline-block; margin: 0.2rem; }
        .btn-action:hover { background: var(--vet-dark); }
        .no-data { text-align: center; padding: 2rem; color: var(--vet-text-light); }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } table { font-size: 0.75rem; } .btn-action { padding: 0.2rem 0.5rem; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span> <span>Lista de Citas</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-calendar-check"></i> Lista de Citas</h1>
        <?php echo $success_message; echo $error_message; ?>

        <?php if (empty($appointments)): ?>
            <div class="no-data"><p>No hay citas registradas.</p><?php if (in_array($role_name, ['Propietario', 'Veterinario', 'admin'])): ?><a href="appointment_schedule.php" class="btn-pdf">Agendar nueva cita</a><?php endif; ?></div>
        <?php else: ?>
            <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Generar PDF</button>
            <div style="overflow-x: auto;">
                <table id="appointmentsTable">
                    <thead><tr><th>ID</th><th>Fecha/Hora</th><th>Mascota</th><?php if ($role_name !== 'Propietario'): ?><th>Dueño</th><?php endif; ?><th>Veterinario</th><th>Motivo</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($appointments as $a): ?>
                        <tr>
                            <td><?php echo $a['id']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($a['appointment_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($a['pet_name']); ?></strong></td>
                            <?php if ($role_name !== 'Propietario'): ?><td><?php echo htmlspecialchars($a['owner_name'] ?? 'N/A'); ?></td><?php endif; ?>
                            <td><?php echo htmlspecialchars($a['vet_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(substr($a['reason'], 0, 50)) . (strlen($a['reason']) > 50 ? '...' : ''); ?></td>
                            <td><span class="status-tag <?php echo $a['status']; ?>"><?php echo $a['status']; ?></span></td>
                            <td>
                                <?php if ($a['status'] === 'PENDIENTE' && in_array($role_name, ['Veterinario', 'admin'])): ?>
                                    <a href="appointment_edit.php?id=<?php echo $a['id']; ?>" class="btn-action">Editar</a>
                                <?php endif; ?>
                                <a href="appointment_receipt.php?id=<?php echo $a['id']; ?>" class="btn-action" target="_blank">Comprobante</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('btnExportPdf')?.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            doc.setFontSize(18);
            doc.text('Lista de Citas', 148, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.text('Generado el: ' + new Date().toLocaleDateString('es-ES'), 148, 28, { align: 'center' });
            doc.autoTable({ html: '#appointmentsTable', startY: 35, theme: 'grid', headStyles: { fillColor: [27, 67, 50], textColor: 255 }, styles: { fontSize: 8 } });
            doc.save('citas.pdf');
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
