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

date_default_timezone_set('America/Caracas');

// Funciones de backup (se mantienen igual)
function compressBackup($sql_file, $backup_path) {
    $date = date('Y-m-d_H-i-s');
    if (function_exists('gzcompress')) {
        $gzname = $backup_path . 'backup_' . $date . '.sql.gz';
        $sql_content = file_get_contents($sql_file);
        $compressed = gzencode($sql_content, 9);
        file_put_contents($gzname, $compressed);
        unlink($sql_file);
        return $gzname;
    } else {
        $new_name = $backup_path . 'backup_' . $date . '.sql';
        rename($sql_file, $new_name);
        return $new_name;
    }
}

function backupDatabase($conn, $backup_path = '../backups/') {
    if (!file_exists($backup_path)) mkdir($backup_path, 0777, true);
    $date = date('Y-m-d_H-i-s');
    $filename = $backup_path . 'temp_backup_' . $date . '.sql';
    
    $tables = array();
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) $tables[] = $row[0];
    
    $return = "/* Backup generado el " . date('Y-m-d H:i:s') . " */\n/* Sistema VetControl */\n\n";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        $row = $result->fetch_row();
        $return .= "\n-- Estructura: $table\nDROP TABLE IF EXISTS `$table`;\n" . $row[1] . ";\n\n";
        
        $result = $conn->query("SELECT * FROM `$table`");
        $num_fields = $result->field_count;
        while ($row = $result->fetch_row()) {
            $return .= "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                if (isset($row[$j])) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace(["\n","\r"], ["\\n","\\r"], $row[$j]);
                    $return .= '"' . $row[$j] . '"';
                } else $return .= 'NULL';
                if ($j < $num_fields-1) $return .= ',';
            }
            $return .= ");\n";
        }
        $return .= "\n";
    }
    $return .= "\n-- PHP Version: " . phpversion() . "\n-- MySQL Version: " . $conn->server_info . "\n-- Total Tables: " . count($tables);
    
    file_put_contents($filename, $return);
    $final = compressBackup($filename, $backup_path);
    return [
        'success' => true,
        'filename' => basename($final),
        'path' => $final,
        'size' => filesize($final),
        'message' => 'Backup completado'
    ];
}

function listBackups($backup_path = '../backups/') {
    $backups = [];
    if (file_exists($backup_path)) {
        $files = scandir($backup_path);
        foreach ($files as $file) {
            if (preg_match('/^backup_.*\.(sql|gz|zip)$/', $file)) {
                $fp = $backup_path . $file;
                $backups[] = [
                    'filename' => $file,
                    'size' => filesize($fp),
                    'date' => date('Y-m-d H:i:s', filemtime($fp)),
                    'size_formatted' => formatBytes(filesize($fp))
                ];
            }
        }
        usort($backups, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));
    }
    return $backups;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B','KB','MB','GB'];
    $bytes = max($bytes,0);
    $pow = floor(($bytes?log($bytes):0)/log(1024));
    $pow = min($pow, count($units)-1);
    return round($bytes/pow(1024,$pow), $precision) . ' ' . $units[$pow];
}

function deleteBackup($file) {
    if (file_exists($file)) {
        return unlink($file) ? ['success'=>true,'message'=>'Eliminado'] : ['success'=>false,'message'=>'Error al eliminar'];
    }
    return ['success'=>false,'message'=>'Archivo no existe'];
}

function getSystemStats($conn) {
    $result = $conn->query("SHOW TABLE STATUS");
    $total_size = 0;
    while ($row = $result->fetch_assoc()) $total_size += $row['Data_length'] + $row['Index_length'];
    $disk_total = disk_total_space('../');
    $disk_free = disk_free_space('../');
    return [
        'database_size_formatted' => formatBytes($total_size),
        'disk_usage_percent' => round((($disk_total - $disk_free) / $disk_total) * 100, 2),
        'disk_free' => $disk_free,
        'backup_count' => count(listBackups())
    ];
}

$action = $_GET['action'] ?? '';
$file = $_GET['file'] ?? '';
$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create_backup') {
        $res = backupDatabase($conn);
        if ($res['success']) {
            $message = $res['message'] . ' (' . formatBytes($res['size']) . ')';
            $type = 'success';
        } else {
            $message = $res['message'];
            $type = 'danger';
        }
    } elseif ($action === 'delete_backup' && $file) {
        $res = deleteBackup('../backups/' . $file);
        $message = $res['message'];
        $type = $res['success'] ? 'success' : 'danger';
    }
}

$backups = listBackups();
$stats = getSystemStats($conn);
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Backup - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 1200px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap:20px; margin-bottom:20px; }
        .stat-card { background: #f8f9fa; padding:20px; border-radius:8px; text-align:center; border-left:4px solid #40916c; }
        .stat-value { font-size:2rem; font-weight:bold; color:#1b4332; }
        .btn { padding:10px 20px; border:none; border-radius:6px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#40916c; color:white; }
        .btn-primary:hover { background:#2d6a4f; }
        .btn-danger { background:#dc3545; color:white; }
        .btn-danger:hover { background:#c82333; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-secondary:hover { background:#5a6268; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th { background:#40916c; color:white; padding:12px; text-align:left; }
        td { padding:10px; border-bottom:1px solid #ddd; }
        tr:hover { background:#f5f5f5; }
        .action-btns { display:flex; gap:10px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="admin_tools.php">Herramientas Admin</a> <span>›</span>
        <span>Sistema de Backup</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-database"></i> Sistema de Backup</h1>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $type; ?>">
                    <i class="fas fa-<?php echo $type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['database_size_formatted']; ?></div>
                    <div class="stat-label">Tamaño BD</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['disk_usage_percent']; ?>%</div>
                    <div class="stat-label">Uso de disco</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['backup_count']; ?></div>
                    <div class="stat-label">Backups</div>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <form method="post" action="?action=create_backup" style="display:inline;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Crear Backup Ahora</button>
                </form>
                <a href="admin_tools.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>

            <h3>Backups existentes</h3>
            <?php if (empty($backups)): ?>
                <p>No hay backups creados.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr><th>Archivo</th><th>Fecha</th><th>Tamaño</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['filename']); ?></td>
                            <td><?php echo $b['date']; ?></td>
                            <td><?php echo $b['size_formatted']; ?></td>
                            <td class="action-btns">
                                <a href="../backups/<?php echo urlencode($b['filename']); ?>" class="btn btn-secondary btn-sm" download>Descargar</a>
                                <form method="post" action="?action=delete_backup&file=<?php echo urlencode($b['filename']); ?>" style="display:inline;" onsubmit="return confirm('¿Eliminar este backup?');">
                                    <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>