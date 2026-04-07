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

$consultation = null;
$error_message = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $consultation_id = intval($_GET['id']);
    if ($consultation_id <= 0) {
        $error_message = "ID de consulta inválido.";
    } else {
        try {
            $sql = "SELECT 
                        c.*, 
                        p.name AS pet_name,
                        pt.name AS species_name,
                        b.name AS breed_name,
                        p.date_of_birth,
                        p.gender,
                        NULL AS weight,
                        NULL AS color
                    FROM consultations c
                    JOIN pets p ON c.pet_id = p.id
                    LEFT JOIN pet_types pt ON p.type_id = pt.id
                    LEFT JOIN breeds b ON p.breed_id = b.id
                    WHERE c.id = :id";
            if ($role_name !== 'admin') {
                $sql .= " AND c.attendant_id = :attendant_id";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $consultation_id, PDO::PARAM_INT);
            if ($role_name !== 'admin') {
                $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$consultation) {
                $error_message = "Consulta no encontrada o no tienes permisos para ver este registro.";
            }
        } catch (PDOException $e) {
            $error_message = "Error de base de datos: " . htmlspecialchars($e->getMessage());
        }
    }
} else {
    $error_message = "ID de consulta no especificado o inválido.";
}

$consultation_safe = $consultation ? [
    'id' => $consultation['id'],
    'pet_name' => $consultation['pet_name'] ?? '',
    'consultation_date' => $consultation['consultation_date'] ?? '',
    'diagnosis' => $consultation['diagnosis'] ?? '',
    'treatment' => $consultation['treatment'] ?? '',
    'notes' => $consultation['notes'] ?? '',
    'species_name' => $consultation['species_name'] ?? 'Desconocida',
    'breed_name' => $consultation['breed_name'] ?? 'Sin raza',
    'date_of_birth' => $consultation['date_of_birth'] ?? '',
    'gender' => $consultation['gender'] ?? '',
] : null;
$consultation_json = json_encode($consultation_safe, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Consulta</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .dashboard-container { max-width: 1000px; margin: 1.5rem auto; padding: 0 1.5rem; }
        .main-content { background: white; border-radius: var(--radius-lg); padding: 1.8rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; font-size: 1.6rem; display: flex; align-items: center; gap: 0.75rem; }
        .detail-section { margin-bottom: 1.8rem; padding: 1rem; border: 1px solid #e2e8e2; border-radius: 14px; background: #fefefe; }
        .detail-section h2 { font-size: 1.2rem; color: var(--vet-dark); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .detail-item { margin-bottom: 0.7rem; display: flex; align-items: flex-start; }
        .detail-item strong { width: 160px; color: var(--vet-dark); font-weight: 600; }
        .detail-value { flex: 1; color: #2d3e3a; }
        .content-box { background: #fafdfa; padding: 1rem; border-left: 4px solid var(--vet-primary); border-radius: 8px; margin-top: 0.5rem; white-space: pre-wrap; }
        .error-message { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 12px; text-align: center; margin: 1rem 0; }
        .action-links { margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; justify-content: center; }
        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: 0.2s; border: none; cursor: pointer; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-back { background: #6c757d; color: white; }
        .btn-back:hover { background: #5a6268; }
        .btn-edit { background: var(--vet-primary); color: white; }
        .btn-edit:hover { background: var(--vet-dark); }
        .btn-pdf { background: #d90429; color: white; }
        .btn-pdf:hover { background: #a8001d; }
        .btn-print { background: #0d6efd; color: white; }
        .btn-print:hover { background: #0a58ca; }
        @media (max-width: 768px) { .detail-item { flex-direction: column; } .detail-item strong { width: auto; margin-bottom: 0.2rem; } .dashboard-container { padding: 0 1rem; } .main-content { padding: 1rem; } }
        @media print { body { padding-top: 0; background: white; } .action-links { display: none; } .main-content { box-shadow: none; border: none; } }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="dashboard-container">
        <div class="main-content">
            <?php if (!empty($error_message)): ?>
                <h1>Error</h1>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <div class="action-links">
                    <a href="consultation_history.php" class="btn btn-back">← Volver al Historial</a>
                </div>
            <?php elseif ($consultation): ?>
                <h1><i class="fas fa-file-alt"></i> Detalles de Consulta #<?php echo htmlspecialchars($consultation['id']); ?></h1>

                <div class="detail-section">
                    <h2><i class="fas fa-calendar-alt"></i> Información General</h2>
                    <div class="detail-item"><strong>Fecha y Hora:</strong><span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($consultation['consultation_date'])); ?></span></div>
                    <div class="detail-item"><strong>Registrado por:</strong><span class="detail-value"><?php echo htmlspecialchars($username); ?> (ID: <?php echo htmlspecialchars($consultation['attendant_id']); ?>)</span></div>
                </div>

                <div class="detail-section">
                    <h2><i class="fas fa-paw"></i> Datos del Paciente</h2>
                    <div class="detail-item"><strong>Nombre:</strong><span class="detail-value"><?php echo htmlspecialchars($consultation['pet_name']); ?></span></div>
                    <div class="detail-item"><strong>Especie:</strong><span class="detail-value"><?php echo htmlspecialchars($consultation['species_name'] ?? 'Desconocida'); ?></span></div>
                    <div class="detail-item"><strong>Raza:</strong><span class="detail-value"><?php echo htmlspecialchars($consultation['breed_name'] ?? 'Sin raza'); ?></span></div>
                    <div class="detail-item"><strong>Género:</strong><span class="detail-value"><?php echo htmlspecialchars($consultation['gender'] ?? 'No especificado'); ?></span></div>
                    <div class="detail-item"><strong>Fecha Nacimiento:</strong><span class="detail-value"><?php echo htmlspecialchars($consultation['date_of_birth'] ?? 'No registrada'); ?></span></div>
                </div>

                <div class="detail-section">
                    <h2><i class="fas fa-stethoscope"></i> Diagnóstico</h2>
                    <div class="content-box"><?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?></div>
                </div>

                <?php if (!empty($consultation['treatment'])): ?>
                <div class="detail-section">
                    <h2><i class="fas fa-prescription-bottle"></i> Tratamiento</h2>
                    <div class="content-box"><?php echo nl2br(htmlspecialchars($consultation['treatment'])); ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($consultation['notes'])): ?>
                <div class="detail-section">
                    <h2><i class="fas fa-comment-dots"></i> Notas</h2>
                    <div class="content-box"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="action-links">
                    <a href="consultation_history.php" class="btn btn-back">← Volver</a>
                    <a href="consultation_edit.php?id=<?php echo urlencode($consultation['id']); ?>" class="btn btn-edit">✏️ Editar</a>
                    <button type="button" class="btn btn-pdf" onclick="exportPDF()">📄 Generar PDF</button>
                    <button type="button" class="btn btn-print" onclick="window.print()">🖨️ Imprimir</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const consultationData = <?php echo $consultation_json ?: 'null'; ?>;

        function exportPDF() {
            if (!consultationData) { alert('No hay datos de la consulta.'); return; }
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            let y = 20;
            doc.setFontSize(18);
            doc.setTextColor(27, 67, 50);
            doc.text('Reporte de Consulta Veterinaria', 105, y, { align: 'center' });
            y += 10;
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            doc.text(`Paciente: ${consultationData.pet_name}`, 20, y);
            doc.text(`Fecha: ${new Date(consultationData.consultation_date).toLocaleString('es-ES')}`, 20, y+6);
            doc.text(`Especie: ${consultationData.species_name}`, 20, y+12);
            doc.text(`Raza: ${consultationData.breed_name}`, 20, y+18);
            y += 30;
            doc.setFontSize(12);
            doc.setTextColor(27, 67, 50);
            doc.text('Diagnóstico', 20, y);
            doc.setFontSize(10);
            doc.setTextColor(0, 0, 0);
            const diagLines = doc.splitTextToSize(consultationData.diagnosis || 'No registrado', 170);
            doc.text(diagLines, 20, y+6);
            y += 10 + (diagLines.length * 5);
            if (consultationData.treatment) {
                doc.setFontSize(12);
                doc.setTextColor(27, 67, 50);
                doc.text('Tratamiento', 20, y);
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0);
                const treatLines = doc.splitTextToSize(consultationData.treatment, 170);
                doc.text(treatLines, 20, y+6);
                y += 10 + (treatLines.length * 5);
            }
            if (consultationData.notes) {
                doc.setFontSize(12);
                doc.setTextColor(27, 67, 50);
                doc.text('Notas', 20, y);
                doc.setFontSize(10);
                doc.setTextColor(0, 0, 0);
                const notesLines = doc.splitTextToSize(consultationData.notes, 170);
                doc.text(notesLines, 20, y+6);
            }
            doc.save(`Consulta_${consultationData.pet_name}_${consultationData.id}.pdf`);
        }
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
