<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Envía un correo usando PHPMailer con configuración SMTP.
 * Los parámetros pueden ser sobreescritos por variables de entorno:
 * SMTP_HOST, SMTP_PORT, SMTP_SECURE, SMTP_USER, SMTP_PASS.
 *
 * @param string $destinatario Correo del destinatario.
 * @param string $asunto Asunto del mensaje.
 * @param string $cuerpoHTML Cuerpo en formato HTML.
 * @param string $cuerpoPlano Versión en texto plano (opcional).
 * @return true|string True si se envió, o mensaje de error.
 */
function enviarCorreoPHPMailer($destinatario, $asunto, $cuerpoHTML, $cuerpoPlano = '') {
    // Valores por defecto: Gmail con puerto 465 y SSL (más fiable en algunos entornos)
    $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $smtpPort = getenv('SMTP_PORT') ?: 465;
    $smtpSecure = getenv('SMTP_SECURE') ?: 'ssl';
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
        
        // Activar depuración (se guarda en error_log de Render)
        $mail->SMTPDebug  = 2;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer debug: $str");
        };

        // Opcional: desactivar verificación SSL si hay problemas de certificado (solo para pruebas)
        // $mail->SMTPOptions = array(
        //     'ssl' => array(
        //         'verify_peer' => false,
        //         'verify_peer_name' => false,
        //         'allow_self_signed' => true
        //     )
        // );

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
