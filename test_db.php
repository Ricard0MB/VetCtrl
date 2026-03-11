<?php
require_once 'conexion.php'; // o como se llame tu archivo de conexión

echo "✅ Si ves esto sin errores, la conexión funciona!";

// Probar una consulta simple
$query = $pdo->query("SELECT 1 as test");
$result = $query->fetch();
echo "<br>Resultado de prueba: " . $result['test'];
?>
