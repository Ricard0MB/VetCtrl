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

    // Verificar credenciales
    if (!$smtpUser || !$smtpPass) {
        $errorMsg = "Error: Credenciales SMTP no configuradas. SMTP_USER: " . ($smtpUser ? 'OK' : 'falta') . ", SMTP_PASS: " . ($smtpPass ? 'OK' : 'falta');
        error_log($errorMsg);
        return $errorMsg;
    }

    $mail = new PHPMailer(true);
    try {
        // Configuración SMTP
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port       = $smtpPort;
        // Activar depuración (los mensajes se guardarán en error_log de Render)
        $mail->SMTPDebug  = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer debug: $str");
        };

        $mail->setFrom($smtpUser, 'VetCtrl');
        $mail->addAddress($destinatario);

        $mail->isHTML(true);
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;
        $mail->AltBody = $cuerpoPlano ?: strip_tags($cuerpoHTML);

        $mail->send();
        return true;
    } catch (Exception $e) {
        $errorMsg = "Error de PHPMailer: " . $mail->ErrorInfo;
        error_log($errorMsg);
        return $errorMsg;
    }
}
?>
