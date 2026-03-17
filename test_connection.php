<?php
$host = 'smtp.gmail.com';
$ports = [587, 465];
foreach ($ports as $port) {
    $connection = @fsockopen($host, $port, $errno, $errstr, 10);
    if ($connection) {
        echo "✅ Conexión exitosa a $host:$port<br>";
        fclose($connection);
    } else {
        echo "❌ Falló conexión a $host:$port - Error $errno: $errstr<br>";
    }
}
?>
