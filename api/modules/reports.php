<?php
// api/modules/reports.php
// Módulo de reportes y estadísticas

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['action'] ?? null;

$user = requireRole(['Veterinario', 'admin']);
$db = getDB();

switch ($action) {

    // GET /api/reports?action=daily&date=2026-09-19
    case 'daily':
        $date = $_GET['date'] ?? date('Y-m-d');
        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        $stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM consultations WHERE consultation_date BETWEEN :s1 AND :e1) AS consultations,
                (SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN :s2 AND :e2) AS appointments,
                (SELECT COUNT(*) FROM vaccines WHERE application_date BETWEEN :s3 AND :e3) AS vaccines,
                (SELECT COUNT(*) FROM treatments WHERE created_at BETWEEN :s4 AND :e4) AS treatments,
                (SELECT COUNT(*) FROM pets WHERE created_at BETWEEN :s5 AND :e5) AS new_pets,
                (SELECT COUNT(*) FROM users WHERE created_at BETWEEN :s6 AND :e6) AS new_users
        ");
        $stmt->execute([
            ':s1' => $start, ':e1' => $end,
            ':s2' => $start, ':e2' => $end,
            ':s3' => $date,  ':e3' => $date,
            ':s4' => $start, ':e4' => $end,
            ':s5' => $start, ':e5' => $end,
            ':s6' => $start, ':e6' => $end,
        ]);
        $stats = $stmt->fetch();

        jsonSuccess([
            'date'  => $date,
            'stats' => $stats,
        ], 'Reporte diario');
        break;

    // GET /api/reports?action=metrics
    case 'metrics':
        $stmt = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE role_id = 2) AS total_owners,
                (SELECT COUNT(*) FROM users WHERE role_id = 1) AS total_vets,
                (SELECT COUNT(*) FROM pets) AS total_pets,
                (SELECT COUNT(*) FROM consultations) AS total_consultations,
                (SELECT COUNT(*) FROM appointments WHERE status = 'PENDIENTE') AS pending_appointments,
                (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) AS today_appointments,
                (SELECT COUNT(*) FROM consultations WHERE DATE(consultation_date) = CURDATE()) AS today_consultations
        ");
        jsonSuccess($stmt->fetch(), 'Métricas generales');
        break;

    // GET /api/reports?action=statistics&period=month
    case 'statistics':
        $period = $_GET['period'] ?? 'month';
        $customStart = $_GET['start_date'] ?? null;
        $customEnd   = $_GET['end_date'] ?? null;

        // Determinar rango
        $today = new DateTime();
        $start = clone $today;
        $end = clone $today;

        switch ($period) {
            case 'today':
                $start->setTime(0,0,0); $end->setTime(23,59,59);
                break;
            case 'yesterday':
                $start->modify('-1 day')->setTime(0,0,0);
                $end->modify('-1 day')->setTime(23,59,59);
                break;
            case 'week':
                $start->modify('monday this week')->setTime(0,0,0);
                $end->modify('sunday this week')->setTime(23,59,59);
                break;
            case 'year':
                $start->setDate((int)$start->format('Y'), 1, 1)->setTime(0,0,0);
                $end->setDate((int)$end->format('Y'), 12, 31)->setTime(23,59,59);
                break;
            case 'custom':
                if ($customStart && $customEnd) {
                    $start = new DateTime($customStart);
                    $end = new DateTime($customEnd);
                    $start->setTime(0,0,0); $end->setTime(23,59,59);
                }
                break;
            case 'month':
            default:
                $start->modify('first day of this month')->setTime(0,0,0);
                $end->modify('last day of this month')->setTime(23,59,59);
                break;
        }

        $s = $start->format('Y-m-d H:i:s');
        $e = $end->format('Y-m-d H:i:s');

        $stmt = $db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM consultations WHERE consultation_date BETWEEN :s1 AND :e1) AS consultations,
                (SELECT COUNT(*) FROM appointments WHERE appointment_date BETWEEN :s2 AND :e2) AS appointments,
                (SELECT COUNT(*) FROM vaccines WHERE application_date BETWEEN :s3 AND :e3) AS vaccines,
                (SELECT COUNT(*) FROM treatments WHERE created_at BETWEEN :s4 AND :e4) AS treatments
        ");
        $stmt->execute([
            ':s1' => $s, ':e1' => $e,
            ':s2' => $s, ':e2' => $e,
            ':s3' => $start->format('Y-m-d'), ':e3' => $end->format('Y-m-d'),
            ':s4' => $s, ':e4' => $e,
        ]);
        $stats = $stmt->fetch();

        // Especies más comunes
        $stmt = $db->query("
            SELECT pt.name AS species, COUNT(*) AS count
            FROM pets p
            LEFT JOIN pet_types pt ON p.type_id = pt.id
            GROUP BY pt.id, pt.name
            ORDER BY count DESC
            LIMIT 10
        ");
        $species = $stmt->fetchAll();

        // Top veterinarios
        $stmt = $db->query("
            SELECT u.username, COUNT(c.id) AS consultations
            FROM users u
            LEFT JOIN consultations c ON c.attendant_id = u.id
            WHERE u.role_id = 1
            GROUP BY u.id, u.username
            ORDER BY consultations DESC
            LIMIT 5
        ");
        $topVets = $stmt->fetchAll();

        jsonSuccess([
            'period'    => $period,
            'range'     => ['start' => $s, 'end' => $e],
            'stats'     => $stats,
            'species'   => $species,
            'top_vets'  => $topVets,
        ], 'Estadísticas');
        break;

    // GET /api/reports?action=logs (bitácora)
    case 'logs':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $where = " WHERE 1=1";
        $params = [];

        if (!empty($_GET['user'])) {
            $where .= " AND username = :user";
            $params[':user'] = $_GET['user'];
        }
        if (!empty($_GET['search'])) {
            $where .= " AND (action LIKE :s OR username LIKE :s)";
            $params[':s'] = '%' . $_GET['search'] . '%';
        }
        if (!empty($_GET['start_date'])) {
            $where .= " AND timestamp >= :start";
            $params[':start'] = $_GET['start_date'] . ' 00:00:00';
        }
        if (!empty($_GET['end_date'])) {
            $where .= " AND timestamp <= :end";
            $params[':end'] = $_GET['end_date'] . ' 23:59:59';
        }

        // Total
        $stmt = $db->prepare("SELECT COUNT(*) FROM db_bitacora" . $where);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Registros
        $sql = "SELECT id, role_id, username, action, timestamp 
                FROM db_bitacora" . $where . " 
                ORDER BY timestamp DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        jsonSuccess([
            'logs'  => $logs,
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
        ], 'Bitácora');
        break;

    default:
        jsonError('Acción de reports no válida. Usa: daily, metrics, statistics, logs', 400);
}
