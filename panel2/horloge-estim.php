<?php
session_start();
$_SESSION["estimfin"] = '0';
error_reporting(0);
include_once('include/config.php');

if ($_SESSION["estimfin"] == '0') { ?> 
    <script type="text/javascript">
        let nIntervId12;
        function compteures() { if (!nIntervId12) { nIntervId12 = setInterval(decomptees, 100);}}
        function decomptees() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response-estim.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteures();compteures()} else {document.getElementById("response-estim").innerHTML=xmlhttp.responseText;}}
        function stopcompteures() { clearInterval(nIntervId12); nIntervId12 = null; }
        stopcompteures();
        compteures();
    </script>
<?php ; }
?> 