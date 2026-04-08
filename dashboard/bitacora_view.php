<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/bitacora_function.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_id'] != 1)) {
    header("Location: ../index.php");
    exit;
}

define('LOG_TABLE', 'db_bitacora');
$limit = 15;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

try {
    $users = [];
    $stmtUsers = $conn->query("SELECT username FROM users ORDER BY username");
    while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) $users[] = $row['username'];

    $filter_user = $_GET['user'] ?? '';
    $filter_start_date = $_GET['start_date'] ?? '';
    $filter_end_date = $_GET['end_date'] ?? '';
    $filter_search = $_GET['search'] ?? '';

    $where = " WHERE 1";
    $params = [];
    if (!empty($filter_search)) { $where .= " AND (action LIKE :search1 OR username LIKE :search2)"; $params[':search1'] = "%$filter_search%"; $params[':search2'] = "%$filter_search%"; }
    if (!empty($filter_user)) { $where .= " AND username = :user"; $params[':user'] = $filter_user; }
    if (!empty($filter_start_date)) { $where .= " AND timestamp >= :start_date"; $params[':start_date'] = $filter_start_date . " 00:00:00"; }
    if (!empty($filter_end_date)) { $where .= " AND timestamp <= :end_date"; $params[':end_date'] = $filter_end_date . " 23:59:59"; }

    $count_sql = "SELECT COUNT(*) AS total FROM " . LOG_TABLE . $where;
    $count_stmt = $conn->prepare($count_sql);
    foreach ($params as $k=>$v) $count_stmt->bindValue($k, $v);
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    $page = max(1, min($page, $total_pages ?: 1));
    $offset = ($page - 1) * $limit;

    $sql = "SELECT * FROM " . LOG_TABLE . $where . " ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
    $total_records = 0; $total_pages = 1; $result = [];
}

$pagination_params = http_build_query(['user'=>$filter_user,'start_date'=>$filter_start_date,'end_date'=>$filter_end_date,'search'=>$filter_search]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Auditoría</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7f9; padding: 2rem; }
        :root { --vet-dark: #1b4332; --vet-primary: #40916c; --shadow-md: 0 8px 20px rgba(0,0,0,0.05); --radius-lg: 16px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .filter-form { background: #f8faf8; padding: 1.2rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; min-width: 150px; flex: 1; }
        .filter-group label { font-size: 0.75rem; font-weight: 600; color: var(--vet-dark); margin-bottom: 0.2rem; }
        .filter-group input, .filter-group select { padding: 0.5rem; border: 1px solid #d0d8d0; border-radius: 8px; font-family: inherit; }
        .btn { padding: 0.5rem 1rem; border-radius: 8px; border: none; font-weight: 600; cursor: pointer; transition: 0.2s; background: var(--vet-primary); color: white; }
        .btn:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { background: #f8faf8; padding: 0.8rem; text-align: left; color: var(--vet-dark); border-bottom: 2px solid #dee6de; }
        td { padding: 0.8rem; border-bottom: 1px solid #eef2ee; vertical-align: top; }
        tr:hover td { background: #fafdfa; }
        .action-cell { max-width: 400px; word-wrap: break-word; white-space: normal; }
        .pagination { margin-top: 1.5rem; display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 0.4rem 0.8rem; border-radius: 6px; background: #f8faf8; color: var(--vet-dark); text-decoration: none; transition: 0.2s; }
        .pagination a:hover { background: var(--vet-primary); color: white; }
        .pagination .active { background: var(--vet-primary); color: white; }
        @media (max-width: 768px) { body { padding: 1rem; } .container { padding: 1rem; } .filter-form { flex-direction: column; } table { font-size: 0.75rem; } th, td { padding: 0.5rem; } }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-history"></i> Bitácora del Sistema (Auditoría)</h1>
        <form method="GET" class="filter-form">
            <div class="filter-group"><label>Buscar:</label><input type="text" name="search" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Palabra clave"></div>
            <div class="filter-group"><label>Usuario:</label><select name="user"><option value="">-- Todos --</option><?php foreach ($users as $u): ?><option value="<?php echo htmlspecialchars($u); ?>" <?php echo $filter_user===$u?'selected':''; ?>><?php echo htmlspecialchars($u); ?></option><?php endforeach; ?></select></div>
            <div class="filter-group"><label>Desde:</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
            <div class="filter-group"><label>Hasta:</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
            <div class="filter-group"><button type="submit" class="btn"><i class="fas fa-search"></i> Filtrar</button></div>
            <div class="filter-group"><a href="bitacora_view.php" class="btn btn-secondary"><i class="fas fa-undo"></i> Limpiar</a></div>
        </form>
        <div class="mb-2">Mostrando registros <?php echo $total_records>0?($offset+1).' al '.min($total_records,$offset+$limit):0; ?> de <?php echo $total_records; ?></div>
        <?php if (isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <div style="overflow-x: auto;"><table><thead><tr><th>ID</th><th>Fecha/Hora</th><th>Usuario</th><th>ID Rol</th><th>Acción Registrada</th></tr></thead><tbody>
        <?php if (!empty($result)): foreach ($result as $row): ?><tr><td><?php echo htmlspecialchars($row['id'] ?? ''); ?></td><td><?php echo htmlspecialchars($row['timestamp'] ?? ''); ?></td><td><?php echo htmlspecialchars($row['username'] ?? ''); ?></td><td><?php echo htmlspecialchars($row['role_id'] ?? ''); ?></td><td class="action-cell"><?php echo htmlspecialchars($row['action'] ?? ''); ?></td></tr><?php endforeach; else: ?><tr><td colspan="5">No hay registros.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($total_pages > 1): ?><div class="pagination"><?php if ($page>1): ?><a href="?page=<?php echo $page-1; ?>&<?php echo $pagination_params; ?>">Anterior</a><?php endif; for($i=1;$i<=$total_pages;$i++): ?><a href="?page=<?php echo $i; ?>&<?php echo $pagination_params; ?>" class="<?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a><?php endfor; if($page<$total_pages): ?><a href="?page=<?php echo $page+1; ?>&<?php echo $pagination_params; ?>">Siguiente</a><?php endif; ?></div><?php endif; ?>
    </div>
</body>
</html>
