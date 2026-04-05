<?php
include ('include/config.php');
date_default_timezone_set('Europe/Rome');
$id_activite = "";
$id_activite = intval($_GET['act']);
$minutes = 0;
$minutes = intval($_GET['min']);
$source = "/index.php";
$source = $_GET['sou'];
$sql = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `ordre` = '1' AND `id-activite` =  '$id_activite' ");
$row = mysqli_fetch_array($sql);
$en_pause = $row['en_pause'];
$_SESSION["en_pause" . $id_activite] = "1";
$_SESSION["act"] = $id_activite;
$actu = date("Y-m-d H:i:s");
//echo $actu . "-" . $en_pause;
if ($en_pause == "0")
    $modif = mysqli_query($con, "UPDATE `blindes-live` SET `heure_pause` = '$actu' , `delta` = '0' , `en_pause` = '1' WHERE `ordre` = '1' AND `id-activite` =  '$id_activite'");
?>
<script type="text/javascript">
    window.location.replace("<?php echo $source . $id_activite; ?>")
</script>