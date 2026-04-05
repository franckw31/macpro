<?php
require_once __DIR__ . '/../serveur-smtp/send.php';

function sendMailViaSMTP($to, $subject, $message) {
    $res = sendRealEmail($to, $subject, $message);
    if (!$res['success']) {
        error_log("Mail Error: " . $res['message']);
    }
    return $res['success'];
}
?>
