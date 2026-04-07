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
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f4f7f9;
            color: #1e2f2a;
            padding-top: 72px;
            line-height: 1.5;
        }

        /* Variables globales */
        :root {
            --vet-dark: #1b4332;
            --vet-primary: #40916c;
            --vet-light: #74c69d;
            --vet-bg: #f4f7f9;
            --vet-card: #ffffff;
            --vet-text: #2d3e3a;
            --vet-text-light: #52796f;
            --shadow-sm: 0 2px 6px rgba(0,0,0,0.04);
            --shadow-md: 0 8px 20px rgba(0,0,0,0.05);
            --shadow-lg: 0 12px 28px rgba(0,0,0,0.08);
            --radius-md: 14px;
            --radius-lg: 18px;
        }

        /* Breadcrumb */
        .breadcrumb {
            max-width: 1440px;
            margin: 0 auto 1rem auto;
            padding: 0.5rem 1.8rem;
            font-size: 0.85rem;
        }
        .breadcrumb a {
            color: var(--vet-primary);
            text-decoration: none;
            font-weight: 500;
        }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: var(--vet-text-light); }

        /* Layout principal */
        .dashboard-main-container {
            display: grid;
            grid-template-columns: 300px 1fr 360px;
            gap: 1.8rem;
            padding: 1.8rem;
            max-width: 1600px;
            margin: 0 auto;
        }
        @media (max-width: 1200px) {
            .dashboard-main-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        /* Tarjetas comunes */
        .dashboard-info-column, .actions-column, .patients-column {
            background: var(--vet-card);
            border-radius: var(--radius-lg);
            padding: 1.6rem;
            box-shadow: var(--shadow-md);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .dashboard-info-column:hover, .actions-column:hover, .patients-column:hover {
            box-shadow: var(--shadow-lg);
        }
        .dashboard-info-column { border-top: 5px solid var(--vet-dark); }
        .actions-column { border-top: 5px solid var(--vet-primary); }
        .patients-column { border-top: 5px solid var(--vet-light); }

        .dashboard-info-column h2 {
            font-size: 1.5rem;
            margin-bottom: 1.2rem;
            color: var(--vet-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Tarjeta de usuario */
        .user-info-card {
            background: linear-gradient(135deg, #f9fbf9 0%, #f0f5f0 100%);
            border-radius: var(--radius-md);
            padding: 1.4rem;
            margin-bottom: 1.5rem;
        }
        .user-info-card p {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0.8rem 0;
        }
        .user-info-card strong {
            min-width: 70px;
            color: var(--vet-dark);
            font-weight: 600;
        }
        .role-badge {
            background: var(--vet-primary);
            color: white;
            padding: 0.25rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        /* Grid de acciones */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.2rem;
            margin-top: 0.5rem;
        }
        .action-card {
            background: #ffffff;
            border: 1px solid #e2e8e4;
            border-radius: var(--radius-md);
            padding: 1.2rem;
            transition: all 0.25s;
        }
        .action-card:hover {
            transform: translateY(-3px);
            border-color: var(--vet-primary);
            box-shadow: var(--shadow-md);
        }
        .action-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.4rem;
            color: var(--vet-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .action-card p {
            color: var(--vet-text-light);
            font-size: 0.85rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        .action-card a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--vet-primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: background 0.2s;
        }
        .action-card a:hover { background: var(--vet-dark); }

        /* Tabla de pacientes */
        .patients-table-container {
            overflow-x: auto;
            margin: 0 -0.5rem;
            padding: 0 0.5rem;
        }
        .patients-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .patients-table th {
            text-align: left;
            padding: 0.9rem 0.8rem;
            background: #f8faf8;
            color: var(--vet-dark);
            font-weight: 600;
            border-bottom: 2px solid #dee6de;
        }
        .patients-table td {
            padding: 0.9rem 0.8rem;
            border-bottom: 1px solid #eef2ee;
            vertical-align: middle;
        }
        .patients-table tr:hover td {
            background-color: #fafdfa;
        }
        .table-action-link {
            color: var(--vet-primary);
            text-decoration: none;
            font-weight: 500;
            margin: 0 0.2rem;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.75rem;
        }
        .table-action-link:hover {
            background: #e9f4e9;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 0.2rem 0.7rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .badge-admin { background: #dc3545; color: white; }
        .badge-vet { background: #0d6efd; color: white; }
        .badge-owner { background: #198754; color: white; }

        /* Alertas */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.8rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            border-left: 4px solid;
            font-size: 0.85rem;
        }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-info { background: #e1f0fa; color: #0c5460; border-left-color: #17a2b8; }
        .alert-warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }

        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: var(--vet-text-light);
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.8rem; opacity: 0.5; }

        /* Animación fade-in */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn 0.4s ease-out; }
    </style>
</head>
<body class="fade-in">
    <?php include '../includes/navbar.php'; ?>

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
            
            <div style="margin-top: 1.2rem; padding: 1rem; background: #f8faf8; border-radius: 12px;">
                <h3 style="font-size: 0.95rem; margin-bottom: 0.5rem; color: var(--vet-dark);">📅 Hoy</h3>
                <p style="color: var(--vet-text-light); font-size: 0.85rem;"><?php echo date('d/m/Y'); ?></p>
                <p style="color: var(--vet-text-light); font-size: 0.8rem; margin-top: 0.5rem;">Accesos rápidos según tu rol</p>
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
                        <a href="pet_register.php" style="color: var(--vet-primary); text-decoration: none; font-weight: 600;">¡Registra tu primera mascota!</a>
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
                                            <br><small style="color: var(--vet-text-light);"><?php echo htmlspecialchars($pet['gender'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($role_name !== 'Propietario'): ?>
                                        <td data-label="Dueño"><?php echo htmlspecialchars($pet['owner_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php endif; ?>
                                    <td data-label="Especie">
                                        <?php echo htmlspecialchars($pet['species_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if (isset($pet['breed_name']) && $pet['breed_name'] !== 'N/A'): ?>
                                            <br><small style="color: var(--vet-text-light);"><?php echo htmlspecialchars($pet['breed_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Acciones">
                                        <a href="pet_profile.php?id=<?php echo $pet['id']; ?>" class="table-action-link" title="Ver perfil"><i class="fas fa-eye"></i> Perfil</a>
                                        <?php if ($role_name !== 'Propietario'): ?>
                                            <a href="vaccine_register.php?id=<?php echo $pet['id']; ?>" class="table-action-link" title="Registrar vacuna"><i class="fas fa-syringe"></i> Vacunar</a>
                                        <?php endif; ?>
                                        <?php if ($role_name === 'admin' || $role_name === 'Veterinario'): ?>
                                            <a href="edit_pet.php?id=<?php echo $pet['id']; ?>" class="table-action-link" title="Editar"><i class="fas fa-edit"></i> Editar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 1.2rem; padding: 0.8rem; background: #f8faf8; border-radius: 10px; font-size: 0.8rem; color: var(--vet-text-light); text-align: right;">
                    <strong>Total:</strong> <?php echo count($pets); ?> paciente<?php echo count($pets) !== 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
