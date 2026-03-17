<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function enviarCorreoPHPMailer($destinatario, $asunto, $cuerpoHTML, $cuerpoPlano = '') {
    // Obtener credenciales de variables de entorno
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = getenv('SMTP_PORT') ?: 587;
    $smtpSecure = getenv('SMTP_SECURE') ?: 'tls';
    $smtpUser = getenv('SMTP_USER');
    $smtpPass = getenv('SMTP_PASS');

    if (!$smtpUser || !$smtpPass) {
        error_log("Error: Credenciales SMTP no configuradas.");
        return false;
    }

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
        error_log("Error enviando correo: " . $mail->ErrorInfo);
        return false;
    }
}
?>
