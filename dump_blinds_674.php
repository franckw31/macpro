<?php
include('panel/include/config.php');
$id = 674;
echo "Dumping blinds for Activity $id\n";
echo "Current Time: " . time() . " (" . date("Y-m-d H:i:s") . ")\n";

$q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
while($b = mysqli_fetch_assoc($q)) {
    echo "ID: {$b['id']} | Ordre: {$b['ordre']} | Nom: {$b['nom']} | SB: {$b['sb']} | BB: {$b['bb']} | Min: {$b['minutes']} | Fin: {$b['fin']} | Pause: {$b['en_pause']}\n";
}
?>