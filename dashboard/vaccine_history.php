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
    <title>Historial de Vacunas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1000px;
            margin: 10px auto 0;
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
        .dashboard-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        h1 {
            color: #1b4332;
            border-bottom: 2px solid #b68b40;
            padding-bottom: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-info { background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .info-header {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            color: #01579b;
        }
        .vaccine-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .vaccine-table th {
            background: #40916c;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .vaccine-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        .vaccine-table tr:hover {
            background: #f5f5f5;
        }
        .date-tag {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.8rem;
            display: inline-block;
        }
        .expired { background: #dc3545; color: white; }
        .due-soon { background: #ffc107; color: #333; }
        .ok { background: #28a745; color: white; }
        .na { background: #6c757d; color: white; }
        .btn-action {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            color: white;
            margin: 2px;
        }
        .btn-view { background: #17a2b8; }
        .btn-apply { background: #40916c; }
        .navigation-links a {
            color: #40916c;
            text-decoration: none;
            font-weight: 600;
            margin: 0 5px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
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
                <strong>Vacunas aplicadas por <?php echo htmlspecialchars($username); ?></strong>
            </div>

            <p style="text-align: center; margin-bottom: 20px;">
                <a href="welcome.php"><i class="fas fa-home"></i> Dashboard</a> |
                <a href="vaccine_select_pet.php">Registrar Vacuna</a> |
                <a href="vaccine_alerts.php">Alertas</a>
            </p>

            <?php echo $message; ?>

            <?php if (empty($vaccines)): ?>
                <div class="no-data">
                    <i class="fas fa-syringe" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>No hay vacunas registradas.</p>
                    <a href="vaccine_select_pet.php" class="btn-primary" style="padding: 8px 16px;">Registrar primera vacuna</a>
                </div>
            <?php else: ?>
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
                            <td><strong><a href="pet_profile.php?id=<?php echo $v['pet_id']; ?>"><?php echo htmlspecialchars($v['pet_name']); ?></a></strong></td>
                            <td>
                                <small><?php echo htmlspecialchars($v['species_name'] ?? 'Desconocida'); ?>
                                <?php echo !empty($v['breed_name']) ? ' / ' . htmlspecialchars($v['breed_name']) : ''; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($v['vaccine_name']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($v['application_date'])); ?></td>
                            <td><?php echo $next ? date('d/m/Y', strtotime($next)) : '—'; ?></td>
                            <td><?php echo htmlspecialchars($v['lote_number'] ?? 'N/A'); ?></td>
                            <td><span class="date-tag <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                            <td>
                                <a href="pet_profile.php?id=<?php echo $v['pet_id']; ?>" class="btn-action btn-view">Perfil</a>
                                <a href="vaccine_register.php?pet_id=<?php echo $v['pet_id']; ?>" class="btn-action btn-apply">Nueva</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
