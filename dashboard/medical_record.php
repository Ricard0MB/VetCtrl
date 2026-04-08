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
    die("Error al cargar el expediente: " . $e->getMessage());
}

$issue_date = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente Médico · <?php echo htmlspecialchars($pet['name']); ?></title>
    <!-- jsPDF y autoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f4f7fc;
            --card-radius: 28px;
            --shadow-sm: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', system-ui, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--gray-bg);
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .record-container {
            max-width: 1100px;
            width: 100%;
            background: white;
            box-shadow: var(--shadow-sm);
            border-radius: var(--card-radius);
            overflow: hidden;
            margin-bottom: 30px;
            border: 1px solid rgba(0,0,0,0.05);
        }
        .record-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 30px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .clinic-info h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .clinic-info p {
            font-size: 0.9rem;
            opacity: 0.85;
        }
        .document-info {
            background: rgba(255,255,255,0.12);
            padding: 12px 24px;
            border-radius: 60px;
            backdrop-filter: blur(4px);
        }
        .document-info .doc-title {
            font-size: 1.3rem;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .patient-data {
            background: #f9fafc;
            padding: 28px 40px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            border-bottom: 1px solid #eef2f6;
        }
        .data-item {
            display: flex;
            flex-direction: column;
        }
        .data-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #5b6e8c;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .data-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e2a3a;
        }
        .record-body {
            padding: 30px 40px;
        }
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 8px;
            margin: 40px 0 20px 0;
            display: inline-block;
        }
        .records-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            font-size: 0.9rem;
            border-radius: 16px;
            overflow: hidden;
        }
        .records-table th {
            background: #eef2f9;
            color: var(--primary-dark);
            font-weight: 700;
            padding: 12px 15px;
            text-align: left;
        }
        .records-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9edf2;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: #e0f2e9; color: #1e7b4a; }
        .badge-warning { background: #fff0db; color: #b45f06; }
        .badge-danger { background: #fee7e7; color: #b91c1c; }
        .badge-secondary { background: #eef2f6; color: #2c3e50; }
        .no-data {
            text-align: center;
            padding: 35px;
            background: #fafcff;
            border-radius: 24px;
            color: #7e8b9c;
            border: 1px dashed #cbd5e1;
        }
        .record-footer {
            background: #f8fafd;
            padding: 18px 40px;
            border-top: 1px solid #e2e8f0;
            font-size: 0.8rem;
            color: #4a5b6e;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .signature {
            font-family: 'Brush Script MT', cursive;
            font-size: 1.3rem;
            color: var(--primary-dark);
        }
        .action-buttons {
            max-width: 1100px;
            width: 100%;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 5px;
            margin-bottom: 20px;
        }
        .btn {
            padding: 12px 28px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            background: white;
            color: var(--primary-dark);
            border: 2px solid var(--primary-dark);
            transition: all 0.2s;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: var(--primary-dark);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary);
        }
        .btn-pdf {
            background: var(--accent);
            color: white;
            border: none;
        }
        .btn-pdf:hover {
            background: #9e6b2f;
            transform: translateY(-2px);
        }
        .btn-outline:hover {
            background: #f1f5f9;
        }
        @media print {
            .action-buttons, .no-print { display: none; }
            body { background: white; padding: 0; }
            .record-container { box-shadow: none; border: 1px solid #ccc; }
        }
        @media (max-width: 700px) {
            .record-header { flex-direction: column; text-align: center; }
            .patient-data { padding: 20px; }
            .record-body { padding: 20px; }
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
                <p style="font-size:0.85rem;">Calle Ejemplo 123, Ciudad • Tel: (123) 456-7890</p>
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

    <div class="action-buttons no-print">
        <button id="downloadPdfBtn" class="btn btn-pdf">📥 Descargar PDF</button>
        <a href="pet_profile.php?id=<?php echo $pet_id; ?>" class="btn btn-outline">⬅️ Volver al Perfil</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>

    <script>
        document.getElementById('downloadPdfBtn').addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
            const pet = <?php echo json_encode($pet, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const consultations = <?php echo json_encode($consultations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const vaccinations = <?php echo json_encode($vaccinations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const treatments = <?php echo json_encode($treatments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            const issueDate = '<?php echo $issue_date; ?>';

            let y = 20;
            doc.setFontSize(20); doc.setTextColor(27, 67, 50); doc.text('VetCtrl', 105, y, { align: 'center' });
            y += 8; doc.setFontSize(12); doc.setTextColor(0,0,0); doc.text('Clínica Veterinaria • Salud y Bienestar Animal', 105, y, { align: 'center' });
            y += 6; doc.setFontSize(9); doc.text('Calle Ejemplo 123, Ciudad • Tel: (123) 456-7890', 105, y, { align: 'center' });
            y += 8; doc.setFontSize(16); doc.setTextColor(27, 67, 50); doc.text('Expediente Médico', 105, y, { align: 'center' });
            y += 6; doc.setFontSize(9); doc.setTextColor(100,100,100); doc.text(`Emisión: ${issueDate}`, 105, y, { align: 'center' });
            y += 10;

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
            doc.autoTable({ startY: y, body: petData, theme: 'plain', styles: { fontSize: 9, cellPadding: 2 }, columnStyles: { 0: { fontStyle: 'bold', cellWidth: 35 }, 1: { cellWidth: 140 } }, margin: { left: 20 } });
            y = doc.lastAutoTable.finalY + 10;

            doc.setFontSize(12); doc.setTextColor(27, 67, 50); doc.text('Historial de Consultas', 20, y);
            y += 5;
            if (consultations.length === 0) { doc.setFontSize(9); doc.text('No hay consultas registradas.', 25, y); y += 10; } 
            else {
                const consData = consultations.map(c => [ c.consultation_date ? new Date(c.consultation_date).toLocaleDateString('es-ES') : '—', c.reason || '—', c.diagnosis || '—', c.treatment || '—', c.vet_name || 'N/A' ]);
                doc.autoTable({ startY: y, head: [['Fecha', 'Motivo', 'Diagnóstico', 'Tratamiento', 'Veterinario']], body: consData, theme: 'grid', headStyles: { fillColor: [27, 67, 50], textColor: 255 }, styles: { fontSize: 8, cellPadding: 2 }, margin: { left: 20, right: 20 } });
                y = doc.lastAutoTable.finalY + 10;
            }

            if (vaccinations.length > 0) {
                doc.setFontSize(12); doc.setTextColor(27, 67, 50); doc.text('Vacunas Aplicadas', 20, y);
                y += 5;
                const vacData = vaccinations.map(v => { const appDate = v.application_date ? new Date(v.application_date).toLocaleDateString('es-ES') : '—'; const nextDate = v.next_dose_date ? new Date(v.next_dose_date).toLocaleDateString('es-ES') : '—'; return [v.vaccine_name || 'Vacuna', appDate, nextDate]; });
                doc.autoTable({ startY: y, head: [['Vacuna', 'Fecha Aplicación', 'Próxima Dosis']], body: vacData, theme: 'grid', headStyles: { fillColor: [27, 67, 50], textColor: 255 }, styles: { fontSize: 8 }, margin: { left: 20, right: 20 } });
                y = doc.lastAutoTable.finalY + 10;
            }

            if (treatments.length > 0) {
                doc.setFontSize(12); doc.setTextColor(27, 67, 50); doc.text('Tratamientos / Prescripciones', 20, y);
                y += 5;
                const treatData = treatments.map(t => { const start = t.start_date ? new Date(t.start_date).toLocaleDateString('es-ES') : '—'; const end = t.end_date ? new Date(t.end_date).toLocaleDateString('es-ES') : '—'; return [t.description || 'Sin descripción', start, end]; });
                doc.autoTable({ startY: y, head: [['Descripción', 'Inicio', 'Fin']], body: treatData, theme: 'grid', headStyles: { fillColor: [27, 67, 50], textColor: 255 }, styles: { fontSize: 8 }, margin: { left: 20, right: 20 } });
                y = doc.lastAutoTable.finalY + 10;
            }

            doc.setFontSize(8); doc.setTextColor(100,100,100); doc.text('Documento generado electrónicamente. Validez clínica respaldada por VetCtrl.', 20, 280);
            doc.setFont('helvetica', 'italic'); doc.text('VetCtrl', 180, 280);
            doc.save(`Expediente_${(pet.name || 'mascota').replace(/[^a-z0-9]/gi,'_')}.pdf`);
        });
    </script>
</body>
</html>
