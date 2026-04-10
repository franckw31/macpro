<?php
// Activer l'affichage des erreurs temporairement pour le debug si besoin
// error_reporting(E_ALL); ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Utiliser include_once pour éviter l'erreur "Cannot redeclare class/function"
if (file_exists('include/config.php')) {
    include_once('include/config.php');
}

$id = intval($_GET['uid']);
$_SESSION["act"] = $id;

// IMPORTANT : Si horloge-heure.php contient aussi include('config.php'), 
// cela peut planter si ce n'est pas un include_once.
// On essaie de charger les vues.
?>

<?php include('horloge-heure.php'); ?>

<div style="color:green ; text-align: center">
    <!-- Les boutons sont gérés dans la page principale, on ne les remet pas ici pour éviter les doublons d'ID -->
</div>

<?php include('horloge-pause.php'); ?>
<div style="color:white ; font-size: 30px ; text-align: center" id="car-pause"></div>

<?php include('horloge-estim.php'); ?>
<div style="color:grey ; font-size: 90px ; text-align: center"></div>