<?php
session_start();
date_default_timezone_set('Arctic/Longyearbyen');
$departsecondes = strtotime(date("Y-m-d H:i:s"));
$arriveesecondes1 = strtotime($_SESSION["fin" . $_SESSION["bl"]]);
$nomr = $_SESSION["nom" . $_SESSION["bl"]];
$antr = $_SESSION["ante" . $_SESSION["bl"]];
$pause = $_SESSION["en_pause" . $_SESSION["act"]];
if ($antr == "0") {
    $antr = "";
} else {
    $antr = " + " . $antr;
}
;

$ecartsecondes1 = $arriveesecondes1 - $departsecondes;
//echo $ecartsecondes1;
if ($ecartsecondes1 < 1) {
    echo "0";
    $_SESSION["stop"] = "1";
    ?>
<script type="text/javascript">
var audio2 = new Audio("/bblinde.mp3");
audio2.play();
</script>
<?php
    $_SESSION["bl"] = $_SESSION["bl"] + 1;
} else {
    if ($pause == "0") {
    //    echo "(" . $_SESSION["bl"] . ")->" . date("i:s", $ecartsecondes1);
        echo date("i:s", $ecartsecondes1);
    } else {
        echo "Pause";
    }
    ;
    // echo $ecartsecondes1;
}

?>