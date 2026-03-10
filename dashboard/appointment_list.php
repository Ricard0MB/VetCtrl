<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo roles permitidos (según matriz, admin y veterinario pueden ver listados; propietario también? En matriz, propietario ve sus propias citas)
// Ajustamos: propietario solo sus citas, admin y veterinario todas.
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

// Construir consulta según rol
if ($role_name === 'Propietario') {
    // Propietario: solo sus citas (attendant_id = user_id)
    $sql = "SELECT 
                a.id, 
                a.appointment_date, 
                a.reason, 
                a.status,
                p.name AS pet_name 
            FROM appointments a 
            JOIN pets p ON a.pet_id = p.id 
            WHERE a.attendant_id = ?
            ORDER BY a.appointment_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // Veterinario y admin: todas las citas (opcionalmente podrían filtrar por ellos mismos, pero según matriz ven todas)
    $sql = "SELECT 
                a.id, 
                a.appointment_date, 
                a.reason, 
                a.status,
                p.name AS pet_name,
                u.username AS owner_name
            FROM appointments a 
            JOIN pets p ON a.pet_id = p.id
            LEFT JOIN users u ON p.owner_id = u.id
            ORDER BY a.appointment_date DESC";
    $stmt = $conn->prepare($sql);
}

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $appointments[] = $row;
        }
        $result->free();
    } else {
        $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al ejecutar la consulta: " . htmlspecialchars($stmt->error) . "</div>";
    }
    $stmt->close();
} else {
    $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error de preparación: " . htmlspecialchars($conn->error) . "</div>";
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Citas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 1200px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1200px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .btn-pdf { background: #b68b40; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 600; margin-bottom: 20px; }
        .btn-pdf:hover { background: #a07632; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #40916c; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .status-tag { padding: 4px 8px; border-radius: 4px; font-weight: bold; display: inline-block; }
        .PENDIENTE { background: #ffc107; color: #333; }
        .COMPLETADA { background: #28a745; color: white; }
        .CANCELADA { background: #dc3545; color: white; }
        /* Estilo de botón azul (mismo que en pet profile) */
        .btn-action {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            margin: 2px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-action:hover {
            background-color: #2980b9;
            color: white;
        }
        .no-data { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Lista de Citas</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-calendar-check"></i> Lista de Citas</h1>

        <?php echo $success_message; ?>
        <?php echo $error_message; ?>

        <?php if (empty($appointments)): ?>
            <div class="no-data">
                <p>No hay citas registradas.</p>
                <?php if (in_array($role_name, ['Propietario', 'Veterinario', 'admin'])): ?>
                    <a href="appointment_schedule.php" class="btn-pdf">Agendar nueva cita</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Generar PDF</button>

            <table id="appointmentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha/Hora</th>
                        <th>Mascota</th>
                        <?php if ($role_name !== 'Propietario'): ?>
                            <th>Dueño</th>
                        <?php endif; ?>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $a): ?>
                    <tr>
                        <td><?php echo $a['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($a['appointment_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($a['pet_name']); ?></strong></td>
                        <?php if ($role_name !== 'Propietario'): ?>
                            <td><?php echo htmlspecialchars($a['owner_name'] ?? 'N/A'); ?></td>
                        <?php endif; ?>
                        <td><?php echo htmlspecialchars(substr($a['reason'], 0, 50)) . (strlen($a['reason']) > 50 ? '...' : ''); ?></td>
                        <td><span class="status-tag <?php echo $a['status']; ?>"><?php echo $a['status']; ?></span></td>
                        <td>
                            <?php if ($a['status'] === 'PENDIENTE'): ?>
                                <a href="appointment_edit.php?id=<?php echo $a['id']; ?>" class="btn-action">Editar</a>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                            <a href="appointment_receipt.php?id=<?php echo $a['id']; ?>" class="btn-action" target="_blank">Comprobante</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
            doc.autoTable({
                html: '#appointmentsTable',
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                styles: { fontSize: 8 }
            });
            doc.save('citas.pdf');
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>