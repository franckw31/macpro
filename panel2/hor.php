<?php
session_start();
$_SESSION["bl"]=1;
$_SESSION["stop"] = '0';
$id=32;
error_reporting(0);
include_once('include/config.php');
$req0=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 1)");
$row0=mysqli_fetch_array($req0);$commence = $row0["fin"];

$actu=strtotime(date("Y-m-d H:i:s"));
$debu=strtotime($commence);
$ecar=$debu-$actu-1200;
$m=(int)gmdate("m ",$ecar); $m=$m-1; $j=(int)gmdate("d ",$ecar);$j=$j-1;
$h=(int)gmdate("H ",$ecar); $mi=(int)gmdate("i ",$ecar);$mi=$mi+1;
$star="Début : ".$j." Jour(s) ".$h." Heure(s) et ".$mi." Minute(s)";
echo "--> ".$ecar."*";

if ($ecar > 0) { echo $star; } else { 

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 1)")) {
    $row=mysqli_fetch_array($res);
    $nom=$row["nom"];$ante1=$row["ante"];$_SESSION["fin"."1"]=$row["fin"];$_SESSION["nom"."1"]=$nom; };    

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 2)")) {
    $row=mysqli_fetch_array($res);
    $nom2=$row["nom"];$ante2=$row["ante"];$_SESSION["fin"."2"]=$row["fin"]; $_SESSION["nom"."2"]=$nom2;};

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 3)")) {
    $row=mysqli_fetch_array($res);
    $nom3=$row["nom"];$ante3=$row["ante"];$_SESSION["fin"."3"]=$row["fin"]; $_SESSION["nom"."3"]=$nom3;};         

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 4)")) {
    $row=mysqli_fetch_array($res);
    $nom4=$row["nom"];$ante4=$row["ante"];$_SESSION["fin"."4"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 5)")) {
    $row=mysqli_fetch_array($res);
    $nom5=$row["nom"];$ante5=$row["ante"];$_SESSION["fin"."5"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 6)")) {
    $row=mysqli_fetch_array($res);
    $nom6=$row["nom"];$ante6=$row["ante"];$_SESSION["fin"."6"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 7)")) {
    $row=mysqli_fetch_array($res);
    $nom7=$row["nom"];$ante7=$row["ante"];$_SESSION["fin"."7"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 8)")) {
    $row=mysqli_fetch_array($res);
    $nom8=$row["nom"];$ante8=$row["ante"];$_SESSION["fin"."8"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 9)")) {
    $row=mysqli_fetch_array($res);
    $nom9=$row["nom"];$ante9=$row["ante"];$_SESSION["fin"."9"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 10)")) {
    $row=mysqli_fetch_array($res);
    $nom10=$row["nom"];$ante10=$row["ante"];$_SESSION["fin"."10"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 11)")) {
    $row=mysqli_fetch_array($res);
    $nom11=$row["nom"];$ante11=$row["ante"];$_SESSION["fin"."11"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 12)")) {
    $row=mysqli_fetch_array($res);
    $nom12=$row["nom"];$ante12=$row["ante"];$_SESSION["fin"."12"]=$row["fin"]; };
    
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 13)")) {
    $row=mysqli_fetch_array($res);
    $nom13=$row["nom"];$ante13=$row["ante"];$_SESSION["fin"."13"]=$row["fin"]; };         
    
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 14)")) {
    $row=mysqli_fetch_array($res);
    $nom14=$row["nom"];$ante14=$row["ante"];$_SESSION["fin"."14"]=$row["fin"]; };
    
 if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 15)")) {
    $row=mysqli_fetch_array($res);
    $nom15=$row["nom"];$ante15=$row["ante"];$_SESSION["fin"."15"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 16)")) {
    $row=mysqli_fetch_array($res);
    $nom16=$row["nom"];$ante16=$row["ante"];$_SESSION["fin"."16"]=$row["fin"]; };
    
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 17)")) {
    $row=mysqli_fetch_array($res);
    $nom17=$row["nom"];$ante17=$row["ante"];$_SESSION["fin"."17"]=$row["fin"]; };
    
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 18)")) {
    $row=mysqli_fetch_array($res);
    $nom18=$row["nom"];$ante18=$row["ante"];$_SESSION["fin"."18"]=$row["fin"]; };
    
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 19)")) {
    $row=mysqli_fetch_array($res);
    $nom19=$row["nom"];$ante19=$row["ante"];$_SESSION["fin"."19"]=$row["fin"]; };

if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 20)")) {
    $row=mysqli_fetch_array($res);
    $nom20=$row["nom"];$ante10=$row["ante"];$_SESSION["fin"."20"]=$row["fin"]; };
  
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 21)")) {
    $row=mysqli_fetch_array($res);
    $nom21=$row["nom"];$ante21=$row["ante"];$_SESSION["fin"."21"]=$row["fin"]; };
    
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 22)")) {
    $row=mysqli_fetch_array($res);
    $nom22=$row["nom"];$ante22=$row["ante"];$_SESSION["fin"."22"]=$row["fin"]; };
        
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 23)")) {
    $row=mysqli_fetch_array($res);
    $nom23=$row["nom"];$ante23=$row["ante"];$_SESSION["fin"."23"]=$row["fin"]; };         
        
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 24)")) {
    $row=mysqli_fetch_array($res);
    $nom24=$row["nom"];$ante24=$row["ante"];$_SESSION["fin"."24"]=$row["fin"]; };
        
if ($res=mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 25)")) {
    $row=mysqli_fetch_array($res);
    $nom25=$row["nom"];$ante25=$row["ante"];$_SESSION["fin"."25"]=$row["fin"]; };
?>

<form>
  <input type="hidden" id="test"  value="<?php echo "test";  ?>">
  <input type="hidden" id="nom"  value="<?php echo $nom;  ?>">  <input type="hidden" id="ante1" value="<?php echo $ante1; ?>">
  <input type="hidden" id="nom2" value="<?php echo $nom2; ?>">  <input type="hidden" id="ante2" value="<?php echo $ante2; ?>">
  <input type="hidden" id="nom3" value="<?php echo $nom3; ?>">  <input type="hidden" id="ante3" value="<?php echo $ante3; ?>">
  <input type="hidden" id="nom4" value="<?php echo $nom4; ?>">  <input type="hidden" id="ante4" value="<?php echo $ante4; ?>">
  <input type="hidden" id="nom5" value="<?php echo $nom5; ?>">  <input type="hidden" id="ante5" value="<?php echo $ante5; ?>">
  <input type="hidden" id="nom6" value="<?php echo $nom6; ?>">  <input type="hidden" id="ante6" value="<?php echo $ante6; ?>">
  <input type="hidden" id="nom7" value="<?php echo $nom7; ?>">  <input type="hidden" id="ante7" value="<?php echo $ante7; ?>">
  <input type="hidden" id="nom8" value="<?php echo $nom8; ?>">  <input type="hidden" id="ante8" value="<?php echo $ante8; ?>">
  <input type="hidden" id="nom9" value="<?php echo $nom9; ?>">  <input type="hidden" id="ante9" value="<?php echo $ante9; ?>">
  <input type="hidden" id="nom10" value="<?php echo $nom10; ?>">  <input type="hidden" id="ante10" value="<?php echo $ante10; ?>">
  <input type="hidden" id="nom11" value="<?php echo $nom11; ?>">  <input type="hidden" id="ante11" value="<?php echo $ante11; ?>">
  <input type="hidden" id="nom12" value="<?php echo $nom12; ?>">  <input type="hidden" id="ante12" value="<?php echo $ante12; ?>">
  <input type="hidden" id="nom13" value="<?php echo $nom13; ?>">  <input type="hidden" id="ante13" value="<?php echo $ante13; ?>">
  <input type="hidden" id="nom14" value="<?php echo $nom14; ?>">  <input type="hidden" id="ante14" value="<?php echo $ante14; ?>">
  <input type="hidden" id="nom15" value="<?php echo $nom15; ?>">  <input type="hidden" id="ante15" value="<?php echo $ante15; ?>">
  <input type="hidden" id="nom16" value="<?php echo $nom16; ?>">  <input type="hidden" id="ante16" value="<?php echo $ante16; ?>">
  <input type="hidden" id="nom17" value="<?php echo $nom17; ?>">  <input type="hidden" id="ante17" value="<?php echo $ante17; ?>">
  <input type="hidden" id="nom18" value="<?php echo $nom18; ?>">  <input type="hidden" id="ante18" value="<?php echo $ante18; ?>">
  <input type="hidden" id="nom19" value="<?php echo $nom19; ?>">  <input type="hidden" id="ante19" value="<?php echo $ante19; ?>">
  <input type="hidden" id="nom20" value="<?php echo $nom20; ?>">  <input type="hidden" id="ante20" value="<?php echo $ante20; ?>">
  <input type="hidden" id="nom21" value="<?php echo $nom21; ?>">  <input type="hidden" id="ante21" value="<?php echo $ante21; ?>">
  <input type="hidden" id="nom22" value="<?php echo $nom22; ?>">  <input type="hidden" id="ante22" value="<?php echo $ante22; ?>">
  <input type="hidden" id="nom23" value="<?php echo $nom23; ?>">  <input type="hidden" id="ante23" value="<?php echo $ante23; ?>">
  <input type="hidden" id="nom24" value="<?php echo $nom24; ?>">  <input type="hidden" id="ante24" value="<?php echo $ante24; ?>">
  <input type="hidden" id="nom25" value="<?php echo $nom25; ?>">  <input type="hidden" id="ante25" value="<?php echo $ante25; ?>">
  
</form>

<?php
// if ($_SESSION["stop"] == '0') 
if (1) { ?> 

    <!-- <div id="response"></div> -->
    <!-- <div id="bob"></div> -->

    <script type="text/javascript">
        let nIntervId;
        stopall();
        function compteur() { if (!nIntervId) { nIntervId = setInterval(decompte, 10);}}
        function decompte() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur();compteur2()} else {document.getElementById("response").innerHTML=document.getElementById("nom").value+" + "+document.getElementById("ante1").value+" : "+xmlhttp.responseText;}}
        function stopcompteur() { clearInterval(nIntervId); nIntervId = null; }

        function compteur2() { if (!nIntervId) { nIntervId = setInterval(decompte2, 10); }}
        function decompte2() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur2();compteur3()} else {document.getElementById("response").innerHTML=document.getElementById("nom2").value+" + "+document.getElementById("ante2").value+" : "+xmlhttp.responseText;}}
        function stopcompteur2() { clearInterval(nIntervId); nIntervId = null; }

        function compteur3() { if (!nIntervId) { nIntervId = setInterval(decompte3, 10); }}
        function decompte3() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur3();compteur4()} else {document.getElementById("response").innerHTML=document.getElementById("nom3").value+" + "+document.getElementById("ante3").value+" : "+xmlhttp.responseText;}}
        function stopcompteur3() { clearInterval(nIntervId); nIntervId = null; }

        function compteur4() { if (!nIntervId) { nIntervId = setInterval(decompte4, 10); }}
        function decompte4() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur4();compteur5()} else {document.getElementById("response").innerHTML=document.getElementById("nom4").value+" + "+document.getElementById("ante4").value+" : "+xmlhttp.responseText;}}
        function stopcompteur4() { clearInterval(nIntervId); nIntervId = null; }

        function compteur5() { if (!nIntervId) { nIntervId = setInterval(decompte5, 10); }}
        function decompte5() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur5();compteur6()} else {document.getElementById("response").innerHTML=document.getElementById("nom5").value+" + "+document.getElementById("ante5").value+" : "+xmlhttp.responseText;}}
        function stopcompteur5() { clearInterval(nIntervId); nIntervId = null; }

        function compteur6() { if (!nIntervId) { nIntervId = setInterval(decompte6, 10); }}
        function decompte6() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur6();compteur7()} else {document.getElementById("response").innerHTML=document.getElementById("nom6").value+" + "+document.getElementById("ante6").value+" : "+xmlhttp.responseText;}}
        function stopcompteur6() { clearInterval(nIntervId); nIntervId = null; }

        function compteur7() { if (!nIntervId) { nIntervId = setInterval(decompte7, 10); }}
        function decompte7() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur7();compteur8()} else {document.getElementById("response").innerHTML=document.getElementById("nom7").value+" + "+document.getElementById("ante7").value+" : "+xmlhttp.responseText;}}
        function stopcompteur7() { clearInterval(nIntervId); nIntervId = null; }

        function compteur8() { if (!nIntervId) { nIntervId = setInterval(decompte8, 10); }}
        function decompte8() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur8();compteur9()} else {document.getElementById("response").innerHTML=document.getElementById("nom8").value+" + "+document.getElementById("ante8").value+" : "+xmlhttp.responseText;}}
        function stopcompteur8() { clearInterval(nIntervId); nIntervId = null; }

        function compteur9() { if (!nIntervId) { nIntervId = setInterval(decompte9, 10); }}
        function decompte9() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur9();compteur10()} else {document.getElementById("response").innerHTML=document.getElementById("nom9").value+" + "+document.getElementById("ante9").value+" : "+xmlhttp.responseText;}}
        function stopcompteur9() { clearInterval(nIntervId); nIntervId = null; }

        function compteur10() { if (!nIntervId) { nIntervId = setInterval(decompte10, 10); }}
        function decompte10() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur10();compteur11()} else {document.getElementById("response").innerHTML=document.getElementById("nom10").value+" + "+document.getElementById("ante10").value+" : "+xmlhttp.responseText;}}
        function stopcompteur10() { clearInterval(nIntervId); nIntervId = null; }

        function compteur11() { if (!nIntervId) { nIntervId = setInterval(decompte11, 10); }}
        function decompte11() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur11();compteur12()} else {document.getElementById("response").innerHTML=document.getElementById("nom11").value+" + "+document.getElementById("ante11").value+" : "+xmlhttp.responseText;}}
        function stopcompteur11() { clearInterval(nIntervId); nIntervId = null; }

        function compteur12() { if (!nIntervId) { nIntervId = setInterval(decompte12, 10); }}
        function decompte12() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur12();compteur13()} else {document.getElementById("response").innerHTML=document.getElementById("nom12").value+" + "+document.getElementById("ante12").value+" : "+xmlhttp.responseText;}}
        function stopcompteur12() { clearInterval(nIntervId); nIntervId = null; }

        function compteur13() { if (!nIntervId) { nIntervId = setInterval(decompte13, 10); }}
        function decompte13() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur13();compteur14()} else {document.getElementById("response").innerHTML=document.getElementById("nom13").value+" + "+document.getElementById("ante13").value+" : "+xmlhttp.responseText;}}
        function stopcompteur13() { clearInterval(nIntervId); nIntervId = null; }

        function compteur14() { if (!nIntervId) { nIntervId = setInterval(decompte14, 10); }}
        function decompte14() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur14();compteur15()} else {document.getElementById("response").innerHTML=document.getElementById("nom14").value+" + "+document.getElementById("ante14").value+" : "+xmlhttp.responseText;}}
        function stopcompteur14() { clearInterval(nIntervId); nIntervId = null; }

        function compteur15() { if (!nIntervId) { nIntervId = setInterval(decompte15, 10); }}
        function decompte15() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur15();compteur16()} else {document.getElementById("response").innerHTML=document.getElementById("nom15").value+" + "+document.getElementById("ante15").value+" : "+xmlhttp.responseText;}}
        function stopcompteur15() { clearInterval(nIntervId); nIntervId = null; }

        function compteur16() { if (!nIntervId) { nIntervId = setInterval(decompte16, 10); }}
        function decompte16() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur16();compteur17()} else {document.getElementById("response").innerHTML=document.getElementById("nom16").value+" + "+document.getElementById("ante16").value+" : "+xmlhttp.responseText;}}
        function stopcompteur16() { clearInterval(nIntervId); nIntervId = null; }

        function compteur17() { if (!nIntervId) { nIntervId = setInterval(decompte17, 10); }}
        function decompte17() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur17();compteur18()} else {document.getElementById("response").innerHTML=document.getElementById("nom17").value+" + "+document.getElementById("ante17").value+" : "+xmlhttp.responseText;}}
        function stopcompteur17() { clearInterval(nIntervId); nIntervId = null; }

        function compteur18() { if (!nIntervId) { nIntervId = setInterval(decompte18, 10); }}
        function decompte18() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur18();compteur19()} else {document.getElementById("response").innerHTML=document.getElementById("nom18").value+" + "+document.getElementById("ante18").value+" : "+xmlhttp.responseText;}}
        function stopcompteur18() { clearInterval(nIntervId); nIntervId = null; }

        function compteur19() { if (!nIntervId) { nIntervId = setInterval(decompte19, 10); }}
        function decompte19() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur19();compteur20()} else {document.getElementById("response").innerHTML=document.getElementById("nom19").value+" + "+document.getElementById("ante19").value+" : "+xmlhttp.responseText;}}
        function stopcompteur19() { clearInterval(nIntervId); nIntervId = null; }
        
        function compteur20() { if (!nIntervId) { nIntervId = setInterval(decompte20, 10); }}
        function decompte20() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur20();compteur21()} else {document.getElementById("response").innerHTML=document.getElementById("nom20").value+" + "+document.getElementById("ante20").value+" : "+xmlhttp.responseText;}}
        function stopcompteur20() { clearInterval(nIntervId); nIntervId = null; }

        function compteur21() { if (!nIntervId) { nIntervId = setInterval(decompte21, 10); }}
        function decompte21() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur21();compteur22()} else {document.getElementById("response").innerHTML=document.getElementById("nom21").value+" + "+document.getElementById("ante21").value+" : "+xmlhttp.responseText;}}
        function stopcompteur21() { clearInterval(nIntervId); nIntervId = null; }

        function compteur22() { if (!nIntervId) { nIntervId = setInterval(decompte22, 10); }}
        function decompte22() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur22();compteur23()} else {document.getElementById("response").innerHTML=document.getElementById("nom22").value+" + "+document.getElementById("ante22").value+" : "+xmlhttp.responseText;}}
        function stopcompteur22() { clearInterval(nIntervId); nIntervId = null; }

        function compteur23() { if (!nIntervId) { nIntervId = setInterval(decompte23, 10); }}
        function decompte23() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur23();compteur24()} else {document.getElementById("response").innerHTML=document.getElementById("nom23").value+" + "+document.getElementById("ante23").value+" : "+xmlhttp.responseText;}}
        function stopcompteur23() { clearInterval(nIntervId); nIntervId = null; }

        function compteur24() { if (!nIntervId) { nIntervId = setInterval(decompte24, 10); }}
        function decompte24() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur24();compteur25()} else {document.getElementById("response").innerHTML=document.getElementById("nom24").value+" + "+document.getElementById("ante24").value+" : "+xmlhttp.responseText;}}
        function stopcompteur24() { clearInterval(nIntervId); nIntervId = null; }

        function compteur25() { if (!nIntervId) { nIntervId = setInterval(decompte25, 10); }}
        function decompte25() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteur25()} else {document.getElementById("response").innerHTML=document.getElementById("nom25").value+" + "+document.getElementById("ante25").value+" : "+xmlhttp.responseText;}}
        function stopcompteur25() { clearInterval(nIntervId); nIntervId = null; }

        function stopall() { stopcompteur();stopcompteur2();stopcompteur3();stopcompteur4();stopcompteur5();stopcompteur6();stopcompteur7();stopcompteur8();stopcompteur9();stopcompteur10();stopcompteur11();stopcompteur12();stopcompteur13();stopcompteur14();stopcompteur15();stopcompteur16();stopcompteur17();stopcompteur18();stopcompteur19();stopcompteur20();stopcompteur21();stopcompteur22();stopcompteur23();stopcompteur(24);stopcompteur25()}
        stopall();
        compteur();
    </script>
    <!-- <div id="response"></div> -->

<?php ; }
};
?> 