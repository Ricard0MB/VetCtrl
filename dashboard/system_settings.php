<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../public/index.php");
    exit;
}

$role_name = $_SESSION['role_name'] ?? '';
if ($role_name !== 'admin') {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Crear tabla si no existe
$conn->query("CREATE TABLE IF NOT EXISTS system_config (
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
$res = $conn->query("SELECT config_key, config_value FROM system_config");
while ($row = $res->fetch_assoc()) {
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
            $conn->begin_transaction();
            $ok = true;
            foreach ($nuevos as $k => $v) {
                $stmt = $conn->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
                $stmt->bind_param("sss", $k, $v, $v);
                if (!$stmt->execute()) {
                    $ok = false;
                    $mensaje = "Error al guardar: " . $stmt->error;
                    $tipo = 'danger';
                    break;
                }
            }
            if ($ok) {
                $conn->commit();
                $mensaje = "Configuración guardada correctamente.";
                $tipo = 'success';
                $config = $nuevos;
            } else {
                $conn->rollback();
            }
        }
    } elseif (isset($_POST['accion']) && $_POST['accion'] === 'restaurar') {
        $conn->query("DELETE FROM system_config");
        $mensaje = "Valores por defecto restaurados.";
        $tipo = 'success';
        $config = $defaults;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configuración del Sistema - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 800px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .form-group { margin-bottom: 20px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #1b4332; }
        input, select, textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        input:focus, select:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #40916c; color: white; }
        .btn-primary:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .switch { margin-left: 10px; }
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
                    <input type="text" name="dias_trabajo" value="<?php echo htmlspecialchars($config['dias_trabajo']); ?>">
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

                <div style="display: flex; gap: 10px; margin-top:30px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar configuración</button>
                    <button type="button" class="btn btn-secondary" onclick="if(confirm('¿Restaurar valores por defecto?')) { document.getElementById('restaurar').submit(); }">Restaurar por defecto</button>
                    <a href="welcome.php" class="btn btn-secondary">Volver</a>
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