<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$username = $_SESSION["username"] ?? 'Usuario';
$current_user_id = $_SESSION['user_id'] ?? 0;
$role_id = $_SESSION['role_id'] ?? 0;
$user_role = $_SESSION['role_name'] ?? 'Propietario';

require_once '../includes/config.php'; // $conn es un objeto PDO
require_once '../includes/bitacora_function.php';

$search_results = [];
$search_query = '';
$message = '';
$is_vet_or_admin = ($user_role === 'Veterinario' || $user_role === 'admin');

if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
    $search_query = trim($_GET['query']);
    $search_pattern = '%' . $search_query . '%';

    $sql_select = "
        SELECT 
            p.id, p.name, 
            pt.name AS species_name, 
            b.name AS breed_name, 
            p.date_of_birth,
            u.username AS owner_name,
            u.id AS owner_id,
            u.ci AS owner_ci
    ";

    $sql_from_join = "
        FROM pets p
        INNER JOIN pet_types pt ON p.type_id = pt.id
        LEFT JOIN breeds b ON p.breed_id = b.id
        INNER JOIN users u ON p.owner_id = u.id 
    ";

    $sql_base_conditions = "(p.name LIKE :pattern1 OR pt.name LIKE :pattern2 OR b.name LIKE :pattern3 OR u.username LIKE :pattern4 OR u.ci LIKE :pattern5)";

    if ($is_vet_or_admin) {
        $sql_where = "WHERE " . $sql_base_conditions;
        $params = [
            ':pattern1' => $search_pattern,
            ':pattern2' => $search_pattern,
            ':pattern3' => $search_pattern,
            ':pattern4' => $search_pattern,
            ':pattern5' => $search_pattern
        ];
    } else {
        $sql_where = "WHERE p.owner_id = :owner_id AND " . $sql_base_conditions;
        $params = [
            ':owner_id' => $current_user_id,
            ':pattern1' => $search_pattern,
            ':pattern2' => $search_pattern,
            ':pattern3' => $search_pattern,
            ':pattern4' => $search_pattern,
            ':pattern5' => $search_pattern
        ];
    }

    $sql = $sql_select . $sql_from_join . $sql_where . " ORDER BY p.name ASC";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (function_exists('log_to_bitacora')) {
            $result_count = count($search_results);
            $action_log = "Busqueda Paciente/Dueño exitosa ({$user_role}): Buscó '{$search_query}'. Resultados: {$result_count}.";
            log_to_bitacora($conn, $action_log, $username, $role_id);
        }

        if (empty($search_results)) {
            $message = "<div class='alert alert-info'><i class='fas fa-info-circle'></i> No se encontraron pacientes que coincidan con la búsqueda: <strong>" . htmlspecialchars($search_query) . "</strong>" . ($is_vet_or_admin ? "" : " (Restringida a tus mascotas)") . "</div>";
        }
    } catch (PDOException $e) {
        if (function_exists('log_to_bitacora')) {
            $action_log = "Error DB: Fallo al buscar paciente/dueño ('{$search_query}'). Detalle: " . $e->getMessage();
            log_to_bitacora($conn, $action_log, $username, $role_id);
        }
        $message = "<div class='alert alert-danger'>Error en la búsqueda: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Paciente - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8fafc;
        }
        body {
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
            background-color: #f4f7fc;
            padding-top: 70px;
        }
        .breadcrumb {
            max-width: 1000px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .dashboard-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 0 20px;
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
        .role-badge {
            background: var(--primary-light);
            color: white;
            padding: 5px 16px;
            border-radius: 40px;
            font-size: 0.8rem;
            display: inline-block;
            margin-bottom: 15px;
        }
        .search-info {
            text-align: center;
            margin-bottom: 20px;
            color: #5b6e8c;
        }
        .restriction-alert {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 20px;
            border-left: 5px solid #ffc107;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .search-form {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .search-form input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 40px;
            font-size: 1rem;
            transition: 0.2s;
        }
        .search-form input:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: 40px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
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
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin: 20px 0 15px;
        }
        .result-header h2 {
            color: var(--primary-dark);
            font-size: 1.3rem;
        }
        .btn-pdf {
            background: var(--accent);
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-pdf:hover {
            background: #9e6b2f;
            transform: translateY(-2px);
        }
        .result-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .pet-card {
            background: white;
            border: 1px solid #eef2f8;
            border-left: 6px solid var(--primary-light);
            padding: 20px;
            border-radius: 24px;
            transition: all 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
            border-left-color: var(--accent);
        }
        .pet-card h3 {
            margin-top: 0;
            color: var(--primary-dark);
            border-bottom: 1px dashed #e2e8f0;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pet-card p {
            margin: 8px 0;
            color: #2c3e50;
        }
        .pet-card strong {
            color: var(--primary);
        }
        .pet-card a {
            color: var(--primary-light);
            text-decoration: none;
            font-weight: 600;
            margin-top: 10px;
            display: inline-block;
        }
        .pet-card a:hover {
            text-decoration: underline;
        }
        @media (max-width: 640px) {
            .main-content { padding: 20px; }
            .search-form { flex-direction: column; }
            .result-header { flex-direction: column; align-items: stretch; gap: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Buscar Pacientes</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-search"></i> Buscar Paciente o Dueño</h1>
            <div style="text-align: center;">
                <span class="role-badge"><i class="fas fa-user-tag"></i> Rol: <?php echo htmlspecialchars($user_role); ?></span>
            </div>

            <div class="search-info">
                <?php if ($is_vet_or_admin): ?>
                    <p><i class="fas fa-globe"></i> Búsqueda completa: por <strong>Nombre de mascota, Especie, Raza, Nombre de usuario del dueño o Cédula (CI)</strong>.</p>
                <?php else: ?>
                    <p><i class="fas fa-lock"></i> Búsqueda restringida a tus mascotas: por <strong>Nombre de mascota, Especie, Raza, tu nombre de usuario o tu cédula</strong>.</p>
                    <div class="restriction-alert">
                        <i class="fas fa-info-circle"></i> Solo puedes buscar en tus propias mascotas. También puedes buscar por tu nombre o cédula.
                    </div>
                <?php endif; ?>
            </div>

            <p style="text-align: center;"><a href="welcome.php" style="color: var(--primary-light);">← Volver al Dashboard</a></p>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
                <input type="text" name="query" placeholder="Nombre de mascota, dueño, especie, raza o cédula..." 
                       value="<?php echo htmlspecialchars($search_query); ?>" required>
                <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Buscar</button>
            </form>
            
            <?php echo $message; ?>

            <?php if (!empty($search_results)): ?>
                <div class="result-header">
                    <h2><i class="fas fa-list-ul"></i> Resultados (<?php echo count($search_results); ?>)</h2>
                    <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar a PDF</button>
                </div>
                <div class="result-list" id="petResultsList">
                    <?php foreach ($search_results as $pet): ?>
                        <div class="pet-card"
                            data-pet-id="<?php echo $pet['id']; ?>"
                            data-pet-name="<?php echo htmlspecialchars($pet['name']); ?>"
                            data-pet-species="<?php echo htmlspecialchars($pet['species_name']); ?>"
                            data-pet-breed="<?php echo htmlspecialchars($pet['breed_name'] ?? 'N/D'); ?>"
                            data-pet-dob="<?php echo htmlspecialchars($pet['date_of_birth'] ?? 'N/D'); ?>"
                            data-pet-owner="<?php echo htmlspecialchars($pet['owner_name'] ?? 'N/D'); ?>"
                            data-owner-ci="<?php echo htmlspecialchars($pet['owner_ci'] ?? 'N/D'); ?>"
                        >
                            <h3><i class="fas fa-dog"></i> <?php echo htmlspecialchars($pet['name']); ?></h3>
                            <p><strong>Especie:</strong> <?php echo htmlspecialchars($pet['species_name']); ?></p>
                            <p><strong>Raza:</strong> <?php echo htmlspecialchars($pet['breed_name'] ?? 'N/D'); ?></p>
                            <p><strong>Dueño:</strong> 
                                <a href="owner_details.php?id=<?php echo $pet['owner_id']; ?>">
                                    <?php echo htmlspecialchars($pet['owner_name']); ?>
                                </a>
                            </p>
                            <?php if ($is_vet_or_admin): ?>
                                <p><strong>Cédula del dueño:</strong> <?php echo htmlspecialchars($pet['owner_ci'] ?? 'N/A'); ?></p>
                            <?php endif; ?>
                            <a href="pet_profile.php?id=<?php echo $pet['id']; ?>">Ver perfil completo →</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('btnExportPdf')?.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            
            const query = "<?php echo htmlspecialchars($search_query, ENT_QUOTES); ?>";
            const username = "<?php echo htmlspecialchars($username, ENT_QUOTES); ?>";
            const role = "<?php echo htmlspecialchars($user_role, ENT_QUOTES); ?>";
            const date = new Date().toLocaleDateString('es-ES', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
            
            doc.setFontSize(18);
            doc.text('Reporte de Búsqueda - VetCtrl', 148, 20, { align: 'center' });
            doc.setFontSize(11);
            doc.text(`Criterio: "${query}"`, 148, 28, { align: 'center' });
            doc.text(`Total resultados: <?php echo count($search_results); ?>`, 148, 34, { align: 'center' });
            doc.setFontSize(9);
            doc.text(`Generado por ${username} (${role}) el ${date}`, 148, 40, { align: 'center' });
            
            const data = [];
            document.querySelectorAll('.pet-card').forEach(card => {
                data.push([
                    card.dataset.petName,
                    card.dataset.petOwner,
                    card.dataset.ownerCi,
                    card.dataset.petSpecies,
                    card.dataset.petBreed,
                    card.dataset.petDob
                ]);
            });
            
            doc.autoTable({
                startY: 50,
                head: [['Mascota', 'Dueño', 'Cédula', 'Especie', 'Raza', 'F. Nacimiento']],
                body: data,
                theme: 'grid',
                headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                styles: { fontSize: 8 }
            });
            
            doc.save(`Reporte_Busqueda_${new Date().toISOString().slice(0,10)}.pdf`);
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
