<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO
require_once '../includes/bitacora_function.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_id'] != 1)) {
    header("Location: ../index.php");
    exit;
}

define('LOG_TABLE', 'db_bitacora');
$limit = 15; // Número de registros por página (definido en el código original, lo agregamos)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

try {
    // Obtener lista de usuarios para el filtro
    $users = [];
    $stmtUsers = $conn->query("SELECT username FROM users ORDER BY username ASC");
    while ($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
        $users[] = $row['username'];
    }

    // Filtros
    $filter_user = $_GET['user'] ?? '';
    $filter_start_date = $_GET['start_date'] ?? '';
    $filter_end_date = $_GET['end_date'] ?? '';
    $filter_search = $_GET['search'] ?? '';

    $where_clause = " WHERE 1";
    $params = [];

    if (!empty($filter_search)) {
        $where_clause .= " AND (action LIKE :search1 OR username LIKE :search2)";
        $params[':search1'] = "%" . $filter_search . "%";
        $params[':search2'] = "%" . $filter_search . "%";
    }

    if (!empty($filter_user)) {
        $where_clause .= " AND username = :user";
        $params[':user'] = $filter_user;
    }

    if (!empty($filter_start_date)) {
        $where_clause .= " AND timestamp >= :start_date";
        $params[':start_date'] = $filter_start_date . " 00:00:00";
    }

    if (!empty($filter_end_date)) {
        $where_clause .= " AND timestamp <= :end_date";
        $params[':end_date'] = $filter_end_date . " 23:59:59";
    }

    // Contar total de registros con filtros
    $count_sql = "SELECT COUNT(*) AS total FROM " . LOG_TABLE . $where_clause;
    $count_stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $count_stmt->bindValue($key, $value);
    }
    $count_stmt->execute();
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    if ($page > $total_pages) { $page = max(1, $total_pages); }
    if ($page < 1) { $page = 1; }
    $offset = ($page - 1) * $limit;

    // Consulta principal con paginación
    $sql = "SELECT * FROM " . LOG_TABLE . $where_clause . " ORDER BY timestamp DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error de base de datos: " . $e->getMessage();
    $total_records = 0;
    $total_pages = 1;
    $result = [];
}

// Construir parámetros para paginación
$pagination_params = http_build_query([
    'user' => $filter_user,
    'start_date' => $filter_start_date,
    'end_date' => $filter_end_date,
    'search' => $filter_search
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitácora de Auditoría</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .table-auto td, .table-auto th {
            padding: 8px 12px;
            text-align: left;
            vertical-align: top;
        }
        .action-cell {
            max-width: 400px;
            word-wrap: break-word; 
            white-space: normal; 
        }
        .pagination-link {
            transition: all 0.1s;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 sm:p-8">

    <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow-xl">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">Bitácora del Sistema (Auditoría)</h1>

        <form method="GET" class="mb-8 p-4 bg-gray-50 rounded-lg shadow-inner flex flex-wrap gap-4 items-end">
            
            <div class="flex flex-col w-full sm:w-64">
                <label for="search" class="text-sm font-medium text-gray-700 mb-1">Buscar Acción/Usuario:</label>
                <input type="text" id="search" name="search" value="<?= htmlspecialchars($filter_search) ?>" 
                       placeholder="Escriba palabra clave..."
                       class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="flex flex-col w-full sm:w-48">
                <label for="user" class="text-sm font-medium text-gray-700 mb-1">Filtrar por Usuario:</label>
                <select id="user" name="user" class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Todos --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= htmlspecialchars($user) ?>" 
                                <?= ($filter_user === $user) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col w-full sm:w-40">
                <label for="start_date" class="text-sm font-medium text-gray-700 mb-1">Desde Fecha:</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>" 
                       class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="flex flex-col w-full sm:w-40">
                <label for="end_date" class="text-sm font-medium text-gray-700 mb-1">Hasta Fecha:</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>" 
                       class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="flex gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 transition duration-150 shadow-md">
                    Filtrar / Buscar
                </button>
                <a href="bitacora_view.php" class="px-4 py-2 bg-gray-300 text-gray-800 font-semibold rounded-md hover:bg-gray-400 transition duration-150 shadow-md whitespace-nowrap">
                    Limpiar
                </a>
            </div>
        </form>

        <div class="mb-4 text-gray-600 font-medium">
            <?php if ($total_records > 0): ?>
                Mostrando registros del <span class="font-bold"><?= $offset + 1 ?></span> al <span class="font-bold"><?= min($total_records, $offset + $limit) ?></span> de un total de <span class="font-bold"><?= $total_records ?></span>
                <?php if (!empty($filter_search) || !empty($filter_user) || !empty($filter_start_date) || !empty($filter_end_date)): ?>
                    (Filtrados)
                <?php endif; ?>
            <?php else: ?>
                No hay registros disponibles.
            <?php endif; ?>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">⚠️ Error de Base de Datos:</strong>
                <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                <p class="text-xs mt-1">Verifique el nombre de la tabla (<?= LOG_TABLE ?>) y los campos de fecha en su base de datos.</p>
            </div>
        <?php endif; ?>

        <div class="overflow-x-auto shadow-md rounded-lg">
            <table class="min-w-full bg-white table-auto">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-3 px-6 text-xs font-medium text-gray-600 uppercase tracking-wider w-1/12">ID</th>
                        <th class="py-3 px-6 text-xs font-medium text-gray-600 uppercase tracking-wider w-1/6">Fecha y Hora</th>
                        <th class="py-3 px-6 text-xs font-medium text-gray-600 uppercase tracking-wider w-1/6">Usuario</th>
                        <th class="py-3 px-6 text-xs font-medium text-gray-600 uppercase tracking-wider w-1/6">ID de Rol</th>
                        <th class="py-3 px-6 text-xs font-medium text-gray-600 uppercase tracking-wider w-1/2">Acción Registrada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (!empty($result)): ?>
                        <?php foreach ($result as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-4 px-6 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['id'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-500 whitespace-nowrap"><?= htmlspecialchars($row['timestamp'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-500"><?= htmlspecialchars($row['username'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-500"><?= htmlspecialchars($row['role_id'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-900 action-cell"><?= htmlspecialchars($row['action'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-500 text-lg">
                                😔 No se encontraron registros que coincidan con los filtros aplicados.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="flex justify-center mt-6">
                <nav class="flex items-center space-x-2" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&<?= $pagination_params ?>" 
                           class="pagination-link px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700">
                            Anterior
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 leading-tight text-gray-300 bg-white border border-gray-300 rounded-l-lg cursor-not-allowed">
                            Anterior
                        </span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?= $i ?>&<?= $pagination_params ?>" 
                           class="pagination-link px-4 py-2 leading-tight <?= $i == $page ? 'text-white bg-blue-600 border-blue-600 font-bold' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&<?= $pagination_params ?>" 
                           class="pagination-link px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700">
                            Siguiente
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-2 leading-tight text-gray-300 bg-white border border-gray-300 rounded-r-lg cursor-not-allowed">
                            Siguiente
                        </span>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
