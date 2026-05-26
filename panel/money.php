<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

// Ensure user logged in
$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    $_SESSION['redirect'] = 'panel/money.php';
    header('Location: logout.php');
    exit;
}

function fmt_money($n){ return number_format($n,0,',',' ') . ' €'; }

// Update balance helper (best-effort)
function updateMemberBalance($membre_id, $con) {
    try {
        $query = "SELECT \
            COALESCE(SUM(CASE WHEN id_type_mvt = 4 THEN montant ELSE 0 END), 0) + \
            COALESCE(SUM(CASE WHEN id_type_mvt = 6 THEN montant ELSE 0 END), 0) + \
            COALESCE(SUM(CASE WHEN id_type_mvt = 5 THEN montant ELSE 0 END), 0) - \
            COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) - \
            COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) - \
            COALESCE(SUM(CASE WHEN id_type_mvt = 3 THEN montant ELSE 0 END), 0) as balance \
            FROM portefeuille WHERE id_mvt_membre = ?";
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $membre_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        $balance = $row['balance'] ?? 0;
        $upd = mysqli_prepare($con, "UPDATE membres SET solde = ? WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($upd, 'di', $balance, $membre_id);
        mysqli_stmt_execute($upd);
        return true;
    } catch (Throwable $e) {
        error_log('updateMemberBalance error: ' . $e->getMessage());
        return false;
    }
}

// Handle add transaction
if (isset($_POST['submit_portefeuille'])) {
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

// Fetch participations for select
$participations = [];
$q = @mysqli_query($con, "SELECT p.`id-participation`, a.`titre-activite`, a.date_depart FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = " . intval($uid) . " ORDER BY a.date_depart DESC");
if ($q) while ($r = mysqli_fetch_assoc($q)) $participations[] = $r;

// Fetch transactions
$transactions = [];
$qt = @mysqli_query($con, "SELECT * FROM portefeuille WHERE id_mvt_membre = " . intval($uid) . " ORDER BY date_mvt ASC");
if ($qt) while ($tr = mysqli_fetch_assoc($qt)) $transactions[] = $tr;

// Compute balance
$solde = 0;
$sq = @mysqli_query($con, "SELECT 
    COALESCE(SUM(CASE WHEN id_type_mvt = 4 THEN montant ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN id_type_mvt = 6 THEN montant ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN id_type_mvt = 5 THEN montant ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN id_type_mvt = 3 THEN montant ELSE 0 END), 0) as balance 
    FROM portefeuille WHERE id_mvt_membre = " . intval($uid));
if ($sq) { $sr = mysqli_fetch_assoc($sq); $solde = $sr['balance']; }

?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mon Portefeuille</title>
<style>
/* copy minimal profile styles */
body{background:rgba(0,0,0,0.85);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;padding:18px;color:#eef6fb}
.sheet{max-width:920px;margin:18px auto;background:#071019;color:#eef6fb;border-radius:12px;padding:16px;box-shadow:0 12px 40px rgba(0,0,0,0.6)}
.card{background:rgba(255,255,255,0.03);padding:12px;border-radius:12px;margin-top:12px;border:1px solid rgba(255,255,255,0.03)}
.label{color:#9aa6b1}
.value{font-weight:700;color:#eef6fb}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.03)}
.btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#08b0ff;color:#04131d;text-decoration:none;font-weight:800}
.form-control{width:100%;padding:8px;border-radius:6px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
</style>
</head>
<body>
<div class="sheet">
    <h2 style="margin:0 0 12px">Mon Portefeuille</h2>
    <?php if (!empty($_SESSION['msg'])) { echo '<div style="background:#163b5a;padding:10px;border-radius:8px;margin-bottom:10px">' . htmlspecialchars($_SESSION['msg']) . '</div>'; $_SESSION['msg']=''; }
          if (!empty($_SESSION['error'])) { echo '<div style="background:#5a1616;padding:10px;border-radius:8px;margin-bottom:10px">' . htmlspecialchars($_SESSION['error']) . '</div>'; $_SESSION['error']=''; }
    ?>

    <div class="card">
        <form method="post">
            <table class="table">
                <tr><th class="label">Opération</th><td><select class="form-control" name="id_type_mvt" required>
                    <option value="">-- Sélectionner --</option>
                    <optgroup label="Débit"><option value="1">Buyin</option><option value="2">Rake</option><option value="3">Gestion</option></optgroup>
                    <optgroup label="Crédit"><option value="4">Gain</option><option value="6">Tombola</option><option value="5">Gestion</option></optgroup>
                </select></td></tr>
                <tr><th class="label">Montant</th><td><input class="form-control" type="number" step="0.01" name="montant" required></td></tr>
                <tr style="display:none"><th class="label">Date</th><td><input class="form-control" type="date" name="date_mvt"></td></tr>
                <tr><th class="label">ID Participation</th><td><select class="form-control" name="id_participation"><option value="">-- Aucune --</option>
                <?php foreach($participations as $p){ echo '<option value="' . intval($p['id-participation']) . '">' . htmlspecialchars(date('d/m/Y', strtotime($p['date_depart'])) . ' - ' . $p['titre-activite']) . '</option>'; } ?>
                </select></td></tr>
                <tr><td colspan="2" style="text-align:center"><button class="btn" type="submit" name="submit_portefeuille">Ajouter Transaction</button></td></tr>
            </table>
        </form>
    </div>

    <div class="card" style="margin-top:14px">
        <div style="margin-bottom:10px;font-weight:700">Transactions</div>
        <table class="table">
            <thead><tr><th>Date</th><th>Participation</th><th>Opération</th><th>Montant</th></tr></thead>
            <tbody>
            <?php foreach($transactions as $t){
                $class = ($t['id_type_mvt'] == 1) ? 'text-danger' : 'text-success';
                $label = 'Inconnu';
                switch($t['id_type_mvt']){
                    case 1: $label='Débit Buyin'; break;
                    case 2: $label='Débit Rake'; break;
                    case 3: $label='Débit Gestion'; break;
                    case 4: $label='Crédit Gain'; break;
                    case 5: $label='Crédit Gestion'; break;
                    case 6: $label='Crédit Tombola'; break;
                }
                echo '<tr><td>' . htmlspecialchars(date('d/m/Y', strtotime($t['date_mvt']))) . '</td><td>' . htmlspecialchars($t['id_participation'] ?: '-') . '</td><td>' . htmlspecialchars($label) . '</td><td>' . htmlspecialchars(number_format($t['montant'],2,',',' ')) . ' €</td></tr>';
            }
            if (count($transactions) === 0) echo '<tr><td colspan="4" style="text-align:center;color:#888">Aucune transaction</td></tr>';
            ?>
            </tbody>
        </table>

        <div style="background-color: #2e6da4; color: white; padding: 12px; margin: 12px 0; border-radius: 8px; font-size: 18px; text-align:center">
            <strong>Solde actuel : </strong>
            <span style="font-size:22px;font-weight:800;color:white; margin-left:8px"><?php echo number_format($solde,2,',',' '); ?> €</span>
        </div>
    </div>

    <div style="margin-top:12px;text-align:center"><a class="btn" href="/panel/profile.php">Retour au profil</a></div>
</div>
</body>
</html>
