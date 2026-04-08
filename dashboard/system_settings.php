<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if ($role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Crear tabla si no existe (usando PDO)
$conn->exec("CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Valores por defecto
$defaults = [
    'nombre_clinica' => 'Clínica Veterinaria VetCtrl',
    'correo_contacto' => 'contacto@vetctrl.com',
    'telefono' => '+1 234 567 8900',
    'direccion' => 'Calle Principal #123',
    'horario_apertura' => '08:00',
    'horario_cierre' => '18:00',
    'dias_trabajo' => 'Lunes a Viernes',
    'citas_por_hora' => 2,
    'recordatorio_citas' => 24,
    'tiempo_max_consulta' => 30,
    'notificaciones_activas' => 1
];

// Cargar configuraciones actuales
$config = $defaults;
$stmt = $conn->query("SELECT config_key, config_value FROM system_config");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['config_key']] = $row['config_value'];
}

$mensaje = '';
$tipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
        $nuevos = [];
        foreach ($defaults as $key => $val) {
            if ($key === 'notificaciones_activas') {
                $nuevos[$key] = isset($_POST[$key]) ? 1 : 0;
            } else {
                $nuevos[$key] = trim($_POST[$key] ?? $val);
            }
        }
        if (empty($nuevos['nombre_clinica'])) {
            $mensaje = "El nombre de la clínica es obligatorio.";
            $tipo = 'danger';
        } else {
            try {
                $conn->beginTransaction();
                foreach ($nuevos as $k => $v) {
                    $sql = "INSERT INTO system_config (config_key, config_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE config_value = :value2";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue(':key', $k);
                    $stmt->bindValue(':value', $v);
                    $stmt->bindValue(':value2', $v);
                    $stmt->execute();
                }
                $conn->commit();
                $mensaje = "Configuración guardada correctamente.";
                $tipo = 'success';
                $config = $nuevos;
            } catch (PDOException $e) {
                $conn->rollBack();
                $mensaje = "Error al guardar: " . $e->getMessage();
                $tipo = 'danger';
            }
        }
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'restaurar') {
        $conn->exec("DELETE FROM system_config");
        $mensaje = "Valores por defecto restaurados.";
        $tipo = 'success';
        $config = $defaults;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - VetCtrl</title>
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
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 900px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 32px;
            padding: 28px 32px;
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
        .alert-success {
            background: #e0f2e9;
            color: #1e7b4a;
            border-left-color: #1e7b4a;
        }
        .alert-danger {
            background: #fee7e7;
            color: #b91c1c;
            border-left-color: #b91c1c;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .switch {
            width: auto;
            margin-left: 10px;
            transform: scale(1.2);
        }
        .btn {
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #eef2f8;
            color: var(--primary-dark);
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .actions-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        @media (max-width: 700px) {
            .card { padding: 20px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Configuración del Sistema</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-cog"></i> Configuración del Sistema</h1>

            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipo; ?>">
                    <i class="fas fa-<?php echo $tipo === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="accion" value="guardar">

                <div class="form-group">
                    <label>Nombre de la clínica *</label>
                    <input type="text" name="nombre_clinica" value="<?php echo htmlspecialchars($config['nombre_clinica']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email de contacto</label>
                        <input type="email" name="correo_contacto" value="<?php echo htmlspecialchars($config['correo_contacto']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" value="<?php echo htmlspecialchars($config['telefono']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Dirección</label>
                    <input type="text" name="direccion" value="<?php echo htmlspecialchars($config['direccion']); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Horario apertura</label>
                        <input type="time" name="horario_apertura" value="<?php echo htmlspecialchars($config['horario_apertura']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Horario cierre</label>
                        <input type="time" name="horario_cierre" value="<?php echo htmlspecialchars($config['horario_cierre']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Días de trabajo</label>
                    <input type="text" name="dias_trabajo" value="<?php echo htmlspecialchars($config['dias_trabajo']); ?>" placeholder="Ej: Lunes a Viernes">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Citas por hora</label>
                        <input type="number" name="citas_por_hora" min="1" max="10" value="<?php echo $config['citas_por_hora']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Recordatorio de citas (horas)</label>
                        <input type="number" name="recordatorio_citas" min="1" value="<?php echo $config['recordatorio_citas']; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tiempo máximo consulta (min)</label>
                        <input type="number" name="tiempo_max_consulta" min="10" value="<?php echo $config['tiempo_max_consulta']; ?>">
                    </div>
                    <div class="form-group">
                        <label>
                            Notificaciones activadas
                            <input type="checkbox" name="notificaciones_activas" value="1" <?php echo $config['notificaciones_activas'] ? 'checked' : ''; ?> class="switch">
                        </label>
                    </div>
                </div>

                <div class="actions-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar configuración</button>
                    <button type="button" class="btn btn-secondary" onclick="if(confirm('¿Restaurar valores por defecto?')) { document.getElementById('restaurar').submit(); }"><i class="fas fa-undo-alt"></i> Restaurar por defecto</button>
                    <a href="welcome.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                </div>
            </form>

            <form id="restaurar" method="post" style="display:none;">
                <input type="hidden" name="accion" value="restaurar">
            </form>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
