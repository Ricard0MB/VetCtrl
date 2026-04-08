<?php
session_start();

// Habilitar la visualización de errores solo durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

// Variables necesarias para la navbar y la lógica
$username = $_SESSION["username"] ?? 'Veterinario'; 
$user_id = $_SESSION['user_id'] ?? 0;
$owner_id = $user_id; // El ID del veterinario que registró el tratamiento

require_once '../includes/config.php'; // $conn es un objeto PDO

$active_treatments = [];
$message = '';

// Procesar actualización de estado
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    
    $treatment_id = $_POST['treatment_id'] ?? 0;
    $new_status = $_POST['new_status'] ?? '';
    
    if (is_numeric($treatment_id) && in_array($new_status, ['ACTIVO', 'COMPLETADO', 'PAUSADO'])) {
        try {
            // Se valida que solo el veterinario que registró el tratamiento pueda actualizarlo
            $sql_update = "UPDATE treatments SET status = :status WHERE id = :id AND attendant_id = :attendant_id";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bindValue(':status', $new_status);
            $stmt_update->bindValue(':id', $treatment_id, PDO::PARAM_INT);
            $stmt_update->bindValue(':attendant_id', $owner_id, PDO::PARAM_INT);
            
            if ($stmt_update->execute()) {
                $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Estado del tratamiento actualizado a <strong>{$new_status}</strong>.</div>";
            } else {
                $errorInfo = $stmt_update->errorInfo();
                $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al actualizar el estado: " . $errorInfo[2] . "</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error de base de datos: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Datos inválidos para la actualización de estado.</div>";
    }
}

// Consulta para obtener SOLO los tratamientos ACTIVO asociados a este veterinario
try {
    $sql_select = "SELECT 
                    t.id, t.title, t.diagnosis, t.medication_details, t.start_date, t.end_date, t.status, 
                    p.name as pet_name,
                    p.id as pet_id,
                    pt.name AS pet_species_name,
                    b.name AS pet_breed_name       
                FROM treatments t
                JOIN pets p ON t.pet_id = p.id
                LEFT JOIN pet_types pt ON p.type_id = pt.id 
                LEFT JOIN breeds b ON p.breed_id = b.id        
                WHERE t.attendant_id = :attendant_id AND t.status = 'ACTIVO'
                ORDER BY t.start_date DESC";
    
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bindValue(':attendant_id', $owner_id, PDO::PARAM_INT);
    $stmt_select->execute();
    
    $active_treatments = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($active_treatments)) {
        $message .= "<div class='alert alert-info'><i class='fas fa-info-circle'></i> No hay tratamientos activos actualmente.</div>";
    }
    
} catch (PDOException $e) {
    $message .= "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar tratamientos: " . $e->getMessage() . "</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seguimiento de Tratamientos Activos - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
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
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
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
        .alert-success {
            background: #e0f2e9;
            color: #1e7b4a;
            border-left-color: #1e7b4a;
        }
        .alert-info {
            background: #e0f2fe;
            color: #0369a1;
            border-left-color: #0ea5e9;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #dc3545;
        }
        .action-bar {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-pdf {
            background: var(--accent);
            color: white;
            padding: 10px 24px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            margin-top: 20px;
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
        .status-ACTIVO {
            background: #ffe599;
            color: #856404;
        }
        .action-form {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .action-form select {
            padding: 8px;
            border-radius: 40px;
            border: 2px solid #e2e8f0;
            background: white;
        }
        .btn-small {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-small:hover {
            background: var(--primary-dark);
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .treatment-table, .treatment-table thead, .treatment-table tbody, .treatment-table th, .treatment-table td, .treatment-table tr {
                display: block;
            }
            .treatment-table th { display: none; }
            .treatment-table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #eef2f8;
            }
            .treatment-table td::before {
                content: attr(data-label);
                font-weight: 600;
                width: 40%;
                color: var(--primary-dark);
            }
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Seguimiento de Tratamientos</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-chart-line"></i> Seguimiento de Tratamientos Activos</h1>
            <p style="text-align: center; color: #5b6e8c;">Lista de tratamientos actualmente en curso.</p>
            
            <div class="action-bar">
                <a href="treatment_select_pet.php" class="btn-primary"><i class="fas fa-plus"></i> Iniciar Nuevo Tratamiento</a>
                <button onclick="exportPDF()" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar a PDF</button>
            </div>

            <?php echo $message; ?>

            <?php if (!empty($active_treatments)): ?>
                <div style="overflow-x: auto;">
                    <table class="treatment-table" id="treatmentTable">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Tratamiento</th>
                                <th>Medicación</th>
                                <th>Inicio/Fin Estimado</th>
                                <th>Estado Actual</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_treatments as $treatment): ?>
                            <tr>
                                <td data-label="Paciente">
                                    <strong><a href="pet_profile.php?id=<?php echo $treatment['pet_id']; ?>" style="color: var(--primary);"><?php echo htmlspecialchars($treatment['pet_name']); ?></a></strong>
                                    <br><small>
                                        <?php 
                                            echo htmlspecialchars($treatment['pet_species_name'] ?? 'Desconocida'); 
                                            if (!empty($treatment['pet_breed_name'])) {
                                                echo ' (' . htmlspecialchars($treatment['pet_breed_name']) . ')';
                                            }
                                        ?>
                                    </small>
                                </td>
                                <td data-label="Tratamiento">
                                    <strong><?php echo htmlspecialchars($treatment['title']); ?></strong>
                                    <br><small>Diagnóstico: <?php echo nl2br(htmlspecialchars(substr($treatment['diagnosis'], 0, 100))) . (strlen($treatment['diagnosis']) > 100 ? '...' : ''); ?></small>
                                </td>
                                <td data-label="Medicación"><?php echo nl2br(htmlspecialchars($treatment['medication_details'])); ?></td>
                                <td data-label="Inicio/Fin">
                                    <strong>Inicio:</strong> <?php echo date('d/m/Y', strtotime($treatment['start_date'])); ?>
                                    <br><strong>Fin Est.:</strong> <?php echo $treatment['end_date'] ? date('d/m/Y', strtotime($treatment['end_date'])) : 'N/D'; ?>
                                </td>
                                <td data-label="Estado">
                                    <span class="status-tag status-<?php echo htmlspecialchars($treatment['status']); ?>">
                                        <?php echo htmlspecialchars($treatment['status']); ?>
                                    </span>
                                </td>
                                <td data-label="Acción">
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="action-form">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="treatment_id" value="<?php echo $treatment['id']; ?>">
                                        <select name="new_status" required>
                                            <option value="">Cambiar Estado</option>
                                            <option value="COMPLETADO">Finalizar</option>
                                            <option value="PAUSADO">Pausar</option>
                                        </select>
                                        <button type="submit" class="btn-small">Actualizar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>

    <script>
        function exportPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('l', 'mm', 'a4');
                const table = document.getElementById('treatmentTable');

                if (!table) {
                    alert('Error: La tabla de tratamientos no se encontró para exportar.');
                    return;
                }

                const title = 'Reporte de Tratamientos Activos';
                doc.setFontSize(18);
                doc.text(title, 148, 20, { align: 'center' });

                doc.setFontSize(10);
                doc.text(`Generado por: <?php echo htmlspecialchars($username); ?>`, 148, 28, { align: 'center' });
                doc.text(`Fecha: ${new Date().toLocaleDateString('es-ES')}`, 148, 34, { align: 'center' });

                const headers = ['Paciente', 'Tratamiento', 'Medicación', 'Inicio/Fin Est.', 'Estado Actual'];
                const data = [];

                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    const rowData = [];
                    for (let i = 0; i < 5; i++) {
                        const cell = cells[i];
                        let text = cell.innerText.trim();
                        if (i === 3) {
                            text = text.replace(/\n/g, ' / ');
                        } else if (i === 4) {
                            const statusTag = cell.querySelector('.status-tag');
                            if (statusTag) text = statusTag.innerText.trim();
                        }
                        rowData.push(text);
                    }
                    data.push(rowData);
                });
                
                if (data.length === 0) {
                    alert('No hay datos de tratamientos activos para exportar.');
                    return;
                }

                doc.autoTable({
                    head: [headers],
                    body: data,
                    startY: 45,
                    theme: 'grid',
                    headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                    styles: { fontSize: 8, cellPadding: 2 },
                    columnStyles: {
                        0: { cellWidth: 35 },
                        1: { cellWidth: 50 },
                        2: { cellWidth: 70 },
                        3: { cellWidth: 35 },
                        4: { cellWidth: 25 },
                    }
                });

                doc.save('Tratamientos_Activos_<?php echo date('Ymd'); ?>.pdf');
            } catch (e) {
                console.error("Error al generar el PDF:", e);
                alert('Ocurrió un error al generar el PDF.');
            }
        }
    </script>
</body>
</html>
