<?php

function send_mail($to, $subject, $message, $headers = null) {
    // Try PHP mail; on local dev this might not be configured. Return true/false.
    if ($headers === null) {
        $headers = 'From: no-reply@localhost' . "\r\n" . 'Reply-To: no-reply@localhost' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
    }

    // Use @ to suppress warnings if mail is not configured on local environment
    $sent = @mail($to, $subject, $message, $headers);
    return $sent;
}

?>