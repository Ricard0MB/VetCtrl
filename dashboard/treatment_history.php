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
    <title>Historial de Tratamientos - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1200px;
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
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
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
        .treatment-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .treatment-table th {
            background: #40916c;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .treatment-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
        }
        .treatment-table tr:hover {
            background: #f5f5f5;
        }
        .status-tag {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
            display: inline-block;
        }
        .status-ACTIVO { background: #ffe599; color: #856404; }
        .status-COMPLETADO { background: #d4edda; color: #155724; }
        .status-PAUSADO { background: #f8d7da; color: #721c24; }
        /* Estilos para botones */
        .btn {
            display: inline-block;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            border: 1px solid transparent;
            padding: 6px 12px;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 4px;
            transition: all 0.15s ease-in-out;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 3px;
        }
        .btn-primary {
            background-color: #40916c;
            border-color: #40916c;
            color: white;
        }
        .btn-primary:hover {
            background-color: #2d6a4f;
            border-color: #2d6a4f;
        }
        .btn-outline-primary {
            background-color: transparent;
            border-color: #40916c;
            color: #40916c;
        }
        .btn-outline-primary:hover {
            background-color: #40916c;
            color: white;
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
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }
        .no-data .btn-primary {
            display: inline-block;
            margin-top: 15px;
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
                    <i class="fas fa-prescription" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>No hay tratamientos registrados.</p>
                    <a href="treatment_select_pet.php" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar nuevo tratamiento</a>
                </div>
            <?php else: ?>
                <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>

                <table class="treatment-table" id="treatmentsTable">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Tratamiento</th>
                            <th>Diagnóstico</th>
                            <th>Fechas (Inicio/Fin)</th>
                            <th>Estado</th>
                            <th>Medicación/Notas</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treatments as $t): ?>
                        <tr>
                            <td>
                                <strong><a href="pet_profile.php?id=<?php echo $t['pet_id']; ?>" class="btn btn-sm btn-outline-primary"><?php echo htmlspecialchars($t['pet_name']); ?></a></strong>
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
                                <a href="pet_profile.php?id=<?php echo $t['pet_id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i> Ver historial</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="navigation-links">
                <a href="welcome.php" class="btn btn-outline-primary"><i class="fas fa-home"></i> Dashboard</a>
                <a href="treatment_followup.php" class="btn btn-primary"><i class="fas fa-chart-line"></i> Seguimiento de Tratamientos</a>
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
