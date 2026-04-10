<?php
// filepath: c:\Users\MSI\Desktop\www\panel\horloge-estim.php

// Sécurité : Si la variable $id n'existe pas (cas d'un appel direct), on la récupère
if (!isset($id) && isset($_GET['uid'])) {
    $id = intval($_GET['uid']);
}

// Sécurité : Si la connexion DB n'est pas là, on l'ouvre
if (!isset($con)) {
    include('include/config.php');
}

if (isset($id) && $id > 0) {
    // On récupère la date de fin du TOUT DERNIER niveau configuré pour ce tournoi
    $q_estim = mysqli_query($con, "SELECT `fin` FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` DESC LIMIT 1");
    
    if ($q_estim && $r_estim = mysqli_fetch_assoc($q_estim)) {
        $timestamp_fin = strtotime($r_estim['fin']);
        // Affiche l'heure au format H:i (ex: 23:45)
        echo "Fin estimée : " . date("H:i", $timestamp_fin);
    } else {
        echo "Fin : --:--";
    }
}
?>