<?php
session_start();

$_SESSION["bl"] = 1;
// $_SESSION["act"]=$id;
$_SESSION["act"] = 32;
$id = 32;
$_SESSION["stop"] = '0';
error_reporting(0);
include_once ('include/config.php');
$req0 = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 1)");
$row0 = mysqli_fetch_array($req0);
$commence = $row0["fin"];

$actu = strtotime(date("Y-m-d H:i:s"));
$debu = strtotime($commence);
$ecar = $debu - $actu - 1200;
$m = (int) date("m ", $ecar);
$m = $m - 1;
$j = (int) date("d ", $ecar);
$j = $j - 1;
$h = (int) date("H ", $ecar);
$mi = (int) date("i ", $ecar);
$mi = $mi + 1;
$star = "Début : " . $j . " Jour(s) " . $h . " Heure(s) et " . $mi . " Minute(s)";
echo "--> " . $ecar . "/";

if ($ecar > 0) {
    echo $star;
} else {

    $cnt = 0;
    $sql = mysqli_query($con, "SELECT  * FROM `blindes-live` WHERE `id-activite` = $id ORDER BY `ordre` ");

    while ($row = mysqli_fetch_array($sql)) {
        $cnt = $cnt + 1;
        $_SESSION["fin" . $cnt] = $row["fin"];
        $_SESSION["nom" . $cnt] = $row["nom"];
        $_SESSION["ante" . $cnt] = $row["ante"];
    }
    ;

    //  if ($_SESSION["stop"] == '0') {
    if (1) { ?>
        <script type="text/javascript">
            let nIntervId;
            stopall();

            function compteur() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte, 500);
                }
            }

            function decompte() {
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                if (xmlhttp.responseText == 0) {
                    stopcompteur();
                    compteur()
                } else {
                    document.getElementById("response").innerHTML = xmlhttp.responseText;
                }
            }

            function stopcompteur() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            // function compteur2() { if (!nIntervId) { nIntervId = setInterval(decompte2, 10); }}
            // function decompte2() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur2();compteur3()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur2() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur3() { if (!nIntervId) { nIntervId = setInterval(decompte3, 10); }}
            // function decompte3() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur3();compteur4()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur3() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur4() { if (!nIntervId) { nIntervId = setInterval(decompte4, 10); }}
            // function decompte4() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur4();compteur6()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur4() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur5() { if (!nIntervId) { nIntervId = setInterval(decompte5, 10); }}
            // function decompte5() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur5();compteur5()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur5() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur6() { if (!nIntervId) { nIntervId = setInterval(decompte6, 10); }}
            // function decompte6() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur6();stopcompteur4();stopcompteur3();stopcompteur2();stopcompteur();compteur6()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur6() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur7() { if (!nIntervId) { nIntervId = setInterval(decompte7, 10); }}
            // function decompte7() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur7();compteur7()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur7() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur8() { if (!nIntervId) { nIntervId = setInterval(decompte8, 10); }}
            // function decompte8() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur8();compteur9()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur8() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur9() { if (!nIntervId) { nIntervId = setInterval(decompte9, 10); }}
            // function decompte9() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur9();compteur10()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur9() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur10() { if (!nIntervId) { nIntervId = setInterval(decompte10, 10); }}
            // function decompte10() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur10();compteur11()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur10() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur11() { if (!nIntervId) { nIntervId = setInterval(decompte11, 10); }}
            // function decompte11() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur11();compteur12()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur11() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur12() { if (!nIntervId) { nIntervId = setInterval(decompte12, 10); }}
            // function decompte12() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur12();compteur13()} else {document.getElementById("response").innerHTML=xmlhttp.responseText;}}
            // function stopcompteur12() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur13() { if (!nIntervId) { nIntervId = setInterval(decompte13, 10); }}
            // function decompte13() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur13();compteur14()} else {document.getElementById("response").innerHTML=document.getElementById("nom13").value+" + "+document.getElementById("ante13").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur13() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur14() { if (!nIntervId) { nIntervId = setInterval(decompte14, 10); }}
            // function decompte14() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur14();compteur15()} else {document.getElementById("response").innerHTML=document.getElementById("nom14").value+" + "+document.getElementById("ante14").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur14() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur15() { if (!nIntervId) { nIntervId = setInterval(decompte15, 10); }}
            // function decompte15() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur15();compteur16()} else {document.getElementById("response").innerHTML=document.getElementById("nom15").value+" + "+document.getElementById("ante15").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur15() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur16() { if (!nIntervId) { nIntervId = setInterval(decompte16, 10); }}
            // function decompte16() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur16();compteur17()} else {document.getElementById("response").innerHTML=document.getElementById("nom16").value+" + "+document.getElementById("ante16").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur16() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur17() { if (!nIntervId) { nIntervId = setInterval(decompte17, 10); }}
            // function decompte17() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur17();compteur18()} else {document.getElementById("response").innerHTML=document.getElementById("nom17").value+" + "+document.getElementById("ante17").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur17() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur18() { if (!nIntervId) { nIntervId = setInterval(decompte18, 10); }}
            // function decompte18() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur18();compteur19()} else {document.getElementById("response").innerHTML=document.getElementById("nom18").value+" + "+document.getElementById("ante18").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur18() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur19() { if (!nIntervId) { nIntervId = setInterval(decompte19, 10); }}
            // function decompte19() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur19();compteur20()} else {document.getElementById("response").innerHTML=document.getElementById("nom19").value+" + "+document.getElementById("ante19").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur19() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur20() { if (!nIntervId) { nIntervId = setInterval(decompte20, 10); }}
            // function decompte20() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur20();compteur21()} else {document.getElementById("response").innerHTML=document.getElementById("nom20").value+" + "+document.getElementById("ante20").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur20() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur21() { if (!nIntervId) { nIntervId = setInterval(decompte21, 10); }}
            // function decompte21() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur21();compteur22()} else {document.getElementById("response").innerHTML=document.getElementById("nom21").value+" + "+document.getElementById("ante21").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur21() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur22() { if (!nIntervId) { nIntervId = setInterval(decompte22, 10); }}
            // function decompte22() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur22();compteur23()} else {document.getElementById("response").innerHTML=document.getElementById("nom22").value+" + "+document.getElementById("ante22").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur22() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur23() { if (!nIntervId) { nIntervId = setInterval(decompte23, 10); }}
            // function decompte23() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur23();compteur24()} else {document.getElementById("response").innerHTML=document.getElementById("nom23").value+" + "+document.getElementById("ante23").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur23() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur24() { if (!nIntervId) { nIntervId = setInterval(decompte24, 10); }}
            // function decompte24() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur24();compteur25()} else {document.getElementById("response").innerHTML=document.getElementById("nom24").value+" + "+document.getElementById("ante24").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur24() { clearInterval(nIntervId); nIntervId = null; }

            // function compteur25() { if (!nIntervId) { nIntervId = setInterval(decompte25, 10); }}
            // function decompte25() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            //     if (xmlhttp.responseText == 0) {stopcompteur25()} else {document.getElementById("response").innerHTML=document.getElementById("nom25").value+" + "+document.getElementById("ante25").value+" : "+xmlhttp.responseText;}}
            // function stopcompteur25() { clearInterval(nIntervId); nIntervId = null; }

            function stopall() {
                stopcompteur();
                stopcompteur2();
                stopcompteur3();
                stopcompteur4();
                stopcompteur5();
                stopcompteur6();
                stopcompteur7();
                stopcompteur8();
                stopcompteur9();
                stopcompteur10();
                stopcompteur11();
                stopcompteur12();
                stopcompteur13();
                stopcompteur14();
                stopcompteur15();
                stopcompteur16();
                stopcompteur17();
                stopcompteur18();
                stopcompteur19();
                stopcompteur20();
                stopcompteur21();
                stopcompteur22();
                stopcompteur23();
                stopcompteur(24);
                stopcompteur25()
            }
            stopall();
            compteur();
            // compteur_pause();
        </script>
        <!-- <div id="response"></div> -->

        <?php ;
    }
}
;
?>