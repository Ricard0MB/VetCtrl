<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

$username = $_SESSION["username"] ?? 'Usuario'; 
$current_user_id = $_SESSION['user_id'] ?? 0;  // Asegúrate de que en login se guarde como 'user_id'
$role_id = $_SESSION['role_id'] ?? 0; 
$user_role = $_SESSION['role_name'] ?? 'Propietario'; 

require_once '../includes/config.php';
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
    
    // Condiciones base: nombre mascota, especie, raza, nombre del dueño, cédula
    $sql_base_conditions = "(p.name LIKE ? OR pt.name LIKE ? OR b.name LIKE ? OR u.username LIKE ? OR u.ci LIKE ?)";
    
    if ($is_vet_or_admin) {
        // Admin/veterinario: búsqueda completa (sin filtro de dueño)
        $sql_where = "WHERE " . $sql_base_conditions;
        $param_types = "sssss";
        $params = [$search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern];
    } else {
        // Propietario: solo sus mascotas + búsqueda ampliada
        $sql_where = "WHERE p.owner_id = ? AND " . $sql_base_conditions;
        $param_types = "isssss"; // i para owner_id, luego 5 s
        $params = [$current_user_id, $search_pattern, $search_pattern, $search_pattern, $search_pattern, $search_pattern];
    }
    
    $sql = $sql_select . $sql_from_join . $sql_where . " ORDER BY p.name ASC";
    
    // --- DEPURACIÓN (solo para admin, puedes activarlo temporalmente) ---
    if ($is_vet_or_admin) {
        echo "<!-- SQL: " . htmlspecialchars($sql) . " -->";
        echo "<!-- Params: " . htmlspecialchars(print_r($params, true)) . " -->";
    }
    // -----------------------------------------------------------------
    
    if ($stmt = $conn->prepare($sql)) {
        // Construir dinámicamente los parámetros para bind_param
        $bind_params = array_merge([$param_types], $params);
        $refs = [];
        foreach ($bind_params as $key => $value) {
            $refs[$key] = &$bind_params[$key];
        }

        if (call_user_func_array([$stmt, 'bind_param'], $refs)) {
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $search_results[] = $row;
                }
                $result->free();
                
                if (function_exists('log_to_bitacora')) {
                    $result_count = count($search_results);
                    $action_log = "Busqueda Paciente/Dueño exitosa ({$user_role}): Buscó '{$search_query}'. Resultados: {$result_count}.";
                    log_to_bitacora($conn, $action_log, $username, $role_id);
                }
                
                if (empty($search_results)) {
                    $message = "<div class='alert alert-info'><i class='fas fa-info-circle'></i> No se encontraron pacientes que coincidan con la búsqueda: <strong>" . htmlspecialchars($search_query) . "</strong>" . ($is_vet_or_admin ? "" : " (Restringida a tus mascotas, ahora también puedes buscar por tu nombre o cédula)") . "</div>";
                }
            } else {
                if (function_exists('log_to_bitacora')) {
                    $action_log = "Error DB (Ejecución): Fallo al buscar paciente/dueño ('{$search_query}'). Detalle: " . $stmt->error;
                    log_to_bitacora($conn, $action_log, $username, $role_id);
                }
                $message = "<div class='alert alert-danger'>Error al ejecutar la consulta de búsqueda: " . $stmt->error . "</div>";
            }
        } else {
            $message = "<div class='alert alert-danger'>Error en el binding de parámetros.</div>";
        }
        $stmt->close();
    } else {
        if (function_exists('log_to_bitacora')) {
            $action_log = "Error DB (Preparación): Fallo al preparar la consulta de búsqueda. Detalle: " . $conn->error;
            log_to_bitacora($conn, $action_log, $username, $role_id);
        }
        $message = "<div class='alert alert-danger'>Error de preparación de la consulta: " . $conn->error . "</div>";
    }
}

if (isset($conn)) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Buscar Paciente - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f4f4;
            padding-top: 70px;
        }
        .breadcrumb {
            max-width: 900px;
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
        .dashboard-container { padding: 40px 20px; max-width: 900px; margin: 0 auto; }
        .main-content { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 6px 16px rgba(0,0,0,0.1); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 20px; text-align: center; }
        .role-badge { background: #40916c; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.9rem; display: inline-block; margin-bottom: 15px; }
        .search-info { text-align: center; margin-bottom: 20px; font-size: 0.95em; color: #495057; }
        .restriction-alert { background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border: 1px solid #ffeeba; margin-top: 15px; }
        .search-form { display: flex; gap: 10px; margin-bottom: 30px; }
        .search-form input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; }
        .btn-primary { background: #2d6a4f; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: #1b4332; }
        .result-actions { display: flex; justify-content: flex-end; margin-bottom: 15px; }
        #btnExportPdf { background: #b68b40; color: white; padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; }
        .result-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
        .pet-card {
            background: white; border: 1px solid #e0e0e0; border-left: 6px solid #40916c; padding: 20px; border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.08); transition: transform 0.3s;
        }
        .pet-card:hover { transform: translateY(-5px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
        .pet-card h3 { margin-top: 0; color: #1b4332; border-bottom: 1px dashed #ced4da; padding-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .pet-card h3::before { content: '🐾'; }
        .pet-card p { margin: 8px 0; color: #343a40; }
        .pet-card strong { color: #2d6a4f; }
        .pet-card a { color: #007bff; text-decoration: none; font-weight: 600; margin-top: 10px; display: inline-block; }
        /* Mensajes de alerta */
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
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .alert-warning { background: #fff3cd; color: #856404; border-left-color: #ffc107; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        @media (max-width: 600px) {
            .search-form { flex-direction: column; }
            .result-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Breadcrumbs -->
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Buscar Pacientes</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1>Buscar Paciente o Dueño 🔎</h1>
            <div style="text-align: center;">
                <span class="role-badge">Rol: <?php echo htmlspecialchars($user_role); ?></span>
            </div>

            <div class="search-info">
                <?php if ($is_vet_or_admin): ?>
                    <p>Búsqueda completa: por <strong>Nombre de mascota, Especie, Raza, Nombre de usuario del dueño o Cédula (CI)</strong>.</p>
                <?php else: ?>
                    <p>Búsqueda restringida a tus mascotas: por <strong>Nombre de mascota, Especie, Raza, tu nombre de usuario o tu cédula</strong>.</p>
                    <div class="restriction-alert">
                        ⚠️ Solo puedes buscar en tus propias mascotas. ¡Ahora también puedes buscar por tu nombre o cédula!
                    </div>
                <?php endif; ?>
            </div>

            <p style="text-align: center;"><a href="welcome.php">← Volver al Dashboard</a></p>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="search-form">
                <input type="text" name="query" placeholder="Nombre de mascota, dueño, especie, raza o cédula..." 
                       value="<?php echo htmlspecialchars($search_query); ?>" required>
                <input type="submit" value="Buscar" class="btn-primary">
            </form>
            
            <?php echo $message; ?>

            <?php if (!empty($search_results)): ?>
                <h2>Resultados (<?php echo count($search_results); ?>)</h2>
                <div class="result-actions">
                    <button id="btnExportPdf">📄 Exportar a PDF</button>
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
                            <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
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