<?php
session_start();

$_SESSION["bl"] = 1;
// $_SESSION["act"]=$id;
$_SESSION["act"] = $id;
// $id=32;
$_SESSION["stop"] = '0';
error_reporting(0);
include_once ('include/config.php');
$req0 = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 1)");
$row0 = mysqli_fetch_array($req0);
$commence = $row0["fin"];
$en_pause = $row0["en_pause"];
$heure_pause = $row0["heure_pause"];
$_SESSION["en_pause" . $id] = "0";
$actu = strtotime(date("Y-m-d H:i:s"));
if ($en_pause == "1") {
    $actu = strtotime(date($heure_pause));
    $_SESSION["en_pause" . $id] = "1";
}
;
// else
// { 
//     $_SESSION["en_pause"] = "0";
// };  
$debu = strtotime($commence);
//$ecar=$debu-$actu-1200;
$m = (int) date("m ", $ecar);
$m = $m - 1;
$j = (int) date("d ", $ecar);
$j = $j - 1;
$h = (int) date("H ", $ecar);
$mi = (int) date("i ", $ecar);
$mi = $mi + 1;
$star = $j . " Jour(s) " . $h . " Hres et " . $mi . " Mins";
// echo "--> ".$ecar."/".$id;

if ($ecar > 0) {
    // echo $star;
    ?>
    <div style="color:red ; font-size: 40px">
        <?php echo $star ?>
    </div> <?php
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
    // echo $_SESSION["nom"."1"];
// echo $_SESSION["bl"];
//  if ($_SESSION["stop"] == '0') {
    if (1) { ?>
        <audio id="audioElement" class="blind-alert-audio" preload="none">
            <source src="/newtimer/changement.mp3" type="audio/mpeg">
            <source src="/newtimer/changement.wav" type="audio/wav">
        </audio>

        <script type="text/javascript">
            let nIntervId;
            let lastBlindLevel = null;
            let audioElement = document.getElementById('audioElement');
            let isPageRefreshed = true;
            let pauseMode = false;
            let pageLoadTime = Date.now();
            let silencePeriodMs = 10000;
            let audioPlayingBeforePause = false;

            // STOP AUDIO IMMEDIATELY on page load
            try {
                audioElement.pause();
                audioElement.currentTime = 0;
                console.log('🔇 Audio arrêté immédiatement au chargement');
            } catch(e) {
                console.log('Error stopping audio on load:', e);
            }

            sessionStorage.removeItem('lastKnownBlindLevel');

            function checkPauseStatus() {
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", "get-pause-status.php", false);
                    xhr.send(null);
                    var status = xhr.responseText.trim();
                    var wasPaused = pauseMode;
                    pauseMode = (status === '1');
                    
                    if (pauseMode && !wasPaused) {
                        audioPlayingBeforePause = true;
                        stopBlindAlert();
                        console.log('⏸️ Pause activé - audio arrêté');
                    }
                    if (!pauseMode && wasPaused) {
                        audioPlayingBeforePause = false;
                        console.log('⏸️ Pause désactivé');
                    }
                } catch(e) {
                    console.log('Error checking pause status:', e);
                }
            }

            function initAudioOnInteraction() {
                audioElement.volume = 1.0;
                audioElement.play().then(() => {
                    audioElement.pause();
                    audioElement.currentTime = 0;
                }).catch(() => {
                    console.log('Audio initialization failed');
                });
            }

            ['click', 'touchstart', 'touchend'].forEach(eventType => {
                document.addEventListener(eventType, initAudioOnInteraction, { once: true });
            });

            function playBlindAlert() {
                if (pauseMode) {
                    console.log('⏸️ En pause - pas de son');
                    return;
                }
                console.log('🔔 ALERTE BLINDE! Tentative de lecture audio...');
                try {
                    audioElement.currentTime = 0;
                    audioElement.volume = 1.0;
                    var playPromise = audioElement.play();
                    
                    if (playPromise !== undefined) {
                        playPromise.then(() => {
                            console.log('✓ Son joué');
                        }).catch(error => {
                            console.log('✗ Erreur lecture audio:', error);
                        });
                    }
                } catch(e) {
                    console.log('✗ Exception audio:', e.message);
                }
            }

            function stopBlindAlert() {
                try {
                    audioElement.pause();
                    audioElement.currentTime = 0;
                    console.log('🔇 Audio arrêté');
                } catch(e) {
                    console.log('Error stopping audio:', e);
                }
            }

            function checkBlindeLevelChange() {
                try {
                    checkPauseStatus();
                    
                    if (pauseMode) {
                        stopBlindAlert();
                        return;
                    }
                    
                    var xhr = new XMLHttpRequest();
                    xhr.open("GET", "get-blindes-level.php", false);
                    xhr.send(null);
                    var currentBlindLevel = xhr.responseText.trim();
                    
                    if (lastBlindLevel === null) {
                        lastBlindLevel = currentBlindLevel;
                        sessionStorage.setItem('lastKnownBlindLevel', lastBlindLevel);
                        console.log('📍 Initial blind level:', lastBlindLevel);
                        return;
                    }
                    
                    if (currentBlindLevel !== lastBlindLevel) {
                        console.log('🚨 BLINDE LEVEL CHANGED! From:', lastBlindLevel, 'To:', currentBlindLevel);
                        var timeSinceLoad = Date.now() - pageLoadTime;
                        var stillInSilencePeriod = timeSinceLoad < silencePeriodMs;
                        
                        if (pauseMode || stillInSilencePeriod) {
                            stopBlindAlert();
                            if (pauseMode) {
                                console.log('⏸️ Mode pause - pas d\'alerte');
                            } else {
                                console.log('⏳ Silence period');
                            }
                        } else if (!isPageRefreshed) {
                            console.log('✅ Alerte sonore activée');
                            playBlindAlert();
                        }
                        
                        isPageRefreshed = false;
                        lastBlindLevel = currentBlindLevel;
                        sessionStorage.setItem('lastKnownBlindLevel', lastBlindLevel);
                    }
                } catch(e) {
                    console.log('Error checking blind level:', e);
                }
            }

            function compteur() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte, 250);
                }
            }

            function decompte() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur();
                    compteur2()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur2() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte2, 250);
                }
            }

            function decompte2() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur2();
                    compteur3()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur2() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur3() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte3, 250);
                }
            }

            function decompte3() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur3();
                    compteur4()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur3() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur4() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte4, 250);
                }
            }

            function decompte4() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur4();
                    compteur6()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur4() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur5() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte5, 250);
                }
            }

            function decompte5() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur5();
                    compteur5()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur5() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur6() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte6, 250);
                }
            }

            function decompte6() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur6();
                    stopcompteur4();
                    stopcompteur3();
                    stopcompteur2();
                    stopcompteur();
                    compteur6()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur6() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur7() {
                if (!nIntervId) {
                    nIntervId = setInterval(decompte7, 250);
                }
            }

            function decompte7() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") {
                    stopcompteur7();
                    compteur7()
                } else {
                    document.getElementById("response").innerHTML = responseText;
                }
            }

            function stopcompteur7() {
                clearInterval(nIntervId);
                nIntervId = null;
            }

            function compteur8() { nIntervId = setInterval(decompte8, 250); }
            function decompte8() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur8(); compteur9(); } else { document.getElementById("response").innerHTML = responseText; }
            }
            function stopcompteur8() { clearInterval(nIntervId); nIntervId = null; }
            
            function compteur9() { nIntervId = setInterval(decompte9, 250); }
            function decompte9() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur9(); compteur10(); } else { document.getElementById("response").innerHTML = responseText; }
            }
            function stopcompteur9() { clearInterval(nIntervId); nIntervId = null; }

            function compteur10() { nIntervId = setInterval(decompte10, 250); }
            function decompte10() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur10(); compteur11(); } else { document.getElementById("response").innerHTML = responseText; }
            }
            function stopcompteur10() { clearInterval(nIntervId); nIntervId = null; }

            function compteur11() { nIntervId = setInterval(decompte11, 250); }
            function decompte11() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur11(); compteur12(); } else { document.getElementById("response").innerHTML = responseText; }
            }
            function stopcompteur11() { clearInterval(nIntervId); nIntervId = null; }

            function compteur12() { nIntervId = setInterval(decompte12, 250); }
            function decompte12() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur12(); compteur13(); } else { document.getElementById("response").innerHTML = responseText; }
            }
            function stopcompteur12() { clearInterval(nIntervId); nIntervId = null; }

            function compteur13() { nIntervId = setInterval(decompte13, 250); }
            function decompte13() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur13(); compteur14(); } else { document.getElementById("response").innerHTML = document.getElementById("nom13").value + " + " + document.getElementById("ante13").value + " : " + responseText; }
            }
            function stopcompteur13() { clearInterval(nIntervId); nIntervId = null; }

            function compteur14() { nIntervId = setInterval(decompte14, 250); }
            function decompte14() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur14(); compteur15(); } else { document.getElementById("response").innerHTML = document.getElementById("nom14").value + " + " + document.getElementById("ante14").value + " : " + responseText; }
            }
            function stopcompteur14() { clearInterval(nIntervId); nIntervId = null; }

            function compteur15() { nIntervId = setInterval(decompte15, 250); }
            function decompte15() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur15(); compteur16(); } else { document.getElementById("response").innerHTML = document.getElementById("nom15").value + " + " + document.getElementById("ante15").value + " : " + responseText; }
            }
            function stopcompteur15() { clearInterval(nIntervId); nIntervId = null; }

            function compteur16() { nIntervId = setInterval(decompte16, 250); }
            function decompte16() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur16(); compteur17(); } else { document.getElementById("response").innerHTML = document.getElementById("nom16").value + " + " + document.getElementById("ante16").value + " : " + responseText; }
            }
            function stopcompteur16() { clearInterval(nIntervId); nIntervId = null; }

            function compteur17() { nIntervId = setInterval(decompte17, 250); }
            function decompte17() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur17(); compteur18(); } else { document.getElementById("response").innerHTML = document.getElementById("nom17").value + " + " + document.getElementById("ante17").value + " : " + responseText; }
            }
            function stopcompteur17() { clearInterval(nIntervId); nIntervId = null; }

            function compteur18() { nIntervId = setInterval(decompte18, 250); }
            function decompte18() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur18(); compteur19(); } else { document.getElementById("response").innerHTML = document.getElementById("nom18").value + " + " + document.getElementById("ante18").value + " : " + responseText; }
            }
            function stopcompteur18() { clearInterval(nIntervId); nIntervId = null; }

            function compteur19() { nIntervId = setInterval(decompte19, 250); }
            function decompte19() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur19(); compteur20(); } else { document.getElementById("response").innerHTML = document.getElementById("nom19").value + " + " + document.getElementById("ante19").value + " : " + responseText; }
            }
            function stopcompteur19() { clearInterval(nIntervId); nIntervId = null; }

            function compteur20() { nIntervId = setInterval(decompte20, 250); }
            function decompte20() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur20(); compteur21(); } else { document.getElementById("response").innerHTML = document.getElementById("nom20").value + " + " + document.getElementById("ante20").value + " : " + responseText; }
            }
            function stopcompteur20() { clearInterval(nIntervId); nIntervId = null; }

            function compteur21() { nIntervId = setInterval(decompte21, 250); }
            function decompte21() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur21(); compteur22(); } else { document.getElementById("response").innerHTML = document.getElementById("nom21").value + " + " + document.getElementById("ante21").value + " : " + responseText; }
            }
            function stopcompteur21() { clearInterval(nIntervId); nIntervId = null; }

            function compteur22() { nIntervId = setInterval(decompte22, 250); }
            function decompte22() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur22(); compteur23(); } else { document.getElementById("response").innerHTML = document.getElementById("nom22").value + " + " + document.getElementById("ante22").value + " : " + responseText; }
            }
            function stopcompteur22() { clearInterval(nIntervId); nIntervId = null; }

            function compteur23() { nIntervId = setInterval(decompte23, 250); }
            function decompte23() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur23(); compteur24(); } else { document.getElementById("response").innerHTML = document.getElementById("nom23").value + " + " + document.getElementById("ante23").value + " : " + responseText; }
            }
            function stopcompteur23() { clearInterval(nIntervId); nIntervId = null; }

            function compteur24() { nIntervId = setInterval(decompte24, 250); }
            function decompte24() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur24(); compteur25(); } else { document.getElementById("response").innerHTML = document.getElementById("nom24").value + " + " + document.getElementById("ante24").value + " : " + responseText; }
            }
            function stopcompteur24() { clearInterval(nIntervId); nIntervId = null; }

            function compteur25() { nIntervId = setInterval(decompte25, 250); }
            function decompte25() {
                checkBlindeLevelChange();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.open("GET", "response.php", false);
                xmlhttp.send(null);
                var responseText = xmlhttp.responseText.trim();
                if (responseText === "0") { stopcompteur25(); } else { document.getElementById("response").innerHTML = document.getElementById("nom25").value + " + " + document.getElementById("ante25").value + " : " + responseText; }
            }
            function stopcompteur25() { clearInterval(nIntervId); nIntervId = null; }

            function stopall() {
                stopcompteur(); stopcompteur2(); stopcompteur3(); stopcompteur4(); stopcompteur5(); stopcompteur6(); stopcompteur7(); stopcompteur8(); stopcompteur9(); stopcompteur10();
                stopcompteur11(); stopcompteur12(); stopcompteur13(); stopcompteur14(); stopcompteur15(); stopcompteur16(); stopcompteur17(); stopcompteur18(); stopcompteur19(); stopcompteur20();
                stopcompteur21(); stopcompteur22(); stopcompteur23(); stopcompteur24(); stopcompteur25();
            }

            stopall();
            compteur();
        </script>

        <div id="response" style="color: red; font-size: 160px; font-weight: normal; text-align: center; padding: 10px;"></div>

        <?php
    }
}
?>
