<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$pet_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

$pet = null;
$consultations = [];
$vaccinations = [];
$treatments = [];

try {
    // Obtener datos del paciente
    $sql_pet = "SELECT 
                p.*,
                pt.name AS species_name,
                b.name AS breed_name,
                u.username AS owner_name,
                u.id AS owner_id
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                LEFT JOIN users u ON p.owner_id = u.id
                WHERE p.id = :pet_id";
    $stmt = $conn->prepare($sql_pet);
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pet) {
        die("Mascota no encontrada.");
    }

    // Verificar permisos
    if ($role_name === 'Propietario' && $pet['owner_id'] != $user_id) {
        die("No tienes permiso para ver este expediente.");
    }

    // Obtener consultas
    $sql_cons = "SELECT c.*, u.username AS vet_name 
                 FROM consultations c
                 LEFT JOIN users u ON c.attendant_id = u.id
                 WHERE c.pet_id = :pet_id 
                 ORDER BY c.consultation_date DESC";
    $stmt = $conn->prepare($sql_cons);
    $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
    $stmt->execute();
    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener vacunas (si la tabla existe)
    $tableCheck = $conn->query("SHOW TABLES LIKE 'vaccinations'");
    if ($tableCheck->rowCount() > 0) {
        $sql_vac = "SELECT v.*, vt.name AS vaccine_name 
                    FROM vaccinations v
                    LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
                    WHERE v.pet_id = :pet_id 
                    ORDER BY v.application_date DESC";
        $stmt = $conn->prepare($sql_vac);
        $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
        $stmt->execute();
        $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Obtener tratamientos (si la tabla existe)
    $tableTreat = $conn->query("SHOW TABLES LIKE 'treatments'");
    if ($tableTreat->rowCount() > 0) {
        // Obtener columnas de la tabla treatments para construir SELECT dinámico
        $columns = [];
        $colResult = $conn->query("SHOW COLUMNS FROM treatments");
        $columns = $colResult->fetchAll(PDO::FETCH_COLUMN);

        $select_fields = ['id'];
        $field_map = ['description' => 'description', 'start_date' => 'start_date', 'end_date' => 'end_date'];
        foreach ($field_map as $alias => $field) {
            if (in_array($field, $columns)) {
                $select_fields[] = $field;
            } else {
                $select_fields[] = "NULL AS $alias";
            }
        }
        $select_sql = implode(', ', $select_fields);
        $sql_treat = "SELECT $select_sql FROM treatments WHERE pet_id = :pet_id ORDER BY start_date DESC";

        $stmt = $conn->prepare($sql_treat);
        $stmt->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
        $stmt->execute();
        $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    // En un entorno real podrías loguear el error y mostrar un mensaje genérico
    die("Error al cargar el expediente: " . $e->getMessage());
}

$issue_date = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Expediente Médico · <?php echo htmlspecialchars($pet['name']); ?></title>
    <!-- jsPDF y autoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <style>
        /* Estilos originales */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            background-color: #f0f2f5;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .record-container {
            max-width: 1100px;
            width: 100%;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid #d0d7de;
        }
        .record-header {
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 5px solid #b68b40;
        }
        .clinic-info h1 {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .clinic-info p {
            font-size: 1rem;
            opacity: 0.9;
        }
        .document-info {
            text-align: right;
            background: rgba(255,255,255,0.15);
            padding: 12px 20px;
            border-radius: 8px;
        }
        .document-info .doc-title {
            font-size: 1.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .document-info .doc-date {
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .patient-data {
            background: #f8fafc;
            padding: 25px 40px;
            border-bottom: 1px solid #e2e8f0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .data-item {
            display: flex;
            flex-direction: column;
        }
        .data-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: #4a5568;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .data-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1e293b;
        }
        .data-value small {
            font-weight: normal;
            font-size: 0.9rem;
            color: #64748b;
        }
        .record-body {
            padding: 30px 40px;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1b4332;
            border-bottom: 3px solid #b68b40;
            padding-bottom: 8px;
            margin: 30px 0 20px 0;
        }
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .records-table th {
            background: #e9edf2;
            color: #1e293b;
            font-weight: 600;
            padding: 12px 10px;
            text-align: left;
            border-bottom: 2px solid #cbd5e1;
        }
        .records-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #e2e8f0; color: #334155; }
        .no-data {
            text-align: center;
            padding: 30px;
            background: #f8fafc;
            border-radius: 8px;
            color: #64748b;
            font-style: italic;
            border: 2px dashed #cbd5e1;
        }
        .record-footer {
            background: #f1f5f9;
            padding: 15px 40px;
            border-top: 1px solid #cbd5e1;
            font-size: 0.85rem;
            color: #475569;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .signature {
            font-family: 'Brush Script MT', cursive;
            font-size: 1.3rem;
            color: #1b4332;
        }
        .action-buttons {
            max-width: 1100px;
            width: 100%;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 15px;
            margin-bottom: 20px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            background: white;
            color: #1b4332;
            border: 2px solid #1b4332;
            transition: all 0.3s;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: #1b4332;
            color: white;
            border: 2px solid #1b4332;
        }
        .btn-primary:hover {
            background: #2d6a4f;
        }
        .btn-pdf {
            background: #b68b40;
            color: white;
            border-color: #b68b40;
        }
        .btn-pdf:hover {
            background: #a07632;
        }
        .btn-outline:hover {
            background: #e9ecef;
        }
        @media print {
            .action-buttons, .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .record-container { box-shadow: none; border: 1px solid #ccc; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="record-container" id="recordContent">
        <div class="record-header">
            <div class="clinic-info">
                <h1>🐾 VetCtrl</h1>
                <p>Clínica Veterinaria • Salud y Bienestar Animal</p>
                <p style="font-size:0.9rem; margin-top:5px;">Calle Ejemplo 123, Ciudad • Tel: (123) 456-7890</p>
            </div>
            <div class="document-info">
                <div class="doc-title">Expediente Médico</div>
                <div class="doc-date">Emisión: <?php echo $issue_date; ?></div>
            </div>
        </div>

        <div class="patient-data">
            <div class="data-item"><span class="data-label">Paciente</span><span class="data-value"><?php echo htmlspecialchars($pet['name']); ?></span></div>
            <div class="data-item"><span class="data-label">Especie / Raza</span><span class="data-value"><?php echo htmlspecialchars($pet['species_name'] ?? 'N/A'); ?> <?php if(!empty($pet['breed_name'])) echo '<small>· '.htmlspecialchars($pet['breed_name']).'</small>'; ?></span></div>
            <div class="data-item"><span class="data-label">Fecha Nacimiento</span><span class="data-value"><?php echo date('d/m/Y', strtotime($pet['date_of_birth'])); ?> <small>(<?php 
                $edad = date_diff(date_create($pet['date_of_birth']), date_create('today'))->y;
                echo $edad . ' año' . ($edad != 1 ? 's' : ''); 
            ?>)</small></span></div>
            <div class="data-item"><span class="data-label">Sexo</span><span class="data-value"><?php echo htmlspecialchars($pet['gender'] ?? 'No especificado'); ?></span></div>
            <div class="data-item"><span class="data-label">Dueño</span><span class="data-value"><?php echo htmlspecialchars($pet['owner_name'] ?? 'N/A'); ?></span></div>
            <div class="data-item"><span class="data-label">ID Mascota</span><span class="data-value">#<?php echo str_pad($pet['id'], 5, '0', STR_PAD_LEFT); ?></span></div>
        </div>

        <div class="record-body">
            <!-- CONSULTAS -->
            <div class="section-title">📋 Historial de Consultas</div>
            <?php if (count($consultations) > 0): ?>
                <table class="records-table">
                    <thead><tr><th>Fecha</th><th>Motivo</th><th>Diagnóstico</th><th>Tratamiento</th><th>Veterinario</th></tr></thead>
                    <tbody>
                        <?php foreach ($consultations as $c): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($c['consultation_date'])); ?></td>
                            <td><?php echo htmlspecialchars($c['reason'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($c['diagnosis'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($c['treatment'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($c['vet_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No hay consultas registradas.</div>
            <?php endif; ?>

            <!-- VACUNAS -->
            <?php if (!empty($vaccinations)): ?>
            <div class="section-title">💉 Vacunas Aplicadas</div>
            <table class="records-table">
                <thead><tr><th>Vacuna</th><th>Fecha Aplicación</th><th>Próxima Dosis</th><th>Estado</th></tr></thead>
                <tbody>
                    <?php foreach ($vaccinations as $v): 
                        $today = date('Y-m-d');
                        $next = $v['next_dose_date'] ?? null;
                        $status = 'Aplicada';
                        $badge = 'badge-secondary';
                        if ($next) {
                            if ($next < $today) { $status = 'Vencida'; $badge = 'badge-danger'; }
                            elseif ($next <= date('Y-m-d', strtotime('+30 days'))) { $status = 'Próxima'; $badge = 'badge-warning'; }
                            else { $status = 'Al día'; $badge = 'badge-success'; }
                        }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($v['vaccine_name'] ?? 'Vacuna ID: '.$v['vaccine_type_id']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($v['application_date'])); ?></td>
                        <td><?php echo $next ? date('d/m/Y', strtotime($next)) : '—'; ?></td>
                        <td><span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- TRATAMIENTOS -->
            <div class="section-title">💊 Tratamientos / Prescripciones</div>
            <?php if (!empty($treatments)): ?>
                <table class="records-table">
                    <thead><tr><th>Descripción</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
                    <tbody>
                        <?php foreach ($treatments as $t): 
                            $desc = $t['description'] ?? 'Sin descripción';
                            $start = $t['start_date'] ?? null;
                            $end = $t['end_date'] ?? null;
                            $today = date('Y-m-d');
                            $estado = 'Activo';
                            $badge = 'badge-success';
                            if ($end) {
                                if ($end < $today) { $estado = 'Finalizado'; $badge = 'badge-secondary'; }
                                elseif ($end >= $today) { $estado = 'En curso'; $badge = 'badge-warning'; }
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($desc); ?></td>
                            <td><?php echo $start ? date('d/m/Y', strtotime($start)) : '—'; ?></td>
                            <td><?php echo $end ? date('d/m/Y', strtotime($end)) : '—'; ?></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo $estado; ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">No hay tratamientos registrados.</div>
            <?php endif; ?>
        </div>

        <div class="record-footer">
            <div>Documento generado electrónicamente. Validez clínica respaldada por VetCtrl.</div>
            <div class="signature">VetCtrl</div>
        </div>
    </div>

    <!-- Botones reubicados después del expediente -->
    <div class="action-buttons no-print">
        <button id="downloadPdfBtn" class="btn btn-pdf">📥 Descargar PDF</button>
        <a href="pet_profile.php?id=<?php echo $pet_id; ?>" class="btn btn-outline">⬅️ Volver al Perfil</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        document.getElementById('downloadPdfBtn').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'portrait',
                unit: 'mm',
                format: 'a4'
            });

            // Datos desde PHP (escapados para JSON)
            const pet = <?php echo json_encode($pet, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const consultations = <?php echo json_encode($consultations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const vaccinations = <?php echo json_encode($vaccinations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const treatments = <?php echo json_encode($treatments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const issueDate = '<?php echo $issue_date; ?>';

            let y = 20;

            // --- Encabezado (sin emoji para evitar problemas) ---
            doc.setFontSize(20);
            doc.setTextColor(27, 67, 50);
            doc.text('VetCtrl', 105, y, { align: 'center' });
            y += 8;
            doc.setFontSize(12);
            doc.setTextColor(0,0,0);
            doc.text('Clínica Veterinaria • Salud y Bienestar Animal', 105, y, { align: 'center' });
            y += 6;
            doc.setFontSize(9);
            doc.text('Calle Ejemplo 123, Ciudad • Tel: (123) 456-7890', 105, y, { align: 'center' });
            y += 8;

            doc.setFontSize(16);
            doc.setTextColor(27, 67, 50);
            doc.text('Expediente Médico', 105, y, { align: 'center' });
            y += 6;
            doc.setFontSize(9);
            doc.setTextColor(100,100,100);
            doc.text(`Emisión: ${issueDate}`, 105, y, { align: 'center' });
            y += 10;

            // --- Datos del paciente ---
            doc.setFontSize(11);
            doc.setTextColor(27, 67, 50);
            doc.text('Datos del Paciente', 20, y);
            y += 5;
            // Calcular edad correctamente
            const birthDate = new Date(pet.date_of_birth);
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const m = today.getMonth() - birthDate.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
            const petData = [
                ['Paciente:', pet.name || ''],
                ['Especie/Raza:', (pet.species_name || 'N/A') + (pet.breed_name ? ' · ' + pet.breed_name : '')],
                ['Fecha Nac.:', birthDate.toLocaleDateString('es-ES') + ' (' + age + ' años)'],
                ['Sexo:', pet.gender || 'N/E'],
                ['Dueño:', pet.owner_name || 'N/A'],
                ['ID:', '#' + String(pet.id).padStart(5, '0')]
            ];
            doc.autoTable({
                startY: y,
                body: petData,
                theme: 'plain',
                styles: { fontSize: 9, cellPadding: 2 },
                columnStyles: { 0: { fontStyle: 'bold', cellWidth: 35 }, 1: { cellWidth: 140 } },
                margin: { left: 20 }
            });
            y = doc.lastAutoTable.finalY + 10;

            // --- Consultas ---
            doc.setFontSize(12);
            doc.setTextColor(27, 67, 50);
            doc.text('Historial de Consultas', 20, y);
            y += 5;
            if (consultations.length === 0) {
                doc.setFontSize(9);
                doc.text('No hay consultas registradas.', 25, y);
                y += 10;
            } else {
                const consData = consultations.map(c => [
                    c.consultation_date ? new Date(c.consultation_date).toLocaleDateString('es-ES') : '—',
                    c.reason || '—',
                    c.diagnosis || '—',
                    c.treatment || '—',
                    c.vet_name || 'N/A'
                ]);
                doc.autoTable({
                    startY: y,
                    head: [['Fecha', 'Motivo', 'Diagnóstico', 'Tratamiento', 'Veterinario']],
                    body: consData,
                    theme: 'grid',
                    headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                    styles: { fontSize: 8, cellPadding: 2 },
                    margin: { left: 20, right: 20 }
                });
                y = doc.lastAutoTable.finalY + 10;
            }

            // --- Vacunas ---
            if (vaccinations.length > 0) {
                doc.setFontSize(12);
                doc.setTextColor(27, 67, 50);
                doc.text('Vacunas Aplicadas', 20, y);
                y += 5;
                const vacData = vaccinations.map(v => {
                    const appDate = v.application_date ? new Date(v.application_date).toLocaleDateString('es-ES') : '—';
                    const nextDate = v.next_dose_date ? new Date(v.next_dose_date).toLocaleDateString('es-ES') : '—';
                    return [v.vaccine_name || 'Vacuna', appDate, nextDate];
                });
                doc.autoTable({
                    startY: y,
                    head: [['Vacuna', 'Fecha Aplicación', 'Próxima Dosis']],
                    body: vacData,
                    theme: 'grid',
                    headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                    styles: { fontSize: 8 },
                    margin: { left: 20, right: 20 }
                });
                y = doc.lastAutoTable.finalY + 10;
            }

            // --- Tratamientos ---
            if (treatments.length > 0) {
                doc.setFontSize(12);
                doc.setTextColor(27, 67, 50);
                doc.text('Tratamientos / Prescripciones', 20, y);
                y += 5;
                const treatData = treatments.map(t => {
                    const start = t.start_date ? new Date(t.start_date).toLocaleDateString('es-ES') : '—';
                    const end = t.end_date ? new Date(t.end_date).toLocaleDateString('es-ES') : '—';
                    return [t.description || 'Sin descripción', start, end];
                });
                doc.autoTable({
                    startY: y,
                    head: [['Descripción', 'Inicio', 'Fin']],
                    body: treatData,
                    theme: 'grid',
                    headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                    styles: { fontSize: 8 },
                    margin: { left: 20, right: 20 }
                });
                y = doc.lastAutoTable.finalY + 10;
            }

            // --- Pie de página ---
            doc.setFontSize(8);
            doc.setTextColor(100,100,100);
            doc.text('Documento generado electrónicamente. Validez clínica respaldada por VetCtrl.', 20, 280);
            doc.setFont('helvetica', 'italic');
            doc.text('VetCtrl', 180, 280);

            doc.save(`Expediente_${(pet.name || 'mascota').replace(/[^a-z0-9]/gi,'_')}.pdf`);
        });
    </script>
</body>
</html>
