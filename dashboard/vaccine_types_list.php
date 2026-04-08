<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$message = '';
$vaccine_types = [];

if (isset($_GET['success']) && $_GET['success'] === 'add') {
    $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Tipo de vacuna registrado exitosamente.</div>";
}

try {
    $sql = "SELECT id, name, description, species_target, attendant_id, created_at FROM vaccine_types ORDER BY created_at DESC";
    $stmt = $conn->query($sql);
    $vaccine_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al ejecutar la consulta: " . htmlspecialchars($e->getMessage()) . "</div>";
}

function getAttendantUsername($conn, $attendant_id) {
    if (empty($attendant_id)) return 'Sistema/Desconocido';
    $sql_user = "SELECT username FROM users WHERE id = :id";
    $stmt = $conn->prepare($sql_user);
    $stmt->bindValue(':id', $attendant_id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? htmlspecialchars($row['username']) : 'ID: ' . $attendant_id;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Tipos de Vacuna - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
        }
        body {
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .dashboard-container {
            display: flex;
            justify-content: center;
            padding: 40px 20px;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 1000px;
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
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
        }
        .alert-success {
            background: #e0f2e9;
            color: #1e7b4a;
            border-left-color: #1e7b4a;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #dc3545;
        }
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        .btn-add {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-add:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .table-responsive {
            overflow-x: auto;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
        }
        .data-table th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
            vertical-align: top;
        }
        .data-table tr:hover td {
            background-color: #f9fbfd;
        }
        .species-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e0f2fe;
            color: #0369a1;
        }
        .description-cell {
            max-width: 300px;
            word-break: break-word;
        }
        .center-text {
            text-align: center;
            padding: 40px;
            color: #5b6e8c;
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .action-bar { flex-direction: column; align-items: stretch; }
            .btn-add { justify-content: center; }
            .data-table th, .data-table td { padding: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-vial"></i> Tipos de Vacunas Registrados</h1>

            <?php echo $message; ?>

            <div class="action-bar">
                <p>Total de Tipos de Vacunas: <strong><?php echo count($vaccine_types); ?></strong></p>
                <a href="vaccine_type_register.php" class="btn-add"><i class="fas fa-plus"></i> Registrar Nuevo Tipo</a>
            </div>

            <?php if (empty($vaccine_types)): ?>
                <div class="center-text">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    No hay tipos de vacunas registrados. ¡Comience a agregar uno!
                </div>
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
                                    <td data-label="ID" class="center-text"><?php echo htmlspecialchars($type['id'] ?? ''); ?></td>
                                    <td data-label="Nombre"><strong><?php echo htmlspecialchars($type['name'] ?? ''); ?></strong></td>
                                    <td data-label="Especie Objetivo">
                                        <?php 
                                            $species = $type['species_target'] ?? '';
                                            if (!empty($species)) {
                                                echo '<span class="species-tag">' . htmlspecialchars($species) . '</span>';
                                            } else {
                                                echo '<span style="color:#999;">No especificada</span>';
                                            }
                                        ?>
                                    </td>
                                    <td data-label="Descripción" class="description-cell">
                                        <?php 
                                            $desc = htmlspecialchars($type['description'] ?? '');
                                            echo strlen($desc) > 50 ? substr($desc, 0, 50) . '...' : ($desc ?: '—');
                                        ?>
                                    </td>
                                    <td data-label="Registrado por"><?php echo getAttendantUsername($conn, $type['attendant_id'] ?? 0); ?></td>
                                    <td data-label="Fecha de Registro"><?php echo date("d/m/Y H:i", strtotime($type['created_at'] ?? 'now')); ?></td>
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
