<?php
session_start();

// Redirigir si el usuario no está logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

$message = '';
$vaccine_types = [];

// 1. Manejar mensajes de éxito (desde vaccine_type_register.php)
if (isset($_GET['success']) && $_GET['success'] === 'add') {
    $message = "<p class='success-message'>✅ Tipo de vacuna registrado exitosamente.</p>";
}

// 2. Consulta para obtener todos los tipos de vacunas
$sql = "SELECT id, name, description, species_target, attendant_id, created_at FROM vaccine_types ORDER BY created_at DESC";

if ($result = $conn->query($sql)) {
    if ($result->num_rows > 0) {
        // Almacenar todos los resultados en un array
        while ($row = $result->fetch_assoc()) {
            $vaccine_types[] = $row;
        }
        $result->free();
    }
} else {
    $message = "<p class='error-message'>Error al ejecutar la consulta: " . $conn->error . "</p>";
}

// 3. Función para obtener el nombre del asistente/veterinario por ID
function getAttendantUsername($conn, $attendant_id) {
    if ($attendant_id === 0) return 'Sistema/Desconocido';
    
    $sql_user = "SELECT username FROM users WHERE id = ?";
    if ($stmt = $conn->prepare($sql_user)) {
        $stmt->bind_param("i", $attendant_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return htmlspecialchars($row['username']);
        }
        $stmt->close();
    }
    return 'ID: ' . $attendant_id;
}


if (isset($conn)) {
    // Si la conexión existe, la cerramos al final.
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Tipos de Vacuna</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        /* Estilos específicos para la tabla de listado */
        body {
            background-color: #f4f4f4; 
            padding-top: 60px;
        }
        .dashboard-container {
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .main-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 1000px;
            color: #333;
        }
        h1 {
            color: #1b4332;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success-message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error-message {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
            text-align: center;
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .action-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-add {
            background-color: #2d6a4f;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .btn-add:hover {
            background-color: #1b4332;
        }
        
        /* Estilos de la tabla */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .data-table th, .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .data-table th {
            background-color: #2d6a4f;
            color: white;
            font-weight: 600;
            cursor: pointer; /* Indica que se puede ordenar */
        }
        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .data-table tr:hover {
            background-color: #f1f1f1;
        }
        .center-text {
            text-align: center;
        }
        .species-tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 500;
            background-color: #e0f2f1;
            color: #004d40;
        }
    </style>
</head>
<body>
    
    <?php include '../includes/navbar.php'; ?>

    <div class="dashboard-container">
        <div class="main-content">
            <h1>📋 Tipos de Vacunas Registrados</h1>

            <?php echo $message; ?>

            <div class="action-bar">
                <p>Total de Tipos de Vacunas: <strong><?php echo count($vaccine_types); ?></strong></p>
                <a href="vaccine_type_register.php" class="btn-add">➕ Registrar Nuevo Tipo</a>
            </div>

            <?php if (empty($vaccine_types)): ?>
                <p class="center-text">No hay tipos de vacunas registrados. ¡Comience a agregar uno!</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Especie Objetivo</th>
                                <th>Descripción</th>
                                <th>Registrado por</th>
                                <th>Fecha de Registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vaccine_types as $type): ?>
                                <tr>
                                    <td class="center-text"><?php echo htmlspecialchars($type['id']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($type['name']); ?></strong></td>
                                    <td><span class="species-tag"><?php echo htmlspecialchars($type['species_target']); ?></span></td>
                                    <td><?php 
                                        // Mostrar una versión corta de la descripción
                                        $desc = htmlspecialchars($type['description']);
                                        echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : $desc;
                                    ?></td>
                                    <td><?php echo getAttendantUsername($conn, $type['attendant_id']); ?></td>
                                    <td><?php echo date("d/m/Y H:i", strtotime($type['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
<?php 
if (isset($conn)) {
    $conn->close();
}
?>