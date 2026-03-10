<?php
session_start();
require_once '../includes/config.php';

// Verificar permisos
$user_role = $_SESSION['role_name'] ?? '';
if (!in_array($user_role, ['Veterinario', 'admin'])) {
    header("Location: welcome.php");
    exit;
}

// Obtener parámetros
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Configurar para impresión/PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Diario PDF - VetControl</title>
    <style>
        /* Estilos optimizados para impresión/PDF */
        @media print {
            @page {
                size: letter;
                margin: 20mm;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                font-size: 12px;
                line-height: 1.4;
                color: #000;
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
        
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            max-width: 210mm;
            margin: 0 auto;
            padding: 10mm;
            background: white;
        }
        
        /* Encabezado */
        .report-header {
            text-align: center;
            border-bottom: 3px solid #1b4332;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .report-header h1 {
            color: #1b4332;
            font-size: 24px;
            margin: 0 0 10px 0;
        }
        
        .report-header .subtitle {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        
        /* Información del reporte */
        .report-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #40916c;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #1b4332;
            font-size: 12px;
        }
        
        .info-value {
            color: #000;
            font-size: 13px;
        }
        
        /* Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-box {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            text-align: center;
            background: white;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #1b4332;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Tablas */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        
        .data-table th {
            background: #1b4332;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .data-table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
        }
        
        .data-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        /* Secciones */
        .section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #40916c;
            color: white;
            padding: 10px 15px;
            margin: 0;
            font-size: 14px;
            border-radius: 4px 4px 0 0;
        }
        
        .section-content {
            border: 1px solid #ddd;
            border-top: none;
            padding: 15px;
            border-radius: 0 0 4px 4px;
        }
        
        /* Footer */
        .report-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        .footer-info {
            margin: 5px 0;
        }
        
        /* Estados */
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .status-completed { background: #d1e7dd; color: #0f5132; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #842029; }
        
        /* Controles de impresión (solo para vista previa) */
        .print-controls {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 30px;
            border: 2px dashed #40916c;
        }
        
        .print-btn {
            background: #40916c;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin: 0 10px;
        }
        
        .print-btn:hover {
            background: #2d6a4f;
        }
        
        /* Responsive */
        @media screen and (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media screen and (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <script>
        function printReport() {
            window.print();
        }
        
        function closeWindow() {
            window.history.back();
        }
        
        // Auto-print cuando se carga para PDF
        window.onload = function() {
            // Si viene de exportación PDF, auto-imprimir
            if (window.location.search.includes('export=pdf')) {
                setTimeout(function() {
                    window.print();
                    // Después de imprimir, cerrar si es ventana emergente
                    setTimeout(function() {
                        if (window.opener) {
                            window.close();
                        }
                    }, 1000);
                }, 500);
            }
        };
    </script>
</head>
<body>
    <!-- Controles de impresión (solo en vista previa) -->
    <?php if (!isset($_GET['export']) || $_GET['export'] !== 'pdf'): ?>
    <div class="print-controls no-print">
        <h3>Vista Previa para Impresión/PDF</h3>
        <p>Esta vista está optimizada para impresión. Haga clic en "Imprimir" para generar el PDF.</p>
        <button class="print-btn" onclick="printReport()">🖨️ Imprimir Reporte</button>
        <button class="print-btn" onclick="closeWindow()" style="background: #6c757d;">← Volver</button>
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            <strong>Consejo:</strong> En la ventana de impresión, seleccione "Guardar como PDF" para descargar.
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Encabezado del Reporte -->
    <div class="report-header">
        <h1>📊 REPORTE DE ACTIVIDAD DIARIA</h1>
        <div class="subtitle">Clínica Veterinaria VetControl</div>
        <div class="subtitle">Sistema de Gestión Veterinaria</div>
    </div>
    
    <!-- Información del Reporte -->
    <div class="report-info">
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Período del Reporte:</div>
                <div class="info-value">
                    <?php echo date('d/m/Y', strtotime($start_date)); ?> 
                    <?php echo ($start_date !== $end_date) ? ' al ' . date('d/m/Y', strtotime($end_date)) : ''; ?>
                </div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Fecha de Generación:</div>
                <div class="info-value"><?php echo date('d/m/Y H:i:s'); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Generado por:</div>
                <div class="info-value"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Sistema'); ?></div>
            </div>
            
            <div class="info-item">
                <div class="info-label">Rol:</div>
                <div class="info-value"><?php echo htmlspecialchars($_SESSION['role_name'] ?? 'Usuario'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Estadísticas Resumidas -->
    <div class="section">
        <h2 class="section-title">📈 RESUMEN ESTADÍSTICO</h2>
        <div class="section-content">
            <?php
            // lógica para obtener estadísticas
            $stats = [
                ['Consultas', '15', '🩺'],
                ['Citas', '8', '📅'],
                ['Vacunas', '12', '💉'],
                ['Tratamientos', '5', '💊'],
                ['Mascotas Nuevas', '3', '🐾'],
                ['Clientes Nuevos', '2', '👥']
            ];
            ?>
            
            <div class="stats-grid">
                <?php foreach ($stats as $stat): ?>
                <div class="stat-box">
                    <div class="stat-label"><?php echo $stat[0]; ?></div>
                    <div class="stat-number"><?php echo $stat[1]; ?></div>
                    <div><?php echo $stat[2]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <div style="display: inline-block; padding: 15px 30px; background: #f8f9fa; border-radius: 8px; border: 2px solid #1b4332;">
                    <div style="font-size: 11px; color: #666; margin-bottom: 5px;">TOTAL DE ACTIVIDADES</div>
                    <div style="font-size: 28px; font-weight: bold; color: #1b4332;">45</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sección de Consultas -->
    <div class="section">
        <h2 class="section-title">🩺 CONSULTAS REALIZADAS</h2>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Paciente</th>
                        <th>Motivo</th>
                        <th>Veterinario</th>
                        <th>Diagnóstico</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>15/12/2023 09:30</td>
                        <td><strong>Luna</strong></td>
                        <td>Control rutinario</td>
                        <td>Dr. Pérez</td>
                        <td>Saludable, vacunar en 6 meses</td>
                    </tr>
                    <tr>
                        <td>15/12/2023 11:15</td>
                        <td><strong>Max</strong></td>
                        <td>Dolor en pata</td>
                        <td>Dr. Pérez</td>
                        <td>Esguince, reposo 1 semana</td>
                    </tr>
                    <!-- Más filas de ejemplo -->
                </tbody>
            </table>
            
            <div style="margin-top: 15px; font-size: 11px; color: #666; text-align: right;">
                Total: <strong>15 consultas</strong> realizadas en el período
            </div>
        </div>
    </div>
    
    <!-- Sección de Citas -->
    <div class="section">
        <h2 class="section-title">📅 CITAS PROGRAMADAS</h2>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Paciente</th>
                        <th>Dueño</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>16/12/2023 10:00</td>
                        <td><strong>Toby</strong></td>
                        <td>María González</td>
                        <td>Vacunación anual</td>
                        <td><span class="status status-pending">PENDIENTE</span></td>
                    </tr>
                    <tr>
                        <td>16/12/2023 14:30</td>
                        <td><strong>Michi</strong></td>
                        <td>Carlos Ruiz</td>
                        <td>Control post-operatorio</td>
                        <td><span class="status status-completed">CONFIRMADA</span></td>
                    </tr>
                    <!-- Más filas de ejemplo -->
                </tbody>
            </table>
            
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <div style="font-size: 11px; color: #666; margin-bottom: 5px;">Distribución por Estado:</div>
                <div style="display: flex; gap: 15px; font-size: 11px;">
                    <div><span class="status status-completed" style="margin-right: 5px;"></span> Confirmadas: <strong>5</strong></div>
                    <div><span class="status status-pending" style="margin-right: 5px;"></span> Pendientes: <strong>3</strong></div>
                    <div><span class="status status-cancelled" style="margin-right: 5px;"></span> Canceladas: <strong>0</strong></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sección de Vacunas -->
    <div class="section">
        <h2 class="section-title">💉 VACUNAS APLICADAS</h2>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Paciente</th>
                        <th>Vacuna</th>
                        <th>Lote</th>
                        <th>Aplicada por</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>15/12/2023</td>
                        <td><strong>Rex</strong></td>
                        <td>Rabia</td>
                        <td>LOT-2023-45</td>
                        <td>Dr. López</td>
                    </tr>
                    <tr>
                        <td>15/12/2023</td>
                        <td><strong>Bella</strong></td>
                        <td>Moquillo</td>
                        <td>LOT-2023-46</td>
                        <td>Dra. Martínez</td>
                    </tr>
                    <!-- Más filas de ejemplo -->
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Sección de Tratamientos -->
    <div class="section">
        <h2 class="section-title">💊 TRATAMIENTOS INICIADOS</h2>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha Inicio</th>
                        <th>Paciente</th>
                        <th>Tratamiento</th>
                        <th>Estado</th>
                        <th>Veterinario</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>15/12/2023</td>
                        <td><strong>Luna</strong></td>
                        <td>Antibiótico para infección</td>
                        <td><span class="status status-pending">ACTIVO</span></td>
                        <td>Dr. Pérez</td>
                    </tr>
                    <!-- Más filas de ejemplo -->
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Sección de Nuevos Registros -->
    <div class="section">
        <h2 class="section-title">📈 NUEVOS REGISTROS</h2>
        <div class="section-content">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h3 style="font-size: 13px; color: #1b4332; margin-top: 0;">Mascotas Nuevas (3)</h3>
                    <div style="font-size: 11px;">
                        <div style="padding: 8px; border-bottom: 1px solid #eee;">
                            <strong>Rocky</strong> (Perro) • Dueño: Ana Torres
                        </div>
                        <div style="padding: 8px; border-bottom: 1px solid #eee;">
                            <strong>Simba</strong> (Gato) • Dueño: José Mendoza
                        </div>
                        <div style="padding: 8px;">
                            <strong>Lola</strong> (Perro) • Dueño: Carla Rojas
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 style="font-size: 13px; color: #1b4332; margin-top: 0;">Clientes Nuevos (2)</h3>
                    <div style="font-size: 11px;">
                        <div style="padding: 8px; border-bottom: 1px solid #eee;">
                            <strong>Roberto Sánchez</strong><br>
                            roberto@email.com
                        </div>
                        <div style="padding: 8px;">
                            <strong>Laura Fernández</strong><br>
                            laura@email.com
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pie del Reporte -->
    <div class="report-footer">
        <div class="footer-info">
            <strong>Clínica Veterinaria VetControl</strong>
        </div>
        <div class="footer-info">
            Av. Principal, Centro Comercial Plaza, Piso 3 • Tel: (0412) 123-4567
        </div>
        <div class="footer-info">
            Email: info@vetcontrol.com • Web: www.vetcontrol.com
        </div>
        <div class="footer-info">
            Reporte generado automáticamente por el sistema VetControl
        </div>
        <div class="footer-info" style="margin-top: 15px;">
            Página 1 de 1
        </div>
    </div>
    
    <!-- Instrucciones de impresión -->
    <div class="print-controls no-print" style="margin-top: 30px;">
        <h4>Instrucciones para guardar como PDF:</h4>
        <ol style="text-align: left; max-width: 600px; margin: 10px auto; font-size: 12px;">
            <li>Haga clic en <strong>"Imprimir Reporte"</strong></li>
            <li>En la ventana de impresión, seleccione <strong>"Guardar como PDF"</strong> como impresora</li>
            <li>Configure los márgenes a <strong>"Mínimo"</strong></li>
            <li>Active la opción <strong>"Fondo de gráficos"</strong></li>
            <li>Haga clic en <strong>"Guardar"</strong> y seleccione la ubicación</li>
        </ol>
    </div>
</body>
</html>
<?php
// Registrar generación de PDF en bitácora
if (file_exists('../includes/bitacora_function.php')) {
    require_once '../includes/bitacora_function.php';
    if (function_exists('register_log')) {
        $action_text = "Reporte diario PDF generado: " . $start_date . " al " . $end_date;
        register_log($action_text);
    }
}

$conn->close();
?>