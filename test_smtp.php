<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/funciones_correo.php';

$destino = 'tucorreo@example.com'; // Cámbialo por un correo tuyo
$asunto = 'Prueba SMTP desde Render';
$mensaje = '<p>Si ves esto, el envío funciona.</p>';
$resultado = enviarCorreoPHPMailer($destino, $asunto, $mensaje);

if ($resultado === true) {
    echo "✅ Correo enviado.";
} else {
    echo "❌ Error: " . $resultado;
}
?>
