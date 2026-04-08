<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_name'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

date_default_timezone_set('America/Caracas');

function formatBytes($bytes, $precision = 2) {
    $units = ['B','KB','MB','GB']; $bytes = max($bytes,0); $pow = floor(($bytes?log($bytes):0)/log(1024)); $pow = min($pow, count($units)-1);
    return round($bytes/pow(1024,$pow), $precision) . ' ' . $units[$pow];
}

function listBackups($path = '../backups/') {
    $backups = [];
    if (file_exists($path)) foreach (scandir($path) as $file) if (preg_match('/^backup_.*\.(sql|gz|zip)$/', $file)) $backups[] = ['filename'=>$file, 'size'=>filesize($path.$file), 'date'=>date('Y-m-d H:i:s', filemtime($path.$file)), 'size_formatted'=>formatBytes(filesize($path.$file))];
    usort($backups, fn($a,$b)=>strtotime($b['date'])-strtotime($a['date']));
    return $backups;
}

function getSystemStats($conn) {
    $stmt = $conn->query("SHOW TABLE STATUS");
    $total_size = 0;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $total_size += $row['Data_length'] + $row['Index_length'];
    $disk_total = disk_total_space('../'); $disk_free = disk_free_space('../');
    return ['database_size_formatted'=>formatBytes($total_size), 'disk_usage_percent'=>round((($disk_total-$disk_free)/$disk_total)*100,2), 'backup_count'=>count(listBackups())];
}

$backups = listBackups();
$stats = getSystemStats($conn);
$message = $type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_backup' && !empty($_GET['file'])) {
    $file = '../backups/' . $_GET['file'];
    if (file_exists($file) && unlink($file)) { $message = "Backup eliminado."; $type = 'success'; $backups = listBackups(); $stats = getSystemStats($conn); }
    else { $message = "Error al eliminar."; $type = 'danger'; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema de Backup - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7f9; padding-top: 72px; }
        :root { --vet-dark: #1b4332; --vet-primary: #40916c; --shadow-md: 0 8px 20px rgba(0,0,0,0.05); --radius-lg: 16px; }
        .breadcrumb { max-width: 1200px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .container { max-width: 1200px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 1.8rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #f8faf8; padding: 1rem; border-radius: 12px; text-align: center; border-left: 4px solid var(--vet-primary); }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--vet-dark); }
        .btn { padding: 0.6rem 1.2rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; transition: 0.2s; margin-right: 0.5rem; }
        .btn-primary { background: var(--vet-primary); color: white; }
        .btn-primary:hover { background: var(--vet-dark); }
        .btn-danger { background: #dc3545; color: white; font-size: 0.8rem; padding: 0.3rem 0.8rem; }
        .btn-secondary { background: #6c757d; color: white; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8faf8; padding: 0.8rem; text-align: left; color: var(--vet-dark); border-bottom: 2px solid #dee6de; }
        td { padding: 0.8rem; border-bottom: 1px solid #eef2ee; }
        tr:hover td { background: #fafdfa; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1rem; } .stats-grid { grid-template-columns: 1fr; } table { font-size: 0.75rem; } }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb"><a href="welcome.php">Inicio</a> <span>›</span> <a href="admin_tools.php">Herramientas Admin</a> <span>›</span> <span>Sistema de Backup</span></div>
    <div class="container">
        <h1><i class="fas fa-database"></i> Sistema de Backup</h1>
        <?php if ($message): ?><div class="alert alert-<?php echo $type; ?>"><i class="fas fa-<?php echo $type==='success'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo $stats['database_size_formatted']; ?></div><div class="stat-label">Tamaño BD</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $stats['disk_usage_percent']; ?>%</div><div class="stat-label">Uso de disco</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo $stats['backup_count']; ?></div><div class="stat-label">Backups</div></div>
        </div>
        <div style="margin-bottom: 1rem;"><a href="backup_system.php?action=create" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Crear Backup Ahora</a><a href="admin_tools.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a></div>
        <h3>Backups existentes</h3>
        <?php if (empty($backups)): ?><p>No hay backups creados.</p>
        <?php else: ?>
            <div style="overflow-x: auto;"><table><thead><tr><th>Archivo</th><th>Fecha</th><th>Tamaño</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($backups as $b): ?><tr><td><?php echo htmlspecialchars($b['filename']); ?></td><td><?php echo $b['date']; ?></td><td><?php echo $b['size_formatted']; ?></td><td><a href="../backups/<?php echo urlencode($b['filename']); ?>" class="btn btn-secondary" download>Descargar</a> <a href="?action=delete_backup&file=<?php echo urlencode($b['filename']); ?>" class="btn btn-danger" onclick="return confirm('¿Eliminar este backup?');">Eliminar</a></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
