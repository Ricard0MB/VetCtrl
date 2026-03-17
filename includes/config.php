<?php
// includes/config.php

$host = getenv('DB_HOST') ?: 'mysql-ec9d57e-vetctrl-db.a.aivencloud.com';
$port = getenv('DB_PORT') ?: '22939';
$dbname = getenv('DB_NAME') ?: 'defaultdb';
$user = getenv('DB_USER') ?: 'avnadmin';
$password = getenv('DB_PASSWORD'); // Sin valor por defecto vacío

if (!$password) {
    die("Error: La contraseña de la base de datos no está configurada.");
}

$ssl_ca = getenv('SSL_CA') ?: __DIR__ . '/ssl/ca.pem';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (file_exists($ssl_ca)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ssl_ca;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    $conn = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>
