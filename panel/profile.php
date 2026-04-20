<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');
include(__DIR__ . '/../include/functions_logs.php');

// French date formatter helper (Intl when available, lightweight fallback otherwise)
function fmt_fr_date($dt, $pattern = "EEEE d MMM (dd/MM)", $tz = 'Europe/Paris'){
    if (empty($dt)) return '—';
    try{
        if (class_exists('IntlDateFormatter')){
            $fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, $tz, IntlDateFormatter::GREGORIAN, $pattern);
            $d = new DateTime($dt);
            return $fmt->format($d);
        }
    }catch(Throwable $e){}
    // fallback: simple French mapping for the patterns we use
    $ts = strtotime($dt);
    if (!$ts) return $dt;
    $days = ['lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche'];
    $months = ['janv','fév','mars','avr','mai','juin','juil','août','sept','oct','nov','déc'];
    if (strpos($pattern, 'EEEE') !== false) {
        $day = $days[intval(date('N',$ts)) - 1];
        $d = intval(date('j',$ts));
        $month = $months[intval(date('n',$ts)) - 1];
        $dd = date('d',$ts);
        return ucfirst($day) . ' ' . $d . ' ' . $month . ' (' . $dd . '/' . date('m',$ts) . ')';
    }
    // default fallback: d MMM HH:mm
    $d = intval(date('j',$ts));
    $month = $months[intval(date('n',$ts)) - 1];
    $time = date('H:i',$ts);
    return $d . ' ' . $month . ' ' . $time;
}

$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    // redirect to login flow if needed
    $_SESSION['redirect'] = 'panel/profile.php';
    header('Location: logout.php');
    exit;
}

$user = ['pseudo' => 'Visiteur', 'photo' => 'noprofil.jpg'];
$q = @mysqli_query($con, "SELECT * FROM membres WHERE `id-membre` = '" . intval($uid) . "' LIMIT 1");
if ($q && ($r = mysqli_fetch_assoc($q))) {
    $user = $r;
}

// Count tombola tickets for this member
$tombola_count = 0;
$qt = @mysqli_query($con, "SELECT COUNT(*) AS c FROM `collections-individu` WHERE `id-indiv` = '".intval($uid)."'");
if ($qt && ($rt = mysqli_fetch_assoc($qt))) $tombola_count = intval($rt['c']);

// last inscription date
$last_insc = null;
$q2 = @mysqli_query($con, "SELECT MAX(ds) as last_ds FROM participation WHERE `id-membre` = '".intval($uid)."'");
if ($q2 && ($r2 = mysqli_fetch_assoc($q2))) $last_insc = $r2['last_ds'];

// If a specific activity uid is provided, fetch its title to display in header
$activity_title = null;
$activity_date = null;
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
    $aid = intval($_GET['uid']);
    $qa = @mysqli_query($con, "SELECT `titre-activite`, `date_depart` FROM activite WHERE `id-activite` = '".intval($aid)."' LIMIT 1");
    if ($qa && ($ra = mysqli_fetch_assoc($qa))) { $activity_title = $ra['titre-activite']; $activity_date = $ra['date_depart']; }
}

// basic stats (best-effort, tolerant to missing columns)
$stats = ['buyins' => 0, 'parts' => 0, 'gains' => 0, 'gains_sum' => 0, 'net' => 0, 'victories' => 0, 'podiums' => 0, 'recaves' => 0, 'best_gain' => 0];
// Sum total expenses (buyin + rake + recaves/addons) for this member, excluding desinscrit
$q3 = @mysqli_query($con, "SELECT COUNT(p.`id-activite`) AS parts, COALESCE(SUM(COALESCE(a.buyin, 0) + COALESCE(a.rake, 0) + (COALESCE(p.recave,0) * COALESCE(a.recave_montant,0)) + (COALESCE(p.addon,0) * COALESCE(a.recave_montant,0))),0) AS buyins FROM participation p LEFT JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`, 'None') NOT IN ('Desinscrit', 'None')");
if ($q3 && ($r3 = mysqli_fetch_assoc($q3))) { $stats['parts'] = intval($r3['parts']); $stats['buyins'] = intval(round(floatval($r3['buyins']))); }
// victories, podiums, recaves, best_gain (count only real participations)
$q4 = @mysqli_query($con, "SELECT SUM(CASE WHEN COALESCE(p.classement,0)=1 AND COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS victories, SUM(CASE WHEN COALESCE(p.classement,999)>0 AND COALESCE(p.classement,999)<=3 THEN 1 ELSE 0 END) AS podiums, COALESCE(MAX(COALESCE(p.gain,0)),0) AS best_gain FROM participation p WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`, 'None') NOT IN ('Desinscrit', 'None')");
if ($q4 && ($r4 = mysqli_fetch_assoc($q4))) { $stats['victories'] = intval($r4['victories']); $stats['podiums'] = intval($r4['podiums']); $stats['best_gain'] = intval($r4['best_gain']); }
// gains: count how many participations had a positive gain, and also fetch sum
$qg = @mysqli_query($con, "SELECT COALESCE(SUM(COALESCE(p.gain,0)),0) AS gains_sum, COALESCE(SUM(COALESCE(p.gain_total,0)),0) AS gains_total_sum, SUM(CASE WHEN COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS gains_count FROM participation p WHERE p.`id-membre` = '".intval($uid)."'");
if ($qg && ($rg = mysqli_fetch_assoc($qg))) {
    $stats['gains'] = intval($rg['gains_count']);
    // keep total sum available if needed elsewhere
    $gains_sum = intval($rg['gains_sum']);
    if ($gains_sum === 0 && !empty($rg['gains_total_sum'])) {
        $gains_sum = intval(round(floatval($rg['gains_total_sum'])));
    }
    $stats['gains_sum'] = $gains_sum;
}
// recaves: sum the recave count recorded on participation rows (p.recave)
$q5 = @mysqli_query($con, "SELECT COALESCE(SUM(COALESCE(p.recave,0)),0) AS recaves FROM participation p WHERE p.`id-membre` = '".intval($uid)."'");
if ($q5 && ($r5 = mysqli_fetch_assoc($q5))) { $stats['recaves'] = intval($r5['recaves']); }

// compute net = total gains sum - total buyins
$gsum = isset($stats['gains_sum']) ? floatval($stats['gains_sum']) : 0;
$buyins_total = isset($stats['buyins']) ? floatval($stats['buyins']) : 0;
$stats['net'] = intval(round($gsum - $buyins_total));

// percentages relative to played parts
$parts_total = max(0, intval($stats['parts']));
$victory_pct = $parts_total > 0 ? round(intval($stats['victories']) / $parts_total * 100, 1) : 0;
$podium_pct = $parts_total > 0 ? round(intval($stats['podiums']) / $parts_total * 100, 1) : 0;
$recave_pct = $parts_total > 0 ? round(intval($stats['recaves']) / $parts_total * 100, 1) : 0;

// bonus inscription tokens: sum participation.jetons_bonus_ins (exclude Desinscrit/None)
$q_bonus = @mysqli_query($con, "SELECT COALESCE(SUM(COALESCE(p.jetons_bonus_ins,0)),0) AS bonus_ins FROM participation p WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`, 'None') NOT IN ('Desinscrit', 'None')");
if ($q_bonus && ($rb = mysqli_fetch_assoc($q_bonus))) { $stats['bonus_ins'] = intval($rb['bonus_ins']); } else { $stats['bonus_ins'] = 0; }

// avatar URL
$avatar_url = 'https://viendez.com/images/noprofil.jpg';
if (!empty($user['photo'])) {
    $avatar_url = 'https://viendez.com/images/faces/' . rawurlencode(basename($user['photo']));
}

function fmt_money($n){ return number_format($n,0,',',' ') . ' €'; }
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Profil - <?php echo htmlspecialchars($user['pseudo']); ?></title>
    <style>
        /* Dark backdrop */
        body{background:rgba(0,0,0,0.85);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;padding:18px;color:#eef6fb}
        /* Centered sheet */
        .sheet{max-width:520px;margin:18px auto;background:#071019;color:#eef6fb;border-radius:18px;padding:16px;box-shadow:0 12px 40px rgba(0,0,0,0.6)}
        .avatar{width:96px;height:96px;border-radius:50%;overflow:hidden;margin:10px auto}
        .avatar img{width:100%;height:100%;object-fit:cover}
        .name{text-align:center;font-weight:800;font-size:20px;margin-top:6px}
        /* Cards use subtle contrast on dark sheet */
        .card{background:rgba(255,255,255,0.03);padding:12px;border-radius:12px;margin-top:12px;border:1px solid rgba(255,255,255,0.03)}
        .card-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.03)}
        .card-row:last-child{border-bottom:none}
        .label{color:#9aa6b1}
        .value{font-weight:700;color:#eef6fb}
        .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
        .stat{background:rgba(255,255,255,0.02);padding:10px;border-radius:10px;text-align:center}
        .stat .num{font-weight:800;font-size:18px}
        .stat .sub{color:#9aa6b1;font-size:12px}
        .top-actions{position:fixed;right:18px;top:18px;display:flex;gap:10px;z-index:20}
        .top-action{background:rgba(255,255,255,0.06);padding:8px 12px;border-radius:20px;border:0;color:#ff9d3b;font-weight:700;backdrop-filter:blur(4px);cursor:pointer;text-decoration:none;display:inline-flex;align-items:center}
        .profile-footer-action{display:flex;justify-content:center;margin-top:18px}
        .profile-footer-action .top-action.logout{color:#ff6b6b;min-width:160px;justify-content:center}
    </style>
</head>
<body>
    <div class="top-actions">
        <button class="top-action" onclick="history.back();">Fermer</button>
    </div>
    <div class="sheet">
        <div class="avatar"><img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="avatar"></div>
        <div class="name"><span style="color:#16a34a"><?php echo htmlspecialchars($user['pseudo']); ?></span></div>

        <div class="card">
            <div style="font-weight:700;margin-bottom:8px"><?php echo !empty($activity_title) ? htmlspecialchars($activity_title) : ((!empty($last_insc))? fmt_fr_date($last_insc, 'EEEE d MMM (dd/MM)') : '—'); ?></div>
            <div class="card-row"><div class="label">Inscription</div><div class="value"><?php echo $last_insc ? fmt_fr_date($last_insc, 'd MMM HH:mm') : '—'; ?><?php $bi = intval(isset($stats['bonus_ins']) ? $stats['bonus_ins'] : 0); if ($bi > 0) { echo ' (<span style="color:#ffd100">+'.intval(min($bi, 5000)).'</span>)'; } ?></div></div>
        </div>

        <div class="card">
            <?php $challenge_uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : null;
            $challenge_rank_display = '#—';
            if ($challenge_uid && !empty($con)) {
                $today = date('Y-m-d');
                $activity_cols = array();
                $colres = @mysqli_query($con, "SHOW COLUMNS FROM activite");
                if ($colres) { while ($cr = mysqli_fetch_assoc($colres)) { $activity_cols[] = $cr['Field']; } }
                $challenge_col = null;
                foreach (array('id_challenge','id-challenge','challenge_id','idchall','id_chall') as $c) { if (in_array($c, $activity_cols)) { $challenge_col = $c; break; } }
                $challenge_id = null;
                $actq = @mysqli_query($con, "SELECT * FROM activite WHERE `id-activite`='".intval($challenge_uid)."' LIMIT 1");
                if ($actq && ($act = mysqli_fetch_assoc($actq))) { foreach (array('id_challenge','id-challenge','challenge_id','idchall','id_chall') as $c) { if (isset($act[$c]) && $act[$c] !== '') { $challenge_id = intval($act[$c]); $challenge_col = $c; break; } } }
                if ($challenge_id && $challenge_col) {
                    $where = "(a.`" . $challenge_col . "` = " . $challenge_id . ") AND a.date_depart < '" . $today . "'";
                    $sql = "SELECT p.`id-membre` AS mid, COALESCE(SUM(COALESCE(p.points,0)),0) AS pts, SUM(CASE WHEN COALESCE(p.classement,0)=1 AND COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS vic FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $where . " GROUP BY p.`id-membre` ORDER BY pts DESC, vic DESC";
                    $q = @mysqli_query($con, $sql);
                    if ($q) { $i = 0; while ($r = mysqli_fetch_assoc($q)) { $i++; if (intval($r['mid']) === intval($uid)) { $challenge_rank_display = '#' . $i; break; } } }
                }
            }
            ?>
            <div class="card-row"><div class="label">Rang Challenge</div><div class="value"><?php echo htmlspecialchars($challenge_rank_display); ?> <a id="link-challenge" href="/panel/challenge_rank.php<?php echo $challenge_uid? '?uid=' . $challenge_uid : ''; ?>" style="margin-left:8px;color:#ff9d3b;font-weight:700">Visualiser</a></div></div>
            <div class="card-row"><div class="label">Vos Tickets de Tombola</div><div class="value"><?php echo intval($tombola_count); ?> <a id="link-tombola" href="/panel/tickets_tombolas.php?id=<?php echo intval($uid); ?>" onclick="window.location.href=this.href;" style="margin-left:8px;color:#16a34a;font-weight:700">Voir</a></div></div>
            <div class="card-row"><div class="label">Notes (Traker)</div><div class="value">— <a id="link-notes" href="#" style="margin-left:8px;color:#08b0ff;font-weight:700">Voir</a></div></div>
        </div>

        <div style="font-weight:700;margin-top:12px;margin-bottom:8px">Statistiques</div>
        <div class="card" style="padding:12px">
            <div style="display:flex;gap:12px">
                <div style="flex:1;text-align:center">
                    <div class="num" style="font-weight:800;color:#9aa6b1"><?php echo htmlspecialchars(number_format($stats['buyins'],0,',',' ')); ?> €</div>
                    <div class="sub"><?php echo intval($stats['parts']); ?> parties</div>
                </div>
                <div style="flex:1;text-align:center">
                    <div class="num" style="font-weight:800;color:#16a34a"><?php echo htmlspecialchars(number_format(isset($stats['gains_sum']) ? $stats['gains_sum'] : 0,0,',',' ')); ?> €</div>
                    <div class="sub"><?php echo intval(isset($stats['gains']) ? $stats['gains'] : 0); ?> fois</div>
                </div>
                <div style="flex:1;text-align:center">
                    <div class="num" style="font-weight:800;color:<?php echo ($stats['net'] < 0) ? '#ff4d4d' : '#16a34a'; ?>"><?php echo htmlspecialchars(number_format($stats['net'],0,',',' ')); ?> €</div>
                    <div class="sub">NET</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px">
                <div style="text-align:center"><div class="num" style="color:#ffd100"><?php echo intval($stats['victories']); ?></div><div class="sub">Victoires <span style="font-size:11px;color:#9aa6b1">(<?php echo $victory_pct; ?>%)</span></div></div>
                <div style="text-align:center"><div class="num" style="color:#ff9d3b"><?php echo intval($stats['podiums']); ?></div><div class="sub">ITM <span style="font-size:11px;color:#9aa6b1">(<?php echo $podium_pct; ?>%)</span></div></div>
                <div style="text-align:center"><div class="num" style="color:#08b0ff"><?php echo intval($stats['recaves']); ?></div><div class="sub">Recaves <span style="font-size:11px;color:#9aa6b1">(<?php echo $recave_pct; ?>%)</span></div></div>
            </div>

            <div style="border-top:1px solid rgba(0,0,0,0.06);margin-top:12px;padding-top:10px;display:flex;justify-content:space-between;align-items:center">
                <div>Meilleur gain</div>
                <div style="font-weight:800;color:#16a34a"><?php echo htmlspecialchars(number_format($stats['best_gain'],0,',',' ')) . ' €'; ?></div>
            </div>
        </div>

        <div class="profile-footer-action">
            <a class="top-action logout" href="/panel/logout.php">Déconnexion</a>
        </div>
    </div>
</body>
</html>
