<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

require_once '../includes/config.php';

$current_user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

$owner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($role_name === 'Propietario') {
    $owner_id = $current_user_id;
}

if ($owner_id <= 0) {
    die("ID de dueño no válido.");
}

// Obtener datos del dueño
$sql_owner = "SELECT u.id, u.username, u.email, u.ci, u.first_name, u.last_name, u.phone, u.address, u.status,
                     u.created_at, r.name as role_name
              FROM users u
              LEFT JOIN roles r ON u.role_id = r.id
              WHERE u.id = ?";
if ($role_name !== 'admin' && $role_name !== 'Veterinario') {
    $sql_owner .= " AND u.role_id = 3";
}

$stmt = $conn->prepare($sql_owner);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$owner = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$owner) {
    die("Dueño no encontrado o no tienes permiso para verlo.");
}

// Obtener mascotas
$sql_pets = "SELECT p.id, p.name, p.date_of_birth, p.gender, 
                    pt.name AS species_name, b.name AS breed_name
             FROM pets p
             LEFT JOIN pet_types pt ON p.type_id = pt.id
             LEFT JOIN breeds b ON p.breed_id = b.id
             WHERE p.owner_id = ?
             ORDER BY p.name ASC";
$stmt = $conn->prepare($sql_pets);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result_pets = $stmt->get_result();
$pets = [];
while ($row = $result_pets->fetch_assoc()) {
    $pets[] = $row;
}
$stmt->close();

$conn->close();

$full_name = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
if (empty($full_name)) {
    $full_name = $owner['username'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perfil de Dueño · <?php echo htmlspecialchars($full_name); ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-top: 70px;
        }
        .breadcrumb {
            max-width: 1000px;
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
        .container {
            max-width: 1000px;
            margin: 30px auto;
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
        .owner-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #1b4332;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .owner-card p {
            margin: 5px 0;
        }
        .owner-card strong {
            color: #1b4332;
            min-width: 80px;
            display: inline-block;
        }
        .pets-section h2 {
            color: #1b4332;
            margin: 20px 0 15px;
            border-bottom: 2px solid #b68b40;
            padding-bottom: 5px;
        }
        .pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .pet-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .pet-card h3 {
            margin: 0 0 10px 0;
            color: #2d6a4f;
        }
        .pet-card p {
            margin: 8px 0;
            color: #495057;
        }
        .pet-card .actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s;
        }
        .btn-primary {
            background: #1b4332;
            color: white;
        }
        .btn-primary:hover {
            background: #2d6a4f;
        }
        .btn-outline {
            background: white;
            color: #1b4332;
            border: 2px solid #1b4332;
        }
        .btn-outline:hover {
            background: #1b4332;
            color: white;
        }
        .btn-sm {
            padding: 5px 12px;
            font-size: 0.9rem;
        }
        .no-pets {
            text-align: center;
            padding: 40px;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
        }
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
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <!-- Breadcrumbs -->
    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="owner_list.php">Dueños</a> <span>›</span>
        <span><?php echo htmlspecialchars($full_name); ?></span>
    </div>

    <div class="container">
        <h1><i class="fas fa-user"></i> Perfil del Dueño</h1>

        <div class="owner-card">
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($full_name); ?></p>
            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($owner['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($owner['email'] ?? 'N/A'); ?></p>
            <p><strong>CI:</strong> <?php echo htmlspecialchars($owner['ci'] ?? 'N/A'); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($owner['phone'] ?? 'N/A'); ?></p>
            <p><strong>Dirección:</strong> <?php echo nl2br(htmlspecialchars($owner['address'] ?? 'N/A')); ?></p>
            <p><strong>Estado:</strong> 
                <span class="badge <?php echo $owner['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                    <?php echo htmlspecialchars($owner['status']); ?>
                </span>
            </p>
            <p><strong>Rol:</strong> <?php echo htmlspecialchars($owner['role_name'] ?? 'Propietario'); ?></p>
            <p><strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($owner['created_at'])); ?></p>
            <p><strong>Total mascotas:</strong> <?php echo count($pets); ?></p>
        </div>

        <div class="pets-section">
            <h2><i class="fas fa-paw"></i> Mascotas de <?php echo htmlspecialchars($full_name); ?></h2>

            <?php if (empty($pets)): ?>
                <div class="no-pets">
                    <i class="fas fa-dog" style="font-size: 3rem; margin-bottom: 15px;"></i>
                    <p>Este dueño no tiene mascotas registradas.</p>
                    <?php if ($role_name !== 'Propietario'): ?>
                        <a href="pet_register.php?owner_id=<?php echo $owner_id; ?>" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i> Registrar nueva mascota
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="pets-grid">
                    <?php foreach ($pets as $pet): ?>
                        <div class="pet-card">
                            <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
                            <p><i class="fas fa-paw"></i> <strong>Especie:</strong> <?php echo htmlspecialchars($pet['species_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-dna"></i> <strong>Raza:</strong> <?php echo htmlspecialchars($pet['breed_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-calendar-alt"></i> <strong>Nacimiento:</strong> <?php echo date('d/m/Y', strtotime($pet['date_of_birth'])); ?></p>
                            <p><i class="fas fa-venus-mars"></i> <strong>Sexo:</strong> <?php echo htmlspecialchars($pet['gender'] ?? 'N/A'); ?></p>
                            <div class="actions">
                                <a href="pet_profile.php?id=<?php echo $pet['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> Ver perfil
                                </a>
                                <?php if ($role_name !== 'Propietario'): ?>
                                    <a href="pet_edit.php?id=<?php echo $pet['id']; ?>" class="btn btn-outline btn-sm">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>