<?php
session_start();
error_reporting(0);
include_once ('include/config.php');

$id_activite = intval($_SESSION["act"]);
$req = mysqli_query($con, "SELECT `en_pause` FROM `blindes-live` WHERE `id-activite` = $id_activite AND `ordre` = 1");
$row = mysqli_fetch_array($req);

if ($row) {
    echo $row['en_pause'];
} else {
    echo "0";
}
?>
