<?php
// test_db.php - Muestra el error real de conexión
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/config.php'; // Ajusta la ruta si es necesario

// Si llegamos aquí, la conexión debería estar en $conn
if (isset($conn) && $conn) {
    echo "✅ Conexión exitosa a la base de datos.";
    // Prueba simple
    $query = $conn->query("SELECT NOW() as ahora");
    $row = $query->fetch(PDO::FETCH_ASSOC);
    echo "<br>Hora del servidor: " . $row['ahora'];
} else {
    echo "❌ La variable \$conn no está definida o es null.";
}
?>
