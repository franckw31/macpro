<?php
session_start();
error_reporting(0);
date_default_timezone_set('Europe/Paris');
include('include/config.php');

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

// Permission contrôle timer : organisateur ou id=265
$current_user_id = intval($_SESSION['id']);
$_org_q = mysqli_query($con, "SELECT `id-membre` FROM `activite` WHERE `id-activite` = '$id' LIMIT 1");
$_org_row = mysqli_fetch_assoc($_org_q);
$_organizer_id = intval($_org_row['id-membre'] ?? 0);
$can_control = ($current_user_id === 265 || $current_user_id === $_organizer_id);

// --- CALCUL DES STATS JOUEURS ---
$act_query = mysqli_query($con, "SELECT jetons, jetons_activite, recave_jetons FROM activite WHERE `id-activite` = '$id'");
$act_row = mysqli_fetch_array($act_query);
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
    $elim_query = mysqli_query($con, "SELECT is_definitive FROM eliminations WHERE id_participation = '$pid' AND is_definitive = 1");
    if (mysqli_num_rows($elim_query) == 0) {
        $active_players++;
    }
}

$total_chips = ($total_players * $start_chips) + ($total_rebuys * $rebuy_chips) + ($total_addons * $rebuy_chips);
$avg_stack = ($active_players > 0) ? floor($total_chips / $active_players) : 0;

// --- ACTIONS AJAX ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $act_id = intval($_GET['uid']);

    if ($action === 'pause') {
        $actu = date("Y-m-d H:i:s");
        $check = mysqli_query($con, "SELECT `en_pause`, `heure_pause` FROM `blindes-live` WHERE `id-activite` = '$act_id' AND `ordre` = '1' LIMIT 1");
        $r = mysqli_fetch_assoc($check);
        if (intval($r['en_pause'] ?? 0) == 0) {
            // Mise en pause (comme en-pause.php)
            mysqli_query($con, "UPDATE `blindes-live` SET `heure_pause` = '$actu', `delta` = '0', `en_pause` = '1' WHERE `ordre` = '1' AND `id-activite` = '$act_id'");
        } else {
            // Dépause (comme de-pause.php)
            $debpause = $r['heure_pause'];
            if ($debpause) {
                $delta = strtotime($actu) - strtotime($debpause);
                mysqli_query($con, "UPDATE `blindes-live` SET `heure_depause` = '$actu', `delta` = '$delta', `en_pause` = '0' WHERE `ordre` = '1' AND `id-activite` = '$act_id'");
                $blinds_q = mysqli_query($con, "SELECT `ordre`, `fin` FROM `blindes-live` WHERE `id-activite` = '$act_id'");
                while ($b = mysqli_fetch_assoc($blinds_q)) {
                    $fin_ts = date_create($b['fin']);
                    date_add($fin_ts, date_interval_create_from_date_string($delta . ' seconds'));
                    $new_fin = date_format($fin_ts, 'Y-m-d H:i:s');
                    mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$new_fin' WHERE `ordre` = '{$b['ordre']}' AND `id-activite` = '$act_id'");
                }
            } else {
                mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '0' WHERE `ordre` = '1' AND `id-activite` = '$act_id'");
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'adjust') {
        $minutes = intval($_GET['min']);
        $sign = ($minutes > 0) ? '+' : '';
        $blinds_q = mysqli_query($con, "SELECT `id`, `fin` FROM `blindes-live` WHERE `id-activite` = '$act_id' AND `fin` > NOW() ORDER BY `ordre` ASC");
        while ($b = mysqli_fetch_assoc($blinds_q)) {
            $new_fin = date("Y-m-d H:i:s", strtotime($b['fin']) + ($minutes * 60));
            mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$new_fin' WHERE `id` = '{$b['id']}'");
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'next' || $action === 'prev' || $action === 'reset' || $action === 'restart') {
        $now = time();
        $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$act_id' ORDER BY `ordre` ASC");
        $blinds = [];
        while($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; }

        $currentIndex = -1;
        foreach($blinds as $k => $b) {
            if (strtotime($b['fin']) > $now) {
                $currentIndex = $k;
                break;
            }
        }

        $targetIndex = $currentIndex;
        if ($action === 'next') {
            if ($currentIndex != -1 && $currentIndex < count($blinds) - 1) $targetIndex = $currentIndex + 1;
        } elseif ($action === 'prev') {
            if ($currentIndex == -1) $targetIndex = count($blinds) - 1;
            elseif ($currentIndex > 0) $targetIndex = $currentIndex - 1;
            else $targetIndex = 0;
        } elseif ($action === 'reset') {
            $targetIndex = ($currentIndex == -1) ? count($blinds) - 1 : $currentIndex;
        } elseif ($action === 'restart') {
            // Repart du niveau 1 et dépause
            $targetIndex = 0;
            mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = 0, `heure_pause` = NULL, `heure_depause` = NULL WHERE `ordre` = '1' AND `id-activite` = '$act_id'");
        }

        if ($targetIndex >= 0 && $targetIndex < count($blinds)) {
            $runningTime = time();
            if ($targetIndex > 0) {
                $prevId = $blinds[$targetIndex - 1]['id'];
                mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '" . date("Y-m-d H:i:s", $runningTime) . "' WHERE `id` = '$prevId'");
            }
            for ($i = $targetIndex; $i < count($blinds); $i++) {
                $duree = strtotime($blinds[$i]['fin']) - strtotime($blinds[$i]['debut']);
                if ($duree <= 0) $duree = 20 * 60;
                $newStart = $runningTime;
                $newEnd = $runningTime + $duree;
                $u_id = $blinds[$i]['id'];
                mysqli_query($con, "UPDATE `blindes-live` SET `debut` = '" . date("Y-m-d H:i:s", $newStart) . "', `fin` = '" . date("Y-m-d H:i:s", $newEnd) . "' WHERE `id` = '$u_id'");
                $runningTime = $newEnd;
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'status') {
        $now = time();

        // Lire le flag pause sur ordre=1
        $q_pause = mysqli_query($con, "SELECT `en_pause`, `heure_pause` FROM `blindes-live` WHERE `id-activite` = '$act_id' AND `ordre` = '1' LIMIT 1");
        $row_pause = $q_pause ? mysqli_fetch_assoc($q_pause) : [];
        $is_paused = (intval($row_pause['en_pause'] ?? 0) == 1);
        $heure_pause = $row_pause['heure_pause'] ?? null;

        // Tous les niveaux
        $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$act_id' ORDER BY `ordre` ASC");
        $blinds = [];
        if ($q) { while ($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; } }

        // Niveau courant = premier dont fin > maintenant
        $current = null;
        $currentIdx = -1;
        foreach ($blinds as $k => $b) {
            if (strtotime($b['fin']) > $now) {
                $current = $b;
                $currentIdx = $k;
                break;
            }
        }

        if (!$current) {
            echo json_encode(['status' => 'finished', 'seconds_remaining' => 0, 'duration_seconds' => 0,
                'blinds_text' => 'Fin', 'blinds_raw' => '0/0', 'level_name' => 'Fin',
                'level_index' => count($blinds), 'level_total' => count($blinds),
                'is_paused' => false, 'next_pause' => '', 'next_blinds_raw' => '']);
            exit;
        }

        // Calcul du temps restant (timezone correctement définie en haut)
        if ($is_paused && $heure_pause) {
            $seconds_remaining = max(0, strtotime($current['fin']) - strtotime($heure_pause));
        } else {
            $seconds_remaining = max(0, strtotime($current['fin']) - $now);
        }

        $debut_ts = strtotime($current['debut']);
        $fin_ts   = strtotime($current['fin']);
        $dur_raw  = ($debut_ts && $debut_ts > 86400) ? ($fin_ts - $debut_ts) : 0;
        $duration_seconds = ($dur_raw > 0 && $dur_raw <= 7200) ? $dur_raw : $seconds_remaining;

        $sb   = intval($current['sb'] ?? 0);
        $bb   = intval($current['bb'] ?? 0);
        $ante = intval($current['ante'] ?? 0);
        $nom  = $current['nom'] ?? '';
        $blinds_raw  = $sb . '/' . $bb;
        $is_break    = ($sb == 0 && $bb == 0);
        $blinds_text = $is_break ? 'PAUSE' : number_format($sb, 0, ',', ' ') . ' / ' . number_format($bb, 0, ',', ' ');
        $ante_text   = ($ante > 0) ? 'Ante : ' . number_format($ante, 0, ',', ' ') : '';
        $level_name  = $nom ?: ($currentIdx + 1);

        // Niveau suivant
        $next_raw = '';
        if (isset($blinds[$currentIdx + 1])) {
            $n   = $blinds[$currentIdx + 1];
            $nsb = intval($n['sb'] ?? 0);
            $nbb = intval($n['bb'] ?? 0);
            $next_raw = ($nsb == 0 && $nbb == 0) ? 'PAUSE' : $nsb . '/' . $nbb;
        }

        // Prochaine pause : on cherche le premier niveau avec sb=0 bb=0 après le niveau courant
        // Le temps jusqu'à sa DÉBUT = fin du niveau qui le précède - maintenant
        $next_pause = '';
        for ($i = $currentIdx + 1; $i < count($blinds); $i++) {
            $nb   = $blinds[$i];
            $nsb2 = intval($nb['sb'] ?? 0);
            $nbb2 = intval($nb['bb'] ?? 0);
            if ($nsb2 == 0 && $nbb2 == 0) {
                // La pause démarre quand le niveau précédent se termine
                $pause_starts_at = strtotime($blinds[$i - 1]['fin']);
                $psec = max(0, $pause_starts_at - $now);
                $ph = floor($psec / 3600);
                $pm = floor(($psec % 3600) / 60);
                if ($ph > 0) {
                    $next_pause = 'dans ' . $ph . 'h' . str_pad($pm, 2, '0', STR_PAD_LEFT);
                } else {
                    $next_pause = 'dans ' . $pm . 'm';
                }
                break;
            }
        }

        // Stats joueurs
        $pq  = mysqli_query($con, "SELECT `id-participation`, `recave`, `addon` FROM `participation` WHERE `id-activite` = '$act_id'");
        $act_r = mysqli_query($con, "SELECT jetons, jetons_activite, recave_jetons FROM activite WHERE `id-activite` = '$act_id'");
        $ar  = mysqli_fetch_array($act_r);
        $sc  = intval($ar['jetons_activite'] ?: $ar['jetons']);
        $rc  = intval($ar['recave_jetons']);
        $tp = 0; $ap = 0; $tr = 0; $ta = 0; $tc = 0;
        while ($pr = mysqli_fetch_array($pq)) {
            $tp++; $tr += intval($pr['recave']); $ta += intval($pr['addon']);
            $eq = mysqli_query($con, "SELECT is_definitive FROM eliminations WHERE id_participation = '{$pr['id-participation']}' AND is_definitive = 1");
            if (mysqli_num_rows($eq) == 0) $ap++;
        }
        $tc     = ($tp * $sc) + ($tr * $rc) + ($ta * $rc);
        $as_val = ($ap > 0) ? floor($tc / $ap) : 0;

        echo json_encode([
            'status'            => $is_paused ? 'paused' : 'running',
            'seconds_remaining' => intval($seconds_remaining),
            'duration_seconds'  => intval($duration_seconds),
            'blinds_text'       => $blinds_text,
            'blinds_raw'        => $blinds_raw,
            'ante_text'         => $ante_text,
            'level_name'        => (string)$level_name,
            'level_index'       => $currentIdx,
            'level_total'       => count($blinds),
            'is_paused'         => $is_paused,
            'next_pause'        => $next_pause,
            'next_blinds_raw'   => $next_raw,
            'next_blinds_text'  => $next_raw,
            'avg_stack'         => number_format($as_val, 0, ',', ' '),
            'players_active'    => $ap,
            'players_total'     => $tp
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <title>Live Timer</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice', e); }"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #000;
            background-image:
                radial-gradient(circle at top, rgba(0,210,255,0.12), transparent 28%),
                radial-gradient(circle at bottom, rgba(255,170,0,0.07), transparent 22%),
                linear-gradient(180deg, #050608 0%, #000 100%);
            color: white;
            font-family: 'Roboto', sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 820px;
            margin: 0 auto;
            padding: 16px 16px 120px;
        }

        /* ---- TOPBAR ---- */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
        }

        .pill-btn {
            min-height: 54px;
            padding: 0 20px;
            font-size: 16px;
            font-weight: 500;
            color: #31c7ff;
            background: rgba(24,24,24,0.92);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 999px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 10px 24px rgba(0,0,0,0.24);
            cursor: pointer;
            transition: all 0.2s;
            text-transform: none;
        }
        .pill-btn:hover { background: rgba(40,40,40,0.92); }

        .title-stack { text-align: center; flex: 1; }
        .live-title { color: #18c4ff; font-size: 28px; font-weight: 700; line-height: 1.1; margin-bottom: 2px; }
        .live-subtitle { color: rgba(255,255,255,0.46); font-size: 14px; font-weight: 500; }

        .right-actions {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            min-height: 54px;
            background: rgba(24,24,24,0.92);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 999px;
        }

        .icon-btn {
            width: 44px; height: 44px; min-height: 44px;
            padding: 0;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: #6b74ff;
            background: transparent;
            border: 0;
            cursor: pointer;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .icon-btn:hover { background: rgba(255,255,255,0.06); }
        .icon-btn.close { background: #000; color: #31c7ff; font-size: 12px; font-weight: 700; border: 1.5px solid #31c7ff !important; }
        .icon-btn.close:hover { background: rgba(49,199,255,0.1); }
        .icon-btn.muted { opacity: 0.5; }

        .icon-svg { width: 24px; height: 24px; display: inline-block; }
        .icon-svg svg { width: 100%; height: 100%; stroke: currentColor; fill: none; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

        #clock { font-size: 13px; font-weight: 700; letter-spacing: 0.04em; color: #6b74ff; }

        /* ---- HERO / RING ---- */
        .hero { padding-top: 8px; text-align: center; }

        .timer-ring {
            --progress: 0;
            width: min(58vw, 340px);
            height: min(58vw, 340px);
            margin: 0 auto;
            border-radius: 50%;
            position: relative;
            background: conic-gradient(#12cfff calc(var(--progress) * 1turn), rgba(18,207,255,0.18) 0);
            box-shadow: 0 0 14px rgba(18,207,255,0.26), 0 0 40px rgba(18,207,255,0.08);
            padding: 8px;
        }
        .timer-ring.paused {
            background: conic-gradient(#ff4444 calc(var(--progress) * 1turn), rgba(255,68,68,0.18) 0);
            box-shadow: 0 0 14px rgba(255,68,68,0.3), 0 0 40px rgba(255,68,68,0.1);
        }
        .timer-ring::before {
            content: '';
            position: absolute;
            inset: 8px;
            border-radius: 50%;
            background: #000;
            box-shadow: inset 0 0 40px rgba(18,207,255,0.06);
        }

        .timer-center {
            position: absolute;
            inset: 0;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .level-line {
            color: rgba(255,255,255,0.52);
            font-size: clamp(13px, 1.8vw, 20px);
            letter-spacing: 0.16em;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 500;
        }

        #timer-display {
            font-size: clamp(68px, 13vw, 140px);
            line-height: 1;
            font-weight: 500;
            color: #12cfff;
            font-variant-numeric: tabular-nums;
            text-shadow: 0 0 18px rgba(18,207,255,0.26);
            transition: color 0.5s;
        }
        #timer-display.paused { color: #ff4444; font-size: clamp(36px, 7vw, 72px); text-shadow: 0 0 18px rgba(255,68,68,0.3); }
        #timer-display.warning { color: #ff4444; text-shadow: 0 0 18px rgba(255,68,68,0.3); }

        /* ---- BLINDES ---- */
        .blinds-block { margin-top: 22px; }

        .blind-info {
            margin: 0;
            font-size: clamp(32px, 6.5vw, 58px);
            line-height: 1;
            color: #ffd119;
            text-shadow: 0 0 14px rgba(255,209,25,0.14);
            font-weight: 700;
            text-align: center;
        }
        .blind-caption { margin-top: 10px; color: rgba(255,255,255,0.32); font-size: 22px; letter-spacing: 0.18em; text-transform: uppercase; }
        .blind-info-next { margin-top: 14px; font-size: 22px; color: rgba(255,255,255,0.55); text-align: center; font-weight: 500; }
        .pause-line { margin-top: 8px; font-size: 16px; text-align: center; color: #cf7a1e; min-height: 22px; }

        .resume-indicator {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 26px; margin-top: 8px; padding: 5px 12px;
            border-radius: 999px;
            background: rgba(60,127,255,0.16); border: 1px solid rgba(144,202,249,0.34);
            color: #b9dbff; font-size: 12px; letter-spacing: 0.04em;
            opacity: 0; transform: translateY(4px);
            transition: opacity 180ms ease, transform 180ms ease;
        }
        .resume-indicator.visible { opacity: 1; transform: translateY(0); }

        /* ---- STATS ---- */
        .stats-bar {
            margin-top: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            font-size: clamp(16px, 2.8vw, 26px);
            font-weight: 700;
        }
        .stats-bar .players { color: #00d2ff; }
        .stats-bar .sep { color: rgba(255,255,255,0.3); }
        .stats-bar .stack-label { color: white; }
        .stats-bar .stack-value { color: #ff4444; cursor: pointer; }
        .stats-bar a { color: white; text-decoration: underline; }

        /* ---- CONTROL DOCK ---- */
        .control-dock {
            margin: 28px auto 0;
            max-width: 620px;
            padding: 14px;
            border-radius: 28px;
            background: rgba(20,20,20,0.94);
            border: 1px solid rgba(255,255,255,0.08);
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            align-items: stretch;
        }

        .ctrl-btn {
            min-height: 64px;
            width: 100%;
            padding: 6px;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-size: 13px;
            font-weight: 700;
            background: #242424;
            color: #f3f5f9;
            border: 1px solid rgba(255,255,255,0.08);
            cursor: pointer;
            text-align: center;
            transition: transform 0.15s, background 0.15s;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.04);
        }
        .ctrl-btn:hover { transform: translateY(-1px); background: #2e2e2e; }
        .ctrl-btn:active { transform: translateY(0); }
        .ctrl-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

        .ctrl-icon { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; }
        .ctrl-icon svg { width: 100%; height: 100%; stroke: currentColor; fill: none; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
        .ctrl-btn small { color: rgba(255,255,255,0.58); font-size: 10px; font-weight: 600; }

        .ctrl-btn.pause-btn { background: rgba(114,62,17,0.45); border-color: rgba(255,149,48,0.45); color: #ff9a2f; }
        .ctrl-btn.pause-btn small { color: #ffb260; }
        .ctrl-btn.pause-btn:hover { background: rgba(130,72,22,0.6); }

        /* ---- ACTION DOCK ---- */
        .action-dock {
            margin: 18px auto 0;
            max-width: 620px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }

        .act-btn {
            min-height: 58px;
            width: 100%;
            padding: 10px 8px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            gap: 6px;
            background: #202020;
            color: #f4f6fb;
            border: 1px solid rgba(255,255,255,0.08);
            cursor: pointer;
            transition: transform 0.15s, background 0.15s;
        }
        .act-btn:hover { transform: translateY(-1px); background: #2a2a2a; }
        .act-btn.red { background: #2c2020; color: #ff8b8b; border-color: rgba(255,107,107,0.2); }
        .act-btn.blue { background: #1c2431; color: #b9dbff; border-color: rgba(100,180,255,0.2); }
        .act-btn.reset-link { background: #2c2020; color: #ff8b8b; border-color: rgba(255,107,107,0.2); text-decoration: none; font-size: 12px; text-align: center; border-radius: 16px; }

        /* ---- VOICE BUTTONS ---- */
        .voice-dock {
            margin: 18px auto 0;
            max-width: 620px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
        }
        .voice-btn {
            min-height: 40px;
            padding: 0 16px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s;
            border: 1px solid;
        }
        .voice-btn:hover { transform: translateY(-1px); }
        .voice-btn.cyan { color: #00d2ff; background: rgba(0,210,255,0.1); border-color: #00d2ff; }
        .voice-btn.yellow { color: #ffc107; background: rgba(255,193,7,0.1); border-color: #ffc107; }
        .voice-btn.green { color: #4cd137; background: rgba(76,209,55,0.1); border-color: #4cd137; }
        .voice-btn.red { color: #e84118; background: rgba(232,65,24,0.1); border-color: #e84118; }

        /* ---- PARTIE SELECTOR ---- */
        .partie-select-wrap {
            margin: 14px auto 0;
            max-width: 620px;
            text-align: center;
        }
        .partie-select {
            background: #1a1a1a;
            color: white;
            border: 1px solid rgba(100,180,255,0.3);
            border-radius: 12px;
            padding: 8px 14px;
            font-size: 14px;
            width: auto;
            max-width: 100%;
        }

        /* ---- RESPONSIVE ---- */
        @keyframes cdPulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.08)} }
        @media (max-width: 600px) {
            .topbar { gap: 6px; }
            .pill-btn { min-height: 44px; padding: 0 14px; font-size: 14px; }
            .right-actions { min-height: 44px; gap: 4px; padding: 4px 8px; }
            .live-title { font-size: 20px; }
            .live-subtitle { font-size: 11px; }
            .blind-caption { font-size: 16px; }
            .blind-info-next { font-size: 15px; }
            .control-dock { gap: 8px; padding: 10px; }
            .ctrl-btn { min-height: 72px; font-size: 12px; }
            .ctrl-btn small { font-size: 9px; }
            .action-dock { grid-template-columns: repeat(4, 1fr); gap: 6px; }
            .act-btn { min-height: 48px; font-size: 11px; padding: 8px 4px; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- TOPBAR -->
    <div class="topbar">
        <a href="quickview.php" class="pill-btn" style="text-decoration:none;">← Retour</a>
        <div class="title-stack">
            <div class="live-title">Live Timer</div>
            <div class="live-subtitle" id="timer-date-label">—</div>
        </div>
        <div class="right-actions">
            <button class="icon-btn" id="soundToggle" type="button" title="Son">
                <span class="icon-svg"><svg viewBox="0 0 24 24"><path d="M14 5l-5 4H5v6h4l5 4V5z"/><path d="M18 9.5a4 4 0 0 1 0 5"/><path d="M20.5 7a7.5 7.5 0 0 1 0 10"/></svg></span>
            </button>
            <button class="icon-btn" type="button" title="Heure"><span id="clock">--:--</span></button>
            <button class="icon-btn close" title="Compte à rebours 30s" onclick="toggleCountdown()">
                30s
            </button>
        </div>
    </div>

    <!-- HERO -->
    <section class="hero">

        <!-- RING TIMER -->
        <div class="timer-ring" id="timer-ring">
            <div class="timer-center">
                <div class="level-line">Niveau <span id="level-num">--</span> / <span id="level-total">--</span></div>
                <div id="timer-display">--:--</div>
            </div>
            <!-- COUNTDOWN OVERLAY -->
            <div id="countdown-overlay" style="display:none;position:absolute;inset:0;z-index:10;border-radius:50%;background:#000;display:none;flex-direction:column;align-items:center;justify-content:center;">
                <div id="cd-display" style="font-size:clamp(68px,13vw,140px);font-weight:500;color:#ffd119;font-variant-numeric:tabular-nums;line-height:1">30</div>
            </div>
        </div>

        <!-- BLINDES -->
        <div class="blinds-block">
            <div class="blind-info" id="blind-info">-- / --</div>
            <div class="blind-caption">Blindes</div>
            <div class="blind-info-next">→ <span id="next-blind-info">--</span><span id="pause-line" style="margin-left:14px;color:#12cfff;font-size:0.82em;"></span></div>
            <div class="resume-indicator" id="resume-indicator"></div>
        </div>

        <!-- STATS JOUEURS -->
        <div class="stats-bar">
            <span class="players" id="stats-active"><?php echo $active_players; ?></span>
            <a href="fullscreen-player.php?uid=<?php echo $id; ?>" class="stats-link">Joueurs</a>
            <span class="sep">/</span>
            <span id="stats-total"><?php echo $total_players; ?></span>
            <span class="sep">&nbsp;·&nbsp;</span>
            <span class="stack-label">Stack M.</span>
            <span class="stack-value" id="stack-value" onclick="announceStack()" title="Annoncer le stack moyen"><?php echo number_format($avg_stack, 0, ',', ' '); ?></span>
        </div>

        <!-- CONTROLE PRINCIPAL -->
        <?php if ($can_control): ?>
        <div class="control-dock">
            <button class="ctrl-btn" id="prevBtn" onclick="doAction('prev')">
                <span class="ctrl-icon"><svg viewBox="0 0 24 24"><path d="M11 7l-5 5 5 5"/><path d="M18 7l-5 5 5 5"/></svg></span>
                <small>Préc.</small>
            </button>
            <button class="ctrl-btn" id="minusBtn" onclick="doAction('adjust', -2)">
                <span class="ctrl-icon"><svg viewBox="0 0 24 24"><path d="M6 12h12"/></svg></span>
                <small>-2 min</small>
            </button>
            <button class="ctrl-btn pause-btn" id="pauseBtn" onclick="doAction('pause')">
                <span class="ctrl-icon"><svg viewBox="0 0 24 24" id="pause-icon"><path d="M10 8v8"/><path d="M14 8v8"/></svg></span>
                <small id="pause-label">Pause</small>
            </button>
            <button class="ctrl-btn" id="plusBtn" onclick="doAction('adjust', 2)">
                <span class="ctrl-icon"><svg viewBox="0 0 24 24"><path d="M12 6v12"/><path d="M6 12h12"/></svg></span>
                <small>+2 min</small>
            </button>
            <button class="ctrl-btn" id="nextBtn" onclick="doAction('next')">
                <span class="ctrl-icon"><svg viewBox="0 0 24 24"><path d="M13 7l5 5-5 5"/><path d="M6 7l5 5-5 5"/></svg></span>
                <small>Suiv.</small>
            </button>
        </div>

        <!-- ACTIONS SECONDAIRES -->
        <div class="action-dock">
            <a href="<?php echo htmlspecialchars($resetBlindsUrl, ENT_QUOTES, 'UTF-8'); ?>" class="act-btn red" onclick="return confirm('Réinitialiser les blindes ?');" style="text-decoration:none;">🔁 Restart Partie</a>
            <button class="act-btn blue" onclick="playWelcomeMessage()">👋 Bienvenue</button>
            <button class="act-btn blue" onclick="playRulesMessage()">⚖️ Règles</button>
            <button class="act-btn blue" onclick="playBlindsMessage()">💰 Blindes</button>
        </div>
        <?php endif; ?>



    <!-- DEBUG VISIBLE - à supprimer après fix -->

    </section>
</div>

<?php
// --- DONNÉES INITIALES POUR LE TIMER (PHP inline) ---
$_now = time();
$_qp = mysqli_query($con, "SELECT `en_pause`, `heure_pause` FROM `blindes-live` WHERE `id-activite` = '$id' AND `ordre` = '1' LIMIT 1");
$_rp = $_qp ? mysqli_fetch_assoc($_qp) : [];
$_init_paused = (intval($_rp['en_pause'] ?? 0) == 1);
$_init_hpause = $_rp['heure_pause'] ?? null;
$_qb = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
$_bl = [];
if ($_qb) { while ($_b = mysqli_fetch_assoc($_qb)) { $_bl[] = $_b; } }
$_cur = null; $_ci = -1;
foreach ($_bl as $_k => $_b) {
    if (strtotime($_b['fin']) > $_now) { $_cur = $_b; $_ci = $_k; break; }
}
if ($_cur) {
    if ($_init_paused && $_init_hpause) {
        $_isec = max(0, strtotime($_cur['fin']) - strtotime($_init_hpause));
    } else {
        $_isec = max(0, strtotime($_cur['fin']) - $_now);
    }
    $_debut_ts = strtotime($_cur['debut']); $_fin_ts = strtotime($_cur['fin']);
    $_dur_raw = ($_debut_ts && $_debut_ts > 86400) ? ($_fin_ts - $_debut_ts) : 0;
    $_idur = ($_dur_raw > 0 && $_dur_raw <= 7200) ? $_dur_raw : intval($_isec);
    $_isb = intval($_cur['sb'] ?? 0); $_ibb = intval($_cur['bb'] ?? 0); $_iant = intval($_cur['ante'] ?? 0);
    $_ibrk = ($_isb == 0 && $_ibb == 0);
    $_ibt  = $_ibrk ? 'PAUSE' : number_format($_isb,0,',',' ') . ' / ' . number_format($_ibb,0,',',' ');
    $_iat  = ($_iant > 0) ? 'Ante : ' . number_format($_iant,0,',',' ') : '';
    $_inm  = $_cur['nom'] ?? '';
    $_iln  = $_inm ?: ($_ci + 1);
    $_inxt = '';
    if (isset($_bl[$_ci+1])) { $_in=$_bl[$_ci+1]; $_ins=intval($_in['sb']??0); $_inb=intval($_in['bb']??0); $_inxt=($_ins==0&&$_inb==0)?'PAUSE':$_ins.'/'.$_inb; }
    // Calcul next_pause pour INIT_TIMER
    $_inp = '';
    for ($_pi = $_ci + 1; $_pi < count($_bl); $_pi++) {
        $_pb = $_bl[$_pi];
        if (intval($_pb['sb']??0) == 0 && intval($_pb['bb']??0) == 0) {
            $_ps = strtotime($_bl[$_pi-1]['fin']) - $_now;
            if ($_ps > 0) { $_ph=floor($_ps/3600); $_pm=floor(($_ps%3600)/60); $_inp=$_ph>0?'dans '.$_ph.'h'.str_pad($_pm,2,'0',STR_PAD_LEFT):'dans '.$_pm.'m'; }
            break;
        }
    }
    $INIT_TIMER = json_encode(['status'=>$_init_paused?'paused':'running','seconds_remaining'=>intval($_isec),'duration_seconds'=>intval($_idur),'blinds_text'=>$_ibt,'blinds_raw'=>$_isb.'/'.$_ibb,'ante_text'=>$_iat,'level_name'=>(string)$_iln,'level_index'=>$_ci,'level_total'=>count($_bl),'is_paused'=>$_init_paused,'next_blinds_raw'=>$_inxt,'next_blinds_text'=>$_inxt,'next_pause'=>$_inp,'avg_stack'=>number_format($avg_stack,0,',',' '),'players_active'=>$active_players,'players_total'=>$total_players]);
} else {
    $INIT_TIMER = json_encode(['status'=>'finished']);
}
?>
<script>
    const ACT_ID = <?php echo $id; ?>;
    const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const API_URL = 'livetimer.php?uid=' + ACT_ID + '&action=status';
    const ACTION_URL = 'livetimer.php?uid=' + ACT_ID + '&action=';
    const INIT_TIMER = <?php echo $INIT_TIMER; ?>;

    // ---- STATE ----
    let isPaused = false;
    let secondsLeft = 0;
    let totalDuration = 0;
    let currentBlindsName = '';
    let lastSB = '', lastBB = '';
    let lastAvgStack = '';
    let lastDurationMin = 0;
    let lastPlayersActive = 0;
    let lastPlayersTotal = 0;
    let speechUnlocked = false;
    let speechVoice = null;
    let localInterval = null;
    let actionInProgress = false;
    let currentLevelIndex = -1;
    let resumeIndicatorTimeout = null;

    // ---- CLOCK ----
    function updateClock() {
        const now = new Date();
        const h = now.getHours().toString().padStart(2, '0');
        const m = now.getMinutes().toString().padStart(2, '0');
        document.getElementById('clock').textContent = h + ':' + m;
        const label = document.getElementById('timer-date-label');
        if (label) {
            const fmt = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }).format(now);
            label.textContent = fmt.charAt(0).toUpperCase() + fmt.slice(1);
        }
    }
    setInterval(updateClock, 1000);
    updateClock();

    // ---- DISPLAY UPDATE ----
    function updateDisplay() {
        const ring = document.getElementById('timer-ring');
        const display = document.getElementById('timer-display');
        if (!ring || !display) return;

        if (isPaused) {
            display.textContent = 'PAUSE';
            display.className = 'paused';
            ring.className = 'timer-ring paused';
            ring.style.setProperty('--progress', '0');
        } else {
            const m = Math.floor(Math.max(0, secondsLeft) / 60).toString().padStart(2, '0');
            const s = (Math.max(0, secondsLeft) % 60).toString().padStart(2, '0');
            display.textContent = m + ':' + s;
            display.className = secondsLeft <= 120 && secondsLeft > 0 ? 'warning' : '';
            ring.className = 'timer-ring';
            if (totalDuration > 0) {
                const elapsed = totalDuration - secondsLeft;
                const progress = Math.max(0, Math.min(1, elapsed / totalDuration));
                ring.style.setProperty('--progress', progress.toFixed(4));
            }
        }
    }

    // ---- LOCAL TICK (entre les syncs) ----
    function startLocalTick() {
        if (localInterval) clearInterval(localInterval);
        if (!isPaused) {
            localInterval = setInterval(() => {
                if (!isPaused && secondsLeft > 0) {
                    secondsLeft--;
                    updateDisplay();
                }
            }, 1000);
        }
    }

    // ---- SYNC DEPUIS API ----
    async function sync(initialData) {
        if (actionInProgress && !initialData) return;
        try {
            let data;
            if (initialData) {
                data = initialData;
            } else {
                const res = await fetch(API_URL + '&t=' + Date.now());
                if (!res.ok) return;
                data = await res.json();
            }
            if (data.status === 'error' || data.status === 'finished') {
                if (data.status === 'finished') {
                    document.getElementById('timer-display').textContent = 'FIN';
                }
                return;
            }

            isPaused = !!data.is_paused;
            currentBlindsName = data.blinds_raw || '';

            // Parse SB/BB
            if (/^(\d{1,6})[-/](\d{1,6})$/.test(currentBlindsName)) {
                const m = currentBlindsName.match(/^(\d{1,6})[-/](\d{1,6})$/);
                lastSB = m[1]; lastBB = m[2];
            } else { lastSB = ''; lastBB = ''; }

            const newLevel = data.level_index !== undefined ? data.level_index : -1;
            if (data.duration_seconds) {
                const newDur = parseInt(data.duration_seconds);
                // Ne remplacer totalDuration que si c'est un nouveau niveau ou si on n'en a pas encore
                if (totalDuration === 0 || newLevel !== currentLevelIndex) {
                    totalDuration = newDur;
                    lastDurationMin = Math.floor(totalDuration / 60);
                }
            }
            currentLevelIndex = newLevel;
            lastAvgStack = data.avg_stack || '';

            const serverSecs = data.seconds_remaining || 0;
            if (!isPaused) {
                // Tolérance de 3s pour éviter les sauts visuels
                if (Math.abs(secondsLeft - serverSecs) > 3 || secondsLeft === 0) {
                    secondsLeft = serverSecs;
                }
                startLocalTick();
            } else {
                if (localInterval) { clearInterval(localInterval); localInterval = null; }
                secondsLeft = serverSecs;
            }

            // Niveau
            if (data.level_index !== undefined) document.getElementById('level-num').textContent = data.level_index + 1;
            if (data.level_total !== undefined) document.getElementById('level-total').textContent = data.level_total;

            // Mise à jour pause/resume btn
            const pauseIcon = document.getElementById('pause-icon');
            const pauseLabel = document.getElementById('pause-label');
            const pauseBtn = document.getElementById('pauseBtn');
            if (isPaused) {
                if (pauseIcon) pauseIcon.innerHTML = '<path d="M8 6l10 6-10 6z"/>';
                if (pauseLabel) pauseLabel.textContent = 'Reprendre';
                if (pauseBtn) pauseBtn.style.background = 'rgba(60,180,60,0.35)';
            } else {
                if (pauseIcon) pauseIcon.innerHTML = '<path d="M10 8v8"/><path d="M14 8v8"/>';
                if (pauseLabel) pauseLabel.textContent = 'Pause';
                if (pauseBtn) pauseBtn.style.background = '';
            }

            // Blindes
            const blindInfo = document.getElementById('blind-info');
            if (blindInfo) {
                let txt = data.blinds_text || currentBlindsName;
                if (data.ante_text) txt += '<br><span style="color:#00d2ff; font-size:0.7em;">' + data.ante_text + '</span>';
                blindInfo.innerHTML = txt;
            }

            // Niveau
            if (data.level_name) document.getElementById('level-num').textContent = data.level_name.replace(/^(Niveau\s*|Level\s*)/i, '');
            if (data.level_total) document.getElementById('level-total').textContent = data.level_total;

            // Prochain niveau
            const nextSpan = document.getElementById('next-blind-info');
            if (nextSpan) {
                const nextRaw = data.next_blinds_text || data.next_blinds_raw || data.next_level_blinds || data.next_blinds || '';
                nextSpan.textContent = nextRaw ? String(nextRaw).replace(/\//g, ' / ') : '--';
            }

            // Pause info (inline avec les prochaines blindes)
            const pauseLine = document.getElementById('pause-line');
            if (pauseLine) {
                if (data.next_pause) {
                    pauseLine.textContent = '· Pause ' + data.next_pause;
                } else {
                    pauseLine.textContent = '';
                }
            }

            // Stats joueurs
            if (data.players_active !== undefined) {
                lastPlayersActive = data.players_active;
                lastPlayersTotal = data.players_total;
                document.getElementById('stats-active').textContent = data.players_active;
                document.getElementById('stats-total').textContent = data.players_total;
            }
            if (data.avg_stack) {
                lastAvgStack = data.avg_stack;
                const sv = document.getElementById('stack-value');
                if (sv) sv.textContent = data.avg_stack;
            }

            updateDisplay();
        } catch (e) {
            console.warn('Sync error', e);
        }
    }

    // Seed immédiat depuis PHP (pas de fetch nécessaire)
    // Pré-initialiser totalDuration AVANT sync pour que le premier updateDisplay() fonctionne
    if (INIT_TIMER && INIT_TIMER.duration_seconds) {
        totalDuration = parseInt(INIT_TIMER.duration_seconds);
    }
    if (INIT_TIMER && INIT_TIMER.seconds_remaining) {
        secondsLeft = parseInt(INIT_TIMER.seconds_remaining);
    }
    sync(INIT_TIMER);
    // Sync réseau toutes les 5 secondes
    setInterval(sync, 5000);

    // ---- ACTIONS ----
    async function doAction(action, param) {
        actionInProgress = true;
        let url = ACTION_URL + action;
        if (action === 'adjust' && param !== undefined) url += '&min=' + param;

        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                // Feedback visuel immédiat
                if (action === 'pause') {
                    isPaused = !isPaused;
                    updateDisplay();
                } else if (action === 'adjust') {
                    secondsLeft = Math.max(0, secondsLeft + param * 60);
                    updateDisplay();
                }
                // Resync après 800ms
                setTimeout(() => { actionInProgress = false; sync(); }, 800);
            } else {
                actionInProgress = false;
            }
        } catch (e) {
            console.warn('Action error', e);
            actionInProgress = false;
        }
    }

    // ---- RESTART (niveau 1 + timer) ----
    function confirmRestart() {
        if (!confirm('Redémarrer les blindes depuis le niveau 1 ?')) return;
        doAction('restart');
    }

    // ---- ANNONCE STACK ----
    function announceStack() {
        const el = document.getElementById('stack-value');
        if (!el) return;
        const val = el.textContent.replace(/\s/g, '');
        const num = parseInt(val);
        if (isNaN(num) || num <= 0) return;
        speakAnnouncement('Stack moyen : ' + num.toLocaleString('fr-FR'));
    }

    // ---- VOICE ----
    function getVoiceName() {
        if (typeof responsiveVoice === 'undefined') return 'French Female';
        const voices = responsiveVoice.getVoices() || [];
        for (const v of voices) {
            if ((v.name || '') === 'French Female') return 'French Female';
        }
        for (const v of voices) {
            if ((v.name || '').includes('Amelie')) return v.name;
        }
        for (const v of voices) {
            const lang = (v.lang || '').toLowerCase();
            const name = (v.name || '').toLowerCase();
            if (lang.startsWith('fr') || name.includes('french')) return v.name || 'French Female';
        }
        return 'French Female';
    }

    function speakAnnouncement(text) {
        const soundToggle = document.getElementById('soundToggle');
        if (soundToggle && soundToggle.classList.contains('muted')) return;
        if (!text) return;

        if (typeof responsiveVoice !== 'undefined') {
            try {
                responsiveVoice.speak(text, getVoiceName(), { rate: 0.95, pitch: 1, volume: 1 });
                return;
            } catch(e) {}
        }
        if ('speechSynthesis' in window) {
            speechVoice = speechVoice || (window.speechSynthesis.getVoices().find(v => v.lang.startsWith('fr')) || null);
            const utter = new SpeechSynthesisUtterance(text);
            utter.lang = speechVoice?.lang || 'fr-FR';
            utter.voice = speechVoice;
            utter.rate = 0.95;
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utter);
        }
    }

    function unlockSpeech() {
        if (speechUnlocked) return;
        speechUnlocked = true;
        if ('speechSynthesis' in window) {
            const p = new SpeechSynthesisUtterance(' ');
            p.volume = 0.01; p.lang = 'fr-FR';
            window.speechSynthesis.speak(p);
            window.speechSynthesis.cancel();
            speechVoice = window.speechSynthesis.getVoices().find(v => v.lang.startsWith('fr')) || null;
        }
        if (typeof responsiveVoice !== 'undefined') {
            try { responsiveVoice.setDefaultVoice(getVoiceName()); } catch(e) {}
        }
    }

    // ---- MESSAGES VOCAUX ----
    function playWelcomeMessage() {
        let text = 'Bienvenue à tous ! Le tournoi vient de commencer. ';
        if (lastSB && lastBB) text += `Les blindes de départ sont ${lastSB} et ${lastBB}. `;
        if (lastDurationMin) text += `Les niveaux durent ${lastDurationMin} minutes. `;
        text += 'Bonne chance à tous !';
        speakAnnouncement(text);
    }

    function playRulesMessage() {
        speakAnnouncement("Petit rappel des règles : pas de boisson ou nourriture sur les tables, on ne fume pas à l'intérieur, il y a des poubelles donc on ne laisse rien traîner, un joueur debout à la première carte posée est éliminé, seul le croupier touche les jetons. Bonne partie à tous !");
    }

    function playBlindsMessage() {
        let text = 'Rappel : les blindes actuelles sont ';
        if (lastSB && lastBB) text += `${lastSB} et ${lastBB}`;
        else text += 'non disponibles';
        if (lastDurationMin) text += `, pour des niveaux de ${lastDurationMin} minutes`;
        if (lastAvgStack) text += `. Le stack moyen est de ${lastAvgStack}`;
        if (lastPlayersActive > 0) text += `. Il reste ${lastPlayersActive} joueurs sur ${lastPlayersTotal}`;
        text += '.';
        speakAnnouncement(text);
    }

    function playSirenAlert() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator();
            const g = ctx.createGain();
            o.type = 'sine'; o.connect(g); g.connect(ctx.destination);
            let t = ctx.currentTime;
            for (let i = 0; i < 3; i++) {
                o.frequency.setValueAtTime(440, t + i*0.6);
                o.frequency.linearRampToValueAtTime(1760, t + i*0.3 + i*0.6);
                o.frequency.linearRampToValueAtTime(440, t + (i+1)*0.6);
            }
            g.gain.setValueAtTime(0.2, t);
            o.start(t); o.stop(t + 1.8);
            o.onended = () => ctx.close();
        } catch(e) {
            speakAnnouncement('Alerte sirène !');
        }
    }

    // ---- COMPTE À REBOURS 30s ----
    let cdTime = 30;
    let cdInterval = null;
    let cdRunning = false;
    let cdOpen = false;

    function toggleCountdown() {
        cdOpen = !cdOpen;
        const overlay = document.getElementById('countdown-overlay');
        overlay.style.display = cdOpen ? 'flex' : 'none';
        if (cdOpen) { cdReset(); cdStart(); }
        else { cdStop(); cdReset(); }
    }

    function cdUpdateDisplay() {
        const el = document.getElementById('cd-display');
        if (!el) return;
        el.textContent = cdTime;
        el.style.color = cdTime <= 5 ? '#ff4444' : '#ffd119';
        el.style.animation = cdTime <= 5 && cdTime > 0 ? 'cdPulse 0.5s infinite' : 'none';
    }

    function cdStart() {
        if (cdRunning || cdTime <= 0) return;
        cdRunning = true;
        cdInterval = setInterval(() => {
            cdTime--;
            cdUpdateDisplay();
            if (cdTime <= 0) {
                clearInterval(cdInterval); cdRunning = false;
                cdPlayAlarm();
                speakAnnouncement('Temps écoulé !');
                setTimeout(() => {
                    cdOpen = false;
                    document.getElementById('countdown-overlay').style.display = 'none';
                    cdReset();
                }, 1500);
            }
        }, 1000);
    }

    function cdStop() {
        if (!cdRunning) return;
        clearInterval(cdInterval); cdRunning = false;
    }

    function cdReset() {
        cdStop(); cdTime = 30; cdUpdateDisplay();
    }

    function cdPlayAlarm() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            // 3 bips descendants
            [880, 660, 440].forEach((freq, i) => {
                const o = ctx.createOscillator();
                const g = ctx.createGain();
                o.type = 'sine';
                o.frequency.setValueAtTime(freq, ctx.currentTime);
                g.gain.setValueAtTime(0, ctx.currentTime + i * 0.28);
                g.gain.linearRampToValueAtTime(0.5, ctx.currentTime + i * 0.28 + 0.02);
                g.gain.linearRampToValueAtTime(0, ctx.currentTime + i * 0.28 + 0.22);
                o.connect(g); g.connect(ctx.destination);
                o.start(ctx.currentTime + i * 0.28);
                o.stop(ctx.currentTime + i * 0.28 + 0.25);
            });
            setTimeout(() => ctx.close(), 1200);
        } catch(e) {}
    }

    // ---- SON TOGGLE ----
    document.getElementById('soundToggle').addEventListener('click', function() {
        const muted = this.classList.toggle('muted');
        this.querySelector('svg').innerHTML = muted
            ? '<path d="M14 5l-5 4H5v6h4l5 4V5z"/><path d="M19 9l-8 8"/><path d="M11 9l8 8"/>'
            : '<path d="M14 5l-5 4H5v6h4l5 4V5z"/><path d="M18 9.5a4 4 0 0 1 0 5"/><path d="M20.5 7a7.5 7.5 0 0 1 0 10"/>';
    });

    // ---- UNLOCK AU PREMIER GESTE ----
    ['click', 'touchstart'].forEach(ev => {
        document.addEventListener(ev, unlockSpeech, { once: true, passive: true });
    });

    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = () => {
            speechVoice = window.speechSynthesis.getVoices().find(v => v.lang.startsWith('fr')) || null;
        };
    }
</script>
</body>
</html>
