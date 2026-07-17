<?php
// Configurar cabeceras para la API (siempre primero)
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Intentar cargar la configuración con manejo de errores
try {
    require_once __DIR__ . '/../includes/config.php';
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al cargar la configuración: ' . $e->getMessage()
    ]);
    exit();
}

$response = [];

// Verificar que la conexión PDO esté disponible
if (!isset($conn) || !($conn instanceof PDO)) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos'
    ]);
    exit();
}

// Solo procesar solicitudes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    // Validar que los campos no estén vacíos
    if (empty($username) || empty($password)) {
        $response['success'] = false;
        $response['message'] = 'Usuario y contraseña son requeridos';
        echo json_encode($response);
        exit();
    }

    try {
        // Consulta preparada (igual a la de tu web, pero sin JOIN por simplicidad)
        $query = "SELECT id, username, email, password, role_id, first_name, last_name, status 
                  FROM users 
                  WHERE username = :login OR email = :login";
        $stmt = $conn->prepare($query);
        $stmt->execute([':login' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar si el usuario existe
        if (!$user) {
            $response['success'] = false;
            $response['message'] = 'Usuario no encontrado';
            echo json_encode($response);
            exit();
        }

        // Verificar estado del usuario
        if ($user['status'] !== 'active') {
            $response['success'] = false;
            $response['message'] = 'Usuario inactivo o suspendido';
            echo json_encode($response);
            exit();
        }

        // Verificar contraseña
        if (password_verify($password, $user['password'])) {
            // Eliminar la contraseña del objeto devuelto
            unset($user['password']);
            $response['success'] = true;
            $response['data'] = $user;
        } else {
            $response['success'] = false;
            $response['message'] = 'Contraseña incorrecta';
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    } catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = 'Error inesperado: ' . $e->getMessage();
    }
} else {
    $response['success'] = false;
    $response['message'] = 'Método no permitido. Use POST.';
}

// Siempre devolver JSON
echo json_encode($response);
?>
