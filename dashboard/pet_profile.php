<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario'; 
$user_id = $_SESSION['id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

$pet_id = null;
$pet_data = null;
$owner_data = null;
$consultation_history = [];
$message = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $pet_id = $_GET['id'];
} else {
    header("Location: search_pet_owner.php?error=invalidid");
    exit();
}

$sql_pet = "SELECT 
            p.*,
            pt.name AS species_name,
            b.name AS breed_name,
            u.id as owner_user_id,
            u.username as owner_name,
            u.email as owner_email
            FROM pets p
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            LEFT JOIN breeds b ON p.breed_id = b.id
            LEFT JOIN users u ON p.owner_id = u.id
            WHERE p.id = ?";
        
if ($stmt_pet = $conn->prepare($sql_pet)) {
    $stmt_pet->bind_param("i", $pet_id);
    $stmt_pet->execute();
    $result_pet = $stmt_pet->get_result();
    
    if ($result_pet->num_rows == 1) {
        $pet_data = $result_pet->fetch_assoc();
        $owner_data = [
            'id' => $pet_data['owner_user_id'],
            'name' => $pet_data['owner_name'],
            'email' => $pet_data['owner_email']
        ];
    } else {
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Paciente no encontrado o ha sido eliminado.</div>";
    }
    $stmt_pet->close();
} else {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error de preparación: " . $conn->error . "</div>";
}

if ($owner_data && $owner_data['id']) {
    $sql_extra = "SELECT phone, address, ci FROM users WHERE id = ?";
    $stmt_extra = $conn->prepare($sql_extra);
    $stmt_extra->bind_param("i", $owner_data['id']);
    $stmt_extra->execute();
    $result_extra = $stmt_extra->get_result();
    $extra = $result_extra->fetch_assoc();
    $owner_data['phone'] = $extra['phone'] ?? 'No registrado';
    $owner_data['address'] = $extra['address'] ?? 'No registrada';
    $owner_data['ci'] = $extra['ci'] ?? 'No registrada';
    $stmt_extra->close();

    $sql_count = "SELECT COUNT(*) as total FROM pets WHERE owner_id = ?";
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("i", $owner_data['id']);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $count_row = $result_count->fetch_assoc();
    $owner_data['pets_count'] = $count_row['total'] ?? 0;
    $stmt_count->close();
}

if ($pet_data) {
    $sql_history = "SELECT c.*, u.username as vet_name 
                    FROM consultations c
                    LEFT JOIN users u ON c.attendant_id = u.id
                    WHERE pet_id = ?  
                    ORDER BY consultation_date DESC";
                    
    if ($stmt_history = $conn->prepare($sql_history)) {
        $stmt_history->bind_param("i", $pet_id);
        $stmt_history->execute();
        $result_history = $stmt_history->get_result();
        
        while ($row = $result_history->fetch_assoc()) {
            $consultation_history[] = $row;
        }
        $stmt_history->close();
    } else {
        $message .= "<div class='alert alert-danger'>Error al cargar historial: " . $conn->error . "</div>";
    }
}

if (isset($conn)) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de <?php echo htmlspecialchars($pet_data['name'] ?? 'Paciente'); ?></title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            padding-top: 60px;
        }
        .breadcrumb {
            max-width: 900px;
            margin: 10px auto 0 auto;
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
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            margin-top: 20px;
        }
        .main-content {
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 10px;
        }
        h2 {
            color: #3498db;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 8px;
            margin-top: 30px;
        }
        .pet-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
            gap:20px;
            border:1px solid #e0e0e0;
        }
        .owner-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
            border:1px solid #e0e0e0;
        }
        .consultation-item {
            border:1px solid #ddd;
            background: white;
            padding:20px;
            margin-bottom:20px;
            border-radius:8px;
        }
        .consultation-item .date-tag {
            background: #3498db;
            color:white;
            padding:5px 12px;
            border-radius:4px;
            font-size:0.9em;
        }
        .action-links a {
            margin-right:15px;
            color:#3498db;
            text-decoration:none;
            font-weight:600;
        }
        .page-actions {
            display:flex;
            justify-content:center;
            gap:15px;
            margin-bottom:30px;
            flex-wrap:wrap;
        }
        .btn {
            display:inline-block;
            padding:10px 20px;
            border-radius:6px;
            text-decoration:none;
            font-weight:600;
            transition:0.3s;
        }
        .back-to-dashboard { background:#3498db; color:white; }
        .export-pdf-btn { background:#2ecc71; color:white; }
        .back-to-search { background:white; color:#3498db; border:1px solid #3498db; }
        .btn-record { background:#8e44ad; color:white; }
        .btn-outline {
            background:white;
            color:#1b4332;
            border:2px solid #1b4332;
            padding:5px 12px;
            border-radius:6px;
        }
        .btn-outline:hover { background:#1b4332; color:white; }
        .btn-sm { padding:5px 12px; font-size:0.9rem; }
        /* Mensajes de alerta */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .alert-warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        @media (max-width:768px) {
            .page-actions { flex-direction:column; }
            .page-actions a, .page-actions button { width:100%; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Breadcrumbs -->
    <?php if ($pet_data): ?>
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="search_pet_owner.php">Pacientes</a> <span>›</span>
        <span><?php echo htmlspecialchars($pet_data['name']); ?></span>
    </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <div class="main-content">
            <?php echo $message; ?>

            <?php if ($pet_data): ?>
                <h1>Perfil de Paciente: <?php echo htmlspecialchars($pet_data['name']); ?> <i class="fas fa-paw"></i></h1>
                
                <div class="page-actions">
                    <a href="welcome.php" class="btn back-to-dashboard"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="search_pet_owner.php" class="btn back-to-search"><i class="fas fa-search"></i> Volver a Búsqueda</a>
                    <button id="exportPdfBtn" class="btn export-pdf-btn"><i class="fas fa-file-pdf"></i> Descargar Historial</button>
                    <a href="medical_record.php?id=<?php echo $pet_data['id']; ?>" class="btn btn-record">📄 Expediente Médico</a>
                </div>

                <?php if ($owner_data): ?>
                <div class="owner-info">
                    <h3><i class="fas fa-user"></i> Información del Dueño</h3>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($owner_data['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($owner_data['email']); ?></p>
                    <p><strong>Cédula:</strong> <?php echo htmlspecialchars($owner_data['ci']); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($owner_data['phone']); ?></p>
                    <p><strong>Dirección:</strong> <?php echo nl2br(htmlspecialchars($owner_data['address'])); ?></p>
                    <p><strong>Mascotas a cargo:</strong> <?php echo $owner_data['pets_count']; ?></p>
                    <?php if ($role_name !== 'Propietario'): ?>
                        <p><a href="owner_details.php?id=<?php echo $owner_data['id']; ?>" class="btn-outline btn-sm">
                            <i class="fas fa-user-circle"></i> Ver perfil completo del dueño
                        </a></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <h2><i class="fas fa-info-circle"></i> Información General</h2>
                <div class="pet-info" id="petInfoSection">
                    <p><strong>Nombre:</strong> <span id="petName"><?php echo htmlspecialchars($pet_data['name']); ?></span></p>
                    <p><strong>Especie:</strong> <span id="petSpecies"><?php echo htmlspecialchars($pet_data['species_name'] ?? 'N/D'); ?></span></p>
                    <p><strong>Raza:</strong> <span id="petBreed"><?php echo htmlspecialchars($pet_data['breed_name'] ?? 'N/D'); ?></span></p>
                    <p><strong>Fecha Nac.:</strong> <span id="petDOB"><?php echo htmlspecialchars($pet_data['date_of_birth'] ?? 'N/D'); ?></span></p>
                    <p><strong>Sexo:</strong> <span id="petGender"><?php echo htmlspecialchars($pet_data['gender'] ?? 'N/D'); ?></span></p>
                    <p style="grid-column: 1 / -1;"><strong>Historial Médico Crónico:</strong> <span id="petChronicHistory"><?php echo nl2br(htmlspecialchars($pet_data['medical_history'] ?? 'Sin historial crónico.')); ?></span></p>
                    <div class="action-links" style="grid-column: 1 / -1; margin-top: 15px; padding-top: 15px; border-top: 1px solid #eaeaea;">
                        <a href="pet_edit.php?id=<?php echo $pet_data['id']; ?>"><i class="fas fa-edit"></i> Editar Datos Básicos</a>
                        <a href="consultation_register.php?pet_id=<?php echo $pet_data['id']; ?>"><i class="fas fa-stethoscope"></i> Registrar Consulta</a>
                        <a href="appointment_schedule.php?pet_id=<?php echo $pet_data['id']; ?>"><i class="fas fa-calendar-plus"></i> Agendar Cita</a>
                    </div>
                </div>

                <h2><i class="fas fa-history"></i> Historial de Consultas (<?php echo count($consultation_history); ?>)</h2>
                
                <?php if (!empty($consultation_history)): ?>
                    <?php foreach ($consultation_history as $consultation): ?>
                        <div class="consultation-item">
                            <h3>
                                Consulta #<?php echo $consultation['id']; ?>
                                <span class="date-tag"><?php echo date('d/m/Y H:i', strtotime($consultation['consultation_date'])); ?></span>
                                <?php if ($consultation['vet_name']): ?>
                                <span style="font-size: 0.9em; color: #7f8c8d;">(Atendido por: <?php echo htmlspecialchars($consultation['vet_name']); ?>)</span>
                                <?php endif; ?>
                            </h3>
                            <div class="consultation-content">
                                <p><strong>Motivo:</strong> <?php echo htmlspecialchars($consultation['reason'] ?? 'No especificado'); ?></p>
                                <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($consultation['diagnosis'] ?? 'N/A'); ?></p>
                                <p><strong>Tratamiento:</strong> <?php echo htmlspecialchars($consultation['treatment'] ?? 'N/A'); ?></p>
                                <p><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($consultation['notes'] ?? 'Sin notas adicionales.')); ?></p>
                            </div>
                            <div class="consultation-actions action-links">
                                <a href="consultation_edit.php?id=<?php echo $consultation['id']; ?>"><i class="fas fa-edit"></i> Editar</a>
                                <a href="vaccine_register.php?pet_id=<?php echo $pet_data['id']; ?>"><i class="fas fa-syringe"></i> Registrar Vacuna</a>
                                <a href="treatment_register.php?pet_id=<?php echo $pet_data['id']; ?>"><i class="fas fa-prescription-bottle-alt"></i> Registrar Tratamiento</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Este paciente no tiene consultas registradas aún.</span>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>No se pudo cargar la información del paciente.</span>
                </div>
                <p style="text-align: center;"><a href="search_pet_owner.php">Volver a Búsqueda</a></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Script para PDF (se mantiene igual) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        if (!exportPdfBtn) return;
        const petData = {
            name: "<?php echo htmlspecialchars($pet_data['name'] ?? '', ENT_QUOTES); ?>",
            species: "<?php echo htmlspecialchars($pet_data['species_name'] ?? 'N/D', ENT_QUOTES); ?>",
            breed: "<?php echo htmlspecialchars($pet_data['breed_name'] ?? 'N/D', ENT_QUOTES); ?>",
            dob: "<?php echo htmlspecialchars($pet_data['date_of_birth'] ?? 'N/D', ENT_QUOTES); ?>",
            gender: "<?php echo htmlspecialchars($pet_data['gender'] ?? 'N/D', ENT_QUOTES); ?>",
            chronicHistory: `<?php echo addslashes(preg_replace('/\s+/', ' ', $pet_data['medical_history'] ?? 'Sin historial crónico.')); ?>`
        };
        const consultationHistory = <?php echo json_encode($consultation_history); ?>;
        const ownerData = <?php echo json_encode($owner_data); ?>;
        exportPdfBtn.addEventListener('click', () => generatePdfHistory(petData, consultationHistory, ownerData));

        function generatePdfHistory(pet, history, owner) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            let y = 20;
            doc.setFontSize(18);
            doc.text(`Historial Clínico - ${pet.name}`, 105, y, { align: 'center' });
            y += 8;
            doc.setFontSize(10);
            doc.text(`Generado el: ${new Date().toLocaleDateString('es-ES')}`, 105, y, { align: 'center' });
            y += 12;

            if (owner) {
                doc.setFontSize(14);
                doc.setTextColor(60, 179, 113);
                doc.text("Información del Dueño", 10, y);
                doc.line(10, y + 1, 200, y + 1);
                y += 8;
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0);
                const ownerInfo = [
                    ['Nombre:', owner.name || 'N/D'],
                    ['Email:', owner.email || 'N/D'],
                    ['Cédula:', owner.ci || 'N/D'],
                    ['Teléfono:', owner.phone || 'N/D'],
                    ['Dirección:', owner.address || 'N/D']
                ];
                doc.autoTable({
                    startY: y,
                    body: ownerInfo,
                    theme: 'plain',
                    styles: { fontSize: 10, cellPadding: 2 },
                    columnStyles: { 0: { fontStyle: 'bold', cellWidth: 30 }, 1: { cellWidth: 140 } }
                });
                y = doc.lastAutoTable.finalY + 10;
            }

            doc.setFontSize(14);
            doc.setTextColor(60, 179, 113);
            doc.text("Información del Paciente", 10, y);
            doc.line(10, y + 1, 200, y + 1);
            y += 5;
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            const petInfo = [
                ['Nombre:', pet.name],
                ['Especie:', pet.species],
                ['Raza:', pet.breed],
                ['Fecha Nac.:', pet.dob],
                ['Sexo:', pet.gender]
            ];
            doc.autoTable({
                startY: y,
                body: petInfo,
                theme: 'plain',
                styles: { fontSize: 10, cellPadding: 2 },
                columnStyles: { 0: { fontStyle: 'bold', cellWidth: 30 }, 1: { cellWidth: 140 } }
            });
            y = doc.lastAutoTable.finalY + 5;
            doc.setFontSize(10);
            doc.text("Historial Médico Crónico:", 10, y);
            y += 2;
            doc.setFontSize(9);
            doc.text(pet.chronicHistory, 10, y, { maxWidth: 190 });
            y = doc.lastAutoTable.finalY + 20;

            doc.setFontSize(14);
            doc.setTextColor(60, 179, 113);
            doc.text(`Historial de Consultas (${history.length})`, 10, y);
            doc.line(10, y + 1, 200, y + 1);
            y += 5;
            if (history.length === 0) {
                doc.setFontSize(10);
                doc.text("No hay consultas registradas.", 10, y + 5);
            } else {
                history.forEach((c, i) => {
                    if (y > 270) { doc.addPage(); y = 20; doc.setFontSize(12); doc.text(`Continuación - ${pet.name}`, 10, y); y += 10; }
                    doc.setFontSize(12);
                    doc.text(`Consulta #${c.id} - ${new Date(c.consultation_date).toLocaleDateString('es-ES')}`, 10, y);
                    y += 5;
                    const data = [
                        ['Motivo:', c.reason || '—'],
                        ['Diagnóstico:', c.diagnosis || '—'],
                        ['Tratamiento:', c.treatment || '—'],
                        ['Notas:', c.notes || '—']
                    ];
                    doc.autoTable({
                        startY: y,
                        body: data,
                        theme: 'grid',
                        headStyles: { fillColor: [240,240,240] },
                        styles: { fontSize: 9, cellPadding: 2 },
                        columnStyles: { 0: { fontStyle: 'bold', cellWidth: 25 } }
                    });
                    y = doc.lastAutoTable.finalY + 5;
                });
            }
            doc.save(`Historial_${pet.name.replace(/\s/g,'_')}.pdf`);
        }
    });
    </script>

    <?php include_once '../includes/footer.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>