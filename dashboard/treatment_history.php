<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo Veterinario y Admin pueden acceder
if ($role_name !== 'Veterinario' && $role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$treatments = [];
$message = '';

try {
    $sql = "SELECT 
                t.*, 
                p.name as pet_name,
                p.id as pet_id,
                pt.name AS species_name,  
                b.name AS breed_name      
            FROM treatments t
            JOIN pets p ON t.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id 
            LEFT JOIN breeds b ON p.breed_id = b.id      
            WHERE t.attendant_id = :attendant_id
            ORDER BY t.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar tratamientos: " . htmlspecialchars($e->getMessage()) . "</div>";
}

function truncateText($text, $length = 100) {
    $text = htmlspecialchars($text);
    if (strlen($text) > $length) {
        return nl2br(substr($text, 0, $length)) . '...';
    }
    return nl2br($text);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Tratamientos - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
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
        .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
            overflow-x: auto;
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
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #dc3545;
        }
        .btn-pdf {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .btn-pdf:hover {
            background: #9e6b2f;
            transform: translateY(-2px);
        }
        .treatment-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
            min-width: 800px;
        }
        .treatment-table th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        .treatment-table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
            vertical-align: top;
        }
        .treatment-table tr:hover td {
            background-color: #f9fbfd;
        }
        .status-tag {
            padding: 5px 12px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.75rem;
            display: inline-block;
        }
        .status-ACTIVO { background: #ffe599; color: #856404; }
        .status-COMPLETADO { background: #d4edda; color: #155724; }
        .status-PAUSADO { background: #f8d7da; color: #721c24; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
        }
        .btn-outline-primary {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .navigation-links {
            margin-top: 30px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            background: #f9fbfd;
            border-radius: 28px;
            color: #5b6e8c;
        }
        @media (max-width: 768px) {
            .action-buttons { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Historial de Tratamientos</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-prescription-bottle-alt"></i> Historial de Tratamientos</h1>

            <?php echo $message; ?>

            <?php if (empty($treatments)): ?>
                <div class="no-data">
                    <i class="fas fa-prescription" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                    <p>No hay tratamientos registrados.</p>
                    <a href="treatment_select_pet.php" class="btn btn-primary" style="background: var(--primary); color: white; padding: 10px 20px; border-radius: 40px; text-decoration: none;"><i class="fas fa-plus"></i> Registrar nuevo tratamiento</a>
                </div>
            <?php else: ?>
                <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>

                <div style="overflow-x: auto;">
                    <table class="treatment-table" id="treatmentsTable">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Tratamiento</th>
                                <th>Diagnóstico</th>
                                <th>Fechas (Inicio/Fin)</th>
                                <th>Estado</th>
                                <th>Medicación/Notas</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($treatments as $t): ?>
                            <tr>
                                <td>
                                    <strong><a href="pet_profile.php?id=<?php echo $t['pet_id']; ?>" class="btn btn-outline-primary btn-sm"><?php echo htmlspecialchars($t['pet_name']); ?></a></strong>
                                    <br><small><?php echo htmlspecialchars($t['species_name'] ?? 'Desconocida'); ?> <?php echo !empty($t['breed_name']) ? '(' . htmlspecialchars($t['breed_name']) . ')' : ''; ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($t['title']); ?></strong>
                                    <br><small>Reg: <?php echo date('d/m/Y', strtotime($t['created_at'])); ?></small>
                                </td>
                                <td><?php echo truncateText($t['diagnosis'], 150); ?></td>
                                <td>
                                    <strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($t['start_date'])); ?>
                                    <br><strong>Fin:</strong> <?php echo $t['end_date'] ? date('d/m/Y', strtotime($t['end_date'])) : 'N/D'; ?>
                                </td>
                                <td>
                                    <span class="status-tag status-<?php echo $t['status']; ?>">
                                        <?php echo $t['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong>Medicación:</strong> <?php echo truncateText($t['medication_details'], 70); ?><br>
                                    <strong>Notas:</strong> <?php echo truncateText($t['notes'], 70); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="treatment_edit.php?id=<?php echo $t['id']; ?>" class="btn btn-warning" title="Editar tratamiento">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <a href="treatment_delete.php?id=<?php echo $t['id']; ?>" class="btn btn-danger" title="Eliminar tratamiento" onclick="return confirm('¿Estás seguro de eliminar este tratamiento? Esta acción no se puede deshacer.');">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div class="navigation-links">
                <a href="welcome.php" class="btn btn-outline-primary"><i class="fas fa-home"></i> Dashboard</a>
                <a href="treatment_followup.php" class="btn btn-primary" style="background: var(--primary); color: white;"><i class="fas fa-chart-line"></i> Seguimiento de Tratamientos</a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btnExportPdf')?.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            doc.setFontSize(18);
            doc.text('Historial de Tratamientos', 148, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.text('Generado el: ' + new Date().toLocaleDateString('es-ES'), 148, 28, { align: 'center' });
            doc.autoTable({
                html: '#treatmentsTable',
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                styles: { fontSize: 8, cellPadding: 2 },
                columnStyles: { 2: { cellWidth: 50 }, 5: { cellWidth: 50 } }
            });
            doc.save('tratamientos.pdf');
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
