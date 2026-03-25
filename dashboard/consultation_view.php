<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn debe ser un objeto PDO

// Variables necesarias para la navbar
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
            // Construir consulta base
            $sql = "SELECT 
                        c.*, 
                        p.name AS pet_name,
                        pt.name AS species_name,
                        b.name AS breed_name,
                        p.date_of_birth,
                        p.gender,
                        NULL AS weight,  -- La columna weight no existe en pets, se usa NULL
                        p.color
                    FROM consultations c
                    JOIN pets p ON c.pet_id = p.id
                    LEFT JOIN pet_types pt ON p.type_id = pt.id
                    LEFT JOIN breeds b ON p.breed_id = b.id
                    WHERE c.id = :id";

            // Si no es admin, filtrar por attendant_id
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

// Preparar datos para JSON con manejo seguro
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
    'weight' => $consultation['weight'] ?? '',   // Ahora weight existe en el array (NULL o vacío)
    'color' => $consultation['color'] ?? ''
] : null;

$consultation_json = json_encode($consultation_safe, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Consulta</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 60px;
            font-family: Arial, sans-serif;
            color: #333;
        }
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .main-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: #1b4332;
            margin-bottom: 25px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            text-align: center;
        }
        .detail-section {
            margin-bottom: 25px;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #fafafa;
        }
        .detail-section h2 {
            font-size: 1.2em;
            color: #40916c;
            border-bottom: 1px dashed #ccc;
            padding-bottom: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-item {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
        }
        .detail-item strong {
            display: inline-block;
            width: 180px;
            color: #2d6a4f;
            font-weight: 600;
        }
        .detail-value {
            flex: 1;
            color: #555;
        }
        .content-box {
            background-color: #fff;
            padding: 15px;
            border-left: 5px solid #03A9F4;
            white-space: pre-wrap;
            border-radius: 0 4px 4px 0;
            line-height: 1.5;
            margin-top: 10px;
        }
        .diagnosis-box { border-left-color: #03A9F4; }
        .treatment-box { border-left-color: #ff9800; }
        .notes-box { border-left-color: #607d8b; }
        .error-message {
            padding: 20px;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            text-align: center;
            margin: 20px 0;
        }
        .action-links {
            margin-top: 30px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .action-links a, .btn-pdf {
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            border-radius: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-back {
            background-color: #6c757d;
            color: white;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
        .btn-edit {
            background-color: #2d6a4f;
            color: white;
        }
        .btn-edit:hover {
            background-color: #1b4332;
        }
        .btn-pdf {
            background-color: #d90429;
            color: white;
        }
        .btn-pdf:hover {
            background-color: #a8001d;
        }
        .btn-print {
            background-color: #0d6efd;
            color: white;
        }
        .btn-print:hover {
            background-color: #0a58ca;
        }
        .pet-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            .detail-item {
                flex-direction: column;
                gap: 5px;
            }
            .detail-item strong {
                width: 100%;
            }
            .action-links {
                flex-direction: column;
                align-items: center;
            }
            .pet-info-grid {
                grid-template-columns: 1fr;
            }
        }
        @media print {
            body {
                padding-top: 0;
                background-color: white;
            }
            .dashboard-container {
                padding: 0;
            }
            .main-content {
                box-shadow: none;
                border: none;
            }
            .action-links {
                display: none;
            }
            .detail-section {
                break-inside: avoid;
            }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>

    <?php include '../includes/navbar.php'; ?>

    <div class="dashboard-container">
        <div class="main-content">

            <?php if (!empty($error_message)): ?>
                <h1>Error al Cargar Consulta</h1>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
                <div class="action-links">
                    <a href="consultation_history.php" class="btn-back">← Volver al Historial</a>
                </div>

            <?php elseif ($consultation): ?>

                <h1>Detalles de Consulta #<?php echo htmlspecialchars($consultation['id']); ?></h1>

                <div class="detail-section">
                    <h2>📅 Información General</h2>
                    <div class="detail-item">
                        <strong>Fecha y Hora:</strong>
                        <span class="detail-value"><?php echo date('d/m/Y H:i', strtotime($consultation['consultation_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <strong>Registrado por:</strong>
                        <span class="detail-value"><?php echo htmlspecialchars($username); ?> (ID: <?php echo htmlspecialchars($consultation['attendant_id']); ?>)</span>
                    </div>
                </div>

                <div class="detail-section">
                    <h2>🐾 Datos del Paciente</h2>
                    <div class="pet-info-grid">
                        <div class="detail-item">
                            <strong>Nombre:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['pet_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Especie:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['species_name'] ?? 'Desconocida'); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Raza:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['breed_name'] ?? 'Sin raza especificada'); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Género:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['gender'] ?? 'No especificado'); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Fecha Nacimiento:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['date_of_birth']); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Peso:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['weight'] ?? 'No registrado'); ?></span>
                        </div>
                        <div class="detail-item">
                            <strong>Color:</strong>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['color'] ?? 'No especificado'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h2>🩺 Diagnóstico</h2>
                    <div class="content-box diagnosis-box">
                        <?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?>
                    </div>
                </div>

                <?php if (!empty($consultation['treatment'])): ?>
                <div class="detail-section">
                    <h2>💊 Tratamiento Sugerido</h2>
                    <div class="content-box treatment-box">
                        <?php echo nl2br(htmlspecialchars($consultation['treatment'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($consultation['notes'])): ?>
                <div class="detail-section">
                    <h2>📝 Notas Adicionales</h2>
                    <div class="content-box notes-box">
                        <?php echo nl2br(htmlspecialchars($consultation['notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="action-links">
                    <a href="consultation_history.php" class="btn-back">← Volver al Historial</a>
                    <a href="consultation_edit.php?id=<?php echo urlencode($consultation['id']); ?>" class="btn-edit">✏️ Editar Consulta</a>
                    <button type="button" class="btn-pdf" onclick="exportPDF()">⬇️ Generar PDF</button>
                    <button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimir</button>
                </div>

            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        const consultationData = <?php echo $consultation_json ?: 'null'; ?>;

        function exportPDF() {
            if (!consultationData) {
                console.error("No hay datos de consulta disponibles para generar el PDF.");
                alert('Error: No se encontraron datos de la consulta.');
                return;
            }

            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                let y = 15;

                doc.setFontSize(20);
                doc.setFont("helvetica", "bold");
                doc.setTextColor(27, 67, 50);
                doc.text('Reporte de Consulta Veterinaria', 105, y, { align: 'center' });
                y += 8;

                doc.setFontSize(10);
                doc.setFont("helvetica", "normal");
                doc.setTextColor(0, 0, 0);
                doc.text(`Generado por: <?php echo htmlspecialchars(addslashes($username)); ?>`, 14, y);
                doc.text(`Fecha: ${new Date().toLocaleDateString('es-ES')}`, 196, y, { align: 'right' });
                y += 10;

                doc.setDrawColor(64, 145, 108);
                doc.setLineWidth(0.5);
                doc.line(14, y, 196, y);
                y += 10;

                doc.setFontSize(14);
                doc.setFont("helvetica", "bold");
                doc.text('Información de la Consulta', 14, y);
                y += 8;

                const generalData = [
                    ['ID Consulta:', consultationData.id.toString()],
                    ['Fecha y Hora:', new Date(consultationData.consultation_date).toLocaleString('es-ES')],
                    ['Paciente:', consultationData.pet_name],
                    ['Especie:', consultationData.species_name],
                    ['Raza:', consultationData.breed_name],
                    ['Fecha Nacimiento:', consultationData.date_of_birth],
                    ['Género:', consultationData.gender || 'No especificado'],
                    ['Peso:', consultationData.weight || 'No registrado'],
                    ['Color:', consultationData.color || 'No especificado']
                ];

                doc.autoTable({
                    startY: y,
                    body: generalData,
                    theme: 'grid',
                    styles: {
                        fontSize: 10,
                        cellPadding: 3,
                        lineColor: [200, 200, 200],
                        lineWidth: 0.1
                    },
                    columnStyles: {
                        0: {
                            fontStyle: 'bold',
                            fillColor: [230, 241, 232],
                            textColor: [45, 106, 79]
                        },
                        1: { cellWidth: 'auto' }
                    },
                    margin: { left: 14, right: 14 },
                    didDrawPage: function(data) {
                        y = data.cursor.y + 10;
                    }
                });

                y = doc.autoTable.previous.finalY + 10;

                drawSection(doc, 'Diagnóstico', consultationData.diagnosis, [3, 169, 244]);

                if (consultationData.treatment && consultationData.treatment.trim() !== '') {
                    y = drawSection(doc, 'Tratamiento Sugerido', consultationData.treatment, [255, 152, 0]);
                }

                if (consultationData.notes && consultationData.notes.trim() !== '') {
                    drawSection(doc, 'Notas Adicionales', consultationData.notes, [96, 125, 139]);
                }

                const pageCount = doc.internal.getNumberOfPages();
                for (let i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(9);
                    doc.setTextColor(128, 128, 128);
                    doc.text(`Página ${i} de ${pageCount}`, 105, 287, { align: 'center' });
                    doc.text('Sistema Veterinario © ' + new Date().getFullYear(), 105, 292, { align: 'center' });
                }

                const safePetName = consultationData.pet_name.replace(/[^\w\s]/gi, '').replace(/\s+/g, '_');
                const filename = `Consulta_${safePetName}_${consultationData.id}.pdf`;
                doc.save(filename);

                console.log(`PDF '${filename}' generado exitosamente.`);

            } catch (error) {
                console.error("Error al generar el PDF:", error);
                alert('Error al generar el PDF. Por favor, inténtelo de nuevo.');
            }
        }

        function drawSection(doc, title, content, color) {
            if (doc.internal.getCurrentPageInfo().pageNumber > 0 && doc.internal.getSize().height - doc.internal.getCurrentPageInfo().pageSize.height < 50) {
                doc.addPage();
                doc.y = 20;
            }

            const currentY = doc.y || 20;
            let y = currentY;

            doc.setFontSize(12);
            doc.setFont("helvetica", "bold");
            doc.setTextColor(...color);
            doc.text(title, 14, y);
            y += 7;

            doc.setFontSize(10);
            doc.setFont("helvetica", "normal");
            doc.setTextColor(0, 0, 0);

            const text = content.trim() || `No se registraron ${title.toLowerCase()}.`;
            const splitText = doc.splitTextToSize(text, 180);

            const lineHeight = 5;
            const boxHeight = splitText.length * lineHeight + 10;

            doc.setFillColor(249, 249, 249);
            doc.setDrawColor(...color);
            doc.setLineWidth(1);
            doc.roundedRect(14, y, 180, boxHeight, 2, 2, 'FD');

            doc.setFillColor(...color);
            doc.rect(14, y, 4, boxHeight, 'F');

            doc.text(splitText, 20, y + 7);

            doc.y = y + boxHeight + 12;
            return doc.y;
        }
    </script>

</body>
</html>
