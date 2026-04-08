<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo Veterinario y Admin pueden acceder
if ($role_name !== 'Veterinario' && $role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$vaccines = [];
$message = '';

try {
    $sql = "SELECT 
                v.*, 
                p.name AS pet_name,
                p.id AS pet_id,
                pt.name AS species_name,      
                b.name AS breed_name,         
                vt.name AS vaccine_name        
            FROM vaccines v
            JOIN pets p ON v.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id 
            LEFT JOIN breeds b ON p.breed_id = b.id      
            JOIN vaccine_types vt ON v.vaccine_type_id = vt.id 
            WHERE v.attendant_id = :attendant_id 
            ORDER BY v.application_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar vacunas: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Vacunas - VetCtrl</title>
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
        .breadcrumb {
            max-width: 1200px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
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
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #dc3545;
        }
        .info-header {
            background: #e0f2fe;
            padding: 15px 20px;
            border-radius: 24px;
            margin-bottom: 25px;
            text-align: center;
            color: #0369a1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .vaccine-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
        }
        .vaccine-table th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        .vaccine-table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
            vertical-align: middle;
        }
        .vaccine-table tr:hover td {
            background-color: #f9fbfd;
        }
        .date-tag {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        .expired { background: #dc3545; color: white; }
        .due-soon { background: #ffc107; color: #333; }
        .ok { background: #28a745; color: white; }
        .na { background: #6c757d; color: white; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
        }
        .btn-info {
            background: #0ea5e9;
            color: white;
        }
        .btn-info:hover {
            background: #0284c7;
            transform: translateY(-2px);
        }
        .btn-success {
            background: var(--primary);
            color: white;
        }
        .btn-success:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-outline-primary {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .navigation-links {
            margin: 25px 0 20px;
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .no-data {
            text-align: center;
            padding: 50px;
            background: #f9fbfd;
            border-radius: 28px;
            color: #5b6e8c;
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .vaccine-table th, .vaccine-table td { padding: 10px; }
            .action-buttons { flex-direction: column; align-items: flex-start; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Historial de Vacunas</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-syringe"></i> Historial de Vacunas</h1>

            <div class="info-header">
                <i class="fas fa-info-circle"></i> Vacunas aplicadas por <strong><?php echo htmlspecialchars($username); ?></strong>
            </div>

            <div class="navigation-links">
                <a href="welcome.php" class="btn btn-outline-primary"><i class="fas fa-home"></i> Dashboard</a>
                <a href="vaccine_select_pet.php" class="btn btn-success"><i class="fas fa-plus-circle"></i> Registrar Vacuna</a>
                <a href="vaccine_alerts.php" class="btn btn-outline-primary"><i class="fas fa-bell"></i> Alertas</a>
            </div>

            <?php echo $message; ?>

            <?php if (empty($vaccines)): ?>
                <div class="no-data">
                    <i class="fas fa-syringe" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                    <p>No hay vacunas registradas.</p>
                    <a href="vaccine_select_pet.php" class="btn btn-success">Registrar primera vacuna</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="vaccine-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Especie/Raza</th>
                                <th>Vacuna</th>
                                <th>Aplicación</th>
                                <th>Próxima Dosis</th>
                                <th>Lote</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $today = date('Y-m-d');
                            foreach ($vaccines as $v): 
                                $next = $v['next_due_date'];
                                $status_class = 'na';
                                $status_text = 'N/D';
                                if ($next) {
                                    $diff = strtotime($next) - strtotime($today);
                                    $days = round($diff / 86400);
                                    if ($days < 0) {
                                        $status_class = 'expired';
                                        $status_text = 'VENCIDA';
                                    } elseif ($days <= 60) {
                                        $status_class = 'due-soon';
                                        $status_text = "Próx. $days días";
                                    } else {
                                        $status_class = 'ok';
                                        $status_text = 'Al día';
                                    }
                                }
                            ?>
                            <tr>
                                <td data-label="Paciente"><strong><?php echo htmlspecialchars($v['pet_name']); ?></strong></td>
                                <td data-label="Especie/Raza">
                                    <small><?php echo htmlspecialchars($v['species_name'] ?? 'Desconocida'); ?>
                                    <?php echo !empty($v['breed_name']) ? ' / ' . htmlspecialchars($v['breed_name']) : ''; ?></small>
                                </td>
                                <td data-label="Vacuna"><?php echo htmlspecialchars($v['vaccine_name']); ?></td>
                                <td data-label="Aplicación"><?php echo date('d/m/Y', strtotime($v['application_date'])); ?></td>
                                <td data-label="Próxima Dosis"><?php echo $next ? date('d/m/Y', strtotime($next)) : '—'; ?></td>
                                <td data-label="Lote"><?php echo htmlspecialchars($v['lote_number'] ?? 'N/A'); ?></td>
                                <td data-label="Estado"><span class="date-tag <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td data-label="Acciones">
                                    <div class="action-buttons">
                                        <a href="pet_profile.php?id=<?php echo $v['pet_id']; ?>" class="btn btn-info"><i class="fas fa-eye"></i> Perfil</a>
                                        <a href="vaccine_register.php?pet_id=<?php echo $v['pet_id']; ?>" class="btn btn-success"><i class="fas fa-syringe"></i> Nueva</a>
                                    </div>
                                </td>
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
