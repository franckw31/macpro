<?php
require_once 'serveur-smtp/send.php';

$res = sendRealEmail('franck.wenger@wanadoo.fr', 'Test Centralisé', 'Ceci est un test utilisant la fonction centralisée.');

if ($res['success']) {
    echo 'Message envoyé !';
} else {
    echo $res['message'];
}
?>
