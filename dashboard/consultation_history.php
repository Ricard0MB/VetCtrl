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

// Solo Veterinario y Admin pueden acceder
if ($role_name === 'admin') {
    $sql = "SELECT c.*, p.name AS pet_name, pt.name AS species_name, u.username AS vet_name 
            FROM consultations c
            JOIN pets p ON c.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            LEFT JOIN users u ON c.attendant_id = u.id
            ORDER BY c.consultation_date DESC";
    $stmt = $conn->prepare($sql);
} elseif ($role_name === 'Veterinario') {
    $sql = "SELECT c.*, p.name AS pet_name, pt.name AS species_name 
            FROM consultations c
            JOIN pets p ON c.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            WHERE c.attendant_id = ?
            ORDER BY c.consultation_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    // propietario no debería acceder a este listado según matriz, pero si accede, solo sus mascotas
    $sql = "SELECT c.*, p.name AS pet_name, pt.name AS species_name 
            FROM consultations c
            JOIN pets p ON c.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            WHERE p.owner_id = ?
            ORDER BY c.consultation_date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
}

$consultations = [];
$error_message = '';

$sql = "SELECT 
            c.id, 
            c.consultation_date, 
            c.diagnosis, 
            p.name AS pet_name,
            pt.name AS species_name 
        FROM consultations c
        JOIN pets p ON c.pet_id = p.id
        LEFT JOIN pet_types pt ON p.type_id = pt.id
        WHERE c.attendant_id = ? 
        ORDER BY c.consultation_date DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $consultations[] = $row;
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
    <title>Historial de Consultas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 900px;
            margin: 10px auto 0;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.95rem;
        }
        .breadcrumb a {
            color: #40916c;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #6c757d;
        }
        .dashboard-container {
            padding: 20px;
            max-width: 900px;
            margin: 0 auto;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        h1 {
            color: #1b4332;
            border-bottom: 2px solid #b68b40;
            padding-bottom: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .consultation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .consultation-table th {
            background: #40916c;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .consultation-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        .consultation-table tr:hover {
            background: #f1f1f1;
        }
        .btn-pdf {
            background: #b68b40;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-pdf:hover {
            background: #a07632;
        }
        .action-link {
            color: #40916c;
            text-decoration: none;
            font-weight: 600;
            margin: 0 5px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Historial de Consultas</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-notes-medical"></i> Historial de Consultas</h1>

            <?php if ($error_message) echo $error_message; ?>

            <?php if (empty($consultations)): ?>
                <div class="no-data">
                    <i class="fas fa-notes-medical" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>No has registrado consultas aún.</p>
                    <a href="consultation_register.php" class="action-link">Registrar nueva consulta</a>
                </div>
            <?php else: ?>
                <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>

                <table class="consultation-table" id="consultationTable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Especie</th>
                            <th>Diagnóstico</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consultations as $c): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($c['consultation_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($c['pet_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($c['species_name'] ?? 'Desconocida'); ?></td>
                                <td><?php echo htmlspecialchars(substr($c['diagnosis'], 0, 50)) . (strlen($c['diagnosis']) > 50 ? '...' : ''); ?></td>
                                <td><a href="consultation_view.php?id=<?php echo $c['id']; ?>" class="action-link">Ver</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('btnExportPdf')?.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(18);
            doc.text('Historial de Consultas', 105, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.text('Generado el: ' + new Date().toLocaleDateString('es-ES'), 105, 28, { align: 'center' });
            doc.autoTable({
                html: '#consultationTable',
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                styles: { fontSize: 9 }
            });
            doc.save('consultas.pdf');
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>