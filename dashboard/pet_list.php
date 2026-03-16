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

$pets = [];
$message = '';

try {
    if ($role_name === 'Veterinario' || $role_name === 'admin') {
        $sql = "SELECT 
                    p.id, p.name, pt.name AS species_name, b.name AS breed_name, 
                    p.gender, p.date_of_birth, u.username AS owner_name,
                    u.email AS owner_email, p.created_at
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                LEFT JOIN users u ON p.owner_id = u.id
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
    } else {
        $sql = "SELECT 
                    p.id, p.name, pt.name AS species_name, b.name AS breed_name, 
                    p.gender, p.date_of_birth, p.created_at
                FROM pets p
                LEFT JOIN pet_types pt ON p.type_id = pt.id
                LEFT JOIN breeds b ON p.breed_id = b.id
                WHERE p.owner_id = :owner_id
                ORDER BY p.name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':owner_id', $user_id, PDO::PARAM_INT);
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
    <title>Listado de Pacientes - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
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
            overflow: hidden; /* Evita desbordes */
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
        .page-subtitle {
            text-align: center;
            color: #6c757d;
            margin-bottom: 20px;
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

        /* ===== BOTÓN EXPORTAR PDF ===== */
        .btn-pdf {
            background: #b68b40;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
            transition: background 0.3s;
            max-width: 100%;
            box-sizing: border-box;
            display: inline-block;
        }
        .btn-pdf:hover {
            background: #a07632;
        }

        /* ===== TABLA ===== */
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }
        .pet-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: auto;
            word-break: break-word;
            font-size: 0.9rem;
        }
        .pet-table th {
            background: #40916c;
            color: white;
            padding: 8px 10px;
            text-align: left;
            white-space: nowrap;
        }
        .pet-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #ddd;
            vertical-align: middle;
        }
        .pet-table tr:hover {
            background: #f1f1f1;
        }

        /* Botones de acción dentro de tabla */
        .btn-action {
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            margin: 2px;
            color: white;
            transition: opacity 0.2s;
            text-align: center;
        }
        .btn-action:hover {
            opacity: 0.9;
        }
        .btn-view { background: #3F51B5; }
        .btn-edit { background: #ff9800; }
        .btn-consulta { background: #17a2b8; }

        /* Contador total */
        .total-count {
            margin-top: 20px;
            text-align: right;
            color: #6c757d;
            font-style: italic;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 50px 30px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #6c757d;
            border: 2px dashed #dee2e6;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }
        .empty-state .btn-primary {
            margin-top: 20px;
        }

        /* ===== MEDIA QUERIES PARA MÓVIL ===== */
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-primary {
                justify-content: center;
            }
            .pet-table th {
                white-space: normal;
            }
            /* En móvil, los botones de acción se muestran uno debajo del otro */
            .pet-table td:last-child {
                display: flex;
                flex-direction: column;
                gap: 4px;
                align-items: flex-start;
            }
            .btn-action {
                width: 100%;
                text-align: center;
            }
            .breadcrumb {
                padding: 10px 15px;
                font-size: 0.85rem;
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
        <span>Listado de Pacientes</span>
    </div>

    <div class="dashboard-container">
        <div class="main-content">
            <h1><i class="fas fa-paw"></i> Listado de Pacientes</h1>
            <p class="page-subtitle">
                <?php echo ($role_name === 'Propietario') ? 'Tus mascotas registradas' : 'Todos los pacientes del sistema'; ?>
            </p>

            <?php echo $message; ?>

            <div class="action-buttons">
                <a href="pet_register.php" class="btn-primary"><i class="fas fa-plus-circle"></i> Registrar Mascota</a>
                <a href="search_pet_owner.php" class="btn-primary" style="background: #3F51B5;"><i class="fas fa-search"></i> Buscar</a>
            </div>

            <?php if (empty($pets)): ?>
                <div class="empty-state">
                    <i class="fas fa-dog"></i>
                    <p>No hay mascotas registradas.</p>
                    <a href="pet_register.php" class="btn-primary">Registrar primera mascota</a>
                </div>
            <?php else: ?>
                <div style="display: flex; justify-content: flex-end;">
                    <button id="btnExportPdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
                </div>

                <div class="table-responsive">
                    <table class="pet-table" id="petsTable">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Especie</th>
                                <th>Raza</th>
                                <th>Género</th>
                                <th>F. Nacimiento</th>
                                <?php if ($role_name !== 'Propietario'): ?>
                                    <th>Dueño</th>
                                    <th>Registro</th>
                                <?php else: ?>
                                    <th>Registro</th>
                                <?php endif; ?>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pets as $pet): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($pet['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($pet['species_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pet['breed_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($pet['gender'] ?? 'N/D'); ?></td>
                                <td><?php echo htmlspecialchars($pet['date_of_birth'] ?? 'Desconocida'); ?></td>
                                
                                <?php if ($role_name !== 'Propietario'): ?>
                                    <td>
                                        <?php if (isset($pet['owner_name'])): ?>
                                            <strong><?php echo htmlspecialchars($pet['owner_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($pet['owner_email'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <em>Dueño no encontrado</em>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($pet['created_at'])); ?></td>
                                <?php else: ?>
                                    <td><?php echo date('d/m/Y', strtotime($pet['created_at'])); ?></td>
                                <?php endif; ?>
                                
                                <td>
                                    <a href="pet_profile.php?id=<?php echo $pet['id']; ?>" class="btn-action btn-view" title="Ver perfil"><i class="fas fa-eye"></i> Ver</a>
                                    <a href="pet_edit.php?id=<?php echo $pet['id']; ?>" class="btn-action btn-edit" title="Editar"><i class="fas fa-edit"></i> Editar</a>
                                    <?php if ($role_name !== 'Propietario'): ?>
                                        <a href="consultation_register.php?pet_id=<?php echo $pet['id']; ?>" class="btn-action btn-consulta" title="Registrar consulta"><i class="fas fa-stethoscope"></i> Consulta</a>
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
