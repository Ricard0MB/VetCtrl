<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$username = $_SESSION["username"] ?? 'Veterinario'; 
$attendant_id = $_SESSION['user_id'];
$alert_records = [];
$message = '';
$today = date('Y-m-d');
$future_date = date('Y-m-d', strtotime('+60 days')); 

try {
    $sql = "SELECT 
                v.*, 
                p.name as pet_name,
                p.owner_id,
                u.username as owner_name,
                u.id as user_id,
                u.phone as owner_phone,
                pt.name AS pet_species_name,   
                b.name AS pet_breed_name,     
                vt.name AS vaccine_name
            FROM vaccines v
            JOIN pets p ON v.pet_id = p.id
            LEFT JOIN pet_types pt ON p.type_id = pt.id 
            LEFT JOIN breeds b ON p.breed_id = b.id      
            LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
            LEFT JOIN users u ON p.owner_id = u.id
            WHERE v.attendant_id = :attendant_id 
            AND v.next_due_date IS NOT NULL
            AND v.next_due_date <= :future_date
            ORDER BY v.next_due_date ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':attendant_id', $attendant_id, PDO::PARAM_INT);
    $stmt->bindValue(':future_date', $future_date);
    $stmt->execute();

    $alert_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($alert_records)) {
        $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> ¡Excelente! No hay vacunas vencidas ni próximas a vencer en los próximos 60 días.</div>";
    }

} catch (PDOException $e) {
    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error al cargar alertas: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas de Vacunas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --danger: #dc3545;
            --warning: #ffc107;
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
        .breadcrumb a:hover {
            text-decoration: underline;
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
        h1 i {
            color: var(--danger);
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
            border-left-color: var(--danger);
        }
        .info-box {
            background: #e0f2fe;
            padding: 16px 20px;
            border-radius: 24px;
            margin-bottom: 25px;
            border-left: 5px solid #0ea5e9;
            color: #0369a1;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .btn-primary, .btn-outline {
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }
        .alert-table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
        }
        .alert-table th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        .alert-table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
            vertical-align: middle;
        }
        .alert-table tr:hover td {
            background-color: #f9fbfd;
        }
        .expired-row {
            background-color: #fee7e7;
        }
        .due-soon-row {
            background-color: #fff3cd;
        }
        .days-remaining {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.75rem;
            text-align: center;
            min-width: 120px;
        }
        .days-remaining.expired {
            background-color: var(--danger);
            color: white;
        }
        .days-remaining.due-soon {
            background-color: var(--warning);
            color: #333;
        }
        .btn-action {
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }
        .btn-action:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .total-count {
            margin-top: 20px;
            text-align: right;
            color: #5b6e8c;
            font-size: 0.85rem;
            padding: 10px;
            background: #f9fbfd;
            border-radius: 20px;
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .action-buttons { flex-direction: column; align-items: stretch; }
            .btn-primary, .btn-outline { justify-content: center; }
            .alert-table th, .alert-table td { padding: 10px; }
            .days-remaining { min-width: auto; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Alertas de Vacunas</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-bell"></i> Alertas de Vacunación</h1>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                Mostrando vacunas vencidas y aquellas que vencen en los próximos <strong>60 días</strong>.
                Solo se muestran las vacunas registradas por <strong><?php echo htmlspecialchars($username); ?></strong>.
            </div>

            <div class="action-buttons">
                <a href="welcome.php" class="btn-outline"><i class="fas fa-home"></i> Dashboard</a>
                <a href="vaccine_history.php" class="btn-outline"><i class="fas fa-calendar-alt"></i> Calendario Completo</a>
                <a href="vaccine_select_pet.php" class="btn-primary"><i class="fas fa-syringe"></i> Aplicar Nueva Vacuna</a>
            </div>

            <?php echo $message; ?>

            <?php if (!empty($alert_records)): ?>
                <div class="table-responsive">
                    <table class="alert-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Vacuna</th>
                                <th>Próxima Dosis</th>
                                <th>Estado</th>
                                <th>Dueño</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alert_records as $record): 
                                $next_due_date = $record['next_due_date'];
                                $diff = strtotime($next_due_date) - strtotime($today);
                                $days_remaining = round($diff / (60 * 60 * 24));

                                if ($days_remaining < 0) {
                                    $status_class = 'expired';
                                    $status_text = "¡VENCIDA hace " . abs($days_remaining) . " días!";
                                    $row_class = 'expired-row';
                                } else {
                                    $status_class = 'due-soon';
                                    $status_text = "Faltan {$days_remaining} días";
                                    $row_class = 'due-soon-row';
                                }
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td data-label="Paciente">
                                        <a href="pet_profile.php?id=<?php echo $record['pet_id']; ?>" style="color: var(--primary-dark); font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($record['pet_name']); ?>
                                        </a>
                                        <div style="font-size: 0.8rem; color: #5b6e8c;">
                                            <?php 
                                                echo htmlspecialchars($record['pet_species_name'] ?? 'Desconocida'); 
                                                if (!empty($record['pet_breed_name'])) {
                                                    echo ' (' . htmlspecialchars($record['pet_breed_name']) . ')';
                                                }
                                            ?>
                                        </div>
                                    </td>
                                    <td data-label="Vacuna"><strong><?php echo htmlspecialchars($record['vaccine_name']); ?></strong></td>
                                    <td data-label="Próxima Dosis"><strong><?php echo date('d/m/Y', strtotime($next_due_date)); ?></strong></td>
                                    <td data-label="Estado"><span class="days-remaining <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td data-label="Dueño">
                                        <strong><?php echo htmlspecialchars($record['owner_name'] ?? 'N/D'); ?></strong>
                                        <?php if (!empty($record['owner_phone'])): ?>
                                            <br><small><a href="tel:<?php echo htmlspecialchars($record['owner_phone']); ?>" style="color: var(--primary-light);">📞 <?php echo htmlspecialchars($record['owner_phone']); ?></a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Acción">
                                        <a href="vaccine_register.php?pet_id=<?php echo $record['pet_id']; ?>" class="btn-action">
                                            <i class="fas fa-syringe"></i> Aplicar Dosis
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="total-count">
                    Total de alertas: <strong><?php echo count($alert_records); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
