<?php
session_start();
error_reporting(0);
include('include/config.php');

// Vérification de session
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$id = intval($_GET['uid']);
$_SESSION["act"] = $id;

// --- LOGIQUE DE VÉRIFICATION DES MISES À JOUR (POLLING) ---
if (isset($_GET['check_updates'])) {
    header('Content-Type: application/json');
    
    // 1. Compte et Recaves des participants
    $q1 = mysqli_query($con, "SELECT COUNT(*) as nb, SUM(recave) as recaves FROM `participation` WHERE `id-activite` = '$id'");
    $d1 = mysqli_fetch_assoc($q1);
    
    // 2. Compte des éliminations
    $q2 = mysqli_query($con, "SELECT COUNT(*) as nb FROM `eliminations` WHERE `id_participation` IN (SELECT `id-participation` FROM `participation` WHERE `id-activite` = '$id')");
    $d2 = mysqli_fetch_assoc($q2);
    
    // 3. Classement (si quelqu'un est sorti)
    $q3 = mysqli_query($con, "SELECT SUM(classement) as sum_rank FROM `participation` WHERE `id-activite` = '$id'");
    $d3 = mysqli_fetch_assoc($q3);

    // Création d'une signature unique de l'état
    $checksum = md5($d1['nb'] . '-' . $d1['recaves'] . '-' . $d2['nb'] . '-' . $d3['sum_rank']);
    
    echo json_encode(['checksum' => $checksum]);
    exit;
}

// Récupération du titre de l'activité, de la date et des infos financières
$act_query = mysqli_query($con, "SELECT `titre-activite`, `buyin`, `recave_montant`, `date_depart`, `type` FROM `activite` WHERE `id-activite` = '$id'");
$act_row = mysqli_fetch_array($act_query);
$activity_title = $act_row['titre-activite'];
$buyin = intval($act_row['buyin']);
$recave_montant = intval($act_row['recave_montant']);
$activity_type = intval($act_row['type']);
$activity_date_simple = '';
if (!empty($act_row['date_depart'])) {
    $activity_date_simple = date('Y-m-d', strtotime($act_row['date_depart']));
}

// Permission bust : organisateur de la partie ou id=265
$current_user_id = intval($_SESSION['id']);
$org_q = mysqli_query($con, "SELECT `id-membre` FROM `activite` WHERE `id-activite` = '$id' LIMIT 1");
$org_row = mysqli_fetch_assoc($org_q);
$organizer_id = intval($org_row['id-membre']);
$can_bust = ($current_user_id === 265 || $current_user_id === $organizer_id);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <meta http-equiv="refresh" content="30"> Rafraîchissement géré par JS désormais -->
    <title>Joueurs - <?php echo htmlspecialchars($activity_title); ?></title>
    
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/card-bg.css">
    <!-- Chargement ResponsiveVoice pour garantir une voix féminine -->
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script>

    <style>
        /* Styles inspirés de fullscreen-cardevent.php */
        :root {
            --font-main: 'Raleway', sans-serif;
            --color-blue: #00d2ff;
            --color-red: #ff3333;
            --color-yellow: #ffc107;
        }

        body {
            background: #000000 !important;
            color: white;
            margin: 0;
            padding: 20px;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
            font-family: var(--font-main);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .header-title {
            text-align: center;
            color: var(--color-blue);
            text-transform: uppercase;
            font-weight: 700;
            font-size: 3vw;
            margin-bottom: 20px;
            text-shadow: 0 0 10px rgba(0, 210, 255, 0.5);
        }

        .content-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 0 50px;
            /* Scrollbar styling */
            scrollbar-width: thin;
            scrollbar-color: var(--color-blue) #333;
        }

        .content-wrapper::-webkit-scrollbar {
            width: 8px;
        }
        .content-wrapper::-webkit-scrollbar-track {
            background: #333;
        }
        .content-wrapper::-webkit-scrollbar-thumb {
            background-color: var(--color-blue);
            border-radius: 4px;
        }

        /* Table Styling */
        .player-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 1px;
        }

        .player-table thead th {
            color: #aaa;
            font-weight: 600;
            text-transform: uppercase;
            padding: 8px 5px;
            vertical-align: middle;
            font-size: 2vw;
            border-bottom: 2px solid #444;
            text-align: left;
        }

        .player-table tbody tr {
            background: rgba(255, 255, 255, 0.05);
            transition: transform 0.2s, background 0.2s;
            border-radius: 10px;
        }
        
        /* Astuce pour border-radius sur tr */
        .player-table tbody tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .player-table tbody tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        .player-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.01);
        }

        .player-table tbody tr.eliminated {
            background: rgba(255, 0, 0, 0.15);
            opacity: 0.7;
        }

        .player-table td {
            padding: 0px 4px;
            font-size: 3vw;
            vertical-align: middle;
            line-height: 1;
        }

        .rank-cell {
            font-weight: bold;
            color: var(--color-yellow);
            width: 80px;
            text-align: center;
            font-size: 3.2vw !important;
        }

        .name-cell {
            font-weight: 700;
            color: white;
        }

        .eliminated .name-cell {
            /* text-decoration: line-through; */
            color: #ff6666;
        }

        .info-cell {
            text-align: center;
            color: #ccc;
            font-size: 2.2vw !important;
        }
        
        /* Style pour la colonne Sorti(e) Par */
        .player-table td:nth-child(4) {
            font-size: 2.5vw;
            color: #aaa;
        }

        .action-cell {
            width: 60px;
            text-align: center;
        }

        .btn-delete {
            background: rgba(255, 51, 51, 0.1);
            border: 1px solid #ff3333;
            color: #ff3333;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-delete:hover {
            background: #ff3333;
            color: white;
            transform: scale(1.1);
        }
        
        .eliminated .btn-delete {
            opacity: 0.3;
            cursor: not-allowed;
            pointer-events: none;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 1vw;
            font-weight: bold;
            text-transform: uppercase;
            vertical-align: middle;
        }
        
        .status-active {
            background-color: rgba(76, 209, 55, 0.2);
            color: #4cd137;
            border: 1px solid #4cd137;
        }
        
        .status-out {
            background-color: rgba(255, 51, 51, 0.2);
            color: #ff3333;
            border: 1px solid #ff3333;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            opacity: 0.3;
            font-size: 24px;
            z-index: 100;
            transition: opacity 0.3s;
        }
        .back-btn:hover { opacity: 1; color: var(--color-blue); }

        /* Bouton micro flottant */
        #voiceMicBtn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6c63ff, #a855f7);
            border: none;
            cursor: pointer;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            box-shadow: 0 4px 20px rgba(108,99,255,.5);
            transition: transform .15s;
        }
        #voiceMicBtn:hover { transform: scale(1.1); }
        #voiceMicBtn.listening {
            background: linear-gradient(135deg, #ef4444, #f97316);
            animation: voicePulse 1.1s infinite;
        }
        @keyframes voicePulse {
            0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.6); }
            70%  { box-shadow: 0 0 0 16px rgba(239,68,68,0); }
            100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
        }
        #voiceToast {
            position: fixed;
            bottom: 105px;
            right: 30px;
            background: rgba(20,20,30,.95);
            border: 1px solid #6c63ff;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: .85rem;
            color: #fff;
            max-width: 280px;
            text-align: center;
            display: none;
            z-index: 9999;
            white-space: pre-line;
        }
        #voiceToast.error { border-color: #ef4444; color: #fca5a5; }
        #voiceToast.success { border-color: #4ade80; color: #86efac; }

        /* Bouton Annuler dernière élimination */
        #undoElimBtn {
            position: fixed;
            bottom: 30px;
            right: 108px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #374151, #6b7280);
            border: none;
            cursor: pointer;
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            box-shadow: 0 4px 16px rgba(0,0,0,.5);
            transition: transform .15s, background .2s;
            opacity: 0.5;
        }
        #undoElimBtn:hover { transform: scale(1.1); opacity: 1; background: linear-gradient(135deg, #dc2626, #ef4444); }
        #undoElimBtn.has-elim { opacity: 1; background: linear-gradient(135deg, #b45309, #f59e0b); }

        /* Stats summary at bottom */
        .stats-footer {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 50px;
            font-size: 2.5vw;
            color: #888;
            border-top: 1px solid #333;
            padding-top: 20px;
        }
        .stat-item strong {
            color: white;
        }

    </style>
</head>
<body>
    <a href="livetimer.php?uid=<?php echo $id; ?>" class="back-btn"><i class="fa fa-arrow-left"></i> Retour</a>

    <div class="header-title">
        <a href="fullscreen-cardevent.php?uid=<?php echo $id; ?>" style="color:inherit; text-decoration:none; cursor:pointer;"><?php echo htmlspecialchars($activity_title); ?></a> <span style="color: white; opacity: 0.5;">
    </div>

    <div class="content-wrapper">
        <table class="player-table">
            <thead>
                <tr>
                    <th style="text-align: center;">#</th>
                    <th>Joueur</th>
                    <th style="text-align: center; width: 7%;">Bounty</th>
                    <th style="text-align: center; width: 7%;">Recaves</th>
                    <th>Sorti(e) Par</th>
                    <th style="text-align: center;"><?php echo ($activity_type == 2) ? 'Jetons' : 'Ticket Tombola'; ?></th>
                    <th style="text-align: center;">Bust</th>
                </tr>
            </thead>
            <tbody id="joueurs-list">
                <?php
                // Requête identique à voir-blindes.php pour la cohérence
                $req = mysqli_query($con, "SELECT p.* FROM `participation` p WHERE p.`id-activite` = '$id' ORDER BY (p.`classement` = 0 OR p.`classement` IS NULL) DESC, p.`classement` ASC, p.`nom-membre` ASC");
                
                $rankingCounter = 1;
                $totalPlayers = 0;
                $activePlayers = 0;
                $totalRecaves = 0;
                
                while ($row = mysqli_fetch_array($req)) {
                    $totalPlayers++;
                    $totalRecaves += intval($row['recave']);
                    
                    // Récupération ID membre et éventuelle phonétique (comme dans voir-blindes.php)
                    $membre_id = 0;
                    $membre_phonetique = '';
                    $pseudo_clean = mysqli_real_escape_string($con, $row['nom-membre']);
                    $mq = mysqli_query($con, "SELECT `id-membre`, `phonetique` FROM `membres` WHERE `pseudo` = '$pseudo_clean' LIMIT 1");
                    if ($mq && mysqli_num_rows($mq) > 0) {
                        $mr = mysqli_fetch_array($mq);
                        $membre_id = intval($mr['id-membre']);
                        if (isset($mr['phonetique']) && $mr['phonetique'] !== '') {
                            $membre_phonetique = $mr['phonetique'];
                        }
                    }

                    // Compter le nombre de joueurs éliminés par ce pseudo (Bounty)
                    $elimCount = 0;
                    $countElimQuery = mysqli_query($con, "SELECT COUNT(*) as cnt FROM `eliminations` e JOIN `participation` p ON e.`id_participation` = p.`id-participation` WHERE p.`id-activite` = '$id' AND e.`nom_membre` = '" . mysqli_real_escape_string($con, $row['nom-membre']) . "'");
                    if ($countElimQuery) {
                        $countElimRow = mysqli_fetch_array($countElimQuery);
                        $elimCount = intval($countElimRow['cnt']);
                    }

                    // Vérification élimination
                    $isEliminated = false;
                    $eliminatorsList = array();
                    
                    // On récupère TOUTES les éliminations pour afficher l'historique
                    $elim_q = mysqli_query($con, "SELECT * FROM `eliminations` WHERE `id_participation` = '" . intval($row['id-participation']) . "' ORDER BY created_at ASC");
                    
                    while ($er = mysqli_fetch_array($elim_q)) {
                        $eliminatorsList[] = $er['nom_membre'];
                        
                        // Si une des éliminations est définitive, le joueur est OUT
                        if (intval($er['is_definitive']) === 1) {
                            $isEliminated = true;
                        }
                    }

                    if (!$isEliminated) {
                        $activePlayers++;
                    }

                    // Récupération du ou des tickets de tombola / Jetons selon le type d'activité
                    $columnDisplay = '-';
                    
                    if ($activity_type == 2 || $activity_type == 3) {
                        // Type 2 ou 3 : afficher les jetons depuis membres.jetons_1 ou jetons_2
                        $jetons_column = ($activity_type == 3) ? 'jetons_2' : 'jetons_1';
                        if ($membre_id > 0) {
                            $jetons_sql = mysqli_query(
                                $con,
                                "SELECT `" . $jetons_column . "` FROM `membres` WHERE `id-membre` = " . intval($membre_id)
                            );
                            if ($jetons_sql && mysqli_num_rows($jetons_sql) > 0) {
                                $jetons_row = mysqli_fetch_array($jetons_sql);
                                $jetons_value = intval($jetons_row[$jetons_column]);
                                if ($jetons_value > 0) {
                                    $columnDisplay = number_format($jetons_value, 0, ',', ' ');
                                }
                            }
                        }
                    } else {
                        // Type par défaut : afficher les tickets de tombola
                        $ticketCodes = array();
                        if ($membre_id > 0 && !empty($activity_date_simple)) {
                            $dateEscaped = mysqli_real_escape_string($con, $activity_date_simple);
                            $ticket_sql = mysqli_query(
                                $con,
                                "SELECT c.`nom` AS qrcode
                                 FROM `collections-individu` ci
                                 JOIN `collections` c ON ci.`id_col` = c.`id_collection`
                                 WHERE ci.`id-indiv` = '" . intval($membre_id) . "'
                                   AND DATE(ci.`date`) = '" . $dateEscaped . "'
                                   AND (ci.`aff_rake` = 0 OR ci.`aff_rake` IS NULL)"
                            );
                            if ($ticket_sql) {
                                while ($trow = mysqli_fetch_array($ticket_sql)) {
                                    if (!empty($trow['qrcode'])) {
                                        $ticketCodes[] = $trow['qrcode'];
                                    }
                                }
                            }
                        }
                        
                        if (!empty($ticketCodes)) {
                            $columnDisplay = htmlspecialchars(implode(', ', $ticketCodes));
                        }
                    }

                    $rowClass = $isEliminated ? 'eliminated' : '';

                    // Affichage du rang
                    if (!$isEliminated) {
                        $rankDisplay = $rankingCounter;
                        $rankingCounter++;
                    } else {
                         // Si éliminé, on affiche son classement final s'il existe, sinon une croix
                         if($row['classement'] > 0) $rankDisplay = $row['classement'];
                         else $rankDisplay = '<i class="fa fa-times"></i>';
                    }

                    echo '<tr class="' . $rowClass . '" 
                              data-id="' . intval($row['id-participation']) . '" 
                              data-member-id="' . $membre_id . '" 
                              data-pseudo="' . htmlspecialchars($row['nom-membre'], ENT_QUOTES) . '" 
                              data-phonetic="' . htmlspecialchars($membre_phonetique, ENT_QUOTES) . '">';
                    
                    echo '<td class="rank-cell">' . $rankDisplay . '</td>';
                    echo '<td class="name-cell">' . htmlspecialchars($row['nom-membre']) . '</td>';
                    
                    // Colonne Bounty
                    echo '<td style="text-align: center; color: #ffffff; font-weight: bold; width: 7%;">' . ($elimCount > 0 ? $elimCount : '<span style="opacity:0.3">-</span>') . '</td>';

                    // Colonne Recaves
                    echo '<td style="text-align: center; color: #ffffff; font-weight: bold; width: 7%;">' . ($row['recave'] > 0 ? $row['recave'] : '<span style="opacity:0.3">-</span>') . '</td>';
                    
                    // Colonne Sorti(e) Par
                    echo '<td>';
                    if (!empty($eliminatorsList)) {
                         // On affiche la liste séparée par des virgules
                         echo '<span class="eliminated-by">' . htmlspecialchars(implode(', ', $eliminatorsList)) . '</span>';
                    } else {
                         echo '<span class="eliminated-by"></span>';
                    }
                    echo '</td>';

                    // Colonne Ticket Tombola ou Jetons
                    echo '<td class="info-cell" style="text-align:center;">' . $columnDisplay . '</td>';
                    
                    // Colonne Actions
                        echo '<td class="action-cell">';
                        if ($can_bust) {
                            echo '<button class="btn-delete" onclick="confirmDeletePlayer(this)" 
                                data-id="' . intval($row['id-participation']) . '" 
                                data-member-id="' . $membre_id . '" 
                                data-name="' . htmlspecialchars($row['nom-membre'], ENT_QUOTES) . '"
                                data-activity-id="' . $id . '"
                                data-phonetic="' . htmlspecialchars($membre_phonetique, ENT_QUOTES) . '">
                                <i class="fa fa-sign-out"></i>
                              </button>';
                        }
                    echo '</td>';
                    
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <div class="stats-footer">
        <div class="stat-item">Joueurs: <strong><?php echo $activePlayers; ?> / <?php echo $totalPlayers; ?></strong></div>
        <div class="stat-item">Total Recaves: <strong><?php echo $totalRecaves; ?></strong></div>
        <?php 
            $pricepool = ($totalPlayers * $buyin) + ($totalRecaves * $recave_montant);
        ?>
        <div class="stat-item">Pricepool: <strong><?php echo number_format($pricepool, 0, ',', ' '); ?> €</strong></div>
    </div>

    <!-- Bouton Annuler dernière élimination -->
    <button id="undoElimBtn" title="Annuler la dernière élimination" onclick="undoLastElimination()">↩️</button>
    <!-- Bouton micro flottant -->
    <button id="voiceMicBtn" title="Commande vocale (ex: élimine Jean)" onclick="toggleVoiceMic()">🎙️</button>
    <div id="voiceToast"></div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>

    <script>
        // --- LOGIQUE DE SUPPRESSION (Adaptée de voir-blindes.js) ---

        // Indique si une élimination (avec sirène/voix) est en cours
        var eliminationInProgress = false;

        // Fonction utilitaire pour parler avec une voix féminine (plus lente)
        function speakWithFemaleVoice(text) {
            // Priorité : ResponsiveVoice en French Female si disponible
            if (typeof responsiveVoice !== 'undefined') {
                try {
                    responsiveVoice.speak(text, 'French Female', { rate: 0.85 });
                    return;
                } catch (e) {
                    console.warn('responsiveVoice speak error, fallback Web Speech', e);
                }
            }

            // Fallback : API Web Speech native en français, un peu plus lente
            if ('speechSynthesis' in window) {
                var msg = new SpeechSynthesisUtterance(text);
                msg.lang = 'fr-FR';
                msg.rate = 0.85; // plus lent que la vitesse normale

                // Essayer de trouver une voix féminine
                var voices = window.speechSynthesis.getVoices();
                var femaleVoice = voices.find(function(v) {
                    return v.lang && v.lang.toLowerCase().startsWith('fr') &&
                           (v.name.includes('Female') || v.name.includes('Hortense') || v.name.includes('Julie') || v.name.includes('Amélie'));
                });

                if (femaleVoice) {
                    msg.voice = femaleVoice;
                }

                window.speechSynthesis.speak(msg);
            }
        }

        // Lecture d'un fichier MP3 de sirène avant le message vocal pour un joueur spécial
        function playSirenThenSpeak(text) {
            try {
            //    var audio = new Audio('https://btones.b-cdn.net/fetch/ba/ba07b0ca3055c9b15f5f08033b8548e0.mp3');
                var audio = new Audio('/panel/sounds/mission.mp3');

                audio.addEventListener('ended', function() {
                    speakWithFemaleVoice(text);
                });

                audio.play().catch(function(err) {
                    console.warn('Lecture sirène échouée, fallback voix directe', err);
                    speakWithFemaleVoice(text);
                });
            } catch (e) {
                console.warn('Erreur initialisation sirène, fallback voix directe', e);
                speakWithFemaleVoice(text);
            }
        }

        function speakWithSirenIfNeeded(memberIdElimine, text) {
            if (memberIdElimine === '1100' || memberIdElimine === 1100 ||
                memberIdElimine === '1103' || memberIdElimine === 1103) {
                playSirenThenSpeak(text);
            } else {
                speakWithFemaleVoice(text);
            }
        }

        // Récupérer le nom phonétique pour un joueur à partir de l'ID de participation
        function getPhoneticNameByParticipationId(participationId, fallbackName) {
            var row = document.querySelector('#joueurs-list tr[data-id="' + participationId + '"]');
            if (!row) return fallbackName;
            var phon = row.getAttribute('data-phonetic');
            if (phon && phon.trim() !== '') return phon;
            return fallbackName;
        }

        // Récupérer l'ID membre à partir de l'ID de participation
        function getMemberIdByParticipationId(participationId) {
            var row = document.querySelector('#joueurs-list tr[data-id="' + participationId + '"]');
            if (!row) return null;
            var mid = row.getAttribute('data-member-id');
            return mid;
        }

        // Récupérer le nom phonétique pour un joueur à partir de l'ID membre
        function getPhoneticNameByMemberId(memberId, fallbackName) {
            var row = document.querySelector('#joueurs-list tr[data-member-id="' + memberId + '"]');
            if (!row) return fallbackName;
            var phon = row.getAttribute('data-phonetic');
            if (phon && phon.trim() !== '') return phon;
            return fallbackName;
        }

        // Action du bouton POUBELLE / SORTIE
        window.confirmDeletePlayer = function(button) {
            var participationId = button.getAttribute('data-id');
            var memberId        = button.getAttribute('data-member-id');
            var name            = button.getAttribute('data-name');
            var activityId      = button.getAttribute('data-activity-id');
            openEliminationModal(participationId, name, activityId);
        };

        window.openEliminationModal = function(victimParticipationId, victimName, activityId) {
            console.log("[Modale] Ouverture pour éliminer: " + victimName);
            
            // Nettoyage ancienne modale
            var oldModal = document.querySelector('.elimination-modal-overlay');
            if(oldModal) oldModal.remove();

            var rows = document.querySelectorAll('#joueurs-list tr');
            var options = '<option value="" data-member-id="">-- Sélectionner un joueur --</option>';
            var countPlayers = 0;

            rows.forEach(function (r) {
                var partId = r.getAttribute('data-id');
                var membreId = r.getAttribute('data-member-id');
                var pseudo = r.getAttribute('data-pseudo');
                
                if (!partId || !pseudo) return;
                
                // On ne peut pas s'éliminer soi-même
                if (String(partId) === String(victimParticipationId)) return; 
                
                // FILTRE : On ne propose que les joueurs EN JEUX (pas éliminés)
                if (r.classList.contains('eliminated')) return;

                options += '<option value="' + pseudo + '" data-member-id="' + membreId + '">' + pseudo + '</option>';
                countPlayers++;
            });
            console.log(" -> Joueurs disponibles pour éliminer: " + countPlayers);

            var overlay = document.createElement('div');
            overlay.className = 'elimination-modal-overlay';
            overlay.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.8);display:flex;align-items:center;justify-content:center;z-index:99999; color: black;';
            
            overlay.innerHTML = `
                <div style="background:#fff;padding:20px;border-radius:10px;min-width:400px;box-shadow:0 0 30px rgba(0,0,0,0.8);">
                    <h3 style="margin:0 0 15px; color: #333;">Sortie de <strong>${victimName}</strong></h3>
                    <p style="margin-bottom: 5px; font-weight: bold;">Qui l'a éliminé ?</p>
                    <select id="eliminatorSelect" class="form-control" style="width:100%; height: 50px; padding:10px; margin-bottom:15px; font-size:16px; color: #333; background-color: #fff;">${options}</select>
                    
                    <div style="margin-top:12px;padding:15px;border:1px solid #ddd;border-radius:4px;background-color:#f9f9f9;">
                        <label style="display:flex;align-items:center;margin:0;cursor:pointer;">
                            <input type="checkbox" id="definitiveElimination" style="margin-right:10px;transform:scale(1.5);cursor:pointer;" />
                            <span style="color:red; font-size:16px; font-weight:bold;">Éliminé définitivement (OUT)</span>
                        </label>
                    </div>

                    <div style="text-align:right;margin-top:20px;">
                        <button class="btn btn-secondary" id="elimCancel">Annuler</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);

            overlay.querySelector('#elimCancel').onclick = function () {
                document.body.removeChild(overlay);
            };

            // Auto-confirme dès qu'un joueur est sélectionné
            overlay.querySelector('#eliminatorSelect').onchange = function () {
                var select = this;
                var eliminatorName = select.value;
                if (eliminatorName === "") return;
                var selectedOption = select.options[select.selectedIndex];
                var eliminatorMemberId = selectedOption.getAttribute('data-member-id');
                var isDefinitive = overlay.querySelector('#definitiveElimination').checked;
                document.body.removeChild(overlay);
                applyElimination(victimParticipationId, eliminatorMemberId, eliminatorName, isDefinitive, activityId, victimName);
            };
        };

        window.applyElimination = function(victimParticipationId, eliminatorMemberId, eliminatorName, isDefinitiveElim, activityId, victimName) {
            console.log("%c[Process] Application de l'élimination...", "color: purple; font-weight: bold;");
            // Bloquer le reload/polling pendant la séquence audio
            window.eliminationInProgress = true;
            
            var markAsEliminatedUI = function() {
                console.log(" -> Mise à jour UI");
                var rows = document.querySelectorAll('#joueurs-list tr');
                rows.forEach(function (r) {
                    if (String(r.getAttribute('data-id')) === String(victimParticipationId)) {
                        // Ajouter classe eliminated (style visuel uniquement)
                        r.classList.add('eliminated');

                        // Mettre à jour la colonne "Sorti(e) Par" (4ème colonne)
                        var eliminatedByCell = r.querySelector('td:nth-child(4)');
                        if (eliminatedByCell) {
                             var currentText = eliminatedByCell.innerText.trim();
                             var newContent = currentText ? currentText + ', ' + eliminatorName : eliminatorName;
                             eliminatedByCell.innerHTML = '<span class="eliminated-by">' + newContent + '</span>';
                        }
                        
                        // Désactiver le bouton
                        var btn = r.querySelector('.btn-delete');
                        if (btn) {
                            btn.style.opacity = '0.3';
                            btn.style.pointerEvents = 'none';
                        }
                    }
                });
            };

            var executeElimination = function() {
                var finalizeElimination = function() {
                    if (isDefinitiveElim) {
                        markAsEliminatedUI();
                    }

                    console.log(" -> Envoi AJAX record_elimination.php");
                    
                    $.ajax({
                        url: 'record_elimination.php',
                        type: 'POST',
                        data: {
                            victim_id: victimParticipationId,
                            eliminator_id: eliminatorMemberId,
                            eliminator_name: eliminatorName,
                            is_definitive: isDefinitiveElim ? 1 : 0,
                            activity_id: activityId
                        },
                        dataType: 'json',
                        success: function (resp) {
                            if (resp && resp.status === 'success') {
                                // Recharger pour mettre à jour les stats et l'ordre
                                // On laisse le temps au MP3 de sirène + voix de se jouer
                                setTimeout(function() { location.reload(); }, 13000);
                            } else {
                                console.error('[Élimination] Erreur:', resp ? resp.message : 'Réponse vide');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('[AJAX Error]', error);
                        }
                    });
                };

                if (isDefinitiveElim) {
                    // Calcul du classement
                    var totalJoueurs = document.querySelectorAll('#joueurs-list tr').length;
                    var dejaElimines = document.querySelectorAll('#joueurs-list tr.eliminated').length;
                    
                    // Si le joueur n'était pas déjà marqué comme éliminé, on l'ajoute au compte
                    // (Mais ici on calcule AVANT de le marquer visuellement, donc c'est bon)
                    
                    var rangCalcule = totalJoueurs - dejaElimines;
                    console.log(" -> Rang calculé: " + rangCalcule);
                    
                    // On envoie le classement
                    $.ajax({
                        url: 'update_recave.php',
                        type: 'POST',
                        data: {
                            updates: JSON.stringify([]),
                            classements: JSON.stringify([{
                                'id-participation': victimParticipationId,
                                'classement': rangCalcule
                            }])
                        },
                        dataType: 'json',
                        success: function(response) {
                            // Message vocal : Elimination définitive avec classement
                            var suffixe = (rangCalcule == 1) ? "ère" : "ème";
                            var victimVoiceName = getPhoneticNameByParticipationId(victimParticipationId, victimName);
                            var eliminatorVoiceName = getPhoneticNameByMemberId(eliminatorMemberId, eliminatorName);

                            // Message de base identique à celui de la recave
                            var messageBase = eliminatorVoiceName + " a éliminé " + victimVoiceName ;
                            var texteVocal = messageBase + ", " + victimVoiceName + " finit en " + rangCalcule + suffixe + " position";
                            var victimMemberId = getMemberIdByParticipationId(victimParticipationId);
                            speakWithSirenIfNeeded(victimMemberId, texteVocal);
                            
                            finalizeElimination();
                        },
                        error: function(xhr, status, error) {
                            console.error("Erreur sauvegarde classement:", error);
                            finalizeElimination();
                        }
                    });
                } else {
                    // Si PAS définitif (Recave)
                    console.log(" -> Recave détectée, incrémentation...");
                    
                    // 1. Trouver la valeur actuelle de recave
                    var currentRecave = 0;
                    var rows = document.querySelectorAll('#joueurs-list tr');
                    rows.forEach(function (r) {
                        if (String(r.getAttribute('data-id')) === String(victimParticipationId)) {
                            // On cherche la cellule recave (4ème colonne maintenant, car Bounty ajouté en 3ème)
                            var recaveCell = r.querySelector('td:nth-child(4)');
                            if (recaveCell) {
                                var val = parseInt(recaveCell.innerText);
                                if (!isNaN(val)) currentRecave = val;
                            }
                        }
                    });
                    
                    var newRecave = currentRecave + 1;
                    console.log(" -> Recave: " + currentRecave + " => " + newRecave);

                    // 2. Mettre à jour la recave via AJAX
                    $.ajax({
                        url: 'update_recave.php',
                        type: 'POST',
                        data: {
                            updates: JSON.stringify([{
                                'id-participation': victimParticipationId,
                                'recave': newRecave
                            }]),
                            classements: JSON.stringify([])
                        },
                        dataType: 'json',
                        success: function(response) {
                            console.log(" -> Recave mise à jour.");
                            
                            // Mise à jour UI immédiate pour la colonne "Sorti(e) Par"
                            var rows = document.querySelectorAll('#joueurs-list tr');
                            rows.forEach(function (r) {
                                if (String(r.getAttribute('data-id')) === String(victimParticipationId)) {
                                    var eliminatedByCell = r.querySelector('td:nth-child(4)');
                                    if (eliminatedByCell) {
                                         var currentText = eliminatedByCell.innerText.trim();
                                         var newContent = currentText ? currentText + ', ' + eliminatorName : eliminatorName;
                                         eliminatedByCell.innerHTML = '<span class="eliminated-by">' + newContent + '</span>';
                                    }
                                }
                            });

                            // Message vocal : Qui a éliminé qui (en utilisant les noms phonétiques si disponibles)
                            var victimVoiceName2 = getPhoneticNameByParticipationId(victimParticipationId, victimName);
                            var eliminatorVoiceName2 = getPhoneticNameByMemberId(eliminatorMemberId, eliminatorName);
                            var victimMemberId2 = getMemberIdByParticipationId(victimParticipationId);
                            var messageBase2;
                            // Si le joueur éliminé est l'un des deux joueurs spéciaux, on renforce le message
                            if (victimMemberId2 === '1100' || victimMemberId2 === 1100 ||
                                victimMemberId2 === '1103' || victimMemberId2 === 1103) {
                                messageBase2 = eliminatorVoiceName2 + " a éliminé " + victimVoiceName2 +
                                    " et oui vous avez bien entendu " + eliminatorVoiceName2 + " a éliminé " + victimVoiceName2;
                            } else {
                                messageBase2 = eliminatorVoiceName2 + " a éliminé " + victimVoiceName2;
                            }
                            var texteVocal2 = messageBase2;
                            speakWithSirenIfNeeded(victimMemberId2, texteVocal2);

                            finalizeElimination();
                        },
                        error: function(xhr, status, error) {
                            console.error("Erreur mise à jour recave:", error);
                            finalizeElimination();
                        }
                    });
                }
            };

            executeElimination();
        };

        // --- ANNULER DERNIÈRE ÉLIMINATION ---
        window.undoLastElimination = function() {
            $.ajax({
                url: 'undo_elimination.php',
                type: 'POST',
                data: { activity_id: <?php echo $id; ?> },
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 'success') {
                        speakWithFemaleVoice('Annulé');
                        setTimeout(function(){ location.reload(); }, 1200);
                    } else {
                        console.error('[Undo] Erreur:', resp ? resp.message : 'Réponse vide');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[Undo AJAX]', error);
                }
            });
        };

        // --- AUTO-REFRESH INTELLIGENT ---
        $(document).ready(function() {
            var currentChecksum = null;
            var activityId = "<?php echo $id; ?>";

            // Fonction de vérification
            function checkUpdates() {
                // Si une élimination avec son est en cours, on ne déclenche pas de reload
                if (window.eliminationInProgress) {
                    return;
                }
                $.ajax({
                    url: 'fullscreen-player.php',
                    type: 'GET',
                    data: { 
                        uid: activityId, 
                        check_updates: 1 
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data && data.checksum) {
                            if (currentChecksum === null) {
                                currentChecksum = data.checksum;
                            } else if (currentChecksum !== data.checksum) {
                                console.log("Changement détecté ! Rechargement...");
                                location.reload();
                            }
                        }
                    },
                    error: function(err) {
                        console.warn("Erreur polling updates", err);
                    }
                });
            }

            // Premier appel pour initialiser
            checkUpdates();

            // Vérification toutes les 5 secondes
            setInterval(checkUpdates, 5000);
        });
    </script>
    
    <!-- ═══════════════════════════════════════════════════════════
         RECONNAISSANCE VOCALE – Élimination joueur
         Commandes : "élimine Jean", "bust Jean", "sortie Jean"
                     "recave Jean", "annule"
    ════════════════════════════════════════════════════════════════ -->
    <script>
    (function() {
        var voiceRecognition = null;
        var voiceListening   = false;
        var voiceActivityId  = "<?php echo $id; ?>";
        var voiceToastTimer  = null;

        // ── Mots-clés déclencheurs ─────────────────────────────────────────
        var KEYWORDS_ELIM   = ['élimine','elimine','éliminé','eliminé','bust','sorti','sortie','out'];
        var KEYWORDS_RECAVE = ['recave','rebuy','re-buy'];

        // ── Afficher un toast ──────────────────────────────────────────────
        function voiceShowToast(msg, type, duration) {
            duration = duration || 3000;
            var t = document.getElementById('voiceToast');
            t.textContent = msg;
            t.className   = type || '';
            t.style.display = 'block';
            clearTimeout(voiceToastTimer);
            voiceToastTimer = setTimeout(function(){ t.style.display = 'none'; }, duration);
        }

        // ── Normaliser une chaîne (minuscules, sans accents) ───────────────
        function normalize(str) {
            return str.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9\s]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        // ── Distance de Levenshtein ──────────────────────────────────────────
        function levenshtein(a, b) {
            var m = a.length, n = b.length;
            var dp = [];
            for (var i = 0; i <= m; i++) { dp[i] = [i]; }
            for (var j = 0; j <= n; j++) { dp[0][j] = j; }
            for (var i = 1; i <= m; i++) {
                for (var j = 1; j <= n; j++) {
                    dp[i][j] = (a[i-1] === b[j-1])
                        ? dp[i-1][j-1]
                        : 1 + Math.min(dp[i-1][j], dp[i][j-1], dp[i-1][j-1]);
                }
            }
            return dp[m][n];
        }

        // ── Score combiné : mots communs + Levenshtein ─────────────────────
        function scoreMatch(query, candidate) {
            var nq = normalize(query);
            var nc = normalize(candidate);
            if (!nq || !nc) return 0;
            // Correspondance exacte
            if (nq === nc) return 1;
            // Le candidat est contenu dans la requête
            if (nq.indexOf(nc) !== -1) return 0.95;
            // Mots en commun
            var wq = nq.split(' '), wc = nc.split(' ');
            var common = wq.filter(function(w){ return wc.indexOf(w) !== -1; }).length;
            var wordScore = common / Math.max(wq.length, wc.length);
            // Levenshtein sur le nom complet (normalisé)
            var maxLen = Math.max(nq.length, nc.length);
            var levScore = maxLen > 0 ? 1 - levenshtein(nq, nc) / maxLen : 0;
            // Levenshtein sur le premier mot du candidat seul (prénom)
            var firstWord = wc[0] || nc;
            var maxLen2 = Math.max(nq.length, firstWord.length);
            var levFirst = maxLen2 > 0 ? 1 - levenshtein(nq, firstWord) / maxLen2 : 0;
            return Math.max(wordScore, levScore * 0.8, levFirst * 0.9);
        }

        // ── Trouver le joueur le plus proche (pseudo OU phonétique) ────────
        function findPlayer(name) {
            var rows = document.querySelectorAll('#joueurs-list tr:not(.eliminated)');
            var best = null, bestScore = 0;

            rows.forEach(function(r) {
                var pseudo   = r.getAttribute('data-pseudo')   || '';
                var phonetic = r.getAttribute('data-phonetic') || '';
                var s1 = scoreMatch(name, pseudo);
                var s2 = phonetic ? scoreMatch(name, phonetic) : 0;
                var score = Math.max(s1, s2);
                if (score > bestScore) { bestScore = score; best = r; }
            });

            console.log('[Voice] findPlayer("' + name + '") → best=' + (best ? best.getAttribute('data-pseudo') : 'null') + ' score=' + bestScore.toFixed(2));
            return (bestScore >= 0.35) ? best : null;
        }

        // ── Suffixes définitif / recave (liste des mots à détecter) ──────────
        var SUFFIX_DEFINITIVE = ['definitivement','definitif','definitive','def'];
        var SUFFIX_RECAVE     = ['qui recave','rebuy','re-buy','recave'];

        // ── Parser la commande vocale ──────────────────────────────────────
        // Formes supportées :
        //   "annule"                            → annule la dernière élimination
        //   "[A] élimine [B] définitivement"   → direct, sans modale
        //   "[A] élimine [B] qui recave"        → direct, sans modale
        //   "[A] élimine [B]"                   → modale pré-remplie (A sélectionné, OUT coché)
        //   "élimine [B]"                       → modale (OUT coché)
        //   "recave [B]"                        → modale (OUT décoché)
        function parseVoiceCommand(texte) {
            var norm = normalize(texte);

            // Commande annulation
            var annuleWords = ['annule','annuler','annulation','undo','retour','efface'];
            for (var ai = 0; ai < annuleWords.length; ai++) {
                if (norm === annuleWords[ai] || norm.indexOf(annuleWords[ai]) !== -1) {
                    voiceShowToast('↩️ Annulation…', '', 2000);
                    undoLastElimination();
                    return;
                }
            }
            console.log('[Voice] normalize:', norm);

            // ── ÉTAPE 1 : détecter et retirer le suffixe définitif/recave
            //    sur TOUTE la phrase normalisée, AVANT de chercher les noms.
            //    On teste les plus longs en premier pour éviter les collisions
            //    (ex: "qui recave" avant "recave").
            var isDefinitive = null; // null = pas de suffixe détecté → modale
            var cleanNorm    = norm;

            var allSuffixes = [
                { words: SUFFIX_DEFINITIVE, val: true  },
                { words: SUFFIX_RECAVE,     val: false }
            ];

            outer:
            for (var si = 0; si < allSuffixes.length; si++) {
                var group = allSuffixes[si];
                for (var sw = 0; sw < group.words.length; sw++) {
                    var nw  = normalize(group.words[sw]);
                    var pos = cleanNorm.indexOf(nw);
                    if (pos !== -1) {
                        isDefinitive = group.val;
                        // Retirer ce mot du texte de travail
                        cleanNorm = (cleanNorm.substring(0, pos) + ' ' + cleanNorm.substring(pos + nw.length)).replace(/\s+/g, ' ').trim();
                        console.log('[Voice] suffixe détecté:', group.words[sw], '→ isDefinitive=', isDefinitive, '| cleanNorm:', cleanNorm);
                        break outer;
                    }
                }
            }

            // ── ÉTAPE 2 : chercher le verbe d'action dans la phrase nettoyée
            var eliminatorPart = null;
            var victimPart     = null;
            var actionType     = null;

            for (var i = 0; i < KEYWORDS_ELIM.length; i++) {
                var kw  = normalize(KEYWORDS_ELIM[i]);
                var idx = cleanNorm.indexOf(kw);
                if (idx !== -1) {
                    actionType     = 'elim';
                    eliminatorPart = cleanNorm.substring(0, idx).trim() || null;
                    victimPart     = cleanNorm.substring(idx + kw.length).trim() || null;
                    break;
                }
            }

            if (!actionType) {
                for (var j = 0; j < KEYWORDS_RECAVE.length; j++) {
                    var kw2  = normalize(KEYWORDS_RECAVE[j]);
                    var idx2 = cleanNorm.indexOf(kw2);
                    if (idx2 !== -1) {
                        actionType   = 'recave';
                        isDefinitive = false; // recave = forcément non-définitif
                        victimPart   = cleanNorm.substring(idx2 + kw2.length).trim() || null;
                        break;
                    }
                }
            }

            console.log('[Voice] action:', actionType, '| elim:', eliminatorPart, '| victim:', victimPart, '| definitive:', isDefinitive);

            if (!actionType) return; // aucune commande reconnue

            if (!victimPart) {
                voiceShowToast('🎙️ Précisez le nom du joueur\nEx: "Pikachu élimine Manon définitivement"', 'error');
                return;
            }

            // ── ÉTAPE 3 : trouver la victime parmi les joueurs en jeu
            var victimRow = findPlayer(victimPart);
            if (!victimRow) {
                voiceShowToast('❓ Joueur non trouvé : "' + victimPart + '"\n(joueurs en jeu seulement)', 'error');
                return;
            }
            var victimId   = victimRow.getAttribute('data-id');
            var victimName = victimRow.getAttribute('data-pseudo');

            // ── ÉTAPE 4 : éliminateur trouvé → DIRECT sans modale
            if (eliminatorPart) {
                var elimRow = findPlayer(eliminatorPart);
                if (elimRow) {
                    var elimMemberId = elimRow.getAttribute('data-member-id');
                    var elimPseudo   = elimRow.getAttribute('data-pseudo');
                    var label        = isDefinitive ? '💀 OUT' : '♻️ Recave';
                    voiceShowToast(label + '\n' + elimPseudo + ' → ' + victimName, 'success', 2500);
                    setTimeout(function() {
                        applyElimination(victimId, elimMemberId, elimPseudo, (isDefinitive === true), voiceActivityId, victimName);
                    }, 600);
                    return;
                }
            }

            // ── ÉTAPE 5 : pas d'éliminateur ou inconnu → exécution directe sans modale
            var isDefFinal = (isDefinitive === true);
            var labelFinal = isDefFinal ? '💀 OUT' : '♻️ Recave';
            voiceShowToast(labelFinal + ' → ' + victimName, 'success', 2500);
            setTimeout(function() {
                applyElimination(victimId, '', '', isDefFinal, voiceActivityId, victimName);
            }, 600);
        }

        // ── Init Web Speech API ────────────────────────────────────────────
        function initVoice() {
            var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                voiceShowToast('⚠️ Reconnais. vocale non dispo\n(Chrome/Edge requis)', 'error', 5000);
                return false;
            }
            voiceRecognition = new SR();
            voiceRecognition.lang           = 'fr-FR';
            voiceRecognition.continuous     = false;
            voiceRecognition.interimResults = true;

            voiceRecognition.onstart = function() {
                voiceListening = true;
                document.getElementById('voiceMicBtn').classList.add('listening');
                voiceShowToast('🔴 Écoute…\n"élimine [nom]" / "recave [nom]"', '', 8000);
            };

            voiceRecognition.onresult = function(e) {
                var interim = '';
                var final   = '';
                for (var i = e.resultIndex; i < e.results.length; i++) {
                    if (e.results[i].isFinal) {
                        final += e.results[i][0].transcript;
                    } else {
                        interim += e.results[i][0].transcript;
                    }
                }
                // Affiche en temps réel ce qui est entendu
                if (interim) voiceShowToast('🎙️ ' + interim + '…', '', 5000);
                if (final) {
                    console.log('[Voice] Final :', final);
                    voiceShowToast('🎙️ "' + final + '"', '', 2000);
                    parseVoiceCommand(final);
                }
            };

            voiceRecognition.onerror = function(e) {
                if (e.error === 'no-speech') {
                    // Redémarre automatiquement si pas de son
                    voiceShowToast('🔴 Écoute…\n(pas de son, réessayez)', '', 5000);
                    try { voiceRecognition.start(); } catch(ex) {}
                    return;
                }
                var msgs = {
                    'not-allowed' : '🚫 Micro refusé – Autorisez l\'accès',
                    'network'     : '🌐 Erreur réseau',
                };
                voiceShowToast(msgs[e.error] || ('Erreur : ' + e.error), 'error');
                setVoiceIdle();
            };

            voiceRecognition.onend = function() {
                // Ne pas marquer idle si on est encore censé écouter (redémarrage auto)
                if (!voiceListening) return;
                setVoiceIdle();
            };
            return true;
        }

        function setVoiceIdle() {
            voiceListening = false;
            var btn = document.getElementById('voiceMicBtn');
            if (btn) btn.classList.remove('listening');
        }

        // ── Toggle micro (exposé globalement) ─────────────────────────────
        window.toggleVoiceMic = function() {
            if (!voiceRecognition && !initVoice()) return;
            if (voiceListening) {
                voiceRecognition.stop();
            } else {
                try { voiceRecognition.start(); }
                catch(e) { voiceRecognition = null; initVoice(); voiceRecognition.start(); }
            }
        };

    })();
    </script>

    <!-- Card Background Script -->
    <script src="assets/js/card-bg.js"></script>
    <script>
        jQuery(document).ready(function () {
            // Initialize card background
            if (window.CardBackground) {
                window.CardBackground.init({
                    spacing: 60,
                    rowHeight: 80,
                    fontSize: 60,
                    opacity: 0.05,
                    alternateColors: true,
                    colors: { even: 'white', odd: 'red' },
                    suits: ['♠','♣','♥','♦'],
                    staggerCycle: 4
                });
            }
        });
    </script>
</body>
</html>
