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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora del Sistema - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
            --gray-bg: #f8f9fc;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            --transition: all 0.2s ease;
        }

        body {
            background-color: var(--gray-bg);
            padding-top: 70px;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }

        .breadcrumb {
            max-width: 1400px;
            margin: 10px auto 0;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.9rem;
        }
        .breadcrumb a {
            color: var(--primary-light);
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #6c757d;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 28px 32px;
            box-shadow: var(--card-shadow);
            border: 1px solid rgba(0, 0, 0, 0.03);
        }

        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.8rem;
            font-weight: 600;
        }
        h1 i {
            color: var(--accent);
        }

        /* Filtros modernos */
        .filter-form {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 28px;
            background: #f9fafb;
            padding: 20px;
            border-radius: 20px;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            flex: 1 1 180px;
        }
        .filter-group label {
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary-dark);
            margin-bottom: 6px;
        }
        .filter-group input, .filter-group select {
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            font-size: 0.9rem;
            transition: var(--transition);
            background: white;
        }
        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(64, 145, 108, 0.2);
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 0.85rem;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #e9ecef;
            color: #2c3e2f;
        }
        .btn-secondary:hover {
            background: #dee2e6;
        }

        /* Tabla elegante */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            border-radius: 20px;
            overflow: hidden;
        }
        th {
            background: var(--primary-dark);
            color: white;
            padding: 14px 16px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 12px 16px;
            border-bottom: 1px solid #edf2f7;
            background-color: white;
            font-size: 0.9rem;
        }
        tr:hover td {
            background-color: #f8fafc;
        }
        .summary {
            font-size: 0.85rem;
            color: #4b5563;
            margin: 15px 0 5px;
        }

        /* Paginación moderna */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 40px;
            text-decoration: none;
            color: var(--primary-dark);
            background: white;
            border: 1px solid #e2e8f0;
            transition: var(--transition);
            font-weight: 500;
        }
        .pagination a:hover {
            background: var(--primary-light);
            color: white;
            border-color: var(--primary-light);
        }
        .pagination .current {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        @media (max-width: 768px) {
            .card { padding: 20px; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
            table, thead, tbody, th, td, tr { display: block; }
            th { display: none; }
            td { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; }
            td::before { content: attr(data-label); font-weight: 600; width: 40%; color: var(--primary-dark); }
        }
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
                <div class="filter-group" style="flex-direction: row; gap: 10px; align-items: center;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                    <a href="log_viewer.php" class="btn btn-secondary"><i class="fas fa-undo-alt"></i> Limpiar</a>
                </div>
            </form>

            <?php if ($total > 0): ?>
                <div class="summary">
                    <i class="fas fa-chart-line"></i> Mostrando registros <?php echo $offset + 1; ?> al <?php echo min($total, $offset + $limit); ?> de <?php echo $total; ?>.
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Fecha/Hora</th><th>Usuario</th><th>Rol</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td data-label="ID"><?php echo $log['id']; ?></td>
                                <td data-label="Fecha/Hora"><?php echo formatTimestamp($log['timestamp']); ?></td>
                                <td data-label="Usuario"><?php echo htmlspecialchars($log['username']); ?></td>
                                <td data-label="Rol"><?php echo htmlspecialchars($role_map[$log['role_id']] ?? $log['role_id']); ?></td>
                                <td data-label="Acción" style="word-break:break-all;"><?php echo htmlspecialchars($log['action']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

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
                <div class="alert alert-info"><i class="fas fa-info-circle"></i> No hay registros que coincidan con los filtros aplicados.</div>
            <?php endif; ?>
        </div>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
