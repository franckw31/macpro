<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');
include(__DIR__ . '/../include/functions_logs.php');

// Ensure user logged in
$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    $_SESSION['redirect'] = 'panel/money.php';
    header('Location: logout.php');
    exit;
}

function fmt_money($n){ return number_format($n,0,',',' ') . ' €'; }

function fmt_fr_date_short($dt){
    if (empty($dt)) return '';
    $ts = strtotime($dt);
    if (!$ts) return $dt;
    $months = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return intval(date('j', $ts)) . ' ' . $months[intval(date('n', $ts)) - 1] . ' ' . date('Y', $ts);
}

// Update balance helper (best-effort)
function updateMemberBalance($membre_id, $con) {
    try {
        // Compute balance using type_mvt.direction when available; fallback to id ranges
        $query = "SELECT COALESCE(SUM(
            CASE
                WHEN (tm.direction IS NOT NULL AND LOWER(tm.direction) IN ('credit','c')) THEN p.montant
                WHEN (tm.direction IS NOT NULL AND LOWER(tm.direction) IN ('debit','d')) THEN -p.montant
                WHEN (tm.direction IS NULL AND p.id_type_mvt NOT BETWEEN 1 AND 3) THEN p.montant
                ELSE -p.montant
            END
        ),0) AS balance
        FROM portefeuille p
        LEFT JOIN type_mvt tm ON p.id_type_mvt = tm.id_type_mvt
        WHERE p.id_mvt_membre = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $membre_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $balance = isset($row['balance']) ? $row['balance'] : 0;
        $upd = mysqli_prepare($con, "UPDATE membres SET solde = ? WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($upd, 'di', $balance, $membre_id);
        mysqli_stmt_execute($upd);
        return true;
    } catch (Throwable $e) {
        error_log('updateMemberBalance error: ' . $e->getMessage());
        return false;
    }
}

// Handle add transaction (only allowed for user 2 or 265)
if (isset($_POST['submit_portefeuille'])) {
    if (!in_array(intval($uid), [2, 265], true)) {
        $_SESSION['error'] = 'Permission refusée';
        header('Location: money.php');
        exit;
    }
    try {
        if (!isset($_POST['id_type_mvt']) || !isset($_POST['montant'])) throw new Exception('Type et montant sont obligatoires');
        $id_type_mvt = intval($_POST['id_type_mvt']);
        $montant = floatval($_POST['montant']);
        $date_mvt = !empty($_POST['date_mvt']) ? $_POST['date_mvt'] : date('Y-m-d');
        $id_participation = !empty($_POST['id_participation']) ? intval($_POST['id_participation']) : null;
        $stmt = mysqli_prepare($con, "INSERT INTO portefeuille (id_mvt_membre, date_mvt, montant, id_type_mvt, id_participation) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) throw new Exception('Prepare failed: ' . mysqli_error($con));
        mysqli_stmt_bind_param($stmt, 'isdii', $uid, $date_mvt, $montant, $id_type_mvt, $id_participation);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
        updateMemberBalance($uid, $con);
        $_SESSION['msg'] = 'Transaction ajoutée avec succès';
        header('Location: money.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Handle delete movement
if (isset($_POST['delete_mvt'])) {
    $del_id = intval($_POST['delete_mvt']);
    try {
        // fetch the movement to check ownership
        $g = mysqli_prepare($con, "SELECT id_mvt_membre, montant, id_type_mvt, date_mvt FROM portefeuille WHERE id_mvt = ? LIMIT 1");
        mysqli_stmt_bind_param($g, 'i', $del_id);
        mysqli_stmt_execute($g);
        $gres = mysqli_stmt_get_result($g);
        $row = mysqli_fetch_assoc($gres);
        if (!$row) throw new Exception('Mouvement introuvable');
        $owner = intval($row['id_mvt_membre']);
        $mv_montant = isset($row['montant']) ? $row['montant'] : '';
        $mv_type = isset($row['id_type_mvt']) ? $row['id_type_mvt'] : '';
        $mv_date = isset($row['date_mvt']) ? $row['date_mvt'] : '';
        // allow if admin (2 or 265) or owner
        if (!in_array(intval($uid), [2,265], true) && $owner !== intval($uid)) {
            throw new Exception('Permission refusée');
        }
        $d = mysqli_prepare($con, "DELETE FROM portefeuille WHERE id_mvt = ? LIMIT 1");
        mysqli_stmt_bind_param($d, 'i', $del_id);
        if (!mysqli_stmt_execute($d)) throw new Exception('Erreur suppression: ' . mysqli_stmt_error($d));
        updateMemberBalance($owner, $con);
        // log activity if helper available
        if (function_exists('log_activity')) {
            $details = "Supprimé id_mvt={$del_id} owner={$owner} montant={$mv_montant} type={$mv_type} date={$mv_date}";
            log_activity($con, 'delete_portefeuille', $details);
        }
        $_SESSION['msg'] = 'Mouvement supprimé';
        header('Location: money.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// Fetch participations for select
$participations = [];
$q = @mysqli_query($con, "SELECT p.`id-participation`, a.`titre-activite`, a.date_depart FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = " . intval($uid) . " ORDER BY a.date_depart DESC");
if ($q) while ($r = mysqli_fetch_assoc($q)) $participations[] = $r;

// Load movement types from DB (table `type_mvt`) if available; fallback to defaults
$mvt_types = [
    1 => 'Débit Buyin',
    2 => 'Débit Rake',
    3 => 'Débit Gestion',
    4 => 'Crédit Gain',
    5 => 'Crédit Gestion',
    6 => 'Crédit Tombola',
];
$mvt_directions = []; // optional: 'debit' or 'credit'
$mtq = @mysqli_query($con, "SELECT id_type_mvt, label, direction FROM type_mvt ORDER BY id_type_mvt ASC");
if ($mtq) {
    while ($mr = mysqli_fetch_assoc($mtq)) {
        $id = intval($mr['id_type_mvt']);
        if ($id <= 0) continue;
        $mvt_types[$id] = isset($mr['label']) && $mr['label'] !== '' ? $mr['label'] : ($mvt_types[$id] ?? ('Type ' . $id));
        if (isset($mr['direction']) && $mr['direction'] !== '') {
            $dir = strtolower(trim($mr['direction']));
            $mvt_directions[$id] = ($dir === 'debit' || $dir === 'd' || $dir === 'deb') ? 'debit' : 'credit';
        }
    }
}

// Fetch transactions
$transactions = [];
$qt = @mysqli_query($con, "SELECT * FROM portefeuille WHERE id_mvt_membre = " . intval($uid) . " ORDER BY date_mvt ASC");
if ($qt) while ($tr = mysqli_fetch_assoc($qt)) $transactions[] = $tr;

// Build participation id -> activity title map for transactions (to display activity title instead of id)
$participation_titles = [];
$part_ids = [];
foreach ($transactions as $tr) {
    if (!empty($tr['id_participation'])) $part_ids[] = intval($tr['id_participation']);
}
$part_ids = array_values(array_unique($part_ids));
if (count($part_ids) > 0) {
    $ids = implode(',', $part_ids);
    $pq = @mysqli_query($con, "SELECT p.`id-participation`, a.`titre-activite`, a.date_depart FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-participation` IN (" . $ids . ")");
    if ($pq) {
        while ($pr = mysqli_fetch_assoc($pq)) {
            $pid = intval($pr['id-participation']);
            $participation_titles[$pid] = [
                'title' => $pr['titre-activite'],
                'date' => $pr['date_depart'] ?? ''
            ];
        }
    }
}

// Compute balance
$solde = 0;
// Compute balance for display using same logic as updateMemberBalance
$sq_stmt = mysqli_prepare($con, "SELECT COALESCE(SUM(
    CASE
        WHEN (tm.direction IS NOT NULL AND LOWER(tm.direction) IN ('credit','c')) THEN p.montant
        WHEN (tm.direction IS NOT NULL AND LOWER(tm.direction) IN ('debit','d')) THEN -p.montant
        WHEN (tm.direction IS NULL AND p.id_type_mvt NOT BETWEEN 1 AND 3) THEN p.montant
        ELSE -p.montant
    END
),0) AS balance
FROM portefeuille p
LEFT JOIN type_mvt tm ON p.id_type_mvt = tm.id_type_mvt
WHERE p.id_mvt_membre = ?");
if ($sq_stmt) {
    mysqli_stmt_bind_param($sq_stmt, 'i', $uid);
    mysqli_stmt_execute($sq_stmt);
    $res = mysqli_stmt_get_result($sq_stmt);
    if ($res && ($sr = mysqli_fetch_assoc($res))) $solde = $sr['balance'];
}

// Fetch pseudo for header
$pseudo = 'Utilisateur';
$pq = @mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = " . intval($uid) . " LIMIT 1");
if ($pq && ($pr = mysqli_fetch_assoc($pq))) { $pseudo = $pr['pseudo']; }

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mon Portefeuille</title>
<style>
/* copy minimal profile styles with responsive layout and color accents */
body{background:linear-gradient(180deg,#051018 0%, rgba(2,8,12,0.85) 100%);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;padding:18px;color:#eef6fb}
.sheet{max-width:980px;margin:18px auto;background:linear-gradient(180deg,#071019,#08131a);color:#eef6fb;border-radius:14px;padding:18px;box-shadow:0 14px 50px rgba(0,0,0,0.6)}
.top{display:grid;grid-template-columns:1fr 420px;gap:14px;align-items:start}
.card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:14px;border-radius:12px;margin-top:8px;border:1px solid rgba(255,255,255,0.04)}
.label{color:#9aa6b1;font-weight:700;font-size:13px}
.value{font-weight:800;color:#eef6fb}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:14px}
.table thead th{color:#a9c2d6;text-align:left;font-weight:800}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#08b0ff;color:#04131d;text-decoration:none;font-weight:800}
.btn.secondary{background:#16a34a;color:#071017}
.btn.orange{background:#ff8a00;color:#fff;border:1px solid rgba(0,0,0,0.08)}
.form-control{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
.muted{color:#9aa6b1;font-size:13px}
.balance-box{background:linear-gradient(90deg,#164a8a,#0aa3ff);padding:14px;border-radius:10px;color:#fff;font-weight:800;text-align:center}
.balance-amount{font-size:22px;font-weight:900}
.debit {color:#ff6b6b}
.credit {color:#8be38b}
.tx-debit td{background:linear-gradient(90deg, rgba(255,107,107,0.03), transparent)}
.tx-credit td{background:linear-gradient(90deg, rgba(139,227,139,0.03), transparent)}

@media (max-width: 880px){
    .top{grid-template-columns:1fr;}
    .sheet{padding:12px}
    .balance-box{padding:12px}
}

/* Truncate long operation labels and style small D/C badge */
.op-label{display:inline-block;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle}
.dir-badge{display:inline-block;padding:6px 8px;border-radius:8px;font-weight:800;font-size:12px}
.mobile-only{display:none}
.btn.small{padding:6px 8px;border-radius:8px;font-size:13px}
.btn.icon{width:36px;height:34px;padding:6px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px}
.btn.icon svg{width:14px;height:14px}
.part-date{color:#9aa6b1;font-size:13px;margin-top:4px}

/* On small screens, hide the separate Montant column and show amount under the Opération label */
.mobile-amt{display:none;color:#cfe8d6;margin-top:6px;font-size:13px}
@media (max-width:880px){
    .table thead th:nth-child(4), .table tbody td:nth-child(4){display:none}
    .mobile-amt{display:block}
    .op-label{max-width:120px}
    .mobile-only{display:inline-block}
    .btn.small{padding:6px 6px;font-size:12px}
}


</style>
</head>
<body>
<div class="sheet">
        <div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 12px">
        <h2 style="margin:0">Portefeuille de <?php echo htmlspecialchars($pseudo); ?></h2>
        <a class="btn orange" href="/panel/profile.php">Retour</a>
        </div>
        <?php if (!empty($_SESSION['msg'])) { echo '<div style="background:#163b5a;padding:10px;border-radius:8px;margin-bottom:10px">' . htmlspecialchars($_SESSION['msg']) . '</div>'; $_SESSION['msg']=''; }
            if (!empty($_SESSION['error'])) { echo '<div style="background:#5a1616;padding:10px;border-radius:8px;margin-bottom:10px">' . htmlspecialchars($_SESSION['error']) . '</div>'; $_SESSION['error']=''; }
        ?>

    <div class="top">
        <?php if (in_array(intval($uid), [2, 265], true)): ?>
        <div class="card">
            <form method="post">
                <table class="table">
                <tr><th class="label">Opération</th><td><select class="form-control" name="id_type_mvt" required>
                    <option value="">-- Sélectionner --</option>
                    <?php
                    // Build optgroups from $mvt_types and optional $mvt_directions
                    $debits = $credits = [];
                    foreach ($mvt_types as $id => $lbl) {
                        $dir = $mvt_directions[$id] ?? (($id >= 1 && $id <= 3) ? 'debit' : 'credit');
                        if ($dir === 'debit') $debits[$id] = $lbl; else $credits[$id] = $lbl;
                    }
                    if (!empty($debits)) {
                        echo '<optgroup label="Débit">';
                        foreach ($debits as $id => $lbl) {
                            echo '<option value="' . intval($id) . '">' . htmlspecialchars($lbl) . '</option>';
                        }
                        echo '</optgroup>';
                    }
                    if (!empty($credits)) {
                        echo '<optgroup label="Crédit">';
                        foreach ($credits as $id => $lbl) {
                            echo '<option value="' . intval($id) . '">' . htmlspecialchars($lbl) . '</option>';
                        }
                        echo '</optgroup>';
                    }
                    ?>
                </select></td></tr>
                <tr><th class="label">Montant</th><td><input class="form-control" type="number" step="0.01" name="montant" required></td></tr>
                <tr style="display:none"><th class="label">Date</th><td><input class="form-control" type="date" name="date_mvt"></td></tr>
                <tr><th class="label">ID Participation</th><td><select class="form-control" name="id_participation"><option value="">-- Aucune --</option>
                <?php foreach($participations as $p){ echo '<option value="' . intval($p['id-participation']) . '">' . htmlspecialchars(date('d/m/Y', strtotime($p['date_depart'])) . ' - ' . $p['titre-activite']) . '</option>'; } ?>
                </select></td></tr>
                <tr><td colspan="2" style="text-align:center"><button class="btn" type="submit" name="submit_portefeuille">Ajouter Transaction</button></td></tr>
                <tr><td colspan="2" style="text-align:center;margin-top:8px">
                    <a class="btn secondary" href="/panel/manage-type-mvt.php" style="margin-top:8px">Gérer types Mouvements</a>
                </td></tr>
            </table>
            </form>
        </div>
        <?php endif; ?>

        <aside class="card" style="height:100%">
            <div style="margin-bottom:8px;font-weight:700">Solde</div>
            <div class="balance-box">
                <div class="muted">Solde actuel</div>
                <div class="balance-amount"><?php echo number_format($solde,2,',',' '); ?> €</div>
            </div>
        </aside>
    </div>

    <div class="card" style="margin-top:14px">
        <div style="margin-bottom:10px;font-weight:700">Transactions</div>
        <table class="table">
            <thead><tr><th>Date</th><th>Activité</th><th>Opération</th><th>Montant</th><th style="width:90px;text-align:center">Actions</th></tr></thead>
            <tbody>
            <?php foreach($transactions as $t){
                $tid = intval($t['id_type_mvt']);
                $label = isset($mvt_types[$tid]) ? $mvt_types[$tid] : 'Inconnu';
                $dir = $mvt_directions[$tid] ?? (($tid >= 1 && $tid <= 3) ? 'debit' : 'credit');
                $isDebit = ($dir === 'debit');
                $rowClass = $isDebit ? 'tx-debit' : 'tx-credit';
                $amt = number_format($t['montant'],2,',',' ');
                echo '<tr class="' . $rowClass . '">';
                $tx_full = date('d/m/Y', strtotime($t['date_mvt']));
                $tx_short = date('d-m', strtotime($t['date_mvt']));
                echo '<td title="' . htmlspecialchars($tx_full) . '">' . htmlspecialchars($tx_short) . '</td>';
                $pid = $t['id_participation'] ?? '';
                $part_label = '-';
                if (!empty($pid)) {
                    $pid_i = intval($pid);
                    if (isset($participation_titles[$pid_i])) {
                        $part_label = $participation_titles[$pid_i]['title'];
                        $part_date = $participation_titles[$pid_i]['date'];
                    } else {
                        $part_label = 'Participation ' . $pid_i;
                        $part_date = '';
                    }
                }
                echo '<td>' . htmlspecialchars($part_label);
                if (!empty($part_date)) {
                    echo '<div class="part-date muted">' . htmlspecialchars(fmt_fr_date_short($part_date)) . '</div>';
                }
                echo '</td>';
                $mid = intval($t['id_mvt'] ?? 0);
                echo '<td><span class="op-label ' . ($isDebit ? 'debit' : 'credit') . '" title="' . htmlspecialchars($label) . '">' . htmlspecialchars($label) . '</span>';
                echo '<div class="mobile-amt ' . ($isDebit ? 'debit' : 'credit') . '">' . htmlspecialchars($amt) . ' €</div>';
                echo '</td>';
                echo '<td class="' . ($isDebit ? 'debit' : 'credit') . '">' . htmlspecialchars($amt) . ' €</td>';
                // Actions column
                echo '<td style="text-align:center;white-space:nowrap">';
                if ($mid > 0 && (in_array(intval($uid), [2,265], true) || intval($t['id_mvt_membre'] ?? 0) === intval($uid))) {
                    echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Confirmer suppression ?\')">';
                    echo '<input type="hidden" name="delete_mvt" value="' . $mid . '">';
                    echo '<button class="btn small" type="submit" style="padding:6px 8px;background:#ff4d4d;color:#fff;border:none;border-radius:8px">Suppr</button>';
                    echo '</form>';
                }
                echo '</td>';
                echo '</tr>';
            }
            if (count($transactions) === 0) echo '<tr><td colspan="5" style="text-align:center;color:#888">Aucune transaction</td></tr>';
            ?>
            </tbody>
        </table>

        
    </div>

    
</div>
</body>
</html>
