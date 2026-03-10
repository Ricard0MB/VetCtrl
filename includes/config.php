<?php
// Activar reporte de errores de MySQL (opcional, ayuda a depurar)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

define('DB_SERVER', 'sql301.infinityfree.com');
define('DB_USERNAME', 'if0_41237524');
define('DB_PASSWORD', '31205408rm');
define('DB_NAME', 'if0_41237524_login_db'); // Asegúrate de que este sea el nombre correcto

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    // En lugar de die(), lanzamos una excepción para que sea capturada si hay error
    throw new Exception("Connection failed: " . $conn->connect_error);
}

// Establecer charset a utf8mb4 (recomendado)
$conn->set_charset('utf8mb4');
?>