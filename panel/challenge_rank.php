<?php
session_start();
// allow temporary debug mode via ?_debug=1 to bypass auth and show errors
$debug_mode = isset($_GET['_debug']) && $_GET['_debug'] === '1';
if ($debug_mode) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
}
include(__DIR__ . '/include/config.php');

 $uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0 && !$debug_mode) {
    $_SESSION['redirect'] = 'panel/challenge_rank.php';
    header('Location: logout.php');
    exit;
}

// Determine filter scope: if ?uid=ACTIVITY_ID provided, only include activities in same "challenge" group
$rows = [];
$where_activity = '0=1';
$detected_challenge_msg = '';
 $today = date('Y-m-d');
if (!empty($con)) {
    // detect which challenge column exists on `activite` to avoid referencing missing columns
    $activity_cols = [];
    $colres = @mysqli_query($con, "SHOW COLUMNS FROM activite");
    if ($colres) {
        while ($cr = mysqli_fetch_assoc($colres)) { $activity_cols[] = $cr['Field']; }
    }
    $activity_challenge_col = null;
    foreach (array('id_challenge','id-challenge','challenge_id','idchall','id_chall') as $c) {
        if (in_array($c, $activity_cols)) { $activity_challenge_col = $c; break; }
    }
    $filter_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : 0;
    // If no activity uid provided, accept direct challenge id via GET/POST (id_challenge, id-challenge, challenge_id)
    $provided_challenge = 0;
    $possible_cnames = array('id_challenge','id-challenge','challenge_id','idchall','id_chall');
    foreach ($possible_cnames as $cn) {
        if (isset($_REQUEST[$cn]) && is_numeric($_REQUEST[$cn])) { $provided_challenge = intval($_REQUEST[$cn]); $provided_challenge_col = $cn; break; }
    }

    if ($filter_uid) {
        // Try to infer grouping keys from the activity row
        $actq = @mysqli_query($con, "SELECT * FROM activite WHERE `id-activite`='".intval($filter_uid)."' LIMIT 1");
        if ($actq && ($act = mysqli_fetch_assoc($actq))) {
            $detected_challenge_msg = 'Activité trouvée: id-activite=' . intval($filter_uid);
            // Prefer explicit challenge id when present
            $challenge_id = null;
            $challenge_col = null;
            foreach (array('id_challenge','id-challenge','challenge_id','idchall','id_chall') as $c) {
                if (isset($act[$c]) && $act[$c] !== '') { $challenge_id = intval($act[$c]); $challenge_col = $c; break; }
            }
            if ($challenge_id && $challenge_col) {
                // Prefer explicit id_challenge found on the activity row: use the exact column name
                $col = $challenge_col;
                $where_activity = "(a.`" . $col . "` = " . $challenge_id . ") AND a.date_depart < '" . $today . "'";
                $detected_challenge_msg .= ' — Filtre appliqué: ' . $col . ' = ' . $challenge_id;
            } else {
                $conds = [];
                // 1) same id_structure / id-structure
                foreach (array('id_structure','id-structure','id_structuree','id-structuree') as $c) {
                    if (!empty($act[$c])) {
                        $si = intval($act[$c]);
                        $conds[] = "COALESCE(a.`$c`,0) = $si";
                    }
                }
                // 2) same structure number if available (structure_num)
                if (!empty($act['structure_num'])) {
                    $sn = mysqli_real_escape_string($con, $act['structure_num']);
                    $conds[] = "COALESCE(a.`structure_num`,'') = '". $sn ."'";
                }
                // 3) same title prefix (text before ':')
                if (!empty($act['titre-activite']) || !empty($act['titre_activite']) || !empty($act['title'])) {
                    $title = !empty($act['titre-activite'])? $act['titre-activite'] : (!empty($act['titre_activite'])? $act['titre_activite'] : $act['title']);
                    $title = trim($title);
                    if ($title !== '') {
                        $prefix = explode(':', $title)[0];
                        $prefix = trim($prefix);
                        if ($prefix !== '') {
                            $prefix_esc = mysqli_real_escape_string($con, $prefix);
                            $conds[] = "a.`titre-activite` LIKE '". $prefix_esc ."%'";
                        }
                    }
                }
                // fallback: restrict to the single activity
                if (empty($conds)) {
                    $where_activity = "a.`id-activite` = " . intval($filter_uid) . " AND a.date_depart < '" . $today . "'";
                    $detected_challenge_msg .= ' — Aucun id_challenge; restreint à id-activite ' . intval($filter_uid);
                } else {
                    $where_activity = '(' . implode(' OR ', $conds) . ") AND a.date_depart < '" . $today . "'";
                    $detected_challenge_msg .= ' — Aucun id_challenge; heuristique WHERE: ' . $where_activity;
                }
            }
        } else {
            // activity not found: restrict to nothing
            $where_activity = '0=1';
            $detected_challenge_msg = 'Activité introuvable: ' . intval($filter_uid);
        }
    }
    else if ($provided_challenge) {
        $detected_challenge_msg = 'Filtré par paramètre fourni: '. $provided_challenge_col .'=' . $provided_challenge;
        if ($activity_challenge_col) {
            $where_activity = "(a.`" . $activity_challenge_col . "` = " . $provided_challenge . ") AND a.date_depart < '" . $today . "'";
        } else {
            // Fallback: if activite has no explicit challenge id column, restrict by the challenge date range
            $pc = intval($provided_challenge);
            $cres = @mysqli_query($con, "SELECT chal_deb, chal_fin FROM challenge WHERE id_challenge = " . $pc . " LIMIT 1");
            if ($cres && ($crow2 = mysqli_fetch_assoc($cres)) && !empty($crow2['chal_deb']) && !empty($crow2['chal_fin'])) {
                $deb = mysqli_real_escape_string($con, $crow2['chal_deb']);
                $fin = mysqli_real_escape_string($con, $crow2['chal_fin']);
                $where_activity = "(a.date_depart BETWEEN '" . $deb . "' AND '" . $fin . "') AND a.date_depart < '" . $today . "'";
                $detected_challenge_msg .= ' — Filtre appliqué par date: ' . $deb . ' → ' . $fin;
            } else {
                $where_activity = '0=1';
                $detected_challenge_msg .= ' — aucune colonne id_challenge détectée sur activite et date challenge introuvable';
            }
        }
    }
    else {
        // fallback: use current month challenge like liste-membres-challenge-itm.php
        $resc = @mysqli_query($con, "SELECT id_challenge FROM challenge WHERE '$today' BETWEEN chal_deb AND chal_fin ORDER BY chal_deb DESC LIMIT 1");
        if ($resc && ($crow = mysqli_fetch_assoc($resc)) && !empty($crow['id_challenge'])) {
            $provided_challenge = intval($crow['id_challenge']);
            // do not expose the explicit current challenge id in the UI
            $detected_challenge_msg = '';
            if ($activity_challenge_col) {
                $where_activity = "(a.`" . $activity_challenge_col . "` = " . $provided_challenge . ") AND a.date_depart < '" . $today . "'";
            } else {
                $where_activity = '0=1';
                $detected_challenge_msg .= ' — aucune colonne id_challenge détectée sur activite';
            }
        } else {
            $detected_challenge_msg = 'Aucun uid fourni';
        }
    }

    // Fetch leaderboard limited to activities matching the inferred scope
    // Define ITM as count of participations where gain > 0
    $sql = "SELECT m.`id-membre` AS mid, m.pseudo AS pseudo, m.photo AS photo, COALESCE(SUM(COALESCE(p.points,0)),0) AS pts, SUM(CASE WHEN COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS itm, SUM(CASE WHEN COALESCE(p.classement,0)=1 AND COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS vic, SUM(CASE WHEN COALESCE(p.classement,999)>0 AND COALESCE(p.classement,999)<=3 THEN 1 ELSE 0 END) AS tf, COUNT(p.`id-activite`) AS parts, COALESCE(SUM(p.gain),0) AS sum_gain FROM participation p JOIN membres m ON m.`id-membre` = p.`id-membre` JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $where_activity . " GROUP BY p.`id-membre` ORDER BY pts DESC, vic DESC LIMIT 200";
    $q = @mysqli_query($con, $sql);
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) { $rows[] = $r; }
    }
}

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Classement Challenge</title>
    <style>
        body{background:rgba(0,0,0,0.85);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;color:#e6eef8}
        .sheet{max-width:760px;margin:10px auto;border-radius:12px;overflow:hidden;background:#0b1220;color:#e6eef8}
        .header{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid rgba(255,255,255,0.04);background:linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.005))}
        .back{height:36px;border-radius:8px;background:transparent;border:1px solid rgba(255,157,59,0.12);display:flex;align-items:center;justify-content:center;padding:6px 10px;font-size:14px;cursor:pointer;color:#ff9d3b;font-weight:700}
        .title{font-weight:800;font-size:15px;text-align:center;flex:1;color:#16a34a}
        .menu{display:none}
        .list{min-height:120px}
        .row{display:flex;align-items:center;padding:4px 8px;border-bottom:1px solid rgba(255,255,255,0.02);line-height:1.15}
        .rank{width:30px;color:#9aa6b1;font-weight:700;font-size:13px}
        .who{flex:1;display:flex;align-items:center;gap:8px}
        .who .avatar{width:32px;height:32px;border-radius:6px;overflow:hidden;background:#0a0f14}
        .who .name{font-weight:700;color:#fff;font-size:14px;line-height:1.1}
        .pts{width:70px;text-align:right;font-weight:800;color:#d1e7ff;font-size:13px}
        .smallcol{width:48px;text-align:center;color:#9fd;font-size:13px}
        /* Center header labels and column cells to align with labels */
        .cols-header{display:flex;gap:10px;align-items:center;font-weight:700;color:#3CA6FF;font-size:13px}
        .cols-header > div{display:flex;align-items:center;justify-content:center}
        .pts{width:80px;text-align:left}
        .rank{width:36px;text-align:left}
        .who{flex:1;display:flex;align-items:center;justify-content:flex-start;gap:8px}
        .smallcol{width:48px;text-align:left;color:#9fd;font-size:13px}
        /* Narrow the last column (Parts) to save horizontal space */
        .smallcol:last-child { width:40px; }
    </style>
</head>
<body>
    <div class="sheet" role="application">
        <div class="header">
            <div class="title"> Classement ITM-2000</div>
            <a class="back" href="/panel/profile.php?uid=<?php echo intval($uid); ?>" aria-label="Fermer">Fermer</a>
        </div>
        <div class="cols-header" style="padding:8px 12px;border-bottom:1px solid rgba(0,0,0,0.04);">
            <div style="width:36px"></div>
            <div style="flex:1">Pseudo</div>
            <div style="width:80px;">POINTS</div>
            <div style="width:48px;">ITM</div>
            <div style="width:48px;">WIN</div>
            <div style="width:40px;">PART</div>
            <div style="width:56px;">JETONS</div>
        </div>
        <!-- detected challenge message intentionally hidden -->
        <div class="list">
            <?php
            if (empty($rows)) {
                echo '<div style="padding:16px 12px;color:#666">Aucun résultat.</div>';
            } else {
                $i = 0;
                foreach ($rows as $r) {
                    $i++;
                    $photo = !empty($r['photo']) ? 'https://viendez.com/images/faces/'.rawurlencode(basename($r['photo'])) : 'https://viendez.com/images/noprofil.jpg';
                    echo '<div class="row">';
                    echo '<div class="rank">' . $i . '</div>';
                    $is_me = (intval($r['mid']) === intval($uid));
                    $display_name = $is_me ? ('<span style="color:#16a34a">' . esc($r['pseudo']) . '</span>') : esc($r['pseudo']);
                    echo '<div class="who"><div class="avatar"><img src="'.esc($photo).'" alt="" style="width:100%;height:100%;object-fit:cover"></div><div><div class="name">' . $display_name . '</div></div></div>';
                    // Build link to activities that contributed to points
                    $link_params = '/panel/activities_points.php?uid=' . intval($r['mid']);
                    if (!empty($filter_uid)) { $link_params .= '&aid=' . intval($filter_uid); }
                    if (!empty($provided_challenge)) {
                        // choose a sensible challenge_col parameter: prefer explicitly provided column,
                        // otherwise use detected activity challenge column, or fallback to date-range mode
                        $colparam = '';
                        if (!empty($provided_challenge_col)) { $colparam = $provided_challenge_col; }
                        else if (!empty($activity_challenge_col)) { $colparam = $activity_challenge_col; }
                        else { $colparam = 'chal_dates'; }
                        $link_params .= '&challenge=' . intval($provided_challenge) . '&challenge_col=' . urlencode($colparam);
                    }
                    $jetons_val = min(50000, 35000 + (floatval($r['sum_gain']) / 10) * 200);
                    echo '<div class="pts"><a href="'. esc($link_params) .'" style="color:#ffffff;text-decoration:underline;text-decoration-color:#3CA6FF">'.number_format(intval($r['pts']),0,',',' ').'</a> </div>';
                    echo '<div class="smallcol">'.intval($r['itm']).'</div>';
                    echo '<div class="smallcol">'.intval($r['vic']).'</div>';
                    echo '<div class="smallcol">'.intval($r['parts']).'</div>';
                    echo '<div class="smallcol" style="width:56px;color:#ffd97d">'.number_format((int)$jetons_val,0,',',' ').'</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
