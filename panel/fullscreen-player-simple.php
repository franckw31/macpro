<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// Vérification de session
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$id = intval($_GET['uid']);
$_SESSION["act"] = $id;

// Derniere elimination (pour diffusion audio sur plusieurs navigateurs)
if (isset($_GET['last_elimination'])) {
    header('Content-Type: application/json');

    $latest_q = mysqli_query($con, "SELECT
            e.id,
            e.id_participation,
            e.id_membre,
            e.nom_membre,
            e.id_membre_victime,
            e.nom_membre_victime,
            e.is_definitive,
            e.created_at,
            p.classement,
            m1.phonetique AS eliminant_phonetique,
            m2.phonetique AS victime_phonetique
        FROM eliminations e
        LEFT JOIN participation p ON e.id_participation = p.`id-participation`
        LEFT JOIN membres m1 ON e.id_membre = m1.`id-membre`
        LEFT JOIN membres m2 ON e.id_membre_victime = m2.`id-membre`
        WHERE e.id_activite = $id
        ORDER BY e.created_at DESC, e.id DESC
        LIMIT 1");

    if ($latest_q && mysqli_num_rows($latest_q) > 0) {
        $latest = mysqli_fetch_assoc($latest_q);
        echo json_encode([
            'id' => $latest['id'],
            'id_membre' => $latest['id_membre'],
            'eliminant_name' => $latest['nom_membre'],
            'eliminant_phonetic' => $latest['eliminant_phonetique'],
            'id_membre_victime' => $latest['id_membre_victime'],
            'victime_name' => $latest['nom_membre_victime'],
            'victime_phonetic' => $latest['victime_phonetique'],
            'is_definitive' => $latest['is_definitive'],
            'classement' => $latest['classement'],
            'created_at' => $latest['created_at']
        ]);
    } else {
        echo json_encode(['id' => null]);
    }
    exit;
}

// Traitement annulation dernière élimination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_derniere'])) {
    try {
        // Trouver la dernière élimination de cette activité
        $q_derniere = mysqli_query($con, "SELECT e.id, e.id_participation, e.is_definitive FROM eliminations e 
                                          WHERE e.id_activite = $id 
                                          ORDER BY e.created_at DESC LIMIT 1");
        
        if (mysqli_num_rows($q_derniere) > 0) {
            $derniere = mysqli_fetch_array($q_derniere);
            $elim_id = intval($derniere['id']);
            $participation_id = intval($derniere['id_participation']);
            $is_definitive = intval($derniere['is_definitive']);
            
            // Supprimer l'élimination
            $delete_result = mysqli_query($con, "DELETE FROM eliminations WHERE id = $elim_id");
            
            if ($delete_result) {
                if ($is_definitive == 1) {
                    // Réinitialiser classement et vainqueur
                    mysqli_query($con, "UPDATE participation SET 
                        classement = 0,
                        `nom-membre-vainqueur` = '',
                        `id-membre-vainqueur` = 0
                        WHERE `id-participation` = $participation_id");
                } else {
                    // Réduire recave et réinitialiser vainqueur
                    mysqli_query($con, "UPDATE participation SET 
                        recave = GREATEST(0, recave - 1),
                        `nom-membre-vainqueur` = '',
                        `id-membre-vainqueur` = 0
                        WHERE `id-participation` = $participation_id");
                }
                $_SESSION['msg'] = 'Dernière élimination annulée';
            } else {
                $_SESSION['msg'] = 'Erreur lors de l\'annulation: ' . mysqli_error($con);
            }
        } else {
            $_SESSION['msg'] = 'Aucune élimination à annuler';
        }
    } catch (Exception $e) {
        $_SESSION['msg'] = 'Exception: ' . $e->getMessage();
    }
    
    header("Location: fullscreen-player-simple.php?uid=" . $id);
    exit;
}

// Traitement de l'élimination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminer'])) {
    try {
        $joueur_elimine = intval($_POST['id_joueur_elimine']);
        $joueur_eliminant = intval($_POST['id_joueur_eliminant']);
        $type_elimination = mysqli_real_escape_string($con, $_POST['type_elimination']); // 'definitive' ou 'recave'
        
        if ($joueur_elimine == 0 || $joueur_eliminant == 0) {
            $_SESSION['msg'] = 'Erreur: Veuillez sélectionner les deux joueurs';
            header("Location: fullscreen-player-simple.php?uid=" . $id);
            exit;
        }
        
        // Récupérer les informations du joueur éliminant
        $q_eliminant = mysqli_query($con, "SELECT m.`id-membre`, m.pseudo FROM participation p 
                                            LEFT JOIN membres m ON p.`id-membre` = m.`id-membre` 
                                            WHERE p.`id-participation` = $joueur_eliminant");
        $eliminant_data = mysqli_fetch_array($q_eliminant);
        $eliminant_membre_id = intval($eliminant_data['id-membre']);
        $eliminant_nom = mysqli_real_escape_string($con, $eliminant_data['pseudo']);
        
        // Récupérer les informations du joueur éliminé (victime)
        $q = mysqli_query($con, "SELECT p.`id-participation`, p.`id-membre`, p.`nom-membre` 
                                  FROM `participation` p 
                                  WHERE p.`id-participation` = $joueur_elimine AND p.`id-activite` = $id");
        
        if (!$q) {
            $_SESSION['msg'] = 'Erreur SQL SELECT: ' . mysqli_error($con);
            header("Location: fullscreen-player-simple.php?uid=" . $id);
            exit;
        }
        
        if (mysqli_num_rows($q) > 0) {
            $row = mysqli_fetch_array($q);
            $participation_id = $row['id-participation'];
            $victime_membre_id = intval($row['id-membre']);
            $victime_nom = mysqli_real_escape_string($con, $row['nom-membre']);
            
            if ($type_elimination === 'definitive') {
                // Enregistrer l'élimination définitive dans la table eliminations avec toutes les colonnes
                $sql_elim = "INSERT INTO `eliminations` 
                    (id_participation, id_membre, nom_membre, id_membre_victime, nom_membre_victime, is_definitive, id_activite, created_at) 
                    VALUES 
                    ($participation_id, $eliminant_membre_id, '$eliminant_nom', $victime_membre_id, '$victime_nom', 1, $id, NOW())";
                
                error_log("DEBUG - SQL DEFINITIVE: " . $sql_elim);
                $insert_elim = mysqli_query($con, $sql_elim);
                
                if (!$insert_elim) {
                    $_SESSION['msg'] = 'Erreur SQL INSERT elimination: ' . mysqli_error($con);
                    error_log("DEBUG - INSERT FAILED: " . mysqli_error($con));
                } else {
                    error_log("DEBUG - INSERT SUCCESS");
                    // Calculer le classement
                    $count_total_query = mysqli_query($con, "SELECT COUNT(*) as total FROM `participation` WHERE `id-activite` = '$id'");
                    $row_total = mysqli_fetch_assoc($count_total_query);
                    $total_joueurs = intval($row_total['total']);

                    $count_elim_query = mysqli_query($con, "SELECT COUNT(*) as elimines FROM `participation` WHERE `id-activite` = '$id' AND `classement` > 0 AND `id-participation` != '$participation_id'");
                    $row_elim = mysqli_fetch_assoc($count_elim_query);
                    $nb_deja_elimines = intval($row_elim['elimines']);

                    $classement = $total_joueurs - $nb_deja_elimines;
                    if ($classement < 1) $classement = 1;
                    
                    // Mettre à jour avec classement et sorti(e) par
                    $update_result = mysqli_query($con, "UPDATE participation SET 
                        classement = $classement,
                        `nom-membre-vainqueur` = '$eliminant_nom',
                        `id-membre-vainqueur` = $eliminant_membre_id
                        WHERE `id-participation` = $participation_id");
                    
                    if (!$update_result) {
                        $_SESSION['msg'] = 'Erreur SQL UPDATE classement: ' . mysqli_error($con);
                    } else {
                        // Si ce joueur est classé 2e, attribuer 1er au dernier joueur encore non classé (le vainqueur)
                        if ($classement == 2) {
                            $winner_q = mysqli_query($con, "SELECT `id-participation` FROM `participation` WHERE `id-activite` = '$id' AND `classement` = 0 LIMIT 1");
                            if ($winner_q && mysqli_num_rows($winner_q) > 0) {
                                $winner_row = mysqli_fetch_assoc($winner_q);
                                $winner_participation_id = intval($winner_row['id-participation']);
                                mysqli_query($con, "UPDATE `participation` SET `classement` = 1 WHERE `id-participation` = $winner_participation_id");
                            }
                        }

                        $_SESSION['msg'] = 'Élimination définitive enregistrée (Classé '.$classement.'e)';
                    }
                }
            } else {
                // Enregistrer une recave (élimination non définitive) dans la table eliminations
                $sql_recave = "INSERT INTO `eliminations` 
                    (id_participation, id_membre, nom_membre, id_membre_victime, nom_membre_victime, is_definitive, id_activite, created_at) 
                    VALUES 
                    ($participation_id, $eliminant_membre_id, '$eliminant_nom', $victime_membre_id, '$victime_nom', 0, $id, NOW())";
                
                error_log("DEBUG - SQL RECAVE: " . $sql_recave);
                $insert_recave = mysqli_query($con, $sql_recave);
                
                if (!$insert_recave) {
                    $_SESSION['msg'] = 'Erreur SQL INSERT recave: ' . mysqli_error($con);
                    error_log("DEBUG - INSERT RECAVE FAILED: " . mysqli_error($con));
                } else {
                    error_log("DEBUG - INSERT RECAVE SUCCESS");
                    // Mettre à jour recave et bounty (sorti(e) par)
                    $update_recave = mysqli_query($con, "UPDATE participation SET 
                        recave = recave + 1,
                        `nom-membre-vainqueur` = '$eliminant_nom',
                        `id-membre-vainqueur` = $eliminant_membre_id
                        WHERE `id-participation` = $participation_id");
                    
                    if (!$update_recave) {
                        $_SESSION['msg'] = 'Erreur SQL UPDATE recave: ' . mysqli_error($con);
                    } else {
                        $_SESSION['msg'] = 'Recave enregistrée';
                    }
                }
            }
        } else {
            $_SESSION['msg'] = 'Joueur non trouvé dans cette activité';
        }
    } catch (Exception $e) {
        $_SESSION['msg'] = 'Exception: ' . $e->getMessage();
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header("Location: fullscreen-player-simple.php?uid=" . $id);
    exit;
}

// Récupérer le titre de l'activité
$act_query = mysqli_query($con, "SELECT `titre-activite` FROM `activite` WHERE `id-activite` = '$id'");
$act_row = mysqli_fetch_array($act_query);
$activity_title = $act_row['titre-activite'];

// Récupérer la liste des joueurs actifs (non définitivement éliminés)
$joueurs_query = mysqli_query($con, "
    SELECT 
        p.`id-participation`,
        m.`id-membre`,
        m.pseudo,
        m.fname,
        m.lname,
        m.phonetique,
        p.recave
    FROM participation p
    LEFT JOIN membres m ON p.`id-membre` = m.`id-membre`
    WHERE p.`id-activite` = $id
    AND p.`id-participation` NOT IN (
        SELECT id_participation FROM eliminations WHERE is_definitive = 1
    )
    ORDER BY m.pseudo ASC
");

$joueurs = [];
while ($row = mysqli_fetch_assoc($joueurs_query)) {
    $joueurs[] = $row;
}

// Compter le nombre total de joueurs et ceux déjà classés (pour le rang audio)
$total_joueurs = 0;
$nb_deja_elimines = 0;

$count_total_query = mysqli_query($con, "SELECT COUNT(*) AS total FROM `participation` WHERE `id-activite` = '$id'");
if ($count_total_query) {
    $row_total = mysqli_fetch_assoc($count_total_query);
    $total_joueurs = intval($row_total['total']);
}

$count_elim_query = mysqli_query($con, "SELECT COUNT(*) AS elimines FROM `participation` WHERE `id-activite` = '$id' AND `classement` > 0");
if ($count_elim_query) {
    $row_elim = mysqli_fetch_assoc($count_elim_query);
    $nb_deja_elimines = intval($row_elim['elimines']);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actions - <?php echo htmlspecialchars($activity_title); ?></title>
    
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <!-- Voix féminine ResponsiveVoice, comme fullscreen-player.php -->
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script>
    
    <style>
        :root {
            --font-main: 'Raleway', sans-serif;
            --color-blue: #00d2ff;
            --color-red: #ff3333;
            --color-green: #00ff00;
            --color-yellow: #ffc107;
        }

        body {
            background-color: #1a1a1a;
            color: white;
            margin: 0;
            padding: 20px;
            font-family: var(--font-main);
        }

        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('bg.png');
            background-size: cover;
            background-position: center;
            opacity: 0.1;
            z-index: -1;
        }

        .header-title {
            text-align: center;
            color: var(--color-blue);
            text-transform: uppercase;
            font-weight: 700;
            font-size: 3.5em;
            margin-bottom: 30px;
            text-shadow: 0 0 10px rgba(0, 210, 255, 0.5);
        }

        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            display: inline-block;
            color: var(--color-blue);
            text-decoration: none;
            font-size: 1.2em;
            padding: 10px 20px;
            border: 2px solid var(--color-blue);
            border-radius: 8px;
            transition: all 0.3s;
            z-index: 1000;
            background-color: rgba(26, 26, 26, 0.9);
        }

        .back-link:hover {
            background-color: var(--color-blue);
            color: #1a1a1a;
            transform: translateX(-5px);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .alert {
            background-color: rgba(0, 210, 255, 0.1);
            border: 2px solid var(--color-blue);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .joueurs-list {
            margin-bottom: 30px;
        }

        .joueur-item {
            background: rgba(255, 255, 255, 0.05);
            border-left: 4px solid var(--color-blue);
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .joueur-item:hover {
            background: rgba(0, 210, 255, 0.1);
            border-left-color: var(--color-green);
            transform: translateX(5px);
        }

        .joueur-info {
            flex: 1;
        }

        .joueur-name {
            font-size: 1.8em;
            font-weight: bold;
            color: var(--color-blue);
        }

        .joueur-recave {
            font-size: 0.9em;
            color: #aaa;
            margin-top: 5px;
        }

        .btn-eliminer {
            background-color: var(--color-red);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-eliminer:hover {
            background-color: #ff5555;
            box-shadow: 0 0 10px rgba(255, 51, 51, 0.5);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #2a2a2a;
            border: 2px solid var(--color-blue);
            border-radius: 10px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 0 30px rgba(0, 210, 255, 0.3);
        }

        .modal-title {
            font-size: 1.8em;
            color: var(--color-blue);
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-section {
            margin-bottom: 25px;
        }

        .modal-label {
            font-size: 1.6em;
            color: white;
            margin-bottom: 10px;
            display: block;
            font-weight: 600;
        }

        .joueurs-select {
            width: 100%;
            padding: 20px;
            height: 100px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--color-blue);
            color: white;
            border-radius: 5px;
            font-size: 1.6em;
            font-family: var(--font-main);
        }

        .joueurs-select option {
            background: #1a1a1a;
            color: white;
        }

        .elimination-types {
            display: flex;
            gap: 10px;
        }

        .type-btn {
            flex: 1;
            padding: 25px;
            border: 2px solid #444;
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1.6em;
            font-weight: bold;
            transition: all 0.3s;
        }

        .type-btn.active {
            border-color: var(--color-green);
            background: rgba(0, 255, 0, 0.2);
            color: var(--color-green);
        }

        .type-btn.red {
            border-color: var(--color-red);
        }

        .type-btn.red.active {
            background: rgba(255, 51, 51, 0.2);
            color: var(--color-red);
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-confirm {
            flex: 1;
            padding: 20px;
            background-color: var(--color-blue);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.6em;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-confirm:hover {
            background-color: #00e8ff;
            box-shadow: 0 0 10px rgba(0, 210, 255, 0.7);
        }

        .btn-cancel {
            flex: 1;
            padding: 20px;
            background-color: #444;
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1.6em;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background-color: #666;
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Retour
    </a>

    <div class="header-title">
        <i class="fa fa-user-times"></i> <a href="fullscreen-player.php?uid=<?php echo $id; ?>" style="text-decoration: none; color: #aaa; font-size: 0.9em; transition: color 0.3s; cursor: pointer;" onmouseover="this.style.color='#00d2ff'" onmouseout="this.style.color='#aaa'">- <?php echo htmlspecialchars($activity_title); ?></a>
    </div>

    <div class="container">
        <?php if (!empty($_SESSION['msg'])): ?>
            <div class="alert">
                <strong>✓</strong> <?php echo htmlspecialchars($_SESSION['msg']); ?>
                <?php unset($_SESSION['msg']); ?>
            </div>
        <?php endif; ?>

        <div class="joueurs-list">
            <h3 style="color: var(--color-blue); margin-bottom: 20px; font-size: 1.5em;">
                Enregistrer une Élimination (<?php echo count($joueurs); ?> joueurs actifs)
            </h3>

            <?php if (count($joueurs) > 0): ?>
                <form method="POST" action="fullscreen-player-simple.php?uid=<?php echo $id; ?>" id="eliminationForm" onsubmit="return confirmEliminationForm()">
                    <div class="modal-section" style="margin-bottom: 25px;">
                        <label class="modal-label">Joueur Éliminé:</label>
                        <select class="joueurs-select" name="id_joueur_elimine" id="joueurElimine" required>
                            <option value="">-- Sélectionner le joueur éliminé --</option>
                            <?php foreach ($joueurs as $j): ?>
                                <option value="<?php echo $j['id-participation']; ?>" data-member-id="<?php echo isset($j['id-membre']) ? intval($j['id-membre']) : 0; ?>" data-pseudo="<?php echo htmlspecialchars($j['pseudo']); ?>" data-phonetic="<?php echo isset($j['phonetique']) ? htmlspecialchars($j['phonetique']) : ''; ?>">
                                    <?php echo htmlspecialchars($j['pseudo']); ?> (Recaves: <?php echo $j['recave']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-section" style="margin-bottom: 25px;">
                        <label class="modal-label">Joueur Éliminant:</label>
                        <select class="joueurs-select" name="id_joueur_eliminant" id="joueurEliminant" required>
                            <option value="">-- Sélectionner le joueur éliminant --</option>
                            <?php foreach ($joueurs as $j): ?>
                                <option value="<?php echo $j['id-participation']; ?>" data-id="<?php echo $j['id-participation']; ?>" data-pseudo="<?php echo htmlspecialchars($j['pseudo']); ?>" data-phonetic="<?php echo isset($j['phonetique']) ? htmlspecialchars($j['phonetique']) : ''; ?>">
                                    <?php echo htmlspecialchars($j['pseudo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="modal-section" style="margin-bottom: 25px;">
                        <label class="modal-label">Type d'Élimination:</label>
                        <div class="elimination-types">
                            <button type="button" class="type-btn active" data-type="recave">
                                <i class="fa fa-refresh"></i> Recave
                            </button>
                            <button type="button" class="type-btn red" data-type="definitive">
                                <i class="fa fa-power-off"></i> Définitive
                            </button>
                        </div>
                        <input type="hidden" name="type_elimination" id="typeElimination" value="recave">
                    </div>

                    <div class="modal-buttons">
                        <input type="hidden" name="eliminer" value="1">
                        <button type="submit" class="btn-confirm">
                            <i class="fa fa-check"></i> Confirmer
                        </button>
                    </div>
                </form>

                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="annuler_derniere" class="btn-cancel" style="width: 100%; padding: 15px;">
                        <i class="fa fa-undo"></i> Annuler dernière élimination
                    </button>
                </form>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #aaa;">
                    <i class="fa fa-check-circle" style="font-size: 3em; margin-bottom: 10px; display: block;"></i>
                    Aucun joueur actif
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Données globales pour le calcul du rang (comme fullscreen-player.php)
        var totalJoueurs = <?php echo isset($total_joueurs) ? $total_joueurs : 0; ?>;
        var dejaElimines = <?php echo isset($nb_deja_elimines) ? $nb_deja_elimines : 0; ?>;
        var eliminationInProgress = false;
        var lastElimIdMemory = null;
        var skipAudioUntil = 0; // Timestamp jusqu'auquel on ne joue pas l'audio du polling

        // Restaurer skipAudioUntil depuis localStorage au chargement de la page
        (function restoreSkipAudioUntil() {
            var activityId = "<?php echo $id; ?>";
            var skipKey = 'skipAudioUntil:' + activityId;
            try {
                var stored = localStorage.getItem(skipKey);
                if (stored) {
                    var storedTime = parseInt(stored, 10);
                    var now = new Date().getTime();
                    if (storedTime > now) {
                        window.skipAudioUntil = storedTime;
                        console.log('Restored skipAudioUntil from localStorage, skipping audio until', new Date(storedTime).toISOString());
                    }
                }
            } catch (e) {
                console.warn('Erreur lecture localStorage skipAudioUntil', e);
            }
        })();

        // Fonction utilitaire pour parler avec une voix féminine (comme fullscreen-player.php)
        function speakWithFemaleVoice(text) {
            if (typeof responsiveVoice !== 'undefined') {
                try {
                    responsiveVoice.speak(text, 'French Female', { rate: 0.85 });
                    return;
                } catch (e) {
                    console.warn('responsiveVoice speak error, fallback Web Speech', e);
                }
            }

            if ('speechSynthesis' in window) {
                var msg = new SpeechSynthesisUtterance(text);
                msg.lang = 'fr-FR';
                msg.rate = 0.85;

                var voices = window.speechSynthesis.getVoices();
                var femaleVoice = voices.find(function(v) {
                    return v.lang && v.lang.toLowerCase().startsWith('fr') &&
                           (v.name.includes('Female') || v.name.includes('Amélie') || v.name.includes('Hortense') || v.name.includes('Julie'));
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
                // Chemin relatif : sounds/mission.mp3 (relative to panel folder)
                var audio = new Audio('./sounds/mission.mp3');
                var sirenPlayed = false;

                audio.addEventListener('ended', function() {
                    sirenPlayed = true;
                    speakWithFemaleVoice(text);
                });

                audio.addEventListener('error', function(err) {
                    console.warn('Fichier audio non trouvé ou erreur CORS (sounds/mission.mp3), fallback voix directe', err);
                    if (!sirenPlayed) {
                        speakWithFemaleVoice(text);
                    }
                });

                var playPromise = audio.play();
                if (playPromise !== undefined) {
                    playPromise.catch(function(err) {
                        console.warn('Lecture sirène échouée', err);
                        if (!sirenPlayed) {
                            speakWithFemaleVoice(text);
                        }
                    });
                }
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

        function getVoiceName(phonetic, fallback) {
            var raw = (phonetic && phonetic.trim() !== '') ? phonetic : fallback;
            return raw.split('.')[0].trim();
        }

        function pollLatestElimination(skipFirst) {
            if (window.eliminationInProgress) {
                return;
            }

            var activityId = "<?php echo $id; ?>";
            var storageKey = 'lastEliminationSpoken:' + activityId;

            fetch('fullscreen-player-simple.php?uid=' + encodeURIComponent(activityId) + '&last_elimination=1', {
                cache: 'no-store'
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data || !data.id) {
                        return;
                    }

                    var storedId = null;
                    var storageAvailable = true;
                    try {
                        storedId = localStorage.getItem(storageKey);
                    } catch (e) {
                        storageAvailable = false;
                        storedId = lastElimIdMemory;
                    }

                    if (!storedId && skipFirst) {
                        if (storageAvailable) {
                            try {
                                localStorage.setItem(storageKey, String(data.id));
                            } catch (e) {
                                return;
                            }
                        } else {
                            lastElimIdMemory = String(data.id);
                        }
                        return;
                    }

                    if (storedId && String(storedId) === String(data.id)) {
                        return;
                    }

                    var voiceEliminant = getVoiceName(data.eliminant_phonetic, data.eliminant_name || '');
                    var voiceVictime = getVoiceName(data.victime_phonetic, data.victime_name || '');
                    var messageBase;

                    if (data.id_membre_victime === '1100' || data.id_membre_victime === 1100 ||
                        data.id_membre_victime === '1103' || data.id_membre_victime === 1103) {
                        messageBase = voiceEliminant + " a éliminé " + voiceVictime + ". Et oui vous avez bien entendu, " + voiceEliminant + " a éliminé " + voiceVictime;
                    } else {
                        messageBase = voiceEliminant + " a éliminé " + voiceVictime;
                    }

                    // Ne pas jouer l'audio si on vient de créer cette élimination (évite doublons)
                    var now = new Date().getTime();
                    if (now < window.skipAudioUntil) {
                        // On a supprimé l'audio, mais on garde la trace en localStorage/memory
                        if (storageAvailable) {
                            try {
                                localStorage.setItem(storageKey, String(data.id));
                            } catch (e) {
                                return;
                            }
                        } else {
                            lastElimIdMemory = String(data.id);
                        }
                        return;
                    }

                    if (String(data.is_definitive) === '1') {
                        var rang = parseInt(data.classement, 10);
                        if (!isNaN(rang) && rang > 0) {
                            var suffixe = (rang == 1) ? "ère" : "ème";
                            speakWithSirenIfNeeded(data.id_membre_victime, messageBase + ", " + voiceVictime + " finit en " + rang + suffixe + " position");
                        } else {
                            speakWithSirenIfNeeded(data.id_membre_victime, messageBase);
                        }
                    } else {
                        speakWithSirenIfNeeded(data.id_membre_victime, messageBase);
                    }

                    if (storageAvailable) {
                        try {
                            localStorage.setItem(storageKey, String(data.id));
                        } catch (e) {
                            return;
                        }
                    } else {
                        lastElimIdMemory = String(data.id);
                    }
                })
                .catch(function(err) {
                    console.warn('Polling elimination error', err);
                });
        }

        (function initEliminationPolling() {
            var first = true;
            function tick() {
                pollLatestElimination(first);
                first = false;
            }
            tick();
            setInterval(tick, 5000);
        })();

        // Gestion des boutons de type d'élimination
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('typeElimination').value = this.dataset.type;
            });
        });

        // Filtrer la liste des éliminants pour ne pas afficher le joueur éliminé
        document.getElementById('joueurElimine').addEventListener('change', function() {
            const eliminéId = this.value;
            const selectEliminant = document.getElementById('joueurEliminant');
            const options = selectEliminant.querySelectorAll('option[data-id]');
            
            options.forEach(option => {
                if (option.getAttribute('data-id') === eliminéId) {
                    option.style.display = 'none';
                    option.disabled = true;
                } else {
                    option.style.display = '';
                    option.disabled = false;
                }
            });
            
            // Réinitialiser la sélection de l'éliminant si c'est le même que l'éliminé
            if (selectEliminant.value === eliminéId) {
                selectEliminant.value = '';
            }
        });

        // Confirmation avant soumission
        function confirmEliminationForm() {
            const selectElimine = document.getElementById('joueurElimine');
            const selectEliminant = document.getElementById('joueurEliminant');
            const typeElimination = document.getElementById('typeElimination').value;
            
            if (!selectElimine.value || !selectEliminant.value) {
                alert('Veuillez sélectionner les deux joueurs');
                return false;
            }
            
            const optElimine = selectElimine.options[selectElimine.selectedIndex];
            const optEliminant = selectEliminant.options[selectEliminant.selectedIndex];

            const pseudoElimine = optElimine.getAttribute('data-pseudo');
            const pseudoEliminant = optEliminant.getAttribute('data-pseudo');

            const membreIdElimine = optElimine.getAttribute('data-member-id');

            const phonElimine = optElimine.getAttribute('data-phonetic') || '';
            const phonEliminant = optEliminant.getAttribute('data-phonetic') || '';

            // Préparation des noms pour la voix :
            // si le pseudo/phonétique contient un '.', on ignore tout ce qu'il y a après
            const voixElimineBrut = phonElimine.trim() !== '' ? phonElimine : pseudoElimine;
            const voixEliminantBrut = phonEliminant.trim() !== '' ? phonEliminant : pseudoEliminant;

            const voixElimine = voixElimineBrut.split('.')[0].trim();
            const voixEliminant = voixEliminantBrut.split('.')[0].trim();
            
            const typeTexte = typeElimination === 'definitive' ? 'DÉFINITIVE' : 'RECAVE';
            
            const message = `Confirmer l'élimination ?\n\n` +
                          `👤 Éliminé: ${pseudoElimine}\n` +
                          `⚔️  Éliminant: ${pseudoEliminant}\n` +
                          `📋 Type: ${typeTexte}\n\n` +
                          `Voulez-vous continuer ?`;

            const ok = confirm(message);
            if (!ok) return false;

            const form = document.getElementById('eliminationForm');
            window.eliminationInProgress = true;

            // Empêcher le polling de rejouer l'audio pendant 15 secondes (on vient de le jouer)
            var activityId = "<?php echo $id; ?>";
            var skipKey = 'skipAudioUntil:' + activityId;
            var skipUntilTime = new Date().getTime() + 15000;
            window.skipAudioUntil = skipUntilTime;
            
            // Sauvegarder dans localStorage pour persister après rechargement
            try {
                localStorage.setItem(skipKey, String(skipUntilTime));
            } catch (e) {
                console.warn('Erreur sauvegarde localStorage skipAudioUntil', e);
            }

            // Message audio comme dans fullscreen-player, mais on protège contre toute erreur JS
            try {
                // Message de base : renforcé uniquement pour certains joueurs spéciaux
                let messageBase;
                if (membreIdElimine === '1100' || membreIdElimine === 1100 ||
                    membreIdElimine === '1103' || membreIdElimine === 1103) {
                    messageBase = voixEliminant + " a éliminé " + voixElimine + ". Et oui vous avez bien entendu, " + voixEliminant + " a éliminé " + voixElimine;
                } else {
                    messageBase = voixEliminant + " a éliminé " + voixElimine;
                }

                if (typeElimination === 'definitive') {
                    let rangCalcule = totalJoueurs - dejaElimines;
                    if (rangCalcule < 1) rangCalcule = 1;
                    const suffixe = (rangCalcule == 1) ? "ère" : "ème";
                    const texteVocal = messageBase + ", " + voixElimine + " finit en " + rangCalcule + suffixe + " position";
                    speakWithSirenIfNeeded(membreIdElimine, texteVocal);
                } else {
                    const texteVocal = messageBase;
                    speakWithSirenIfNeeded(membreIdElimine, texteVocal);
                }
            } catch (e) {
                console.warn('Erreur lors du déclenchement audio de l\'élimination', e);
            }

            // Laisser le temps à la sirène + message complet de jouer avant de recharger la page
            setTimeout(function() {
                if (form) {
                    form.submit();
                }
            }, 13000);

            // On empêche la soumission immédiate (pour ne pas couper le son)
            return false;
        }
    </script>
</body>
</html>
