<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

// Inicializar variables de sesión para la barra de navegación
$username = $_SESSION["username"] ?? 'Veterinario'; 
$attendant_id = $_SESSION['user_id'];
$alert_records = [];
$message = '';
$today = date('Y-m-d');
$future_date = date('Y-m-d', strtotime('+60 days')); 

try {
    // Consulta SQL - incluyendo teléfono del dueño
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

// No es necesario cerrar la conexión explícitamente con PDO
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alertas de Vacunas - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== RESET Y BASE ===== */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f4f4f4;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
            line-height: 1.5;
        }

        /* ===== BREADCRUMB ===== */
        .breadcrumb {
            max-width: 1200px;
            margin: 10px auto 0;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.95rem;
            word-break: break-word;
            white-space: normal;
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

        /* ===== CONTENEDOR PRINCIPAL ===== */
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .main-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        /* ===== TÍTULOS ===== */
        h1 {
            color: #1b4332;
            border-bottom: 2px solid #b68b40;
            padding-bottom: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        h1 i {
            color: #e74c3c; /* Rojo para icono de alerta */
        }

        /* ===== ALERTAS ===== */
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

        /* ===== BOTONES PRINCIPALES ===== */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: #40916c;
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s, transform 0.2s;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 100%;
            box-sizing: border-box;
            text-align: center;
        }
        .btn-primary:hover {
            background: #2d6a4f;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .btn-outline {
            background: transparent;
            border: 2px solid #40916c;
            color: #40916c;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-outline:hover {
            background: #40916c;
            color: white;
        }

        /* ===== TABLA DE ALERTAS ===== */
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }
        .alert-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: auto;
            word-break: break-word;
            font-size: 0.9rem;
        }
        .alert-table th {
            background: #40916c; /* Verde institucional (igual que pet_list) */
            color: white;
            padding: 12px 15px;
            text-align: left;
            white-space: nowrap;
            font-weight: 600;
        }
        .alert-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        .alert-table tr:hover {
            background: #f8f9fa;
        }
        .expired-row {
            background-color: #ffeaea;
            border-left: 5px solid #dc3545;
        }
        .due-soon-row {
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
        }
        .days-remaining {
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 20px;
            color: white;
            display: inline-block;
            font-size: 0.9em;
            text-align: center;
            min-width: 120px;
        }
        .days-remaining.expired {
            background-color: #dc3545; /* Rojo para vencidas */
        }
        .days-remaining.due-soon {
            background-color: #ffc107; /* Naranja para próximas */
            color: #333;
        }

        /* Botón de acción */
        .btn-action {
            background-color: #40916c;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-block;
            transition: background 0.2s;
        }
        .btn-action:hover {
            background-color: #2d6a4f;
            text-decoration: none;
        }

        .total-count {
            margin-top: 20px;
            text-align: right;
            color: #6c757d;
            font-style: italic;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .info-box {
            background: #e8f4fc;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        /* ===== MEDIA QUERIES ===== */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-primary, .btn-outline {
                justify-content: center;
            }
            .alert-table th, .alert-table td {
                padding: 8px 10px;
                white-space: normal;
            }
            .days-remaining {
                min-width: auto;
                padding: 4px 8px;
            }
            .main-content {
                padding: 20px 15px;
            }
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
                <i class="fas fa-info-circle"></i> Mostrando vacunas vencidas y aquellas que vencen en los próximos <strong>60 días</strong>.
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
                                     <td>
                                        <a href="pet_profile.php?id=<?php echo $record['pet_id']; ?>" style="color: #1b4332; font-weight: 600; text-decoration: none;">
                                            <?php echo htmlspecialchars($record['pet_name']); ?>
                                        </a>
                                        <div class="pet-info" style="font-size: 0.85rem; color: #6c757d;">
                                            <?php 
                                                echo htmlspecialchars($record['pet_species_name'] ?? 'Desconocida'); 
                                                if (!empty($record['pet_breed_name'])) {
                                                    echo ' (' . htmlspecialchars($record['pet_breed_name']) . ')';
                                                }
                                            ?>
                                        </div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($record['vaccine_name']); ?></strong></td>
                                    <td><strong><?php echo date('d/m/Y', strtotime($next_due_date)); ?></strong></td>
                                    <td><span class="days-remaining <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($record['owner_name'] ?? 'N/D'); ?></strong>
                                            <?php if (!empty($record['owner_phone'])): ?>
                                                <br><small><a href="tel:<?php echo htmlspecialchars($record['owner_phone']); ?>" style="color: #40916c;">📞 <?php echo htmlspecialchars($record['owner_phone']); ?></a></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
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
