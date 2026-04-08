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

// Capturar mensajes de error/success desde la URL
$error_msg = $_GET['error'] ?? '';
$success_msg = $_GET['msg'] ?? '';
$message = '';
if ($error_msg) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> " . htmlspecialchars($error_msg) . "</div>";
} elseif ($success_msg) {
    $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> " . htmlspecialchars($success_msg) . "</div>";
}

$pet_id = null;
$pet_data = null;
$owner_data = null;
$consultation_history = [];

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $pet_id = intval($_GET['id']);
} else {
    header("Location: search_pet_owner.php?error=invalidid");
    exit();
}

try {
    // Obtener datos de la mascota
    $sql_pet = "SELECT 
                p.id,
                p.name,
                p.type_id,
                p.breed_id,
                p.gender,
                p.date_of_birth,
                p.medical_history,
                p.owner_id,
                p.created_at,
                pt.name AS species_name,
                b.name AS breed_name,
                u.id as owner_user_id,
                u.username as owner_name,
                u.email as owner_email
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                LEFT JOIN users u ON p.owner_id = u.id
                WHERE p.id = :pet_id";

    $stmt_pet = $conn->prepare($sql_pet);
    $stmt_pet->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt_pet->execute();
    $pet_data = $stmt_pet->fetch(PDO::FETCH_ASSOC);

    if (!$pet_data) {
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Paciente no encontrado o ha sido eliminado.</div>";
    } else {
        // Preparar datos del dueño si existe
        if (!empty($pet_data['owner_user_id'])) {
            $owner_data = [
                'id' => $pet_data['owner_user_id'],
                'name' => $pet_data['owner_name'] ?? 'Usuario eliminado',
                'email' => $pet_data['owner_email'] ?? 'No disponible'
            ];

            $sql_extra = "SELECT phone, address, ci FROM users WHERE id = :owner_id";
            $stmt_extra = $conn->prepare($sql_extra);
            $stmt_extra->bindValue(':owner_id', $owner_data['id'], PDO::PARAM_INT);
            $stmt_extra->execute();
            $extra = $stmt_extra->fetch(PDO::FETCH_ASSOC);
            $owner_data['phone'] = $extra['phone'] ?? 'No registrado';
            $owner_data['address'] = $extra['address'] ?? 'No registrada';
            $owner_data['ci'] = $extra['ci'] ?? 'No registrada';

            $sql_count = "SELECT COUNT(*) as total FROM pets WHERE owner_id = :owner_id";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bindValue(':owner_id', $owner_data['id'], PDO::PARAM_INT);
            $stmt_count->execute();
            $count_row = $stmt_count->fetch(PDO::FETCH_ASSOC);
            $owner_data['pets_count'] = $count_row['total'] ?? 0;
        } else {
            $owner_data = null;
        }

        // Obtener historial de consultas
        $sql_history = "SELECT c.*, u.username as vet_name 
                        FROM consultations c
                        LEFT JOIN users u ON c.attendant_id = u.id
                        WHERE pet_id = :pet_id  
                        ORDER BY consultation_date DESC";
        $stmt_history = $conn->prepare($sql_history);
        $stmt_history->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
        $stmt_history->execute();
        $consultation_history = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar datos: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de <?php echo htmlspecialchars($pet_data['name'] ?? 'Paciente'); ?></title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8fafc;
        }
        body {
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
            background-color: #f4f7fc;
            padding-top: 70px;
        }
        .breadcrumb {
            max-width: 1100px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .dashboard-container {
            padding: 20px;
            max-width: 1100px;
            margin: 0 auto;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
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
        .alert-success { background: #e0f2e9; color: #1e7b4a; border-left-color: #1e7b4a; }
        .alert-danger { background: #fee7e7; color: #b91c1c; border-left-color: #b91c1c; }
        .page-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 30px;
            justify-content: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .btn-pdf { background: var(--accent); color: white; }
        .btn-pdf:hover { background: #9e6b2f; transform: translateY(-2px); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #b91c1c; transform: translateY(-2px); }
        .owner-info, .pet-info {
            background: #f9fbfd;
            padding: 24px;
            border-radius: 24px;
            margin-bottom: 30px;
            border: 1px solid #eef2f8;
        }
        .pet-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px,1fr));
            gap: 20px;
        }
        .consultation-item {
            background: white;
            border: 1px solid #eef2f8;
            border-radius: 24px;
            padding: 20px;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .consultation-item:hover {
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            border-color: var(--primary-light);
        }
        .consultation-header {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: baseline;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--accent);
        }
        .consultation-actions {
            margin-top: 15px;
            display: flex;
            gap: 12px;
        }
        .consultation-actions a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 500;
        }
        .action-links {
            margin-top: 15px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .btn-outline {
            background: white;
            border: 2px solid var(--primary);
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-outline:hover {
            background: var(--primary);
            color: white;
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .pet-info { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

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
                <h1><i class="fas fa-dog"></i> Perfil de Paciente: <?php echo htmlspecialchars($pet_data['name']); ?></h1>
                
                <div class="page-actions">
                    <a href="welcome.php" class="btn btn-primary"><i class="fas fa-home"></i> Dashboard</a>
                    <a href="search_pet_owner.php" class="btn btn-primary"><i class="fas fa-search"></i> Volver a Búsqueda</a>
                    <button id="exportPdfBtn" class="btn btn-pdf"><i class="fas fa-file-pdf"></i> Descargar Historial</button>
                    <a href="medical_record.php?id=<?php echo $pet_data['id']; ?>" class="btn btn-primary">📄 Expediente Médico</a>
                    <?php if (in_array($role_name, ['Veterinario', 'admin'])): ?>
                        <button id="deletePetBtn" class="btn btn-danger"><i class="fas fa-trash-alt"></i> Eliminar Mascota</button>
                    <?php endif; ?>
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
                        <p><a href="owner_details.php?id=<?php echo $owner_data['id']; ?>" class="btn-outline"><i class="fas fa-user-circle"></i> Ver perfil completo del dueño</a></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <h2><i class="fas fa-info-circle"></i> Información General</h2>
                <div class="pet-info" id="petInfoSection">
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($pet_data['name']); ?></p>
                    <p><strong>Especie:</strong> <?php echo htmlspecialchars($pet_data['species_name'] ?? 'N/D'); ?></p>
                    <p><strong>Raza:</strong> <?php echo htmlspecialchars($pet_data['breed_name'] ?? 'N/D'); ?></p>
                    <p><strong>Fecha Nac.:</strong> <?php echo htmlspecialchars($pet_data['date_of_birth'] ?? 'N/D'); ?></p>
                    <p><strong>Sexo:</strong> <?php echo htmlspecialchars($pet_data['gender'] ?? 'N/D'); ?></p>
                    <p style="grid-column: 1 / -1;"><strong>Historial Médico Crónico:</strong> <?php echo nl2br(htmlspecialchars($pet_data['medical_history'] ?? 'Sin historial crónico.')); ?></p>
                    <div class="action-links">
                        <a href="pet_edit.php?id=<?php echo $pet_data['id']; ?>" class="btn-outline"><i class="fas fa-edit"></i> Editar Datos Básicos</a>
                        <a href="consultation_register.php?pet_id=<?php echo $pet_data['id']; ?>" class="btn-outline"><i class="fas fa-stethoscope"></i> Registrar Consulta</a>
                        <a href="appointment_schedule.php?pet_id=<?php echo $pet_data['id']; ?>" class="btn-outline"><i class="fas fa-calendar-plus"></i> Agendar Cita</a>
                    </div>
                </div>

                <h2><i class="fas fa-history"></i> Historial de Consultas (<?php echo count($consultation_history); ?>)</h2>
                
                <?php if (!empty($consultation_history)): ?>
                    <?php foreach ($consultation_history as $consultation): ?>
                        <div class="consultation-item">
                            <div class="consultation-header">
                                <h3>Consulta #<?php echo $consultation['id']; ?></h3>
                                <span class="badge" style="background: var(--accent); color: white; padding: 4px 12px; border-radius: 40px;"><?php echo date('d/m/Y H:i', strtotime($consultation['consultation_date'])); ?></span>
                                <?php if ($consultation['vet_name']): ?>
                                <span><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($consultation['vet_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="consultation-content">
                                <p><strong>Motivo:</strong> <?php echo htmlspecialchars($consultation['reason'] ?? 'No especificado'); ?></p>
                                <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars($consultation['diagnosis'] ?? 'N/A'); ?></p>
                                <p><strong>Tratamiento:</strong> <?php echo htmlspecialchars($consultation['treatment'] ?? 'N/A'); ?></p>
                                <p><strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($consultation['notes'] ?? 'Sin notas adicionales.')); ?></p>
                            </div>
                            <div class="consultation-actions">
                                <a href="consultation_edit.php?id=<?php echo $consultation['id']; ?>"><i class="fas fa-edit"></i> Editar Consulta</a>
                                <a href="vaccine_register.php?pet_id=<?php echo $pet_data['id']; ?>"><i class="fas fa-syringe"></i> Registrar Vacuna</a>
                                <a href="treatment_register.php?pet_id=<?php echo $pet_data['id']; ?>"><i class="fas fa-prescription-bottle-alt"></i> Registrar Tratamiento</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info" style="background: #eef2ff; border-left-color: #3b82f6;">
                        <i class="fas fa-info-circle"></i>
                        <span>Este paciente no tiene consultas registradas aún.</span>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>No se pudo cargar la información del paciente.</span>
                </div>
                <p style="text-align: center;"><a href="search_pet_owner.php" class="btn btn-primary">Volver a Búsqueda</a></p>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const exportPdfBtn = document.getElementById('exportPdfBtn');
        if (exportPdfBtn) {
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
        }

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

        const deleteBtn = document.getElementById('deletePetBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('¿Estás seguro de que deseas eliminar esta mascota?\nEsta acción eliminará permanentemente la mascota y todos sus registros asociados (consultas, vacunas, etc.). Esta operación no se puede deshacer.')) {
                    window.location.href = 'pet_delete.php?id=<?php echo $pet_id; ?>&confirm=1';
                }
            });
        }
    });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
