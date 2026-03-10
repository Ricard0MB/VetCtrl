<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/bitacora_function.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || ($_SESSION['role_id'] != 1)) {
    header("location: ../public/index.php");
    exit;
}

define('LOG_TABLE', 'db_bitacora'); 

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;


$users_query = $conn->query("SELECT username FROM users ORDER BY username ASC");
$users = [];
while ($row = $users_query->fetch_assoc()) {
    $users[] = $row['username'];
}


$filter_user = $_GET['user'] ?? '';
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_search = $_GET['search'] ?? ''; 

$where_clause = " WHERE 1";
$params = [];
$types = '';


if (!empty($filter_search)) {
    $where_clause .= " AND (action LIKE ? OR username LIKE ?)";
    $types .= "ss";
    $search_param = "%" . $filter_search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($filter_user)) {
    $where_clause .= " AND username = ?";
    $types .= "s";
    $params[] = $filter_user;
}

if (!empty($filter_start_date)) {
    $where_clause .= " AND timestamp >= ?";
    $types .= "s";
    $params[] = $filter_start_date . " 00:00:00"; 
}

if (!empty($filter_end_date)) {
    $where_clause .= " AND timestamp <= ?";
    $types .= "s";
    $params[] = $filter_end_date . " 23:59:59";
}


$count_sql = "SELECT COUNT(*) AS total FROM " . LOG_TABLE . $where_clause;
$count_stmt = $conn->prepare($count_sql);

$filter_params = $params; 
$filter_types = $types;

if ($count_stmt) {
    if (!empty($filter_params)) {
        $count_stmt->bind_param($filter_types, ...$filter_params);
    }
    
    if (!$count_stmt->execute()) {
        $error = "Error al ejecutar la consulta de conteo: " . $count_stmt->error;
        $total_records = 0;
    } else {
        $count_result = $count_stmt->get_result()->fetch_assoc();
        $total_records = $count_result['total'];
    }
    $total_pages = ceil($total_records / $limit);
    $count_stmt->close();
} else {
    $error = "Error al preparar la consulta de conteo: " . $conn->error;
    $total_records = 0;
    $total_pages = 1;
}

if ($page > $total_pages) { $page = $total_pages; }
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $limit; 


$sql = "SELECT * FROM " . LOG_TABLE . $where_clause . " ORDER BY timestamp DESC LIMIT ? OFFSET ?";

$final_types = $filter_types . "ii"; 
$final_params = $filter_params;
$final_params[] = $limit;
$final_params[] = $offset;


$stmt = $conn->prepare($sql);

if ($stmt) {
    if (!empty($final_params)) {
        $stmt->bind_param($final_types, ...$final_params);
    }
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
    } else {
        $error = "Error al ejecutar la consulta principal: " . $stmt->error;
    }
} else {
    $error = "Error al preparar la consulta principal: " . $conn->error;
}


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
                    <?php if (isset($result) && $result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-4 px-6 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['id'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-500 whitespace-nowrap"><?= htmlspecialchars($row['timestamp'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-500"><?= htmlspecialchars($row['username'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-500"><?= htmlspecialchars($row['role_id'] ?? '') ?></td>
                                <td class="py-4 px-6 text-sm text-gray-900 action-cell"><?= htmlspecialchars($row['action'] ?? '') ?></td>
                            </tr>
                        <?php endwhile; ?>
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
<?php
if (isset($stmt)) { $stmt->close(); }
if (isset($conn)) { $conn->close(); }
?>

 body {
            background-color: #e9ecef;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .login-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border-top: 5px solid #2d6a4f;
        }
        h1 {
            color: #1b4332;
            margin-bottom: 25px;
            font-size: 2em;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        h1 span {
            margin-left: 10px;
            font-size: 1.2em;
        }
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus {
             border-color: #40916c;
             box-shadow: 0 0 0 0.2rem rgba(64, 145, 108, 0.25);
             outline: none;
        }
        .btn-primary {
            background-color: #2d6a4f;
            color: white;
            padding: 12px 20px;
            border: none;
            cursor: pointer;
            border-radius: 8px;
            font-size: 1.1em;
            width: 100%;
            font-weight: 700;
            transition: background-color 0.3s ease, transform 0.1s;
        }
        .btn-primary:hover {
            background-color: #1b4332;
            transform: translateY(-1px);
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            text-align: left;
            font-size: 0.95em;
            font-weight: 600;
        }
        .register-link {
            margin-top: 20px;
            font-size: 0.95em;
            color: #495057;
        }
        .register-link a {
            color: #40916c;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        .register-link a:hover {
            color: #2d6a4f;
            text-decoration: underline;
        }
        
        /* Media Query para pantallas pequeñas */
        @media (max-width: 500px) {
            .login-container {
                margin: 20px;
                padding: 30px 20px;
            }
        }