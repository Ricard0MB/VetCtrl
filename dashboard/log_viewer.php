<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO

// --- Activar solo para depuración (quitar en producción) ---
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Verificar autenticación
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

// Verificar rol (permisos)
$role_name = $_SESSION['role_name'] ?? '';
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

define('LOG_TABLE', 'db_bitacora');

// Verificar que la tabla exista (opcional, pero útil para diagnóstico)
try {
    $checkTable = $conn->query("SHOW TABLES LIKE '" . LOG_TABLE . "'");
    if ($checkTable->rowCount() == 0) {
        die("La tabla '" . LOG_TABLE . "' no existe en la base de datos.");
    }
} catch (PDOException $e) {
    die("Error al verificar tabla: " . $e->getMessage());
}

$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Obtener lista de usuarios para el filtro (con manejo de error)
$users = [];
try {
    $res = $conn->query("SELECT DISTINCT username FROM " . LOG_TABLE . " ORDER BY username");
    while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
        $users[] = $row['username'];
    }
} catch (PDOException $e) {
    error_log("Error al obtener usuarios para filtro: " . $e->getMessage());
    // No detenemos la ejecución, simplemente no mostramos el filtro
}

// Filtros
$filter_user = $_GET['user'] ?? '';
$filter_start = $_GET['start_date'] ?? '';
$filter_end = $_GET['end_date'] ?? '';
$filter_search = $_GET['search'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($filter_search)) {
    $where .= " AND (action LIKE :search1 OR username LIKE :search2)";
    $params[':search1'] = "%$filter_search%";
    $params[':search2'] = "%$filter_search%";
}
if (!empty($filter_user)) {
    $where .= " AND username = :user";
    $params[':user'] = $filter_user;
}
if (!empty($filter_start)) {
    $where .= " AND timestamp >= :start_date";
    $params[':start_date'] = $filter_start . " 00:00:00";
}
if (!empty($filter_end)) {
    $where .= " AND timestamp <= :end_date";
    $params[':end_date'] = $filter_end . " 23:59:59";
}

// ----- Consulta para contar total de registros (con paginación) -----
try {
    $count_sql = "SELECT COUNT(*) as total FROM " . LOG_TABLE . " " . $where;
    $stmt_count = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt_count->bindValue($key, $value);
    }
    $stmt_count->execute();
    $total = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    die("Error al contar registros: " . $e->getMessage());
}

$total_pages = ceil($total / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

// ----- Consulta principal con límite y offset -----
$logs = [];
try {
    $sql = "SELECT id, timestamp, action, username, role_id FROM " . LOG_TABLE . " " . $where . " ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $logs[] = $row;
    }
} catch (PDOException $e) {
    die("Error al obtener registros: " . $e->getMessage());
}

// Mapa de roles (ajusta los IDs según tu tabla 'users')
$role_map = [1 => 'Veterinario', 2 => 'Propietario', 3 => 'Admin'];

// Función para formatear fecha
function formatTimestamp($ts) {
    return date('d/m/Y H:i:s', strtotime($ts));
}

// Parámetros para paginación (conservar filtros)
$pagination_params = http_build_query([
    'user' => $filter_user,
    'start_date' => $filter_start,
    'end_date' => $filter_end,
    'search' => $filter_search
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bitácora del Sistema - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* (los mismos estilos que tenías) */
        body { background-color: #f8f9fa; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 1400px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 1400px; margin: 20px auto; padding: 20px; }
        .card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .filter-form { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight:600; margin-bottom:5px; color:#1b4332; }
        .filter-group input, .filter-group select { padding:8px; border:1px solid #ccc; border-radius:4px; min-width:150px; }
        .btn { padding:8px 15px; border:none; border-radius:4px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#40916c; color:white; }
        .btn-primary:hover { background:#2d6a4f; }
        .btn-secondary { background:#6c757d; color:white; }
        .btn-secondary:hover { background:#5a6268; }
        table { width:100%; border-collapse:collapse; margin-top:20px; }
        th { background:#40916c; color:white; padding:12px; text-align:left; }
        td { padding:10px; border-bottom:1px solid #ddd; }
        tr:hover { background:#f5f5f5; }
        .pagination { display:flex; justify-content:center; margin-top:20px; gap:5px; }
        .pagination a, .pagination span { padding:8px 12px; border:1px solid #ddd; border-radius:4px; text-decoration:none; color:#333; }
        .pagination .current { background:#40916c; color:white; border-color:#40916c; }
        .summary { margin-bottom:15px; color:#6c757d; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Bitácora del Sistema</span>
    </div>

    <div class="container">
        <div class="card">
            <h1><i class="fas fa-history"></i> Bitácora del Sistema</h1>

            <form method="get" class="filter-form">
                <div class="filter-group">
                    <label>Buscar</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Texto en acción o usuario">
                </div>
                <div class="filter-group">
                    <label>Usuario</label>
                    <select name="user">
                        <option value="">Todos</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo htmlspecialchars($u); ?>" <?php echo ($filter_user === $u) ? 'selected' : ''; ?>><?php echo htmlspecialchars($u); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Desde</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($filter_start); ?>">
                </div>
                <div class="filter-group">
                    <label>Hasta</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($filter_end); ?>">
                </div>
                <div class="filter-group" style="justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="log_viewer.php" class="btn btn-secondary">Limpiar</a>
                </div>
            </form>

            <?php if ($total > 0): ?>
                <div class="summary">
                    Mostrando registros <?php echo $offset + 1; ?> al <?php echo min($total, $offset + $limit); ?> de <?php echo $total; ?>.
                </div>
                <table>
                    <thead>
                        <tr><th>ID</th><th>Fecha/Hora</th><th>Usuario</th><th>Rol</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo formatTimestamp($log['timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($role_map[$log['role_id']] ?? $log['role_id']); ?></td>
                            <td style="word-break:break-all;"><?php echo htmlspecialchars($log['action']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&<?php echo $pagination_params; ?>">« Anterior</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo $pagination_params; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&<?php echo $pagination_params; ?>">Siguiente »</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <p>No hay registros que coincidan con los filtros aplicados.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
<?php
// No es necesario cerrar la conexión explícitamente con PDO
?>
