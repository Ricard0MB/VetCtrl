<?php
<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
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

// No es necesario cerrar la conexión explícitamente con PDO
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>VetCtrl · Dashboard</title>
    <link rel="stylesheet" href="../public/css/style.css">
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
            overflow-y: auto;
            max-height: calc(100vh - 120px);
            border-top: 5px solid #40916c;
        }
        .actions-column h2 {
            color: #1b4332;
            font-size: 1.5rem;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
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
        .patients-column {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow-y: auto;
            max-height: calc(100vh - 120px);
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
            min-width: 300px;
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
        .actions-column::-webkit-scrollbar,
        .patients-column::-webkit-scrollbar {
            width: 6px;
        }
        .actions-column::-webkit-scrollbar-track,
        .patients-column::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .actions-column::-webkit-scrollbar-thumb,
        .patients-column::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        .actions-column::-webkit-scrollbar-thumb:hover,
        .patients-column::-webkit-scrollbar-thumb:hover {
            background: #a1a1a1;
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
    </style>
</head>
<body class="fade-in">
    <?php include '../includes/navbar.php'; ?>

    <!-- Breadcrumbs -->
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span> <span>Dashboard</span>
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
            <h2>⚡ Acciones Rápidas</h2>
            <div class="actions-grid">
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
                                    <td>
                                        <strong><?php echo htmlspecialchars($pet['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <?php if (isset($pet['gender'])): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($pet['gender'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ($role_name !== 'Propietario'): ?>
                                        <td><?php echo htmlspecialchars($pet['owner_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <?php echo htmlspecialchars($pet['species_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (isset($pet['breed_name']) && $pet['breed_name'] !== 'N/A'): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars($pet['breed_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
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
</body>
</html>
