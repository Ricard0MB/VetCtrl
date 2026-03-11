<?php
// Conexión a MySQL en Aiven usando variables de entorno de Render

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASSWORD');

// Si las variables no existen, muestra error claro
if (!$host || !$port || !$dbname || !$user || $pass === false) {
    die("Error: Faltan variables de entorno. Verifica DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD en Render.");
}

try {
    // ¡IMPORTANTE! Usamos host y port EXPLÍCITAMENTE
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Opcional: probar conexión
    // echo "Conexión exitosa a la base de datos";
    
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
