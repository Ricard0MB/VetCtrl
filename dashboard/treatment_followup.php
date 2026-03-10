<?php
session_start();

// Habilitar la visualización de errores solo durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit; // ¡Error corregido aquí!
}

// Variables necesarias para la navbar y la lógica
$username = $_SESSION["username"] ?? 'Veterinario'; 
$user_id = $_SESSION['user_id'] ?? 0;
$owner_id = $user_id; // El ID del veterinario que registró el tratamiento

require_once '../includes/config.php';

$active_treatments = [];
$message = '';


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    
    
    $treatment_id = trim($_POST['treatment_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    
    
    if (is_numeric($treatment_id) && in_array($new_status, ['ACTIVO', 'COMPLETADO', 'PAUSADO'])) {
        
        // Se valida que solo el veterinario que registró el tratamiento pueda actualizarlo
        $sql_update = "UPDATE treatments SET status = ? WHERE id = ? AND attendant_id = ?";
        
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sii", $new_status, $treatment_id, $owner_id);
            
            if ($stmt_update->execute()) {
                // Uso de etiquetas HTML dentro de PHP para el mensaje de éxito
                $message = "<p class='success-message'>✅ Estado del tratamiento actualizado a <strong>{$new_status}</strong>.</p>";
            } else {
                $message = "<p class='error-message'>Error al actualizar el estado: " . $stmt_update->error . "</p>";
            }
            $stmt_update->close();
        } else {
            $message = "<p class='error-message'>Error de preparación de la consulta UPDATE: " . $conn->error . "</p>";
        }
    } else {
        $message = "<p class='error-message'>Datos inválidos para la actualización de estado.</p>";
    }
}


// Consulta para obtener SOLO los tratamientos ACTIVO asociados a este veterinario
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
            WHERE t.attendant_id = ? AND t.status = 'ACTIVO'
            ORDER BY t.start_date DESC"; 
            
if ($stmt_select = $conn->prepare($sql_select)) {
    $stmt_select->bind_param("i", $owner_id);
    
    if ($stmt_select->execute()) {
        $result = $stmt_select->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $active_treatments[] = $row;
        }
        $result->free();
        
        if (empty($active_treatments)) {
            $message .= "<p class='info-message'>No hay tratamientos activos actualmente.</p>";
        }
        
    } else {
        $message .= "<p class='error-message'>Error al ejecutar la consulta de selección: " . $stmt_select->error . "</p>";
    }
    $stmt_select->close();
} else {
    $message .= "<p class='error-message'>Error de preparación de la consulta SELECT: " . $conn->error . "</p>";
}

if (isset($conn)) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Seguimiento de Tratamientos Activos</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 60px; /* Espacio para la navbar fija */
        }
        .dashboard-container {
            padding: 40px 20px;
        }
        .main-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #1b4332;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            text-align: center;
        }
        .treatment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .treatment-table th, .treatment-table td { 
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
            vertical-align: top;
            font-size: 0.9em;
        }
        .treatment-table th {
            background-color: #40916c; /* Color más fuerte para cabecera */
            color: white;
            font-weight: 600;
        }
        .treatment-table tr:hover {
            background-color: #f1f8f0; /* Color suave al pasar el ratón */
        }
        .status-tag {
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: bold;
            display: inline-block;
            font-size: 0.85em;
        }
        .status-ACTIVO { background-color: #ffe599; color: #856404; }
        .status-COMPLETADO { background-color: #d4edda; color: #155724; }
        .status-PAUSADO { background-color: #f8d7da; color: #721c24; }
        .info-message, .success-message, .error-message {
             margin-top: 20px;
             text-align: center;
             padding: 15px;
             border-radius: 4px;
             font-weight: 500;
        }
        .info-message {
            background-color: #e3f2fd; border: 1px solid #b3e5fc; color: #01579b;
        }
        .success-message {
            background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724;
        }
        .error-message {
            background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
        }
        .action-form {
            display: flex;
            flex-direction: column; /* Cambiar a columna para mejor vista en celda */
            align-items: flex-start;
            gap: 5px;
        }
        .action-form select {
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 0.8em;
            width: 100%;
            background-color: #2d6a4f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-small:hover {
            background-color: #1b4332;
        }
        .btn-pdf {
            background-color: #C0392B; /* Rojo de PDF */
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }
        .btn-pdf:hover {
            background-color: #A93226;
        }
    </style>
    <!-- Incluir librerías jsPDF y jspdf-autotable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>
    <div class="dashboard-container">
        <div class="main-content">
            <h1>Seguimiento de Tratamientos Activos 🩺</h1>
            <p style="text-align: center;">Lista de tratamientos actualmente en curso.</p>
            
            <div style="text-align: center;">
                <a href="treatment_select_pet.php" class="btn-primary" style="display: inline-block; margin: 10px; text-decoration: none;">+ Iniciar Nuevo Tratamiento</a>
                <button onclick="exportPDF()" class="btn-pdf">⬇️ Exportar a PDF</button>
            </div>

            <?php echo $message; ?>

            <?php if (!empty($active_treatments)): ?>
                <table class="treatment-table" id="treatmentTable">
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Tratamiento</th>
                            <th>Medicación</th>
                            <th>Inicio/Fin Estimado</th>
                            <th>Estado Actual</th>
                            <th>Acción</th> <!-- Esta columna se omite en el PDF -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_treatments as $treatment): ?>
                        <tr>
                            <td>
                                <strong><a href="pet_profile.php?id=<?php echo $treatment['pet_id']; ?>"><?php echo htmlspecialchars($treatment['pet_name']); ?></a></strong>
                                <br><small>
                                    <?php 
                                        
                                        echo htmlspecialchars($treatment['pet_species_name'] ?? 'Desconocida'); 
                                        
                                        
                                        if (!empty($treatment['pet_breed_name'])) {
                                            echo ' (' . htmlspecialchars($treatment['pet_breed_name']) . ')';
                                        }
                                    ?>
                                </small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($treatment['title']); ?></strong>
                                <br><small>Diagnóstico: <?php echo nl2br(htmlspecialchars(substr($treatment['diagnosis'], 0, 100))) . (strlen($treatment['diagnosis']) > 100 ? '...' : ''); ?></small>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($treatment['medication_details'])); ?></td>
                            <td>
                                **Inicio:** <?php echo date('d/m/Y', strtotime($treatment['start_date'])); ?>
                                <br>**Fin Est.:** <?php echo $treatment['end_date'] ? date('d/m/Y', strtotime($treatment['end_date'])) : 'N/D'; ?>
                            </td>
                            <td>
                                <span class="status-tag status-<?php echo htmlspecialchars($treatment['status']); ?>">
                                    <?php echo htmlspecialchars($treatment['status']); ?>
                                </span>
                            </td>
                            <td>
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
            <?php endif; ?>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>

    <script>
        // Necesitamos la función window.jspdf.jsPDF para inicializar
        const { jsPDF } = window.jspdf;

        // Usa una función personalizada en lugar de alert/confirm para notificaciones
        function showMessage(type, text) {
            // Ejemplo de implementación simple para mostrar mensajes, puedes usar un modal más sofisticado si lo prefieres
            console.log(`[${type.toUpperCase()}] ${text}`);
            const messageArea = document.querySelector('.main-content');
            if (messageArea) {
                const tempDiv = document.createElement('div');
                tempDiv.className = `${type}-message`;
                tempDiv.style.marginTop = '20px';
                tempDiv.style.textAlign = 'center';
                tempDiv.style.padding = '15px';
                tempDiv.style.borderRadius = '4px';
                tempDiv.style.fontWeight = '500';
                tempDiv.innerHTML = text;
                messageArea.insertBefore(tempDiv, messageArea.querySelector('table') || messageArea.querySelector('h1').nextSibling);
                setTimeout(() => tempDiv.remove(), 5000); // Eliminar después de 5 segundos
            }
        }

        function exportPDF() {
            try {
                const doc = new jsPDF('l', 'mm', 'a4'); // 'l' para orientación horizontal (Landscape)
                const table = document.getElementById('treatmentTable');

                if (!table) {
                    showMessage('error', 'Error: La tabla de tratamientos no se encontró para exportar.');
                    return;
                }

                // 1. Título y Metadata
                const title = 'Reporte de Tratamientos Activos';
                doc.setFontSize(18);
                doc.text(title, 14, 20); // (x, y)

                doc.setFontSize(10);
                doc.text(`Generado por: <?php echo htmlspecialchars($username); ?>`, 14, 28);
                doc.text(`Fecha: ${new Date().toLocaleDateString('es-ES')}`, 14, 34);

                // 2. Preparar los datos
                // Se omite la última columna "Acción"
                const headers = ['Paciente', 'Tratamiento', 'Medicación', 'Inicio/Fin Est.', 'Estado Actual'];
                const data = [];

                // Iterar sobre las filas del cuerpo de la tabla (omitiendo la última columna 'Acción')
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    // Solo tomamos las primeras 5 celdas 
                    const rowData = [];

                    for (let i = 0; i < 5; i++) {
                        const cell = cells[i];
                        // Limpiamos el texto para el PDF, eliminando saltos de línea y enlaces
                        let text = cell.innerText.trim();
                            
                        // Para la columna de Inicio/Fin, reemplazamos **Inicio:** y **Fin Est.:** que no se ven en innerText
                        // Pero aseguramos una mejor presentación de la fecha
                        if (i === 3) { // Columna de Inicio/Fin Estimado
                            // Reemplazamos los saltos de línea por espacios o comas para que se muestre en una línea o con salto de línea en autoTable
                            text = text.replace(/\n/g, ' / ');
                        } else if (i === 4) { // Estado Actual
                            // Solo tomamos el texto del tag, que es el estado en mayúsculas
                            const statusTag = cell.querySelector('.status-tag');
                            if (statusTag) {
                                text = statusTag.innerText.trim();
                            }
                        }
                        
                        rowData.push(text);
                    }
                    data.push(rowData);
                });
                
                if (data.length === 0) {
                    showMessage('info', 'No hay datos de tratamientos activos para exportar a PDF.');
                    return;
                }

                // 3. Generar la tabla con jspdf-autotable
                doc.autoTable({
                    head: [headers],
                    body: data,
                    startY: 40,
                    theme: 'striped',
                    headStyles: { 
                        fillColor: [64, 145, 108], // #40916c
                        textColor: 255, 
                        fontStyle: 'bold' 
                    },
                    styles: {
                        fontSize: 9,
                        cellPadding: 2,
                        overflow: 'linebreak'
                    },
                    columnStyles: {
                        // Ancho de columnas para el formato A4 horizontal
                        0: { cellWidth: 40 }, // Paciente
                        1: { cellWidth: 70 }, // Tratamiento
                        2: { cellWidth: 100 }, // Medicación
                        3: { cellWidth: 35 }, // Inicio/Fin
                        4: { cellWidth: 20 }, // Estado
                    }
                });

                // 4. Guardar el PDF
                doc.save('Tratamientos_Activos_<?php echo date('Ymd'); ?>.pdf');
                showMessage('success', '✅ PDF generado exitosamente. La descarga debería comenzar en breve.');


            } catch (e) {
                console.error("Error al generar el PDF:", e);
                // Usamos la función de mensaje personalizada para evitar alert()
                showMessage('error', 'Ocurrió un error al generar el PDF. Revisa la consola para más detalles.');
            }
        }
    </script>

</body>
</html>