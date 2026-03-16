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
    // Consulta SQL - incluyendo teléfono del dueño (asumiendo campo 'phone' en users)
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
        $message = "<p class='success-message'>🎉 ¡Excelente! No hay vacunas vencidas ni próximas a vencer en los próximos 60 días.</p>";
    }

} catch (PDOException $e) {
    $message = "<p class='error-message'>Error al cargar alertas: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// No es necesario cerrar la conexión explícitamente con PDO
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Alertas de Vacunas - Veterinaria</title>
    <link rel="stylesheet" href="../public/css/style.css"> 
    <style>
        /* TEMA CLARO - MODO DÍA */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #f8f9fa; /* Fondo gris claro */
            color: #333; /* Texto oscuro */
            padding-top: 70px;
        }
        
        .dashboard-container {
            padding-left: 20px;
            padding-right: 20px;
            max-width: 1200px;
            margin: 0 auto;
            background-color: #ffffff; /* Fondo blanco */
            min-height: calc(100vh - 70px);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding-bottom: 30px;
            border-radius: 10px;
            border: 1px solid #e0e0e0;
        }
        .main-content {
            background-color: #ffffff; /* Fondo blanco */
            color: #333; /* Texto oscuro */
            padding: 30px;
            border-radius: 10px;
            max-width: 1000px;
            margin: 0 auto;
            border: 1px solid #eaeaea;
        }
        
        h1 {
            color: #e74c3c; /* Rojo para alertas - mantenido por ser crítico */
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            text-align: center;
            font-weight: 600;
            font-size: 2em;
        }
        
        .alert-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        .alert-table th, .alert-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            color: #333; 
        }
        .alert-table th {
            background-color: #e74c3c; /* Rojo para encabezado de alertas */
            color: white;
            font-weight: 600;
            font-size: 0.9em;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        
        .expired-row {
            background-color: #ffeaea; /* Rojo muy claro para vencidas */
            border-left: 5px solid #dc3545;
        }
        .due-soon-row {
            background-color: #fff3cd; /* Amarillo claro para próximas */
            border-left: 5px solid #ffc107;
        }
        .alert-table tr:hover {
            background-color: #f8f9fa;
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
            background-color: #dc3545;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }
        .days-remaining.due-soon {
            background-color: #ffc107;
            color: #333;
            box-shadow: 0 2px 4px rgba(255, 193, 7, 0.2);
        }
        .success-message {
            background-color: #d4edda; /* Verde claro */
            color: #155724; /* Verde oscuro */
            border: 1px solid #c3e6cb;
            margin-top: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            border-left: 4px solid #28a745;
        }
        .error-message {
            background-color: #f8d7da; /* Rojo claro */
            color: #721c24; /* Rojo oscuro */
            border: 1px solid #f5c6cb;
            margin-top: 20px;
            padding: 15px 20px;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            border-left: 4px solid #dc3545;
        }
        
        .main-content a {
            color: #3498db; /* Azul para enlaces */
            text-decoration: none;
            font-weight: 500;
        }
        .main-content a:hover {
            text-decoration: underline;
            color: #2980b9;
        }
        
        .alert-info {
            background-color: #e8f4fc; /* Azul muy claro */
            color: #0c5460;
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid #3498db;
            font-size: 0.95em;
        }
        
        .alert-info strong {
            color: #2c3e50;
        }
        
        .navigation-links {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #eaeaea;
        }
        
        .navigation-links a {
            margin: 0 10px;
            padding: 8px 16px;
            border-radius: 4px;
            background-color: #f1f1f1;
            transition: all 0.3s;
        }
        
        .navigation-links a:hover {
            background-color: #3498db;
            color: white;
            text-decoration: none;
        }
        
        .pet-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        
        .owner-info {
            font-size: 0.9em;
        }
        
        .owner-info small {
            color: #666;
        }
        
        .action-button {
            background-color: #2ecc71; /* Verde para acción */
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9em;
            transition: all 0.3s;
            display: inline-block;
            text-align: center;
            min-width: 120px;
        }
        
        .action-button:hover {
            background-color: #27ae60;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding-left: 10px;
                padding-right: 10px;
            }
            .main-content {
                padding: 15px;
            }
            .alert-table {
                display: block;
                overflow-x: auto;
            }
            .navigation-links {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .navigation-links a {
                margin: 0;
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="dashboard-container">
        <div class="main-content">
            <h1>Alertas de Vacunación Críticas 🚨</h1>
            
            <div class="alert-info">
                <strong>Nota:</strong> Mostrando vacunas vencidas y aquellas que vencen en los próximos <strong>60 días</strong>.
                Solo se muestran las vacunas registradas por <strong><?php echo htmlspecialchars($username); ?></strong>.
            </div>
            
            <div class="navigation-links">
                <a href="welcome.php">← Volver al Dashboard</a> 
                <a href="vaccine_history.php">📅 Ver Calendario Completo</a>
                <a href="vaccine_select_pet.php">💉 Aplicar Nueva Vacuna</a>
            </div>

            <?php echo $message; ?>

            <?php if (!empty($alert_records)): ?>
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
                                <a href="pet_profile.php?id=<?php echo $record['pet_id']; ?>">
                                    <strong><?php echo htmlspecialchars($record['pet_name']); ?></strong>
                                </a>
                                <div class="pet-info">
                                    <?php 
                                        echo htmlspecialchars($record['pet_species_name'] ?? 'Desconocida'); 
                                        if (!empty($record['pet_breed_name'])) {
                                            echo ' (' . htmlspecialchars($record['pet_breed_name']) . ')';
                                        }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['vaccine_name']); ?></strong>
                            </td> 
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($next_due_date)); ?></strong>
                            </td>
                            <td>
                                <span class="days-remaining <?php echo $status_class; ?>">
                                    <?php echo $status_text; ?>
                                </span>
                            </td>
                            <td>
                                <div class="owner-info">
                                    <strong><?php echo htmlspecialchars($record['owner_name'] ?? 'N/D'); ?></strong>
                                    <?php if (!empty($record['owner_phone'])): ?>
                                        <br><small><a href="tel:<?php echo htmlspecialchars($record['owner_phone']); ?>">
                                            📞 <?php echo htmlspecialchars($record['owner_phone']); ?>
                                        </a></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="vaccine_register.php?id=<?php echo $record['pet_id']; ?>" class="action-button">
                                    💉 Aplicar Dosis
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="margin-top: 25px; text-align: center; color: #7f8c8d; font-size: 0.9em;">
                    Total de alertas: <strong><?php echo count($alert_records); ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
