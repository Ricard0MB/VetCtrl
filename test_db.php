<?php
// test_db.php - Versión corregida
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ruta correcta a tu archivo de conexión
require_once __DIR__ . '/includes/config.php';

if (isset($conn) && $conn) {
    echo "✅ Conexión exitosa a la base de datos.<br>";
    // Prueba simple
    $query = $conn->query("SELECT NOW() as ahora");
    $row = $query->fetch(PDO::FETCH_ASSOC);
    echo "Hora del servidor: " . $row['ahora'];
} else {
    echo "❌ La variable \$conn no está definida o es null.";
}
?>
