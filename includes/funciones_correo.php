<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function enviarCorreoPHPMailer($destinatario, $asunto, $cuerpoHTML, $cuerpoPlano = '') {
    $smtpHost = 'smtp.gmail.com';
    $smtpPort = 587;
    $smtpSecure = 'tls';
    $smtpUser = 'tucorreo@gmail.com'; // REEMPLAZA
    $smtpPass = 'tu-contraseña-de-aplicacion'; // REEMPLAZA

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port       = $smtpPort;

        $mail->setFrom($smtpUser, 'VetCtrl');
        $mail->addAddress($destinatario);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;
        $mail->AltBody = $cuerpoPlano ?: strip_tags($cuerpoHTML);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Error al enviar: " . $mail->ErrorInfo;
    }
}
?>
