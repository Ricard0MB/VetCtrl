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

if ($role_name !== 'admin' && $role_name !== 'Veterinario') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$owners = [];
$error_message = '';

try {
    $stmt_role = $conn->prepare("SELECT id FROM roles WHERE name = :role_name");
    $stmt_role->bindValue(':role_name', 'Propietario');
    $stmt_role->execute();
    $role_propietario = $stmt_role->fetch(PDO::FETCH_ASSOC);

    if (!$role_propietario) {
        $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error: No se encontró el rol 'Propietario' en el sistema.</div>";
    } else {
        $role_id = $role_propietario['id'];
        $sql = "SELECT id, username, email, first_name, last_name, phone, ci 
                FROM users 
                WHERE role_id = :role_id
                ORDER BY first_name, last_name ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':role_id', $role_id, PDO::PARAM_INT);
        $stmt->execute();
        $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Dueños - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
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
            max-width: 1100px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .container {
            max-width: 1100px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
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
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left: 5px solid #b91c1c;
        }
        .btn {
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        .btn-pdf {
            background: var(--accent);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-pdf:hover {
            background: #9e6b2f;
            transform: translateY(-2px);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 20px;
            overflow: hidden;
            margin-top: 20px;
        }
        .table th {
            background: var(--primary-dark);
            color: white;
            padding: 14px;
            text-align: left;
            font-weight: 600;
        }
        .table td {
            padding: 12px;
            border-bottom: 1px solid #eef2f8;
        }
        .table tr:hover td {
            background-color: #f9fbfd;
        }
        .btn-sm {
            padding: 5px 14px;
            font-size: 0.8rem;
        }
        @media (max-width: 700px) {
            .container { padding: 20px; }
            .table, .table thead, .table tbody, .table th, .table td, .table tr { display: block; }
            .table th { display: none; }
            .table td { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
            .table td::before { content: attr(data-label); font-weight: 600; width: 40%; color: var(--primary-dark); }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Lista de Dueños</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-users"></i> Lista de Dueños (Clientes)</h1>
        <a href="welcome.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver</a>
        <?php if (!empty($owners)): ?>
            <button id="generatePdf" class="btn-pdf" style="margin-left: 15px;"><i class="fas fa-file-pdf"></i> Generar PDF</button>
        <?php endif; ?>

        <?php if ($error_message) echo $error_message; ?>

        <?php if (empty($owners) && !$error_message): ?>
            <p>No hay dueños registrados.</p>
        <?php elseif (!empty($owners)): ?>
            <div style="overflow-x: auto;">
                <table class="table" id="ownerTable">
                    <thead>
                        <tr><th>ID</th><th>Nombre Completo</th><th>Email</th><th>Teléfono</th><th>Cédula</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($owners as $owner): 
                            $full_name = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
                            if (empty($full_name)) $full_name = $owner['username'];
                        ?>
                        <tr>
                            <td data-label="ID"><?php echo $owner['id']; ?></td>
                            <td data-label="Nombre"><?php echo htmlspecialchars($full_name); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($owner['email']); ?></td>
                            <td data-label="Teléfono"><?php echo htmlspecialchars($owner['phone'] ?? 'N/A'); ?></td>
                            <td data-label="Cédula"><?php echo htmlspecialchars($owner['ci'] ?? 'N/A'); ?></td>
                            <td data-label="Acciones"><a href="owner_details.php?id=<?php echo $owner['id']; ?>" class="btn btn-primary btn-sm">Ver Detalle</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('generatePdf')?.addEventListener('click', function() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.setFontSize(18);
            doc.text("Lista de Dueños", 14, 22);
            doc.setFontSize(11);
            doc.text("Generado el: " + new Date().toLocaleDateString('es-ES'), 14, 30);
            doc.autoTable({
                html: '#ownerTable',
                startY: 40,
                theme: 'grid',
                headStyles: { fillColor: [27, 67, 50], textColor: 255 },
                styles: { fontSize: 9 }
            });
            doc.save("duenios.pdf");
        });
    </script>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
