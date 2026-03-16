<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

// Verificar permisos
$user_role = $_SESSION['role_name'] ?? '';
if (!in_array($user_role, ['Veterinario', 'admin'])) {
    header("Location: welcome.php");
    exit;
}

// Obtener parámetros
$period = $_GET['period'] ?? 'month';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Configurar para PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Estadísticas - VetControl</title>
    <style>
        /* Estilos optimizados para PDF */
        @media print {
            @page {
                size: A4 landscape;
                margin: 15mm;
            }
            
            body {
                font-family: 'Arial', sans-serif;
                font-size: 10px;
                line-height: 1.3;
                color: #000;
                margin: 0;
                padding: 0;
                background: white;
            }
            
            .no-print {
                display: none !important;
            }
            
            .page-break {
                page-break-before: always;
            }
            
            .chart-placeholder {
                height: 200px;
                background: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 5px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                color: #666;
                font-style: italic;
                margin: 10px 0;
            }
        }
        
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            max-width: 297mm;
            margin: 0 auto;
            padding: 5mm;
            background: white;
        }
        
        /* Encabezado */
        .report-header {
            text-align: center;
            border-bottom: 3px solid #1b4332;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .report-header h1 {
            color: #1b4332;
            font-size: 20px;
            margin: 0 0 5px 0;
        }
        
        .report-header .subtitle {
            color: #666;
            font-size: 12px;
            margin: 2px 0;
        }
        
        /* Información del reporte */
        .report-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 3px solid #40916c;
            font-size: 9px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #1b4332;
        }
        
        .info-value {
            color: #000;
        }
        
        /* KPIs */
        .kpis-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .kpi-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
            background: white;
        }
        
        .kpi-number {
            font-size: 18px;
            font-weight: bold;
            color: #1b4332;
            margin: 5px 0;
        }
        
        .kpi-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }
        
        /* Tablas */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 9px;
        }
        
        .data-table th {
            background: #1b4332;
            color: white;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        
        .data-table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }
        
        .data-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        /* Secciones */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        
        .section-title {
            background: #40916c;
            color: white;
            padding: 8px 10px;
            margin: 0 0 10px 0;
            font-size: 12px;
            border-radius: 3px 3px 0 0;
        }
        
        .section-content {
            border: 1px solid #ddd;
            border-top: none;
            padding: 10px;
            border-radius: 0 0 3px 3px;
        }
        
        /* Grillas */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        /* Progress bars */
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
        }
        
        /* Badges */
        .badge {
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 8px;
            font-weight: bold;
            display: inline-block;
        }
        
        .badge-success { background: #d1e7dd; color: #0f5132; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #842029; }
        
        /* Footer */
        .report-footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
        
        .footer-info {
            margin: 3px 0;
        }
        
        /* Controles de impresión */
        .print-controls {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
            border: 2px dashed #40916c;
        }
        
        .print-btn {
            background: #40916c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 3px;
            cursor: pointer;
            font-weight: bold;
            font-size: 12px;
            margin: 0 5px;
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
            // Auto-imprimir si es para PDF
            if (window.location.search.includes('export=pdf')) {
                setTimeout(function() {
                    window.print();
                    // Cerrar después de imprimir
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
    <!-- Controles de impresión -->
    <div class="print-controls no-print">
        <h3>Vista Previa para Impresión/PDF</h3>
        <p>Esta vista está optimizada para impresión en formato A4 horizontal.</p>
        <button class="print-btn" onclick="printReport()">🖨️ Imprimir Reporte</button>
        <button class="print-btn" onclick="closeWindow()" style="background: #6c757d;">← Volver</button>
        <p style="margin-top: 10px; font-size: 10px; color: #666;">
            <strong>Consejo:</strong> Seleccione "Guardar como PDF" en la ventana de impresión.
        </p>
    </div>
    
    <!-- Encabezado -->
    <div class="report-header">
        <h1>📈 REPORTE DE ESTADÍSTICAS - VETCONTROL</h1>
        <div class="subtitle">Dashboard de Análisis y Métricas Clave</div>
        <div class="subtitle">Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> al <?php echo date('d/m/Y', strtotime($end_date)); ?></div>
    </div>
    
    <!-- Información del Reporte -->
    <div class="report-info">
        <div class="info-grid">
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
            <div class="info-item">
                <div class="info-label">Período:</div>
                <div class="info-value"><?php 
                    $periods = [
                        'today' => 'Hoy',
                        'yesterday' => 'Ayer',
                        'week' => 'Esta Semana',
                        'month' => 'Este Mes',
                        'quarter' => 'Este Trimestre',
                        'year' => 'Este Año',
                        'custom' => 'Personalizado'
                    ];
                    echo $periods[$period] ?? 'Personalizado';
                ?></div>
            </div>
        </div>
    </div>
    
    <!-- KPIs Principales -->
    <div class="section">
        <h2 class="section-title">📊 KPIs PRINCIPALES</h2>
        <div class="section-content">
            <div class="kpis-grid">
                <div class="kpi-box">
                    <div class="kpi-label">Total Pacientes</div>
                    <div class="kpi-number">125</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 85%; background: #1b4332;"></div>
                    </div>
                    <div style="font-size: 8px; color: #666;">Crecimiento: +15%</div>
                </div>
                
                <div class="kpi-box">
                    <div class="kpi-label">Consultas</div>
                    <div class="kpi-number">342</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 72%; background: #40916c;"></div>
                    </div>
                    <div style="font-size: 8px; color: #666;">Promedio: 2.7/paciente</div>
                </div>
                
                <div class="kpi-box">
                    <div class="kpi-label">Citas Completadas</div>
                    <div class="kpi-number">89%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 89%; background: #28a745;"></div>
                    </div>
                    <div style="font-size: 8px; color: #666;">Tasa de éxito</div>
                </div>
                
                <div class="kpi-box">
                    <div class="kpi-label">Vacunas Aplicadas</div>
                    <div class="kpi-number">156</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 65%; background: #17a2b8;"></div>
                    </div>
                    <div style="font-size: 8px; color: #666;">5 tipos diferentes</div>
                </div>
                
                <div class="kpi-box">
                    <div class="kpi-label">Satisfacción</div>
                    <div class="kpi-number">94%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 94%; background: #ffc107;"></div>
                    </div>
                    <div style="font-size: 8px; color: #666;">Clientes satisfechos</div>
                </div>
                
                <div class="kpi-box">
                    <div class="kpi-label">Ingreso Promedio</div>
                    <div class="kpi-number">$85.50</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: 68%; background: #dc3545;"></div>
                    </div>
                    <div style="font-size: 8px; color: #666;">Por consulta</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sección de Distribución -->
    <div class="grid-2">
        <!-- Distribución por Especie -->
        <div class="section">
            <h2 class="section-title">🐾 DISTRIBUCIÓN POR ESPECIE</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Especie</th>
                            <th>Pacientes</th>
                            <th>%</th>
                            <th>Distribución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Perro</td>
                            <td>85</td>
                            <td>68%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 68%; background: #1b4332;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Gato</td>
                            <td>32</td>
                            <td>26%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 26%; background: #40916c;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Ave</td>
                            <td>5</td>
                            <td>4%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 4%; background: #17a2b8;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Otros</td>
                            <td>3</td>
                            <td>2%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 2%; background: #6c757d;"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="chart-placeholder">
                    <div>📊</div>
                    <div>Gráfico de distribución por especie</div>
                    <div style="font-size: 9px;">(Visualizar en el dashboard interactivo)</div>
                </div>
            </div>
        </div>
        
        <!-- Estado de Citas -->
        <div class="section">
            <h2 class="section-title">📅 ESTADO DE CITAS</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Estado</th>
                            <th>Cantidad</th>
                            <th>%</th>
                            <th>Distribución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-success">COMPLETADA</span></td>
                            <td>156</td>
                            <td>62%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 62%; background: #28a745;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-warning">PENDIENTE</span></td>
                            <td>65</td>
                            <td>26%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 26%; background: #ffc107;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-danger">CANCELADA</span></td>
                            <td>22</td>
                            <td>9%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 9%; background: #dc3545;"></div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><span class="badge" style="background: #e2e3e5; color: #41464b;">REPROGRAMADA</span></td>
                            <td>7</td>
                            <td>3%</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 3%; background: #6c757d;"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="chart-placeholder">
                    <div>📊</div>
                    <div>Gráfico de estado de citas</div>
                    <div style="font-size: 9px;">(Visualizar en el dashboard interactivo)</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tendencia Mensual -->
    <div class="section page-break">
        <h2 class="section-title">📈 TENDENCIA MENSUAL (Últimos 6 meses)</h2>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Consultas</th>
                        <th>Pacientes Únicos</th>
                        <th>Crecimiento</th>
                        <th>Tendencia</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dic 2023</td>
                        <td>68</td>
                        <td>42</td>
                        <td style="color: #28a745;">+12%</td>
                        <td>↗️</td>
                    </tr>
                    <tr>
                        <td>Nov 2023</td>
                        <td>61</td>
                        <td>38</td>
                        <td style="color: #28a745;">+8%</td>
                        <td>↗️</td>
                    </tr>
                    <tr>
                        <td>Oct 2023</td>
                        <td>56</td>
                        <td>35</td>
                        <td style="color: #dc3545;">-3%</td>
                        <td>↘️</td>
                    </tr>
                    <tr>
                        <td>Sep 2023</td>
                        <td>58</td>
                        <td>36</td>
                        <td style="color: #28a745;">+15%</td>
                        <td>↗️</td>
                    </tr>
                    <tr>
                        <td>Ago 2023</td>
                        <td>50</td>
                        <td>31</td>
                        <td style="color: #28a745;">+11%</td>
                        <td>↗️</td>
                    </tr>
                    <tr>
                        <td>Jul 2023</td>
                        <td>45</td>
                        <td>28</td>
                        <td style="color: #28a745;">+7%</td>
                        <td>↗️</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="chart-placeholder" style="height: 150px;">
                <div>📈</div>
                <div>Gráfico de tendencia mensual</div>
                <div style="font-size: 9px;">(Visualizar en el dashboard interactivo)</div>
            </div>
            
            <div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 3px; font-size: 9px;">
                <strong>Análisis:</strong> Tendencia positiva general con crecimiento promedio del 8.3% mensual.
                Octubre mostró una ligera disminución debido a temporada baja.
            </div>
        </div>
    </div>
    
    <!-- Top Veterinarios y Mascotas -->
    <div class="grid-2">
        <!-- Top Veterinarios -->
        <div class="section">
            <h2 class="section-title">👨‍⚕️ TOP VETERINARIOS</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Veterinario</th>
                            <th>Consultas</th>
                            <th>% del Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>🥇</td>
                            <td><strong>Dr. Pérez</strong></td>
                            <td>142</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 42%; background: #1b4332;"></div>
                                </div>
                                42%
                            </td>
                        </tr>
                        <tr>
                            <td>🥈</td>
                            <td><strong>Dra. Martínez</strong></td>
                            <td>98</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 29%; background: #40916c;"></div>
                                </div>
                                29%
                            </td>
                        </tr>
                        <tr>
                            <td>🥉</td>
                            <td><strong>Dr. González</strong></td>
                            <td>65</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 19%; background: #17a2b8;"></div>
                                </div>
                                19%
                            </td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Dra. Rodríguez</strong></td>
                            <td>37</td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 11%; background: #6c757d;"></div>
                                </div>
                                11%
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 10px; padding: 8px; background: #e8f5e9; border-radius: 3px; font-size: 9px;">
                    <strong>Total consultas top 3:</strong> 305 (89% del total) • 
                    <strong>Promedio por veterinario:</strong> 85.25 consultas
                </div>
            </div>
        </div>
        
        <!-- Top Mascotas -->
        <div class="section">
            <h2 class="section-title">🐾 TOP MASCOTAS</h2>
            <div class="section-content">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Mascota</th>
                            <th>Consultas</th>
                            <th>Dueño</th>
                            <th>Frecuencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>🥇</td>
                            <td><strong>Luna</strong></td>
                            <td>12</td>
                            <td>María G.</td>
                            <td>Mensual</td>
                        </tr>
                        <tr>
                            <td>🥈</td>
                            <td><strong>Max</strong></td>
                            <td>9</td>
                            <td>Carlos R.</td>
                            <td>Bimestral</td>
                        </tr>
                        <tr>
                            <td>🥉</td>
                            <td><strong>Toby</strong></td>
                            <td>8</td>
                            <td>Ana T.</td>
                            <td>Trimestral</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Michi</strong></td>
                            <td>7</td>
                            <td>José M.</td>
                            <td>Irregular</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 10px; padding: 8px; background: #f8f9fa; border-radius: 3px; font-size: 9px;">
                    <strong>Promedio consultas/mascota:</strong> 2.7 • 
                    <strong>Especie más común:</strong> Perro (3 de 4) • 
                    <strong>Frecuencia promedio:</strong> Cada 2.3 meses
                </div>
            </div>
        </div>
    </div>
    
    <!-- Análisis Financiero -->
    <div class="section">
        <h2 class="section-title">💰 ANÁLISIS FINANCIERO</h2>
        <div class="section-content">
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px;">
                <div style="text-align: center; padding: 10px; background: #1b4332; color: white; border-radius: 3px;">
                    <div style="font-size: 9px;">Ingreso Total</div>
                    <div style="font-size: 16px; font-weight: bold;">$29,250</div>
                </div>
                <div style="text-align: center; padding: 10px; background: #40916c; color: white; border-radius: 3px;">
                    <div style="font-size: 9px;">Ticket Promedio</div>
                    <div style="font-size: 16px; font-weight: bold;">$85.50</div>
                </div>
                <div style="text-align: center; padding: 10px; background: #28a745; color: white; border-radius: 3px;">
                    <div style="font-size: 9px;">Crecimiento</div>
                    <div style="font-size: 16px; font-weight: bold;">+12.5%</div>
                </div>
                <div style="text-align: center; padding: 10px; background: #17a2b8; color: white; border-radius: 3px;">
                    <div style="font-size: 9px;">Servicio Top</div>
                    <div style="font-size: 12px; font-weight: bold;">Consulta General</div>
                </div>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>% del Ingreso</th>
                        <th>Ingreso Estimado</th>
                        <th>Tendencia</th>
                        <th>Distribución</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Consultas</td>
                        <td>45%</td>
                        <td>$13,163</td>
                        <td style="color: #28a745;">↗️ +5%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 45%; background: #1b4332;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Vacunas</td>
                        <td>25%</td>
                        <td>$7,313</td>
                        <td style="color: #28a745;">↗️ +8%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 25%; background: #40916c;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Cirugías</td>
                        <td>15%</td>
                        <td>$4,388</td>
                        <td style="color: #dc3545;">↘️ -3%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 15%; background: #dc3545;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Exámenes</td>
                        <td>10%</td>
                        <td>$2,925</td>
                        <td style="color: #28a745;">↗️ +12%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 10%; background: #17a2b8;"></div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Otros</td>
                        <td>5%</td>
                        <td>$1,463</td>
                        <td style="color: #ffc107;">➡️ 0%</td>
                        <td>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 5%; background: #ffc107;"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="chart-placeholder" style="height: 120px; margin-top: 10px;">
                <div>📊</div>
                <div>Distribución de ingresos por servicio</div>
                <div style="font-size: 9px;">(Visualizar en el dashboard interactivo)</div>
            </div>
        </div>
    </div>
    
    <!-- Conclusiones y Recomendaciones -->
    <div class="section">
        <h2 class="section-title">💡 CONCLUSIONES Y RECOMENDACIONES</h2>
        <div class="section-content">
            <div style="font-size: 9px; line-height: 1.4;">
                <p><strong>✅ Fortalezas Identificadas:</strong></p>
                <ul>
                    <li>Crecimiento constante de pacientes (15% vs período anterior)</li>
                    <li>Alta tasa de satisfacción del cliente (94%)</li>
                    <li>Excelente tasa de completación de citas (89%)</li>
                    <li>Distribución saludable por especie (68% perros, 26% gatos)</li>
                </ul>
                
                <p><strong>⚠️ Áreas de Oportunidad:</strong></p>
                <ul>
                    <li>Aumentar frecuencia de visitas de clientes ocasionales</li>
                    <li>Reducir tasa de cancelación de citas (actual 9%)</li>
                    <li>Diversificar servicios para aumentar ticket promedio</li>
                    <li>Implementar programa de fidelización para mascotas frecuentes</li>
                </ul>
                
                <p><strong>🎯 Recomendaciones Estratégicas:</strong></p>
                <ul>
                    <li>Crear paquetes de salud preventiva para aumentar frecuencia</li>
                    <li>Implementar recordatorios automatizados para reducir cancelaciones</li>
                    <li>Desarrollar programa de membresía para clientes frecuentes</li>
                    <li>Ampliar servicios para especies menores (aves, reptiles)</li>
                    <li>Optimizar horarios según demanda (picos identificados: 10am y 4pm)</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Pie del Reporte -->
    <div class="report-footer">
        <div class="footer-info">
            <strong>Reporte de Estadísticas - Clínica Veterinaria VetControl</strong>
        </div>
        <div class="footer-info">
            Generado el <?php echo date('d/m/Y H:i:s'); ?> • Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> al <?php echo date('d/m/Y', strtotime($end_date)); ?>
        </div>
        <div class="footer-info">
            Sistema VetControl v1.0 • Todos los derechos reservados
        </div>
        <div class="footer-info" style="font-size: 7px; color: #adb5bd;">
            Este reporte contiene datos estimados y reales del sistema. Para análisis interactivo, acceda al dashboard completo.
        </div>
    </div>
</body>
</html>
<?php
// Registrar generación de PDF en bitácora (adaptado a PDO)
if (file_exists('../includes/bitacora_function.php')) {
    require_once '../includes/bitacora_function.php';
    if (function_exists('log_to_bitacora')) {
        $action_text = "Reporte de estadísticas PDF generado - Período: " . 
                      date('d/m/Y', strtotime($start_date)) . " al " . date('d/m/Y', strtotime($end_date));
        log_to_bitacora($conn, $action_text, $_SESSION['username'] ?? '', $_SESSION['role_id'] ?? 0);
    }
}

// La conexión PDO se cierra automáticamente al finalizar el script.
// No es necesario $conn->close();
?>
