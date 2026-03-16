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

// Solo admin y veterinario
if ($role_name !== 'admin' && $role_name !== 'Veterinario') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$owners = [];
$error_message = '';

try {
    $sql = "SELECT id, username, email, first_name, last_name, phone, ci 
            FROM users 
            WHERE role_id = 3
            ORDER BY first_name, last_name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Lista de Dueños - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <style>
        body {
            background-color: #f4f4f4;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 1100px;
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
        .container {
            max-width: 1100px;
            margin: 20px auto;
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
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: 0.3s;
        }
        .btn-primary {
            background: #1b4332;
            color: white;
        }
        .btn-primary:hover {
            background: #2d6a4f;
        }
        .btn-pdf {
            background: #b68b40;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .btn-pdf:hover {
            background: #a07632;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .table th {
            background: #40916c;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
        }
        .table tr:hover {
            background: #f5f5f5;
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
            <button id="generatePdf" class="btn-pdf"><i class="fas fa-file-pdf"></i> Generar PDF</button>
        <?php endif; ?>

        <?php if ($error_message) echo $error_message; ?>

        <?php if (empty($owners)): ?>
            <p>No hay dueños registrados.</p>
        <?php else: ?>
            <table class="table" id="ownerTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Cédula</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($owners as $owner): 
                        $full_name = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));
                        if (empty($full_name)) $full_name = $owner['username'];
                    ?>
                        <tr>
                            <td><?php echo $owner['id']; ?></td>
                            <td><?php echo htmlspecialchars($full_name); ?></td>
                            <td><?php echo htmlspecialchars($owner['email']); ?></td>
                            <td><?php echo htmlspecialchars($owner['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($owner['ci'] ?? 'N/A'); ?></td>
                            <td><a href="owner_details.php?id=<?php echo $owner['id']; ?>" class="btn btn-primary btn-sm">Ver Detalle</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
