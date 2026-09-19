<?php
// api/modules/admin.php
// Módulo de administración (configuración, backup)

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method   = getMethod();
$segments = getUriSegments();
$action   = $segments[1] ?? $_GET['action'] ?? null;

$user = requireRole(['admin']);
$db = getDB();

switch ($action) {

    // GET /api/admin?action=stats
    case 'stats':
        $stmt = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM users WHERE role_id = 1) AS total_veterinarios,
                (SELECT COUNT(*) FROM users WHERE role_id = 2) AS total_propietarios,
                (SELECT COUNT(*) FROM pets) AS total_pacientes,
                (SELECT COUNT(*) FROM appointments WHERE status = 'PENDIENTE' AND DATE(appointment_date) = CURDATE()) AS citas_hoy
        ");
        jsonSuccess($stmt->fetch(), 'Estadísticas admin');
        break;

    // GET /api/admin?action=settings
    case 'settings':
        $stmt = $db->query("SELECT config_key, config_value FROM system_config");
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['config_key']] = $row['config_value'];
        }
        jsonSuccess($config, 'Configuración del sistema');
        break;

    // PUT /api/admin?action=settings
    case 'settings' && $method === 'PUT':
        $body = getJsonBody();
        if (empty($body)) jsonError('Nada que actualizar', 422);

        try {
            $db->beginTransaction();
            foreach ($body as $key => $value) {
                $stmt = $db->prepare("
                    INSERT INTO system_config (config_key, config_value)
                    VALUES (:k, :v)
                    ON DUPLICATE KEY UPDATE config_value = :v2
                ");
                $stmt->execute([':k' => $key, ':v' => $value, ':v2' => $value]);
            }
            $db->commit();

            $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
               ->execute([
                   ':r' => $user['role_id'],
                   ':u' => $user['username'],
                   ':a' => "Configuración del sistema actualizada vía API",
               ]);

            jsonSuccess(null, 'Configuración actualizada');
        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            jsonError('Error al guardar: ' . $e->getMessage(), 500);
        }
        break;

    // POST /api/admin?action=clear-cache
    case 'clear-cache':
        if ($method !== 'POST') jsonError('Método no permitido', 405);
        // Simulado (no hay caché real en este proyecto)
        $db->prepare("INSERT INTO db_bitacora (role_id, username, action, timestamp) VALUES (:r, :u, :a, NOW())")
           ->execute([
               ':r' => $user['role_id'],
               ':u' => $user['username'],
               ':a' => "Caché limpiada vía API",
           ]);
        jsonSuccess(null, 'Caché limpiada');
        break;

    default:
        jsonError('Acción de admin no válida. Usa: stats, settings, clear-cache', 400);
}
