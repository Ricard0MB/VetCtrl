<?php
// includes/config.php - Versión PDO para Aiven MySQL

$host = getenv('DB_HOST') ?: 'mysql-ec9d57e-vetctrl-db.a.aivencloud.com';
$port = getenv('DB_PORT') ?: '22939';
$dbname = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: 'avnadmin';
$password = getenv('DB_PASSWORD') ?: '';
$ssl_ca = getenv('SSL_CA') ?: __DIR__ . '/ssl/ca.pem';

$conn = null;

try {
    // Construir DSN (Data Source Name)
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    
    // Opciones de PDO
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    // Agregar SSL si existe el certificado
    if (file_exists($ssl_ca)) {
        $dsn .= ";ssl_ca=$ssl_ca";
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ssl_ca;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    
    // Crear conexión
    $conn = new PDO($dsn, $user, $password, $options);
    
} catch (PDOException $e) {
    // Mostrar el error real (solo temporalmente para debug)
    die("Error de conexión DETALLADO: " . $e->getMessage());
}
?>
