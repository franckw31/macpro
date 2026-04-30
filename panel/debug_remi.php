<?php
include __DIR__ . '/include/config.php';

echo "<pre>";

echo "=== membres (pseudo like Remi) ===\n";
$q = mysqli_query($con, "SELECT `id-membre`, pseudo FROM membres WHERE pseudo LIKE '%Remi%' OR pseudo LIKE '%remi%'");
while($r = mysqli_fetch_assoc($q)) print_r($r);

echo "\n=== participation (nom-membre like Remi) - 10 dernières ===\n";
$q = mysqli_query($con, "SELECT `id-participation`, `id-activite`, `nom-membre`, `id-membre` FROM participation WHERE `nom-membre` LIKE '%Remi%' OR `nom-membre` LIKE '%remi%' ORDER BY `id-activite` DESC LIMIT 10");
while($r = mysqli_fetch_assoc($q)) print_r($r);

echo "\n=== eliminations (nom_membre like Remi) - 10 dernières ===\n";
$q = mysqli_query($con, "SELECT id, id_activite, nom_membre, id_membre FROM eliminations WHERE nom_membre LIKE '%Remi%' OR nom_membre LIKE '%remi%' ORDER BY id DESC LIMIT 10");
while($r = mysqli_fetch_assoc($q)) print_r($r);

echo "\n=== Toutes les eliminations de la dernière activité ===\n";
$q = mysqli_query($con, "SELECT id, id_activite, nom_membre, id_membre, nom_membre_victime FROM eliminations ORDER BY id DESC LIMIT 20");
while($r = mysqli_fetch_assoc($q)) print_r($r);

echo "</pre>";
