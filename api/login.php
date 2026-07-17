<?php
// ============================================================
// CABECERAS CORS (soportan OPTIONS, POST y GET para pruebas)
// ============================================================
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Respuesta para preflight (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================================
// INCLUIR CONFIGURACIÓN (con manejo de errores)
// ============================================================
$configPath = __DIR__ . '/../includes/config.php';
if (!file_exists($configPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Error interno: archivo de configuración no encontrado en ' . $configPath
    ]);
    exit();
}

try {
    require_once $configPath;
    // Verificar que la conexión existe
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception('La conexión a la base de datos no está disponible.');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error de configuración: ' . $e->getMessage()
    ]);
    exit();
}

// ============================================================
// VARIABLE DE RESPUESTA
// ============================================================
$response = [];

// ============================================================
// SOLO ACEPTAR MÉTODO POST (o GET para pruebas, pero lo dejamos solo POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['success'] = false;
    $response['message'] = 'Método no permitido. Use POST. Recibido: ' . $_SERVER['REQUEST_METHOD'];
    echo json_encode($response);
    exit();
}

// ============================================================
// LEER DATOS JSON DEL CUERPO DE LA PETICIÓN
// ============================================================
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Si no se pudo decodificar JSON, mostrar error
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    $response['success'] = false;
    $response['message'] = 'Error: JSON inválido. Asegúrate de enviar Content-Type: application/json';
    echo json_encode($response);
    exit();
}

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// ============================================================
// VALIDAR CAMPOS VACÍOS
// ============================================================
if (empty($username) || empty($password)) {
    $response['success'] = false;
    $response['message'] = 'Usuario/Email y contraseña son obligatorios.';
    echo json_encode($response);
    exit();
}

// ============================================================
// AUTENTICACIÓN (misma lógica que tu login de la web)
// ============================================================
try {
    $sql = "
        SELECT 
            u.id, 
            u.username, 
            u.email,
            u.password, 
            u.role_id, 
            r.name as role_name,
            u.first_name,
            u.last_name,
            u.status
        FROM users u
        INNER JOIN roles r ON u.role_id = r.id
        WHERE u.username = :username OR u.email = :email
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':username' => $username,
        ':email'    => $username
    ]);

    if ($stmt->rowCount() === 0) {
        $response['success'] = false;
        $response['message'] = 'Usuario/Email no encontrado.';
        echo json_encode($response);
        exit();
    }

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar estado del usuario
    if ($user['status'] !== 'active') {
        $response['success'] = false;
        $response['message'] = 'Usuario inactivo o suspendido.';
        echo json_encode($response);
        exit();
    }

    // Verificar contraseña (usa password_hash de la web)
    if (!password_verify($password, $user['password'])) {
        $response['success'] = false;
        $response['message'] = 'Contraseña incorrecta.';
        echo json_encode($response);
        exit();
    }

    // Éxito: quitar campo password antes de devolver
    unset($user['password']);
    $response['success'] = true;
    $response['message'] = 'Inicio de sesión exitoso.';
    $response['data'] = $user;

} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error general: ' . $e->getMessage();
}

// ============================================================
// DEVOLVER RESPUESTA JSON
// ============================================================
echo json_encode($response);
exit();
?>
