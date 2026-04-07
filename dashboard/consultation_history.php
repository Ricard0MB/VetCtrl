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

$consultations = [];
$error_message = '';

try {
    if ($role_name === 'admin') {
        $sql = "SELECT 
                    c.id, 
                    c.consultation_date, 
                    c.diagnosis, 
                    p.name AS pet_name,
                    pt.name AS species_name,
                    u.username AS vet_name 
                FROM consultations c
                JOIN pets p ON c.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN users u ON c.attendant_id = u.id
                ORDER BY c.consultation_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role_name === 'Veterinario') {
        $sql = "SELECT 
                    c.id, 
                    c.consultation_date, 
                    c.diagnosis, 
                    p.name AS pet_name,
                    pt.name AS species_name 
                FROM consultations c
                JOIN pets p ON c.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                WHERE c.attendant_id = :user_id
                ORDER BY c.consultation_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sql = "SELECT 
                    c.id, 
                    c.consultation_date, 
                    c.diagnosis, 
                    p.name AS pet_name,
                    pt.name AS species_name 
                FROM consultations c
                JOIN pets p ON c.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                WHERE p.owner_id = :user_id
                ORDER BY c.consultation_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar consultas: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Consultas - VetCtrl</title>
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
            --vet-light: #74c69d;
            --shadow-md: 0 8px 20px rgba(0,0,0,0.05);
            --radius-lg: 16px;
        }
        .breadcrumb { max-width: 1200px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .dashboard-container { max-width: 1200px; margin: 1.5rem auto; padding: 0 1.5rem; }
        .main-content { background: white; border-radius: var(--radius-lg); padding: 1.8rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-light); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1.2rem; border-left: 4px solid; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .consultation-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .consultation-table th { background: #f8faf8; padding: 0.9rem 0.8rem; text-align: left; color: var(--vet-dark); font-weight: 600; border-bottom: 2px solid #dee6de; }
        .consultation-table td { padding: 0.8rem; border-bottom: 1px solid #eef2ee; vertical-align: middle; }
        .consultation-table tr:hover td { background: #fafdfa; }
        .btn-pdf { background: var(--vet-primary); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 8px; cursor: pointer; font-weight: 600; margin-bottom: 1rem; transition: 0.2s; }
        .btn-pdf:hover { background: var(--vet-dark); }
        .action-link { color: var(--vet-primary); text-decoration: none; font-weight: 500; padding: 0.25rem 0.6rem; border-radius: 6px; transition: background 0.2s; }
        .action-link:hover { background: #e9f4e9; }
        .no-data { text-align: center; padding: 2rem; background: #f8faf8; border-radius: 14px; color: var(--vet-text-light); }
        @media (max-width: 768px) { .dashboard-container { padding: 0 1rem; } .main-content { padding: 1rem; } .consultation-table { font-size: 0.75rem; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span> <span>Historial de Consultas</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-notes-medical"></i> Historial de Consultas</h1>

            <?php if ($error_message) echo $error_message; ?>

            <?php if (empty($consultations)): ?>
                <div class="no-data">
                    <i class="fas fa-notes-medical" style="font-size: 2.5rem; margin-bottom: 0.8rem; opacity: 0.5;"></i>
                    <p>No hay consultas registradas.</p>
                    <?php if (in_array($role_name, ['Veterinario', 'admin'])): ?>
                        <a href="consultation_register.php" class="action-link">Registrar nueva consulta</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
                <div style="overflow-x: auto;">
                    <table class="consultation-table" id="consultationTable">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Paciente</th>
                                <th>Especie</th>
                                <th>Diagnóstico</th>
                                <?php if ($role_name === 'admin'): ?>
                                    <th>Veterinario</th>
                                <?php endif; ?>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultations as $c): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($c['consultation_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($c['pet_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($c['species_name'] ?? 'Desconocida'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($c['diagnosis'], 0, 60)) . (strlen($c['diagnosis']) > 60 ? '...' : ''); ?></td>
                                    <?php if ($role_name === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($c['vet_name'] ?? 'N/A'); ?></td>
                                    <?php endif; ?>
                                    <td><a href="consultation_view.php?id=<?php echo $c['id']; ?>" class="action-link">Ver</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
