<?php
session_start();
include('include/config.php');

$activi = intval($_GET['uid']);
$logext = intval($_GET['logext']);
$logid = $_SESSION['id'];
echo "-".$logid."-".$activi."-".$logext;
if ($logid == 0) {
   $logid = $logext;
} ;
echo $logid;
// Vérifier si l'utilisateur est déjà inscrit
$sql0 = mysqli_query($conn, "SELECT * FROM `participation` WHERE `id-membre` = '$logid' AND `id-activite` = '$activi'");
$rowcount = mysqli_num_rows($sql0);

if ($rowcount == 0) {
    // Obtenir l'ordre d'inscription
    $sql1 = mysqli_query($conn, "SELECT * FROM `participation` WHERE `id-activite` = '$activi' AND (`option` LIKE 'Reservation' OR `option` LIKE 'Option' OR `option` LIKE 'Inscrit')");
    $ordre = mysqli_num_rows($sql1) + 1;
    
    // Récupérer le pseudo du membre
    $sql4 = mysqli_query($conn, "SELECT * FROM `membres` WHERE `id-membre` = $logid");
    $row4 = mysqli_fetch_array($sql4);
    $nom_mem = $row4['pseudo'];
    
    // Insérer la participation
    $sql2 = mysqli_query($conn, "INSERT INTO `participation` (`id-membre`, `id-activite`, `ordre`, `nom-membre`, `id-siege`, `id-table`) VALUES ('$logid', '$activi', '$ordre', '$nom_mem', 0, 0)");
}

// Redirection vers la page de l'activité
 header("Location: /panel/voir-activite.php?uid=" . $activi);
exit();