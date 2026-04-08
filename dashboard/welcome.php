<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Usuario';
$role_name = $_SESSION['role_name'] ?? 'Propietario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_id = $_SESSION['role_id'] ?? 2;

$pets = [];
$message = '';

try {
    // Consulta según el rol usando PDO
    if ($role_name === 'Propietario') {
        $sql = "SELECT p.id, p.name, p.date_of_birth, p.gender, 
                       pt.name AS species_name, 
                       b.name AS breed_name 
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                WHERE p.owner_id = ? 
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$user_id]);
    } elseif ($role_name === 'Veterinario' || $role_name === 'admin') {
        $sql = "SELECT p.id, p.name, p.date_of_birth, p.gender, 
                       pt.name AS species_name, 
                       b.name AS breed_name,
                       u.username as owner_name
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                LEFT JOIN users u ON p.owner_id = u.id
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } else {
        $message = "Rol no reconocido: " . htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8');
    }

    if (isset($stmt) && empty($message)) {
        $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    $message = "Error de base de datos: " . $e->getMessage();
    error_log("Error en welcome.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>VetCtrl · Dashboard</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="../public/css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ---------- ANIMACIÓN FADE-IN ---------- */
        .fade-in {
            animation: fadeIn 0.6s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            padding-top: 70px;
        }
        /* Breadcrumbs */
        .breadcrumb {
            max-width: 1600px;
            margin: 10px auto 0 auto;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
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
        .help-btn {
            background: #40916c;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 6px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .help-btn:hover {
            background: #2d6a4f;
            transform: translateY(-2px);
        }
        .dashboard-main-container {
            display: grid;
            grid-template-columns: 280px 1fr 350px;
            gap: 20px;
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
            min-height: calc(100vh - 90px);
        }
        @media (max-width: 1200px) {
            .dashboard-main-container {
                grid-template-columns: 1fr;
                grid-template-rows: auto auto auto;
            }
        }
        .dashboard-info-column {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            height: fit-content;
            border-top: 5px solid #1b4332;
        }
        .dashboard-info-column h2 {
            color: #1b4332;
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .user-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .user-info-card p {
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .user-info-card strong {
            color: #1b4332;
            min-width: 80px;
            display: inline-block;
        }
        .user-info-card .role-badge {
            background: #40916c;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .actions-column {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-top: 5px solid #40916c;
        }
        .actions-column h2 {
            color: #1b4332;
            font-size: 1.5rem;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .search-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 5px 15px;
            border-radius: 40px;
        }
        .search-actions i {
            color: #6c757d;
        }
        .search-actions input {
            border: none;
            background: transparent;
            padding: 8px 0;
            font-size: 0.9rem;
            outline: none;
            width: 200px;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
        .action-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 10px;
            padding: 25px;
            transition: all 0.3s ease;
            border: 2px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #40916c;
        }
        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #40916c, #2d6a4f);
        }
        .action-card h3 {
            color: #1b4332;
            font-size: 1.2rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .action-card p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        .action-card a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #40916c;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
            font-size: 0.95rem;
        }
        .action-card a:hover {
            background: #2d6a4f;
        }
        /* Ocultar tarjetas que no coincidan con la búsqueda */
        .action-card.hidden {
            display: none;
        }
        .patients-column {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-top: 5px solid #2d6a4f;
        }
        .patients-column h2 {
            color: #1b4332;
            font-size: 1.5rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .patients-table-container {
            overflow-x: auto;
        }
        .patients-table {
            width: 100%;
            border-collapse: collapse;
        }
        .patients-table th {
            background: #f8f9fa;
            padding: 15px 12px;
            text-align: left;
            color: #1b4332;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            font-size: 0.95rem;
        }
        .patients-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee;
            font-size: 0.95rem;
        }
        .patients-table tr:hover {
            background: #f8f9fa;
        }
        .table-action-link {
            color: #40916c;
            text-decoration: none;
            font-weight: 500;
            margin: 0 8px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background 0.3s;
        }
        .table-action-link:hover {
            background: rgba(64, 145, 108, 0.1);
            text-decoration: none;
        }
        /* Mensajes de alerta unificados */
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
        .alert i {
            font-size: 1.4rem;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #17a2b8;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: #ffc107;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-admin {
            background: #dc3545;
            color: white;
        }
        .badge-vet {
            background: #0d6efd;
            color: white;
        }
        .badge-owner {
            background: #198754;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
        /* Tutorial Modal (estilo consistente con index.php) */
        .tutorial-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .tutorial-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .tutorial-modal {
            background: white;
            max-width: 550px;
            width: 90%;
            border-radius: 20px;
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .tutorial-overlay.active .tutorial-modal {
            transform: translateY(0);
        }
        .tutorial-header {
            background: #1b4332;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tutorial-header h3 {
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tutorial-header button {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
        }
        .tutorial-content {
            padding: 25px;
            min-height: 300px;
        }
        .step-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: #1b4332;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step-desc {
            font-size: 1rem;
            line-height: 1.5;
            color: #333;
            margin-bottom: 20px;
        }
        .step-desc a {
            color: #40916c;
            text-decoration: none;
            font-weight: bold;
        }
        .step-image-placeholder {
            background: #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
            color: #6c757d;
        }
        .tutorial-footer {
            display: flex;
            justify-content: space-between;
            padding: 15px 25px 25px;
            border-top: 1px solid #e0e0e0;
        }
        .tutorial-footer button {
            padding: 8px 20px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-prev {
            background: #e9ecef;
            color: #495057;
        }
        .btn-next {
            background: #40916c;
            color: white;
        }
        .step-indicators {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        .step-dot {
            width: 8px;
            height: 8px;
            background: #cbd5e0;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .step-dot.active {
            background: #40916c;
            width: 24px;
            border-radius: 12px;
        }
        .tip-box {
            background: #e9f5ef;
            border-left: 4px solid #40916c;
            padding: 12px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 0.9rem;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .step-list {
            margin: 10px 0 10px 20px;
            padding-left: 0;
            list-style-type: none;
        }
        .step-list li {
            margin-bottom: 8px;
            position: relative;
            padding-left: 22px;
        }
        .step-list li:before {
            content: "✓";
            color: #40916c;
            position: absolute;
            left: 0;
            font-weight: bold;
        }
        /* Botón flotante de ayuda (opcional, ya tenemos en breadcrumb) */
        @media (max-width: 768px) {
            .breadcrumb {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .search-actions input {
                width: 150px;
            }
        }
    </style>
</head>
<body class="fade-in">
    <?php include '../includes/navbar.php'; ?>

    <!-- Breadcrumbs con botón de ayuda -->
    <div class="breadcrumb">
        <div>
            <a href="welcome.php">Inicio</a> <span>›</span> <span>Dashboard</span>
        </div>
        <button id="helpBtn" class="help-btn"><i class="fas fa-question-circle"></i> Ayuda</button>
    </div>
    
    <div class="dashboard-main-container">
        <!-- Columna izquierda -->
        <div class="dashboard-info-column">
            <h2>📊 Dashboard</h2>
            <div class="user-info-card">
                <p><strong>👤 Usuario:</strong> <span><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span></p>
                <p><strong>🎯 Rol:</strong> 
                    <span class="role-badge">
                        <?php 
                            $badge_class = '';
                            if ($role_name === 'admin') $badge_class = 'badge-admin';
                            elseif ($role_name === 'Veterinario') $badge_class = 'badge-vet';
                            else $badge_class = 'badge-owner';
                        ?>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($role_name, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </span>
                </p>
                <p><strong>🆔 ID:</strong> <span>#<?php echo htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8'); ?></span></p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>
            
            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <h3 style="font-size: 1.1rem; margin-bottom: 10px; color: #1b4332;">📅 Hoy</h3>
                <p style="color: #666; font-size: 0.95rem;"><?php echo date('d/m/Y'); ?></p>
                <p style="color: #666; font-size: 0.95rem; margin-top: 10px;">Accesos rápidos disponibles según tu rol</p>
            </div>
        </div>
        
        <!-- Columna central - Acciones rápidas -->
        <div class="actions-column">
            <h2>
                ⚡ Acciones Rápidas
                <?php if ($role_name === 'Veterinario' || $role_name === 'admin'): ?>
                <div class="search-actions">
                    <i class="fas fa-search"></i>
                    <input type="text" id="actionSearch" placeholder="Buscar función..." autocomplete="off">
                </div>
                <?php endif; ?>
            </h2>
            <div class="actions-grid" id="actionsGrid">
                <?php if ($role_name === 'admin'): ?>
                    <div class="action-card"><h3>👥 Gestión de Empleados</h3><p>Registrar y administrar el personal.</p><a href="employee_list.php">Administrar Empleados</a></div>
                    <div class="action-card"><h3>🏢 Configuración del Sistema</h3><p>Ajustes generales y configuración.</p><a href="system_settings.php">Configurar Sistema</a></div>
                    <div class="action-card"><h3>📊 Reportes y Estadísticas</h3><p>Genera reportes detallados.</p><a href="reports_dashboard.php">Ver Reportes</a></div>
                    <div class="action-card"><h3>📘 Bitácora del Sistema</h3><p>Registro completo de actividad.</p><a href="log_viewer.php">Ver Bitácora</a></div>
                    <div class="action-card"><h3>🔧 Herramientas de Admin</h3><p>Herramientas avanzadas.</p><a href="admin_tools.php">Acceder</a></div>
                    <div class="action-card"><h3>📈 Dashboard de Métricas</h3><p>Métricas y KPIs.</p><a href="daily_report.php">Ver Métricas</a></div>
                    <div class="action-card"><h3>🔔 Alertas de Vacunas</h3><p>Dosis pendientes o vencidas.</p><a href="vaccine_alerts.php">Ver Alertas</a></div>
                    <div class="action-card"><h3>💉 Aplicar Vacuna</h3><p>Aplica una nueva dosis.</p><a href="vaccine_select_pet.php">Aplicar Vacuna</a></div>
                    <div class="action-card"><h3>🐾 Registrar Paciente</h3><p>Agrega una nueva mascota.</p><a href="pet_register.php">Registrar Mascota</a></div>
                <?php elseif ($role_name === 'Veterinario'): ?>
                    <div class="action-card"><h3>💉 Aplicar Vacuna</h3><p>Aplica una nueva dosis.</p><a href="vaccine_select_pet.php">Aplicar Vacuna</a></div>
                    <div class="action-card"><h3>🔔 Alertas de Vacunas</h3><p>Dosis pendientes.</p><a href="vaccine_alerts.php">Ver Alertas</a></div>
                    <div class="action-card"><h3>🐾 Registrar Paciente</h3><p>Agrega una nueva mascota.</p><a href="pet_register.php">Registrar Mascota</a></div>
                    <div class="action-card"><h3>📝 Tipos de Vacuna</h3><p>Gestiona vacunas disponibles.</p><a href="vaccine_types_list.php">Administrar Tipos</a></div>
                    <div class="action-card"><h3>📋 Historial Médico</h3><p>Consulta historial completo.</p><a href="pet_list.php">Ver Historiales</a></div>
                    <div class="action-card"><h3>📅 Agenda de Citas</h3><p>Gestiona tus citas.</p><a href="appointment_list.php">Ver Agenda</a></div>
                    <div class="action-card"><h3>🔍 Consultar Paciente</h3><p>Busca información de pacientes.</p><a href="search_pet_owner.php">Buscar</a></div>
                    <div class="action-card"><h3>📄 Generar Consulta</h3><p>Nuevo reporte de consulta.</p><a href="consultation_register.php">Nueva Consulta</a></div>
                <?php elseif ($role_name === 'Propietario'): ?>
                    <div class="action-card"><h3>🐾 Ver Mis Mascotas</h3><p>Accede al listado de tus mascotas.</p><a href="pet_list.php">Ver Mascotas</a></div>
                    <div class="action-card"><h3>🐶 Nueva Mascota</h3><p>Agrega una nueva mascota.</p><a href="pet_register.php">Registrar Mascota</a></div>
                    <div class="action-card"><h3>📅 Agendar Cita</h3><p>Solicita una consulta.</p><a href="appointment_schedule.php">Agendar Cita</a></div>
                    <div class="action-card"><h3>📋 Mis Citas</h3><p>Revisa tus citas activas.</p><a href="appointment_list.php">Ver Citas</a></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Columna derecha - Lista de pacientes -->
        <div class="patients-column">
            <h2>
                <?php if ($role_name === 'Propietario'): ?>
                    🐕 Mis Mascotas
                <?php else: ?>
                    👥 Lista de Pacientes
                <?php endif; ?>
            </h2>
            
            <?php if (empty($pets)): ?>
                <div class="empty-state">
                    <i class="fas fa-dog"></i>
                    <p>No hay pacientes registrados.</p>
                    <?php if ($role_name === 'Propietario'): ?>
                        <a href="pet_register.php" style="color: #40916c; text-decoration: none; font-weight: 600;">
                            ¡Registra tu primera mascota!
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="patients-table-container">
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <?php if ($role_name !== 'Propietario'): ?>
                                    <th>Dueño</th>
                                <?php endif; ?>
                                <th>Especie</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pets as $pet): ?>
                                <tr>
                                    <td data-label="Nombre">
                                        <strong><?php echo htmlspecialchars($pet['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if (isset($pet['gender'])): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($pet['gender'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ($role_name !== 'Propietario'): ?>
                                        <td data-label="Dueño"><?php echo htmlspecialchars($pet['owner_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php endif; ?>
                                    
                                    <td data-label="Especie">
                                        <?php echo htmlspecialchars($pet['species_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (isset($pet['breed_name']) && $pet['breed_name'] !== 'N/A'): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($pet['breed_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td data-label="Acciones">
                                        <a href="pet_profile.php?id=<?php echo $pet['id']; ?>" class="table-action-link" title="Ver perfil">👁️ Perfil</a>
                                        <?php if ($role_name !== 'Propietario'): ?>
                                            <a href="vaccine_register.php?id=<?php echo $pet['id']; ?>" class="table-action-link" title="Registrar vacuna">💉 Vacunar</a>
                                        <?php endif; ?>
                                        <?php if ($role_name === 'admin' || $role_name === 'Veterinario'): ?>
                                            <a href="edit_pet.php?id=<?php echo $pet['id']; ?>" class="table-action-link" title="Editar">✏️ Editar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 0.9rem; color: #666;">
                    <strong>Total:</strong> <?php echo count($pets); ?> paciente<?php echo count($pets) !== 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Tutorial -->
    <div id="tutorialOverlay" class="tutorial-overlay">
        <div class="tutorial-modal">
            <div class="tutorial-header">
                <h3><i class="fas fa-graduation-cap"></i> Ayuda - Funciones disponibles</h3>
                <button id="closeTutorialBtn">&times;</button>
            </div>
            <div class="tutorial-content" id="tutorialContent"></div>
            <div class="tutorial-footer">
                <button id="prevStepBtn" class="btn-prev" style="visibility: hidden;">← Anterior</button>
                <button id="nextStepBtn" class="btn-next">Siguiente →</button>
            </div>
            <div class="step-indicators" id="stepIndicators"></div>
        </div>
    </div>

    <script>
        // ========== TUTORIAL POR ROL ==========
        const role = "<?php echo $role_name; ?>";
        
        // Definición de pasos según rol
        const tutorials = {
            Propietario: [
                { title: "🐾 Ver mis mascotas", desc: "En la columna derecha encontrarás el listado de tus mascotas registradas. Puedes hacer clic en 'Perfil' para ver detalles completos, historial de consultas y vacunas." },
                { title: "🐶 Registrar nueva mascota", desc: "Haz clic en 'Registrar Mascota' en la sección de Acciones Rápidas. Completa los datos requeridos (nombre, especie, fecha de nacimiento, etc.) y guarda. La mascota aparecerá en tu listado." },
                { title: "📅 Agendar cita", desc: "Usa el botón 'Agendar Cita' para solicitar una consulta veterinaria. Elige la mascota, fecha y hora deseada. Recibirás confirmación." },
                { title: "📋 Ver mis citas", desc: "Desde 'Mis Citas' puedes revisar el estado de tus solicitudes, cancelar o reprogramar." },
                { title: "🔔 Recordatorios", desc: "Recibirás notificaciones sobre vacunas pendientes y citas próximas. También puedes ver alertas desde el panel." }
            ],
            Veterinario: [
                { title: "💉 Aplicar vacuna", desc: "Selecciona 'Aplicar Vacuna' en Acciones Rápidas. Busca al paciente, elige el tipo de vacuna, registra la fecha y la próxima dosis. El sistema generará alertas automáticas." },
                { title: "🔔 Alertas de vacunas", desc: "Revisa 'Alertas de Vacunas' para ver qué pacientes tienen dosis vencidas o próximas a vencer. Puedes contactar al dueño desde allí." },
                { title: "🐾 Registrar paciente", desc: "Registra una nueva mascota con todos sus datos. Asigna el dueño correspondiente (debe estar registrado previamente)." },
                { title: "📝 Tipos de vacuna", desc: "Administra el catálogo de vacunas disponibles. Puedes agregar nuevos tipos, especificando especie objetivo y descripción." },
                { title: "📋 Historial médico", desc: "Accede al historial completo de consultas y tratamientos desde 'Ver Historiales' o desde el perfil de cada mascota." },
                { title: "📅 Agenda de citas", desc: "Gestiona las citas programadas. Puedes confirmar, reprogramar o cancelar. Visualiza tu agenda diaria." },
                { title: "🔍 Buscar paciente", desc: "Utiliza la búsqueda avanzada para encontrar pacientes por nombre, dueño, cédula o especie. Ideal para localizar rápidamente." },
                { title: "📄 Generar consulta", desc: "Registra una nueva consulta médica: motivo, diagnóstico, tratamiento y notas. Se vincula automáticamente al historial del paciente." }
            ],
            admin: [
                { title: "👥 Gestión de empleados", desc: "Registra, edita o elimina usuarios del sistema. Asigna roles (Veterinario, Propietario, Admin). Controla accesos." },
                { title: "🏢 Configuración del sistema", desc: "Ajusta parámetros globales: nombre de la clínica, horarios, recordatorios, notificaciones, etc." },
                { title: "📊 Reportes y estadísticas", desc: "Genera reportes diarios, mensuales y personalizados. Visualiza métricas de consultas, citas, vacunas y más." },
                { title: "📘 Bitácora del sistema", desc: "Consulta el registro completo de actividades de todos los usuarios. Auditoría de cambios y acciones." },
                { title: "🔧 Herramientas de admin", desc: "Accede a utilidades avanzadas como backup de base de datos, limpieza de logs, etc." },
                { title: "📈 Dashboard de métricas", desc: "Panel con KPIs clave: total de pacientes, consultas hoy, citas pendientes, etc." },
                { title: "🔔 Alertas de vacunas", desc: "Supervisa todas las vacunas próximas a vencer en el sistema. Puedes comunicarte con los dueños." },
                { title: "💉 Aplicar vacuna", desc: "Registra nuevas dosis para cualquier paciente. El sistema actualiza automáticamente el historial." },
                { title: "🐾 Registrar paciente", desc: "Da de alta nuevas mascotas, asignándoles dueño, especie y datos clínicos." }
            ]
        };

        const steps = tutorials[role] || tutorials.Propietario;
        let currentStep = 0;
        const overlay = document.getElementById('tutorialOverlay');
        const tutorialContent = document.getElementById('tutorialContent');
        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const stepIndicators = document.getElementById('stepIndicators');

        function renderStep() {
            const step = steps[currentStep];
            tutorialContent.innerHTML = `
                <div class="step-title">${step.title}</div>
                <div class="step-desc">${step.desc}</div>
                <div class="step-image-placeholder">
                    <i class="fas fa-info-circle" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                    Usa las tarjetas de acciones para acceder rápidamente a estas funciones.
                </div>
            `;
            prevBtn.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
            nextBtn.textContent = currentStep === steps.length - 1 ? 'Finalizar' : 'Siguiente →';
            updateDots();
        }

        function updateDots() {
            const dots = document.querySelectorAll('.step-dot');
            dots.forEach((dot, i) => dot.classList.toggle('active', i === currentStep));
        }

        function openTutorial() {
            currentStep = 0;
            renderStep();
            overlay.classList.add('active');
        }

        nextBtn.addEventListener('click', () => {
            if (currentStep < steps.length - 1) {
                currentStep++;
                renderStep();
            } else {
                overlay.classList.remove('active');
            }
        });

        prevBtn.addEventListener('click', () => {
            if (currentStep > 0) {
                currentStep--;
                renderStep();
            }
        });

        document.getElementById('closeTutorialBtn').addEventListener('click', () => overlay.classList.remove('active'));
        document.getElementById('helpBtn').addEventListener('click', openTutorial);

        // Inicializar indicadores de paso
        steps.forEach((_, i) => {
            const dot = document.createElement('div');
            dot.classList.add('step-dot');
            if (i === 0) dot.classList.add('active');
            stepIndicators.appendChild(dot);
        });

        // ========== BÚSQUEDA DE FUNCIONES (solo Veterinario/Admin) ==========
        <?php if ($role_name === 'Veterinario' || $role_name === 'admin'): ?>
        const searchInput = document.getElementById('actionSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.action-card');
                cards.forEach(card => {
                    const title = card.querySelector('h3')?.innerText.toLowerCase() || '';
                    const desc = card.querySelector('p')?.innerText.toLowerCase() || '';
                    if (title.includes(term) || desc.includes(term) || term === '') {
                        card.classList.remove('hidden');
                    } else {
                        card.classList.add('hidden');
                    }
                });
            });
        }
        <?php endif; ?>
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
