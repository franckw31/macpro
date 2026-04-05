<?php
// filepath: c:\Users\MSI\Desktop\www\panel\cardevent-api.php
ob_start();
session_start();
error_reporting(0);
include('include/config.php');
ob_clean();

// Normalise un statut (minuscule, trim, accents simplifiés)
function normalize_status($status) {
    $s = trim(mb_strtolower($status, 'UTF-8'));
    $s = strtr($s, [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'â' => 'a',
        'ù' => 'u', 'û' => 'u',
        'ô' => 'o',
        'î' => 'i'
    ]);
    return $s;
}

header('Content-Type: application/json');
date_default_timezone_set('Europe/Paris');

$response = [
    'status' => 'error',
    'message' => 'Init',
    'seconds_remaining' => 0,
    'blinds_text' => 'Chargement...',
    'next_pause' => ''
];

try {
    if (!isset($con)) throw new Exception("DB Connection failed");

    $id = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    $now = time();

    // 1. Pause globale et décalage
    // D'après en-pause.php, 'en_pause' et 'heure_pause' sont dans la ligne 'ordre' = 1
    $q_pause = mysqli_query($con, "SELECT `en_pause`, `heure_pause` FROM `blindes-live` WHERE `id-activite` = '$id' AND `ordre` = '1' LIMIT 1");
    $r_pause = ($q_pause) ? mysqli_fetch_assoc($q_pause) : null;
    $is_paused = ($r_pause && $r_pause['en_pause'] == 1);
    
    $pause_elapsed = 0;
    if ($is_paused && !empty($r_pause['heure_pause'])) {
        $pause_elapsed = $now - strtotime($r_pause['heure_pause']);
        if ($pause_elapsed < 0) $pause_elapsed = 0;
    }

    $now_adjusted = $now - $pause_elapsed;

    // 2. Niveau en cours
    // On cherche le premier niveau qui finit dans le futur (ajusté si pause en cours)
    $sql = "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' 
            AND fin > FROM_UNIXTIME($now_adjusted) 
            ORDER BY fin ASC LIMIT 1";
            
    $q = mysqli_query($con, $sql);
    if (!$q) throw new Exception("Erreur SQL Current: " . mysqli_error($con));
    
    $current = mysqli_fetch_assoc($q);

    // 3. Prochaine pause
    $next_pause_text = "";
    // On cherche dans la colonne 'nom' le texte 'ause' (pour Pause)
    $sql_np = "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' 
               AND `nom` LIKE '%ause%' 
               AND DATE_SUB(fin, INTERVAL minutes MINUTE) > FROM_UNIXTIME($now_adjusted) 
               ORDER BY fin ASC LIMIT 1";
               
    $q_np = mysqli_query($con, $sql_np);
    
    if ($q_np && $r_np = mysqli_fetch_assoc($q_np)) {
        // Calcul du début : Fin - Minutes
        $start_timestamp = strtotime($r_np['fin']) - ($r_np['minutes'] * 60);
        // Le décompte vers la pause doit aussi être "gelé" si on est déjà en pause globale
        $diff = $start_timestamp - $now_adjusted;
        
        if ($diff > 0) {
            $h = floor($diff / 3600);
            $m = floor(($diff % 3600) / 60);
            $next_pause_text = "Pause dans " . ($h > 0 ? $h."h " : "") . $m . "m";
        }
    }

    // 4. Stats Joueurs
    $act_query = mysqli_query($con, "SELECT jetons, jetons_activite, recave_jetons FROM activite WHERE `id-activite` = '$id'");
    $act_row = mysqli_fetch_array($act_query);
    // Utilise jetons_activite (moyenne calculée) si disponible, sinon jetons (initial)
    $start_chips = intval($act_row['jetons_activite'] ?? $act_row['jetons']);
    $rebuy_chips = intval($act_row['recave_jetons']);

    $part_query = mysqli_query($con, "SELECT `id-participation`, `recave`, `addon`, `option` FROM `participation` WHERE `id-activite` = '$id'");
    $total_players = 0;
    $total_rebuys = 0;
    $total_addons = 0;
    $active_players = 0;

    while ($row = mysqli_fetch_array($part_query)) {
        $total_players++;
        $total_rebuys += intval($row['recave']);
        $total_addons += intval($row['addon']);
        
        $pid = $row['id-participation'];
        $status = isset($row['option']) ? $row['option'] : '';
        $normStatus = normalize_status($status);

        // Détection d'élimination via table dédiée
        $elim_query = mysqli_query($con, "SELECT is_definitive FROM eliminations WHERE id_participation = '$pid' AND is_definitive = 1");
        $isDefElim = ($elim_query && mysqli_num_rows($elim_query) > 0);

        // Détection d'élimination via statut de participation (valeurs fréquentes)
        $isStatusElim = in_array($normStatus, [
            'elimine', 'eliminee', 'eliminer', 'elimines', 'eliminees',
            'eliminee', 'elim', 'out', 'ko',
            'desinscrit', 'desinscrite', 'none'
        ]);

        if (!$isDefElim && !$isStatusElim) {
            $active_players++;
        }
    }

    $total_chips = ($total_players * $start_chips) + ($total_rebuys * $rebuy_chips) + ($total_addons * $rebuy_chips);
    $avg_stack = ($active_players > 0) ? floor($total_chips / $active_players) : 0;

    $response['players_active'] = $active_players;
    $response['players_total'] = $total_players;
    $response['avg_stack'] = number_format($avg_stack, 0, ',', ' ');

    // Dernière élimination (définitive) pour annonces vocales
    $last_elim_sql = "SELECT id, nom_membre, nom_membre_victime, UNIX_TIMESTAMP(created_at) AS ts 
                     FROM eliminations 
                     WHERE id_activite = '$id' AND is_definitive = 1 
                     ORDER BY created_at DESC LIMIT 1";
    $q_last_elim = mysqli_query($con, $last_elim_sql);
    if ($q_last_elim && $last = mysqli_fetch_assoc($q_last_elim)) {
        $response['last_elimination_id'] = intval($last['id']);
        $response['last_elimination_ts'] = intval($last['ts']);
        $response['last_elimination_killer'] = $last['nom_membre'];
        $response['last_elimination_victim'] = $last['nom_membre_victime'];
    } else {
        $response['last_elimination_id'] = null;
        $response['last_elimination_ts'] = null;
        $response['last_elimination_killer'] = null;
        $response['last_elimination_victim'] = null;
    }

    // Dernière élimination non définitive (recave) pour annonces vocales
    $last_recave_sql = "SELECT id, nom_membre, nom_membre_victime, UNIX_TIMESTAMP(created_at) AS ts 
                        FROM eliminations 
                        WHERE id_activite = '$id' AND is_definitive = 0 
                        ORDER BY created_at DESC LIMIT 1";
    $q_last_recave = mysqli_query($con, $last_recave_sql);
    if ($q_last_recave && $lastR = mysqli_fetch_assoc($q_last_recave)) {
        $response['last_recave_elimination_id'] = intval($lastR['id']);
        $response['last_recave_elimination_ts'] = intval($lastR['ts']);
        $response['last_recave_elimination_killer'] = $lastR['nom_membre'];
        $response['last_recave_elimination_victim'] = $lastR['nom_membre_victime'];
    } else {
        $response['last_recave_elimination_id'] = null;
        $response['last_recave_elimination_ts'] = null;
        $response['last_recave_elimination_killer'] = null;
        $response['last_recave_elimination_victim'] = null;
    }


    $response['status'] = 'success';
    $response['is_paused'] = $is_paused;
    $response['next_pause'] = $next_pause_text;

    // Compter le nombre total de niveaux
    $sql_total = "SELECT COUNT(*) as total FROM `blindes-live` WHERE `id-activite` = '$id'";
    $q_total = mysqli_query($con, $sql_total);
    $r_total = mysqli_fetch_assoc($q_total);
    $total_levels = intval($r_total['total']);

    if ($current) {
        $response['seconds_remaining'] = strtotime($current['fin']) - $now_adjusted;
        // CORRECTION : Utilisation de 'sb' et 'bb' au lieu de small_blind/big_blind
        $response['blinds_text'] = $current['sb'] . " / " . $current['bb'];
        $response['ante_text'] = !empty($current['ante']) ? "Ante " . $current['ante'] : "";
        $response['level_id'] = $current['id'];
        $response['blinds_raw'] = $current['sb'] . "-" . $current['bb'];
        
        // Calcul de la durée basé sur 'minutes' (plus stable que debut/fin)
        $remaining = strtotime($current['fin']) - $now_adjusted;
        $theoretical_duration = intval($current['minutes']) * 60;
        
        // Si le temps restant est supérieur à la durée théorique (cas d'ajout de temps), on agrandit la jauge
        // Sinon on garde la durée théorique du niveau (ex: 20min)
        $response['duration_seconds'] = max($theoretical_duration, $remaining);
        
        $response['level_name'] = "Niveau " . $current['ordre'] . " / " . $total_levels; 
        $response['level_order'] = intval($current['ordre']);
        $response['total_levels'] = $total_levels;

        // Fournir les blindes suivantes (prochain niveau non pause)
        $next_sql = "SELECT sb, bb FROM `blindes-live` WHERE `id-activite` = '$id' 
                     AND `ordre` > " . intval($current['ordre']) . " 
                     AND (sb IS NOT NULL AND bb IS NOT NULL) 
                     AND (sb <> '0' OR bb <> '0')
                     ORDER BY `ordre` ASC LIMIT 1";
        $q_next2 = mysqli_query($con, $next_sql);
        if ($q_next2 && $next2 = mysqli_fetch_assoc($q_next2)) {
            $response['next_blinds_text'] = $next2['sb'] . " / " . $next2['bb'];
            $response['next_blinds_raw'] = $next2['sb'] . "-" . $next2['bb'];
        } else {
            $response['next_blinds_text'] = '';
            $response['next_blinds_raw'] = '';
        }
    } else {
        // Pas de niveau actif -> On cherche le prochain niveau à venir
        $sql_next = "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' 
                     AND DATE_SUB(fin, INTERVAL minutes MINUTE) > FROM_UNIXTIME($now_adjusted) 
                     ORDER BY fin ASC LIMIT 1";
        $q_next = mysqli_query($con, $sql_next);
        
        if ($q_next && $next = mysqli_fetch_assoc($q_next)) {
            $response['blinds_text'] = "Prochain: " . $next['sb'] . "/" . $next['bb'];
            $response['next_blinds_text'] = $next['sb'] . " / " . $next['bb'];
            $response['next_blinds_raw'] = $next['sb'] . "-" . $next['bb'];
            $response['status'] = 'waiting';
        } else {
            $response['blinds_text'] = "Terminé ou Non Configuré";
            $response['status'] = 'finished';
        }
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    // On affiche l'erreur SQL dans le texte des blindes pour que vous la voyiez
    $response['blinds_text'] = "Erreur SQL : " . $e->getMessage();
}

echo json_encode($response);
?>