<?php
// includes/conexion.php

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');
$ssl_ca = getenv('SSL_CA');

try {
    // Opción 1: Usando PDO (Recomendado)
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    $options = [
        PDO::MYSQL_ATTR_SSL_CA => $ssl_ca,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Cambia a true en producción
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    $conn = new PDO($dsn, $user, $password, $options);
    
    // echo "Conexión exitosa"; // Puedes descomentar para probar
    
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
