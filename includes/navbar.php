<?php
// navbar.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variables de sesión necesarias
$username = $_SESSION["username"] ?? 'Usuario';
$role_name = $_SESSION['role_name'] ?? 'Propietario'; // Valor por defecto
$user_id = $_SESSION['user_id'] ?? 0;

// Determinar si es admin, veterinario o propietario
$is_admin = ($role_name === 'admin');
$is_vet = ($role_name === 'Veterinario');
$is_owner = ($role_name === 'Propietario');

// Común a todos: enlaces que todos pueden ver
$common_links = [
    ['url' => 'welcome.php', 'label' => 'Inicio', 'icon' => '🏠'],
    ['url' => 'pet_list.php', 'label' => 'Mis Mascotas', 'icon' => '🐕', 'roles' => ['Propietario']],
    ['url' => 'pet_list.php', 'label' => 'Lista de Pacientes', 'icon' => '🐾', 'roles' => ['Veterinario', 'admin']],
    ['url' => 'search_pet_owner.php', 'label' => 'Buscar', 'icon' => '🔍'],
];

// Enlaces para propietario
$owner_links = [
    ['url' => 'pet_register.php', 'label' => 'Registrar Mascota', 'icon' => '➕'],
    ['url' => 'appointment_schedule.php', 'label' => 'Agendar Cita', 'icon' => '📅'],
    ['url' => 'appointment_list.php', 'label' => 'Mis Citas', 'icon' => '📋'],
];

// Enlaces para veterinario
$vet_links = [
    ['url' => 'consultation_register.php', 'label' => 'Registrar Consulta', 'icon' => '🩺'],
    ['url' => 'vaccine_select_pet.php', 'label' => 'Aplicar Vacuna', 'icon' => '💉'],
    ['url' => 'vaccine_alerts.php', 'label' => 'Alertas de Vacunas', 'icon' => '⚠️'],
    ['url' => 'treatment_select_pet.php', 'label' => 'Registrar Tratamiento', 'icon' => '💊'],
    ['url' => 'appointment_schedule_vet.php', 'label' => 'Agenda de Citas', 'icon' => '📅'],
    ['url' => 'medical_history.php', 'label' => 'Historial Médico', 'icon' => '📋'],
    ['url' => 'vaccine_types_list.php', 'label' => 'Tipos de Vacuna', 'icon' => '📝'],
    ['url' => 'owner_list.php', 'label' => 'Lista de Dueños', 'icon' => '👥'],
];

// Enlaces para admin (todos los de veterinario más los de administración)
$admin_links = array_merge($vet_links, [
    ['url' => 'employee_list.php', 'label' => 'Gestión de Empleados', 'icon' => '👥'],
    ['url' => 'log_viewer.php', 'label' => 'Bitácora del Sistema', 'icon' => '📘'],
    ['url' => 'system_settings.php', 'label' => 'Configuración', 'icon' => '⚙️'],
    ['url' => 'reports_dashboard.php', 'label' => 'Reportes', 'icon' => '📊'],
    ['url' => 'daily_report.php', 'label' => 'Reporte Diario', 'icon' => '📈'],
    ['url' => 'pet_type_register.php', 'label' => 'Administrar Especies', 'icon' => '🐶'],
    ['url' => 'breed_register.php', 'label' => 'Administrar Razas', 'icon' => '🐱'],
]);

// Función para mostrar un enlace si el rol tiene permiso
function render_link($link, $role) {
    if (isset($link['roles']) && !in_array($role, $link['roles'])) {
        return '';
    }
    $icon = $link['icon'] ?? '';
    $label = $link['label'];
    $url = $link['url'];
    return "<a href=\"$url\">$icon $label</a>";
}
?>
<div class="navbar">
    <div class="nav-left">
        <a href="welcome.php" class="logo-link">🐾 VetCtrl</a>
        <div class="dropdown">
            <button class="dropbtn" id="dropdownBtn">Menú Principal ▼</button>
            <div class="dropdown-content" id="dropdownMenu">
                <?php
                // Mostrar enlaces comunes
                foreach ($common_links as $link) {
                    echo render_link($link, $role_name);
                }
                echo '<div class="divider"></div>';

                // Mostrar enlaces según el rol
                if ($is_owner) {
                    foreach ($owner_links as $link) {
                        echo "<a href=\"{$link['url']}\">{$link['icon']} {$link['label']}</a>";
                    }
                } elseif ($is_vet) {
                    foreach ($vet_links as $link) {
                        echo "<a href=\"{$link['url']}\">{$link['icon']} {$link['label']}</a>";
                    }
                } elseif ($is_admin) {
                    // Agrupar por secciones para mejor organización
                    echo '<span class="section-title">📋 CONSULTAS</span>';
                    echo '<a href="consultation_register.php"> Registrar Consulta</a>';
                    echo '<a href="consultation_history.php"> Historial de Consultas</a>';
                    echo '<div class="divider"></div>';

                    echo '<span class="section-title">🐾 PACIENTES</span>';
                    echo '<a href="pet_list.php"> Lista de Pacientes</a>';
                    echo '<a href="pet_register.php"> Registrar Mascota</a>';
                    echo '<a href="owner_list.php"> Lista de Dueños</a>';
                    echo '<div class="divider"></div>';

                    echo '<span class="section-title">💉 VACUNAS</span>';
                    echo '<a href="vaccine_select_pet.php"> Aplicar Vacuna</a>';
                    echo '<a href="vaccine_alerts.php"> Alertas de Vacunas</a>';
                    echo '<a href="vaccine_types_list.php"> Tipos de Vacuna</a>';
                    echo '<div class="divider"></div>';

                    echo '<span class="section-title">📅 CITAS</span>';
                    echo '<a href="appointment_schedule.php"> Agendar Cita</a>';
                    echo '<a href="appointment_list.php"> Lista de Citas</a>';
                    echo '<div class="divider"></div>';

                    echo '<span class="section-title">💊 TRATAMIENTOS</span>';
                    echo '<a href="treatment_select_pet.php"> Registrar Tratamiento</a>';
                    echo '<a href="treatment_history.php"> Historial de Tratamientos</a>';
                    echo '<div class="divider"></div>';

                    echo '<span class="section-title">⚙️ ADMINISTRACIÓN</span>';
                    echo '<a href="employee_list.php"> Gestión de Empleados</a>';
                    echo '<a href="log_viewer.php"> Bitácora del Sistema</a>';
                    echo '<a href="reports_dashboard.php"> Reportes</a>';
                    echo '<a href="pet_type_register.php"> Administrar Especies</a>';
                    echo '<a href="breed_register.php"> Administrar Razas</a>';
                }
                ?>
            </div>
        </div>
    </div>

    <span class="user-info">
        <button class="back-button" id="backButton" title="Volver a la página anterior">← Volver</button>
        <span class="user-greeting">Bienvenido, <?php echo htmlspecialchars($username); ?></span>
        <a href="../public/logout.php" class="logout-link">Cerrar Sesión</a>
    </span>
</div>

<style>
/* ========================================================= */
/* 🚀 NUEVA NAVBAR (DISEÑO UNIFICADO) 🚀                    */
/* ========================================================= */
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 70px;                      /* misma altura que welcome.php */
    background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
    color: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;    /* logo a la izquierda, usuario a la derecha */
    padding: 0 30px;
}

/* Logo y enlace */
.logo-link {
    text-decoration: none;
    color: white;
    font-size: 1.5rem;                 /* igual que welcome.php */
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Contenedor de información de usuario */
.user-info {
    display: flex;
    align-items: center;
    gap: 20px;                         /* separación entre "Bienvenido" y botón */
    color: white;
}

/* Texto de bienvenida */
.user-greeting {
    font-size: 1rem;
}

/* Botón de cerrar sesión (igual que en welcome.php) */
.logout-link {
    color: white;
    text-decoration: none;
    background: rgba(255,255,255,0.2);
    padding: 8px 15px;
    border-radius: 5px;
    font-weight: 500;
    transition: background 0.3s;
}

.logout-link:hover {
    background: rgba(255,255,255,0.3);
}

/* Botón "Volver" */
.back-button {
    background: transparent;
    border: none;
    color: white;
    font-size: 1rem;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 5px;
    transition: background 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
}
.back-button:hover {
    background: rgba(255,255,255,0.2);
}

/* ========== MENÚ DESPLEGABLE ========== */
.nav-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropbtn {
    background-color: #1b4332;          /* verde oscuro */
    color: white;
    padding: 10px 15px;
    font-size: 16px;
    font-weight: bold;
    border: none;
    cursor: pointer;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.dropbtn:hover {
    background-color: #3d8a5c;          /* verde más claro */
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: #f9f9f9;
    min-width: 250px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    z-index: 1001;
    border-radius: 5px;
    padding: 10px 0;
    left: 0;
    top: 100%;
}

.dropdown-content a {
    color: #333;
    padding: 8px 16px;
    text-decoration: none;
    display: block;
    font-size: 0.95em;
    transition: background 0.2s;
}

.dropdown-content .section-title {
    font-weight: bold;
    color: #2d6a4f;
    padding-top: 10px;
    padding-bottom: 5px;
    pointer-events: none;
}

.dropdown-content .sub-link {
    padding-left: 25px;
}

.dropdown-content a:hover {
    background-color: #e2e2e2;
}

.dropdown-content .alert-link {
    color: #b00;
    font-weight: bold;
}

.divider {
    height: 1px;
    background-color: #ddd;
    margin: 5px 0;
}

/* Mostrar el menú solo cuando tiene la clase 'show' */
.dropdown-content.show {
    display: block;
}
</style>

<script>
    // Toggle del menú con click
    const dropdownBtn = document.getElementById('dropdownBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    dropdownBtn.addEventListener('click', function(event) {
        event.stopPropagation(); // Evita que el click se propague al documento
        dropdownMenu.classList.toggle('show');
    });

    // Cerrar el menú si se hace clic fuera de él
    document.addEventListener('click', function(event) {
        if (!dropdownBtn.contains(event.target) && !dropdownMenu.contains(event.target)) {
            dropdownMenu.classList.remove('show');
        }
    });

    // Botón "Volver" que usa history.back()
    const backButton = document.getElementById('backButton');
    if (backButton) {
        backButton.addEventListener('click', function() {
            window.history.back();
        });
    }
</script>
