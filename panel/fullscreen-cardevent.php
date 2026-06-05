<?php
session_start();
error_reporting(0);
include('include/config.php');
// Vérification de session

// Vérification de session
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

// Vérifier les droits admin
$user_id = $_SESSION['id'];
$user_query = mysqli_query($con, "SELECT droits FROM membres WHERE `id-membre` = " . intval($user_id));
$user_row = mysqli_fetch_array($user_query);
$is_admin = (intval($user_row['droits']) == 2);

$id = intval($_GET['uid']);
$_SESSION["act"] = $id;
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';
$resetBlindsUrl = '/panel/creation-blindes.php?zero=1&act=' . $id . '&sou=' . rawurlencode($currentUrl);

// --- CALCUL DES STATS JOUEURS (STACK MOYEN & JOUEURS RESTANTS) ---
$act_query = mysqli_query($con, "SELECT jetons, jetons_activite, recave_jetons FROM activite WHERE `id-activite` = '$id'");
$act_row = mysqli_fetch_array($act_query);
// Utilise jetons_activite (moyenne calculée) si disponible, sinon jetons (initial)
$start_chips = intval($act_row['jetons_activite'] ?? $act_row['jetons']);
$rebuy_chips = intval($act_row['recave_jetons']);

$part_query = mysqli_query($con, "SELECT `id-participation`, `recave`, `addon` FROM `participation` WHERE `id-activite` = '$id'");
$total_players = 0;
$total_rebuys = 0;
$total_addons = 0;
$active_players = 0;

while ($row = mysqli_fetch_array($part_query)) {
    $total_players++;
    $total_rebuys += intval($row['recave']);
    $total_addons += intval($row['addon']);
    
    $pid = $row['id-participation'];
    // Check definitive elimination
    $elim_query = mysqli_query($con, "SELECT is_definitive FROM eliminations WHERE id_participation = '$pid' AND is_definitive = 1");
    if (mysqli_num_rows($elim_query) == 0) {
        $active_players++;
    }
}

// On suppose que l'Addon donne le même montant que la Recave (à défaut d'info contraire)
$total_chips = ($total_players * $start_chips) + ($total_rebuys * $rebuy_chips) + ($total_addons * $rebuy_chips);
$avg_stack = ($active_players > 0) ? floor($total_chips / $active_players) : 0;
// ---------------------------------------------------------------

// --- LOGIQUE PHP ---
if (isset($_POST['moins'])) { ?> <script>window.location.replace("/panel/modif-horloge.php?act=<?php echo $id ?>&min=-2&sou=/panel/fullscreen-cardevent.php?uid=");</script> <?php }
if (isset($_POST['plus'])) { ?> <script>window.location.replace("/panel/modif-horloge.php?act=<?php echo $id ?>&min=+2&sou=/panel/fullscreen-cardevent.php?uid=");</script> <?php }
if (isset($_POST['pauseresume'])) {
    $check_pause = mysqli_query($con, "SELECT `en_pause` FROM `blindes-live` WHERE `id-activite` = '$id' LIMIT 1");
    $row_pause = mysqli_fetch_array($check_pause);
    if (intval($row_pause['en_pause']) == 0) { ?> <script>window.location.replace("/panel/en-pause.php?act=<?php echo $id ?>&sou=/panel/fullscreen-cardevent.php?uid=");</script> <?php } 
    else { ?> <script>window.location.replace("/panel/de-pause.php?act=<?php echo $id ?>&sou=/panel/fullscreen-cardevent.php?uid=");</script> <?php }
}
if (isset($_POST['next_blind']) || isset($_POST['prev_blind']) || isset($_POST['reset_blind'])) {
    $now = time();
    
    // 1. On récupère toutes les blindes
    $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
    $blinds = [];
    while($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; }
    
    // 2. On cherche l'index de la blinde ACTUELLE
    $currentIndex = -1;
    foreach($blinds as $k => $b) { 
        if (strtotime($b['fin']) > $now) { 
            $currentIndex = $k; 
            break; 
        }
    }
    
    // 3. On détermine la cible (Target)
    $targetIndex = $currentIndex;

    if (isset($_POST['next_blind'])) {
        // Si pas fini et qu'il reste des niveaux après
        if ($currentIndex != -1 && $currentIndex < count($blinds) - 1) {
            $targetIndex = $currentIndex + 1;
        }
    }
    
    if (isset($_POST['prev_blind'])) {
        if ($currentIndex == -1) {
            // Si fini, "Précédent" réactive le dernier niveau
            $targetIndex = count($blinds) - 1;
        } elseif ($currentIndex > 0) {
            $targetIndex = $currentIndex - 1;
        } else {
            $targetIndex = 0; // Sécurité
        }
    }
    
    if (isset($_POST['reset_blind'])) {
        if ($currentIndex == -1) {
            // Si fini, "Reset" réactive le dernier niveau
            $targetIndex = count($blinds) - 1;
        } else {
            $targetIndex = $currentIndex;
        }
    }

    // 4. MISE A JOUR DE LA BASE DE DONNEES
    if ($targetIndex >= 0 && $targetIndex < count($blinds)) {
        $runningTime = time();
        
        // IMPORTANT : On force la fin du niveau précédent pour éviter les chevauchements
        if ($targetIndex > 0) {
            $prevId = $blinds[$targetIndex - 1]['id'];
            $sql_prev_end = date("Y-m-d H:i:s", $runningTime);
            // On met à jour la fin du niveau d'avant à "Maintenant"
            mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$sql_prev_end' WHERE `id` = '$prevId'");
        }

        // On décale le niveau cible et tous les suivants
        for ($i = $targetIndex; $i < count($blinds); $i++) {
            // Durée théorique du niveau (ou 20min par défaut si erreur)
            $duree = strtotime($blinds[$i]['fin']) - strtotime($blinds[$i]['debut']);
            if ($duree <= 0) $duree = 20 * 60; 
            
            $newStart = $runningTime;
            $newEnd = $runningTime + $duree;
            
            $u_id = $blinds[$i]['id'];
            $sql_s = date("Y-m-d H:i:s", $newStart);
            $sql_e = date("Y-m-d H:i:s", $newEnd);
            
            mysqli_query($con, "UPDATE `blindes-live` SET `debut` = '$sql_s', `fin` = '$sql_e' WHERE `id` = '$u_id'");
            
            // Le prochain niveau commencera quand celui-ci finit
            $runningTime = $newEnd;
        }
    }
    
    header("Location: fullscreen-cardevent.php?uid=$id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timer Fullscreen</title>
    
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700|Raleway:300,400,700" rel="stylesheet">
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script>
    <script>
        // Fallback iOS amélioré (V4 - Debug & Specific Names)
        if (typeof responsiveVoice !== 'undefined') {
            responsiveVoice.OnVoiceReady = function() {
                var voice = "French Female"; // Par défaut
                var voices = responsiveVoice.getVoices();
                var debugMsg = "Voices found: " + voices.length;
                
                var foundFemale = false;
                var foundAmelie = null;
                var foundThomas = null;
                var foundFrench = null;
                
                for (var i = 0; i < voices.length; i++) {
                    var v = voices[i];
                    var name = v.name || "";
                    var lang = v.lang || "";
                    
                    if (name === "French Female") foundFemale = true;
                    if (name.indexOf("Amelie") !== -1) foundAmelie = name;
                    if (name.indexOf("Thomas") !== -1) foundThomas = name;
                    
                    if (!foundFrench && (lang.indexOf("fr") === 0 || name.indexOf("French") !== -1)) {
                        foundFrench = name;
                    }
                }
                
                if (foundFemale) voice = "French Female";
                else if (foundAmelie) voice = foundAmelie;
                else if (foundFrench) voice = foundFrench;
                else if (foundThomas) voice = foundThomas;
                
                console.log("Default Voice set to: " + voice);
                responsiveVoice.setDefaultVoice(voice);
            };
        }
    </script>

    <style>
        /* ==========================================================================
           REGLAGES DES TAILLES
           ========================================================================== */
        :root {
            --size-clock: 20vw;    /* HORLOGE (Rouge) */
            --size-blinds: 10vw;   /* BLINDES (Jaune) */
            --size-message: 3vw;
            --size-estim: 2vw;
            
            /* --- CONFIGURATION LIGNE PAUSE --- */
            --size-pause: 5vmin;     /* Taille du texte */
            --color-pause: #ff0000; /* Couleur (rouge) */
            --font-pause: 'Raleway', sans-serif; /* Police */
        }

        body {
            background-color: #1a1a1a;
            color: white;
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            font-family: 'Raleway', sans-serif;
            position: relative;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            opacity: 0.1;
            z-index: -1;
        }

        .cardevent-container {
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* --- CIBLAGE PRECIS DES ELEMENTS DE L'HORLOGE --- */
        
        /* 1. L'HEURE (ID défini dans horloge-heure.php) */
        /* #cardevent-display {
            font-size: var(--size-clock) !important;
            color: #ff3333 !important; 
            line-height: 1 !important;
            font-weight: bold;
            text-shadow: 0 0 40px rgba(255, 0, 0, 0.4);
        } */
        
        /* Style spécifique quand en pause (ajouté par JS) */
        /* #cardevent-display.paused {
            color: orange !important;
        } */

        /* 2. LES BLINDES (ID défini dans horloge-heure.php) */
        /* #level-info {
            font-size: var(--size-blinds) !important;
            color: #ffc107 !important; 
            line-height: 1.2 !important;
            font-weight: bold;
            margin-top: 10px;
        } */
        
        /* Style pour les Ante (si présents) */
        /* .ante-text {
            color: #4a90e2 !important; 
            font-size: 0.8em; 
        } */

        /* 3. MESSAGES */
        #zone-message {
            font-size: var(--size-message) !important;
            color: #fcfcfaff !important;
            margin-top: 20px;
            min-height: 1.2em;
        }

        /* 4. LIGNE PAUSE (Ajout) */
        #car-pause {
            font-size: var(--size-pause) !important;
            color: var(--color-pause) !important;
            font-family: var(--font-pause) !important;
            margin-top: 15px;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        #zone-estim {
            font-size: var(--size-estim) !important;
            color: #ff0000 !important; /* Rouge pour l'heure de pause */
            margin-top: 5px;
        }

        /* CONTROLES */
        .controls-area {
            margin-top: 30px;
            background: transparent;
            padding: 20px;
            border-radius: 15px;
            width: 50%;
            max-width: 800px;
        }
        .controls-area .btn { font-size: 16px !important; padding: 8px; }
        .btn-block { font-weight: bold; text-transform: uppercase; margin-bottom: 5px; }
        .btn-primaryg { background-color: #4a90e2; color: white; border: none; }
        .btn-primary-rouge { background-color: #e74c3c; color: white; border: none; }
        
        .back-btn { position: absolute; top: 20px; left: 20px; opacity: 0.3; transition: opacity 0.3s; z-index: 999; }
        .back-btn:hover { opacity: 1; }
        
        /* Style pour l'heure en haut à droite */
        .top-right-clock {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 66px;
            color: white;
            opacity: 0.5;
            font-weight: bold;
            z-index: 999;
        }

        /* Effet au survol du conteneur (Identique à horloge-heure.php) */
        .cardevent-circle-container:hover #cardevent-display {
            transform: scale(1.1); /* Agrandissement */
            cursor: pointer;
        }

        /* Overlay pour les boutons -2/+2 */
        .cardevent-buttons-overlay {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 70vmin;
            height: 70vmin;
            pointer-events: none;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            z-index: 100;
            padding-bottom: 10px; /* Petit espacement du bas */
        }
        
        @media (orientation: portrait) {
            .cardevent-buttons-overlay {
                width: 90vmin;
                height: 90vmin;
            }
        }

        .cardevent-control-btn {
            pointer-events: auto;
            background-color: #00d2ff; /* Même bleu que le cercle */
            color: #1a1a1a; /* Texte foncé pour contraste */
            border: none;
            font-weight: bold;
            border-radius: 50px;
            padding: 5px 15px;
            font-size: 2vmin;
            box-shadow: 0 0 10px rgba(0, 210, 255, 0.5);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .cardevent-control-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 15px rgba(0, 210, 255, 0.8);
            color: white;
        }
    </style>
</head>
<body>

    <a href="voir-blindes.php?uid=<?php echo $id; ?>" class="back-btn">
        <img src="assets/images/logo.png" alt="Retour Admin" style="height: 100px;">
    </a>
    
    <!-- Heure en haut à droite -->
    <?php if ($is_admin) { ?>
    <a href="<?php echo htmlspecialchars($resetBlindsUrl, ENT_QUOTES, 'UTF-8'); ?>" 
       class="top-right-clock" 
       id="real-time-clock"
       onclick="return confirm('Êtes-vous sûr de vouloir réinitialiser les blindes ?');"
       style="cursor: pointer; text-decoration: none; color: white; opacity: 1;">
        Il est --:--
    </a>
    <?php } else { ?>
    <div class="top-right-clock" 
         id="real-time-clock"
         style="cursor: not-allowed; text-decoration: none; color: white; opacity: 0.5;"
         title="Seuls les administrateurs peuvent réinitialiser les blindes">
        Il est --:--
    </div>
    <?php } ?>
    
    <div class="cardevent-container">
        
        <!-- ZONE HORLOGE & BLINDES -->
        <div id="zone-clock-container" style="position: relative;">
            <?php include('horloge-heure.php'); ?>
            
            <!-- Boutons -2 / +2 positionnés sur le cercle -->
            <form method="post" class="cardevent-buttons-overlay">
                <button type="submit" name="moins" class="cardevent-control-btn"><i class="fa fa-minus"></i> 2</button>
                <button type="submit" name="plus" class="cardevent-control-btn"><i class="fa fa-plus"></i> 2</button>
            </form>
        </div>

        <?php if (isset($_SESSION['id']) && (intval($_SESSION['id']) == 265 || intval($_SESSION['id']) == 1)): ?>
        <div id="choix-partie" style="margin-top: 10px; z-index: 2001; background: rgba(0,0,0,0.7); padding: 5px; border-radius: 5px;">
            <select class="form-control" style="background: #222; color: white; border: 1px solid #4a90e2; width: auto; display: inline-block; font-size: 1.5vmin; height: auto;" onchange="if(this.value) window.top.location.href='fullscreen.php?uid=' + this.value">
                <option value="">-- Changer de partie active --</option>
                <?php
                $now_sql = date("Y-m-d");
                $q_active = mysqli_query($con, "SELECT `id-activite`, `titre-activite`, `date_depart` FROM `activite` WHERE `date_depart` >= DATE_SUB('$now_sql', INTERVAL 5 DAY) ORDER BY `date_depart` DESC, `id-activite` DESC LIMIT 20");
                if (!$q_active) {
                    echo '<option value="">Erreur SQL: ' . mysqli_error($con) . '</option>';
                } else if (mysqli_num_rows($q_active) == 0) {
                    echo '<option value="">Aucune partie trouvée (5j)</option>';
                } else {
                    while ($g = mysqli_fetch_array($q_active)) {
                        $selected = ($g['id-activite'] == $id) ? "selected" : "";
                        $dateDisp = date('d/m', strtotime($g['date_depart']));
                        echo '<option value="' . $g['id-activite'] . '" ' . $selected . '>' . htmlspecialchars($g['titre-activite']) . ' (' . $dateDisp . ')</option>';
                    }
                }
                ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- ZONE MESSAGE -->
        <!-- <div id="zone-message">
            <div id="car-pause"></div>
        </div> -->

        <!-- ZONE STATS JOUEURS -->
        <div id="zone-stats" style="font-size: calc(3vw + 4px) !important; color: rgba(11, 245, 65, 0.83); margin-top: 20px; font-weight: bold; position: relative; z-index: 2000;">
            <?php echo $active_players; ?> <a href="fullscreen-player.php?uid=<?php echo $id; ?>" style="color:white; text-decoration:underline; cursor:pointer;">Joueurs</a> / <?php echo $total_players; ?> &nbsp;, &nbsp; <span style="color:white">Stack M. </span> <strong id="stack-value" style="color:#ff0000; font-size: calc(3vw + 4px); cursor: pointer;" onclick="announceStack()" title="Cliquer pour annoncer le stack moyen"><?php echo number_format($avg_stack, 0, ',', ' '); ?></strong>
        </div>

        <!-- ZONE ESTIMATION -->
        <!-- <div id="zone-estim">
            <?php include('horloge-estim.php'); ?>
            <div style="color:inherit"></div>
        </div> -->

        <!-- CONTROLES (Supprimés car déplacés sur le cercle) -->
        <!-- <div class="controls-area"></div> -->

        <!-- Remplacer l'include de la pause par ce bloc complet -->
        <!-- <div style="display: flex; justify-content: center; align-items: center; gap: 30px; margin-top: 20px;">
            Wrapper pour le décompte 
            <div id="wrapper-pause" style="color:white ; font-size: 50px ; text-align: center; white-space: nowrap;">
                <?php include('car-pause.php'); ?>
            </div>
            
             Wrapper pour l'heure estimée 
            <div id="estim-pause" style="color:#00ff00 ; font-size: 50px ; text-align: center; font-weight: bold;"></div>
        </div>
 -->
        <!-- <script>
            setInterval(function() {
                var text = "";
                var wrapper = document.getElementById('wrapper-pause');
                
                // 1. Récupérer le texte visible
                if (wrapper) {
                    text = wrapper.innerText || wrapper.textContent;
                }
                
                // Si le wrapper est vide, chercher par ID spécifique potentiel
                if (!text || text.trim() === "") {
                    var el = document.getElementById('car-pause') || document.getElementById('cardevent-pause');
                    if (el) text = el.innerText || el.textContent;
                }

                var ep = document.getElementById('estim-pause');

                if(text && ep) {
                    // Nettoyage
                    text = text.trim();
                    
                    var totalSec = -1;
                    
                    // Regex pour MM:SS ou HH:MM:SS avec espaces optionnels
                    var matchTime = text.match(/(\d+)\s*:\s*(\d+)(?:\s*:\s*(\d+))?/);
                    
                    if (matchTime) {
                        if (matchTime[3]) {
                            // H:M:S
                            totalSec = parseInt(matchTime[1]) * 3600 + parseInt(matchTime[2]) * 60 + parseInt(matchTime[3]);
                        } else {
                            // M:S
                            totalSec = parseInt(matchTime[1]) * 60 + parseInt(matchTime[2]);
                        }
                    } else {
                        // Regex pour "XX min"
                        var matchMin = text.match(/(\d+)\s*min/i);
                        if (matchMin) {
                            totalSec = parseInt(matchMin[1]) * 60;
                        }
                    }

                    if (totalSec >= 0 && totalSec < 86400) {
                        var now = new Date();
                        var estim = new Date(now.getTime() + (totalSec+900) * 1000);
                        
                        var h = estim.getHours();
                        var m = estim.getMinutes();
                        if(m < 10) m = '0' + m;
                        
                        ep.innerText = "vers " + h + 'h' + m;
                    } else {
                        ep.innerText = ""; 
                    }
                }
            }, 1000);
        </script> -->
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
        function updateRealTimeClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            document.getElementById('real-time-clock').innerText = `${hours}:${minutes}`;
        }
        
        // Update immediately and then every second (to be accurate when minute changes)
        updateRealTimeClock();
        setInterval(updateRealTimeClock, 1000);

        // --- ANNONCE VOCALE DU STACK MOYEN ---
        var lastAnnouncedStack = null;
        var lastStackCheckTime = 0;
        
        function announceStack() {
            var stackElement = document.getElementById('stack-value');
            if (!stackElement) return;
            
            // Récupérer la valeur numérique (sans espaces)
            var stackText = stackElement.innerText.replace(/\s/g, '');
            var stackValue = parseInt(stackText);
            
            if (isNaN(stackValue) || stackValue <= 0) return;
            
            // Formater le nombre pour l'annonce vocale
            var announcement = "Stack moyen : " + stackValue.toLocaleString('fr-FR');
            
            // Utiliser ResponsiveVoice si disponible
            if (typeof responsiveVoice !== 'undefined') {
                responsiveVoice.speak(announcement, "French Female", {
                    rate: 0.9,
                    pitch: 1,
                    volume: 1
                });
            } else {
                // Fallback sur l'API native si ResponsiveVoice n'est pas disponible
                if ('speechSynthesis' in window) {
                    var msg = new SpeechSynthesisUtterance(announcement);
                    msg.lang = 'fr-FR';
                    msg.rate = 0.9;
                    window.speechSynthesis.speak(msg);
                }
            }
            
            lastAnnouncedStack = stackValue;
            console.log('Stack moyen annoncé : ' + stackValue);
        }
        
        // Vérification automatique toutes les 30 secondes
        setInterval(function() {
            var stackElement = document.getElementById('stack-value');
            if (!stackElement) return;
            
            // Ne pas annoncer si on est en pause
            var timerDisplay = document.getElementById('cardevent-display');
            if (timerDisplay && timerDisplay.classList.contains('paused')) return;
            
            var stackText = stackElement.innerText.replace(/\s/g, '');
            var currentStack = parseInt(stackText);
            
            if (isNaN(currentStack) || currentStack <= 0) return;
            
            // Annoncer si le stack a changé significativement (plus de 10%)
            if (lastAnnouncedStack === null || Math.abs(currentStack - lastAnnouncedStack) / lastAnnouncedStack > 0.1) {
                var now = Date.now();
                // Éviter les annonces trop rapprochées (minimum 60 secondes)
                if (now - lastStackCheckTime > 60000) {
                    announceStack();
                    lastStackCheckTime = now;
                }
            }
        }, 30000);
    </script>

</body>
</html>