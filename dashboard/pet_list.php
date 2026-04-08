<?php
header('Content-Type: text/html; charset=utf-8');
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$role_name = $_SESSION['role_name'] ?? 'Propietario';
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? 'Usuario';

// Obtener filtros de la URL (GET)
$search_name = trim($_GET['search_name'] ?? '');
$species_filter = intval($_GET['species'] ?? 0);
$owner_filter = intval($_GET['owner'] ?? 0);

// Obtener especies para el filtro
$species_list = [];
try {
    $stmt = $conn->query("SELECT id, name FROM pet_types ORDER BY name");
    $species_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // silencioso
}

// Obtener dueños para el filtro (solo para roles que lo necesiten)
$owners_list = [];
if (in_array($role_name, ['Veterinario', 'admin'])) {
    try {
        $stmt = $conn->query("SELECT id, username, first_name, last_name FROM users WHERE role_id = 3 ORDER BY first_name");
        $owners_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // silencioso
    }
}

$pets = [];
$message = '';

try {
    // Construir la consulta base según el rol
    $sqlBase = "";
    $params = [];

    if ($role_name === 'Veterinario' || $role_name === 'admin') {
        $sqlBase = "SELECT 
                        p.id, p.name, pt.name AS species_name, b.name AS breed_name, 
                        p.gender, p.date_of_birth, u.username AS owner_name,
                        u.email AS owner_email, p.created_at
                    FROM pets p
                    LEFT JOIN pet_types pt ON p.type_id = pt.id
                    LEFT JOIN breeds b ON p.breed_id = b.id
                    LEFT JOIN users u ON p.owner_id = u.id
                    WHERE 1=1";
    } else {
        $sqlBase = "SELECT 
                        p.id, p.name, pt.name AS species_name, b.name AS breed_name, 
                        p.gender, p.date_of_birth, p.created_at
                    FROM pets p
                    LEFT JOIN pet_types pt ON p.type_id = pt.id
                    LEFT JOIN breeds b ON p.breed_id = b.id
                    WHERE p.owner_id = :owner_id";
        $params[':owner_id'] = $user_id;
    }

    // Añadir filtros dinámicos
    if (!empty($search_name)) {
        $sqlBase .= " AND p.name LIKE :search_name";
        $params[':search_name'] = "%$search_name%";
    }
    if ($species_filter > 0) {
        $sqlBase .= " AND p.type_id = :species_id";
        $params[':species_id'] = $species_filter;
    }
    if (($role_name === 'Veterinario' || $role_name === 'admin') && $owner_filter > 0) {
        $sqlBase .= " AND p.owner_id = :owner_id_filter";
        $params[':owner_id_filter'] = $owner_filter;
    }

    // Ordenar por más reciente primero
    $sqlBase .= " ORDER BY p.created_at DESC";

    $stmt = $conn->prepare($sqlBase);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar pacientes: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listado de Pacientes - VetCtrl</title>
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
            --gray-bg: #f8fafc;
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
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
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
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-subtitle {
            text-align: center;
            color: #5b6e8c;
            margin-bottom: 20px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-danger { background: #fee7e7; color: #b91c1c; border-left: 5px solid #b91c1c; }
        .filter-section {
            background: #f9fbfd;
            padding: 24px;
            border-radius: 24px;
            margin-bottom: 25px;
            border: 1px solid #eef2f8;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1 1 200px;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--primary-dark);
            margin-bottom: 6px;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
        }
        .filter-group input:focus, .filter-group select:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn-filter, .btn-reset {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-reset {
            background: #eef2f8;
            color: var(--primary-dark);
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
        .btn-primary:hover, .btn-filter:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-pdf {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
            transition: 0.2s;
        }
        .btn-pdf:hover {
            background: #9e6b2f;
            transform: translateY(-2px);
        }
        .table-responsive {
            overflow-x: auto;
        }
        .pet-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
        }
        .pet-table th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        .pet-table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
        }
        .pet-table tr:hover td {
            background-color: #f9fbfd;
        }
        .btn-action {
            padding: 5px 12px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            margin: 2px;
            color: white;
        }
        .btn-view { background: #3F51B5; }
        .btn-edit { background: #ff9800; }
        .btn-consulta { background: #17a2b8; }
        .total-count {
            margin-top: 20px;
            text-align: right;
            color: #5b6e8c;
            font-size: 0.85rem;
        }
        .empty-state {
            text-align: center;
            padding: 50px;
            background: #f9fbfd;
            border-radius: 24px;
            color: #5b6e8c;
        }
        @media (max-width: 768px) {
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
            .pet-table th { display: none; }
            .pet-table td { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eef2f8; }
            .pet-table td::before { content: attr(data-label); font-weight: 600; width: 40%; color: var(--primary-dark); }
            .action-buttons { flex-direction: column; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Listado de Pacientes</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-paw"></i> Listado de Pacientes</h1>
            <p class="page-subtitle">
                <?php echo ($role_name === 'Propietario') ? 'Tus mascotas registradas' : 'Todos los pacientes del sistema'; ?>
            </p>

            <?php echo $message; ?>

            <div class="filter-section">
                <form method="get" class="filter-form">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Buscar por nombre</label>
                        <input type="text" name="search_name" placeholder="Ej: Max" value="<?php echo htmlspecialchars($search_name); ?>">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-paw"></i> Especie</label>
                        <select name="species">
                            <option value="0">Todas</option>
                            <?php foreach ($species_list as $specie): ?>
                                <option value="<?php echo $specie['id']; ?>" <?php echo ($species_filter == $specie['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specie['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (in_array($role_name, ['Veterinario', 'admin'])): ?>
                    <div class="filter-group">
                        <label><i class="fas fa-user"></i> Dueño</label>
                        <select name="owner">
                            <option value="0">Todos</option>
                            <?php foreach ($owners_list as $owner): 
                                $owner_name = trim($owner['first_name'] . ' ' . $owner['last_name']);
                                if (empty($owner_name)) $owner_name = $owner['username'];
                            ?>
                                <option value="<?php echo $owner['id']; ?>" <?php echo ($owner_filter == $owner['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($owner_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="filter-group" style="display: flex; gap: 10px;">
                        <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filtrar</button>
                        <a href="pet_list.php" class="btn-reset"><i class="fas fa-times"></i> Limpiar</a>
                    </div>
                </form>
            </div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <a href="pet_register.php" class="btn-primary"><i class="fas fa-plus-circle"></i> Registrar Mascota</a>
                <a href="search_pet_owner.php" class="btn-primary" style="background: #3F51B5;"><i class="fas fa-search"></i> Buscar avanzado</a>
                <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
            </div>

            <?php if (empty($pets)): ?>
                <div class="empty-state">
                    <i class="fas fa-dog" style="font-size: 3rem; opacity: 0.5;"></i>
                    <p>No hay mascotas registradas con los filtros seleccionados.</p>
                    <a href="pet_register.php" class="btn-primary">Registrar primera mascota</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="pet-table" id="petsTable">
                        <thead>
                            <tr>
                                <th>Nombre</th><th>Especie</th><th>Raza</th><th>Género</th><th>F. Nacimiento</th>
                                <?php if ($role_name !== 'Propietario'): ?><th>Dueño</th><th>Registro</th><?php else: ?><th>Registro</th><?php endif; ?>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pets as $pet): ?>
                            <tr>
                                <td data-label="Nombre"><strong><?php echo htmlspecialchars($pet['name']); ?></strong></td>
                                <td data-label="Especie"><?php echo htmlspecialchars($pet['species_name'] ?? 'N/A'); ?></td>
                                <td data-label="Raza"><?php echo htmlspecialchars($pet['breed_name'] ?? 'N/A'); ?></td>
                                <td data-label="Género"><?php echo htmlspecialchars($pet['gender'] ?? 'N/D'); ?></td>
                                <td data-label="F. Nacimiento"><?php echo htmlspecialchars($pet['date_of_birth'] ?? 'Desconocida'); ?></td>
                                <?php if ($role_name !== 'Propietario'): ?>
                                    <td data-label="Dueño"><strong><?php echo htmlspecialchars($pet['owner_name'] ?? 'N/A'); ?></strong><br><small><?php echo htmlspecialchars($pet['owner_email'] ?? ''); ?></small></td>
                                    <td data-label="Registro"><?php echo date('d/m/Y', strtotime($pet['created_at'])); ?></td>
                                <?php else: ?>
                                    <td data-label="Registro"><?php echo date('d/m/Y', strtotime($pet['created_at'])); ?></td>
                                <?php endif; ?>
                                <td data-label="Acciones">
                                    <a href="pet_profile.php?id=<?php echo $pet['id']; ?>" class="btn-action btn-view"><i class="fas fa-eye"></i> Ver</a>
                                    <a href="pet_edit.php?id=<?php echo $pet['id']; ?>" class="btn-action btn-edit"><i class="fas fa-edit"></i> Editar</a>
                                    <?php if ($role_name !== 'Propietario'): ?>
                                        <a href="consultation_register.php?pet_id=<?php echo $pet['id']; ?>" class="btn-action btn-consulta"><i class="fas fa-stethoscope"></i> Consulta</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total-count">Total: <?php echo count($pets); ?> mascota<?php echo count($pets) !== 1 ? 's' : ''; ?></div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('btnExportPdf')?.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('landscape');
            doc.setFontSize(18);
            doc.text('Listado de Pacientes', 148, 20, { align: 'center' });
            doc.setFontSize(10);
            doc.text('Generado el: ' + new Date().toLocaleDateString('es-ES'), 148, 28, { align: 'center' });
            doc.autoTable({
                html: '#petsTable',
                startY: 35,
                theme: 'grid',
                headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                styles: { fontSize: 8 }
            });
            doc.save('pacientes.pdf');
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
