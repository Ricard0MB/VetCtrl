<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$current_user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

$owner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($role_name === 'Propietario') {
    $owner_id = $current_user_id;
}

if ($owner_id <= 0) {
    die("ID de dueño no válido.");
}

$owner = null;
$pets = [];

try {
    $sql_owner = "SELECT u.id, u.username, u.email, u.ci, u.first_name, u.last_name, u.phone, u.address, u.status,
                         u.created_at, r.name as role_name
                  FROM users u
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE u.id = :owner_id";
    if ($role_name !== 'admin' && $role_name !== 'Veterinario') {
        $sql_owner .= " AND u.role_id = 3";
    }
    $stmt = $conn->prepare($sql_owner);
    $stmt->bindValue(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$owner) {
        die("Dueño no encontrado o no tienes permiso para verlo.");
    }

    $sql_pets = "SELECT p.id, p.name, p.date_of_birth, p.gender, 
                        pt.name AS species_name, b.name AS breed_name
                 FROM pets p
                 LEFT JOIN pet_types pt ON p.type_id = pt.id
                 LEFT JOIN breeds b ON p.breed_id = b.id
                 WHERE p.owner_id = :owner_id
                 ORDER BY p.name ASC";
    $stmt = $conn->prepare($sql_pets);
    $stmt->bindValue(':owner_id', $owner_id, PDO::PARAM_INT);
    $stmt->execute();
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al cargar los datos: " . $e->getMessage());
}

$full_name = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
if (empty($full_name)) {
    $full_name = $owner['username'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Dueño · <?php echo htmlspecialchars($full_name); ?></title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8fafc;
        }
        body {
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
            background-color: var(--gray-bg);
            padding-top: 70px;
        }
        .breadcrumb {
            max-width: 1100px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .container {
            max-width: 1100px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .owner-card {
            background: linear-gradient(135deg, #f9fbfd 0%, #ffffff 100%);
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 35px;
            border: 1px solid #eef2f8;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }
        .owner-card p {
            margin: 6px 0;
            display: flex;
            align-items: baseline;
            gap: 8px;
            flex-wrap: wrap;
        }
        .owner-card strong {
            color: var(--primary-dark);
            min-width: 100px;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: #e0f2e9; color: #1e7b4a; }
        .badge-danger { background: #fee7e7; color: #b91c1c; }
        .pets-section h2 {
            color: var(--primary-dark);
            margin: 20px 0 20px;
            border-left: 5px solid var(--accent);
            padding-left: 15px;
        }
        .pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        .pet-card {
            background: white;
            border-radius: 24px;
            padding: 22px;
            transition: all 0.2s;
            border: 1px solid #eef2f8;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1);
            border-color: var(--primary-light);
        }
        .pet-card h3 {
            margin: 0 0 12px;
            color: var(--primary);
            font-weight: 700;
        }
        .pet-card p {
            margin: 8px 0;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .actions {
            margin-top: 18px;
            display: flex;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-outline {
            background: white;
            color: var(--primary-dark);
            border: 2px solid var(--primary-dark);
        }
        .btn-outline:hover {
            background: var(--primary-dark);
            color: white;
        }
        .btn-sm {
            padding: 5px 14px;
            font-size: 0.8rem;
        }
        .no-pets {
            text-align: center;
            padding: 50px;
            background: #f9fbfd;
            border-radius: 28px;
            color: #6c7a91;
        }
        @media (max-width: 700px) {
            .container { padding: 20px; }
            .owner-card { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="owner_list.php">Dueños</a> <span>›</span>
        <span><?php echo htmlspecialchars($full_name); ?></span>
    </div>

    <div class="container">
        <h1><i class="fas fa-user-circle"></i> Perfil del Dueño</h1>

        <div class="owner-card">
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($full_name); ?></p>
            <p><strong>Usuario:</strong> <?php echo htmlspecialchars($owner['username']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($owner['email'] ?? 'N/A'); ?></p>
            <p><strong>CI:</strong> <?php echo htmlspecialchars($owner['ci'] ?? 'N/A'); ?></p>
            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($owner['phone'] ?? 'N/A'); ?></p>
            <p><strong>Dirección:</strong> <?php echo nl2br(htmlspecialchars($owner['address'] ?? 'N/A')); ?></p>
            <p><strong>Estado:</strong> <span class="badge <?php echo $owner['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>"><?php echo htmlspecialchars($owner['status']); ?></span></p>
            <p><strong>Rol:</strong> <?php echo htmlspecialchars($owner['role_name'] ?? 'Propietario'); ?></p>
            <p><strong>Miembro desde:</strong> <?php echo date('d/m/Y', strtotime($owner['created_at'])); ?></p>
            <p><strong>Total mascotas:</strong> <?php echo count($pets); ?></p>
        </div>

        <div class="pets-section">
            <h2><i class="fas fa-paw"></i> Mascotas de <?php echo htmlspecialchars($full_name); ?></h2>

            <?php if (empty($pets)): ?>
                <div class="no-pets">
                    <i class="fas fa-dog" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>Este dueño no tiene mascotas registradas.</p>
                    <?php if ($role_name !== 'Propietario'): ?>
                        <a href="pet_register.php?owner_id=<?php echo $owner_id; ?>" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Registrar nueva mascota</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="pets-grid">
                    <?php foreach ($pets as $pet): ?>
                        <div class="pet-card">
                            <h3><i class="fas fa-dog"></i> <?php echo htmlspecialchars($pet['name']); ?></h3>
                            <p><i class="fas fa-paw"></i> <strong>Especie:</strong> <?php echo htmlspecialchars($pet['species_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-dna"></i> <strong>Raza:</strong> <?php echo htmlspecialchars($pet['breed_name'] ?? 'N/A'); ?></p>
                            <p><i class="fas fa-calendar-alt"></i> <strong>Nacimiento:</strong> <?php echo date('d/m/Y', strtotime($pet['date_of_birth'])); ?></p>
                            <p><i class="fas fa-venus-mars"></i> <strong>Sexo:</strong> <?php echo htmlspecialchars($pet['gender'] ?? 'N/A'); ?></p>
                            <div class="actions">
                                <a href="pet_profile.php?id=<?php echo $pet['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Ver perfil</a>
                                <?php if ($role_name !== 'Propietario'): ?>
                                    <a href="pet_edit.php?id=<?php echo $pet['id']; ?>" class="btn btn-outline btn-sm"><i class="fas fa-edit"></i> Editar</a>
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
