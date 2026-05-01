<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include __DIR__ . '/include/config.php';

if (!function_exists('h')) { function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); } }

// ── Variables d'état ──────────────────────────────────────────────────────────
$step       = isset($_POST['step']) ? intval($_POST['step']) : 1;
$pseudo_q   = trim($_POST['pseudo'] ?? '');
$member_id  = isset($_POST['member_id']) && is_numeric($_POST['member_id']) ? intval($_POST['member_id']) : null;
$confirmed_ids = isset($_POST['confirmed_ids']) ? array_map('intval', explode(',', $_POST['confirmed_ids'])) : [];

$candidates = []; // participations éligibles au calcul
$member_row = null;
$message    = '';
$saved_count = 0;

// ── Étape 2 : recherche du membre et calcul des candidats ─────────────────────
if ($step === 2 && $pseudo_q !== '') {
    // Chercher le membre par pseudo
    $esc = mysqli_real_escape_string($con, $pseudo_q);
    $mq = @mysqli_query($con, "SELECT `id-membre`, pseudo FROM membres WHERE pseudo = '$esc' LIMIT 1");
    if (!$mq || mysqli_num_rows($mq) === 0) {
        // Essai partiel
        $mq = @mysqli_query($con, "SELECT `id-membre`, pseudo FROM membres WHERE pseudo LIKE '%$esc%' LIMIT 10");
        if ($mq && mysqli_num_rows($mq) > 1) {
            $step = 1;
            $message = '<span style="color:#ff6b6b">Plusieurs membres trouvés. Soyez plus précis :</span><ul style="margin-top:6px">';
            while ($rm = mysqli_fetch_assoc($mq)) $message .= '<li>' . h($rm['pseudo']) . '</li>';
            $message .= '</ul>';
        } elseif ($mq && mysqli_num_rows($mq) === 1) {
            $member_row = mysqli_fetch_assoc($mq);
            $member_id  = intval($member_row['id-membre']);
        } else {
            $step = 1;
            $message = '<span style="color:#ff6b6b">Aucun membre trouvé pour « ' . h($pseudo_q) . ' ».</span>';
        }
    } else {
        $member_row = mysqli_fetch_assoc($mq);
        $member_id  = intval($member_row['id-membre']);
    }

    if ($member_id) {
        // Récupérer toutes les participations sans sergio_score (ou déjà calculé)
        // On vérifie :
        //   - classement > 0  (position enregistrée)
        //   - l'activité a des participants avec classement (données suffisantes)
        $pq = @mysqli_query($con, "
            SELECT
                p.`id-participation`    AS id_part,
                p.`id-activite`         AS id_act,
                p.classement,
                p.recave,
                p.sergio_score,
                COALESCE(a.`titre-activite`, 'Partie') AS titre,
                a.date_depart,
                a.buyin,
                a.rake,
                -- nb joueurs avec classement renseigné dans cette activité
                (SELECT COUNT(*) FROM participation p2
                 WHERE p2.`id-activite` = p.`id-activite`
                   AND COALESCE(p2.`option`,'None') NOT IN ('Desinscrit','None')) AS nb_joueurs,
                -- nb recaves total de l'activité
                (SELECT COALESCE(SUM(COALESCE(p3.recave,0)),0) FROM participation p3
                 WHERE p3.`id-activite` = p.`id-activite`
                   AND COALESCE(p3.`option`,'None') NOT IN ('Desinscrit','None')) AS total_recaves
            FROM participation p
            JOIN activite a ON a.`id-activite` = p.`id-activite`
            WHERE p.`id-membre` = '".intval($member_id)."'
              AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None')
              AND a.date_depart <= NOW()
            ORDER BY a.date_depart DESC
            LIMIT 300
        ");
        while ($row = mysqli_fetch_assoc($pq)) {
            $nb    = intval($row['nb_joueurs']);
            $rank  = intval($row['classement']);
            $total_rec = intval($row['total_recaves']);
            $my_rec    = intval($row['recave'] ?? 0);

            // Critères de qualité des données
            $has_classement = $rank > 0;
            $has_joueurs    = $nb >= 2;

            // Calcul du score (même formule que resume.php)
            $denom = $nb + $total_rec - $my_rec;
            $score_calc = null;
            if ($denom > 0 && $has_classement) {
                $score_calc = round((1 - ($rank / $denom)) * 20, 2);
            }

            $already = $row['sergio_score'];
            $status  = 'skip'; // skip / eligible / already_same / will_update

            if (!$has_classement)    { $status = 'no_classement'; }
            elseif (!$has_joueurs)   { $status = 'no_joueurs'; }
            elseif ($score_calc === null) { $status = 'skip'; }
            elseif ($already !== null && floatval($already) == $score_calc) { $status = 'already_same'; }
            else                     { $status = 'eligible'; }

            $candidates[] = [
                'id_part'     => intval($row['id_part']),
                'id_act'      => intval($row['id_act']),
                'titre'       => $row['titre'],
                'date'        => $row['date_depart'],
                'rank'        => $rank,
                'nb_joueurs'  => $nb,
                'total_rec'   => $total_rec,
                'my_rec'      => $my_rec,
                'score_calc'  => $score_calc,
                'score_exist' => $already,
                'status'      => $status,
            ];
        }
    }
}

// ── Étape 3 : sauvegarde confirmée ────────────────────────────────────────────
if ($step === 3 && !empty($confirmed_ids) && $member_id) {
    // Recalcul et sauvegarde des IDs confirmés
    foreach ($confirmed_ids as $pid) {
        if ($pid <= 0) continue;
        // Recharger les données de cette participation
        $rq = @mysqli_query($con, "
            SELECT p.`id-participation` AS id_part, p.classement, p.recave,
                   (SELECT COUNT(*) FROM participation p2
                    WHERE p2.`id-activite` = p.`id-activite`
                      AND COALESCE(p2.`option`,'None') NOT IN ('Desinscrit','None')) AS nb_joueurs,
                   (SELECT COALESCE(SUM(COALESCE(p3.recave,0)),0) FROM participation p3
                    WHERE p3.`id-activite` = p.`id-activite`
                      AND COALESCE(p3.`option`,'None') NOT IN ('Desinscrit','None')) AS total_recaves
            FROM participation p
            WHERE p.`id-participation` = '".intval($pid)."'
              AND p.`id-membre` = '".intval($member_id)."'
            LIMIT 1
        ");
        if (!$rq) continue;
        $rd = mysqli_fetch_assoc($rq);
        if (!$rd) continue;
        $rank  = intval($rd['classement']);
        $nb    = intval($rd['nb_joueurs']);
        $total_rec = intval($rd['total_recaves']);
        $my_rec    = intval($rd['recave'] ?? 0);
        $denom = $nb + $total_rec - $my_rec;
        if ($denom <= 0 || $rank <= 0) continue;
        $score = round((1 - ($rank / $denom)) * 20, 2);
        @mysqli_query($con, "UPDATE `participation` SET `sergio_score` = '".floatval($score)."' WHERE `id-participation` = '".intval($pid)."'");
        $saved_count++;
    }
    $step = 4; // résultat final
    // recharger le pseudo
    $mq2 = @mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '".intval($member_id)."' LIMIT 1");
    if ($mq2) { $member_row = mysqli_fetch_assoc($mq2); }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mise à jour SergioScore</title>
    <style>
    :root{--muted:#8b98a6;--gold:#ffb400;--orange:#ff7a45;--green:#18b041;--blue:#00b6ff;--red:#ff6b6b;--bg:#071019;--card:#0d1d2b}
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:var(--bg);color:#eef6fb;font-family:Inter,system-ui,-apple-system,Arial;font-size:14px;padding-bottom:40px}
    a{color:var(--orange);text-decoration:none}
    .page{max-width:820px;margin:0 auto;padding:16px 12px;position:relative}
    .close-btn{position:absolute;top:16px;right:12px;padding:6px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.08);color:var(--orange);font-weight:700}
    h1{font-size:20px;font-weight:900;color:var(--gold);margin-bottom:4px}
    .sub{color:var(--muted);font-size:13px;margin-bottom:18px}
    label{display:block;margin-bottom:6px;font-weight:700;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.5px}
    input[type=text]{width:100%;max-width:360px;background:#0d1d2b;border:1px solid rgba(255,255,255,0.12);border-radius:10px;color:#eef6fb;padding:10px 14px;font-size:15px;outline:none}
    input[type=text]:focus{border-color:var(--blue)}
    .btn{display:inline-flex;align-items:center;gap:6px;padding:9px 20px;border-radius:10px;border:0;font-weight:800;font-size:14px;cursor:pointer}
    .btn-primary{background:var(--blue);color:#04131d}
    .btn-gold{background:var(--gold);color:#04131d}
    .btn-danger{background:var(--red);color:#fff}
    .btn-muted{background:rgba(255,255,255,0.08);color:#eef6fb}
    .msg{margin-bottom:16px;padding:10px 14px;border-radius:8px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);font-size:13px}

    /* Table */
    table{width:100%;border-collapse:collapse;margin-top:14px}
    th{font-size:11px;color:var(--muted);text-align:left;padding:6px 8px;border-bottom:1px solid rgba(255,255,255,.06);text-transform:uppercase;letter-spacing:.3px}
    th.c,td.c{text-align:center}
    th.r,td.r{text-align:right}
    td{padding:8px 8px;border-bottom:1px solid rgba(255,255,255,.03);vertical-align:middle}
    tr:hover td{background:rgba(255,255,255,.02)}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700}
    .tag-eligible{background:rgba(24,176,65,.15);color:var(--green)}
    .tag-skip{background:rgba(255,255,255,.06);color:var(--muted)}
    .tag-same{background:rgba(0,182,255,.08);color:var(--blue)}
    .tag-no{background:rgba(255,107,107,.1);color:var(--red)}
    .summary-bar{background:var(--card);border-radius:10px;padding:12px 16px;margin-top:14px;display:flex;gap:20px;flex-wrap:wrap;border:1px solid rgba(255,255,255,.05)}
    .summary-bar .item{text-align:center}
    .summary-bar .val{font-size:20px;font-weight:900}
    .summary-bar .lbl{font-size:11px;color:var(--muted);margin-top:2px}
    .actions{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap;align-items:center}
    .check-all-row{margin-bottom:8px;font-size:12px;color:var(--muted)}
    input[type=checkbox]{accent-color:var(--green);width:16px;height:16px;cursor:pointer}
    </style>
</head>
<body>
<div class="page">
    <a href="/panel/profile.php" class="close-btn">← Retour</a>
    <h1>🔧 Mise à jour SergioScore</h1>
    <div class="sub">Calcul et alimentation des anciennes participations.</div>

    <?php if ($message): ?>
    <div class="msg"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════ ÉTAPE 1 : Saisie pseudo -->
    <?php if ($step === 1): ?>
    <form method="post">
        <input type="hidden" name="step" value="2">
        <label for="pseudo">Pseudo du joueur à traiter</label>
        <input type="text" id="pseudo" name="pseudo" value="<?php echo h($pseudo_q); ?>" placeholder="Ex: Franck" autofocus>
        <div class="actions">
            <button type="submit" class="btn btn-primary">🔍 Rechercher</button>
        </div>
    </form>

    <!-- ═══════════════════════════════════════════════════ ÉTAPE 2 : Confirmation -->
    <?php elseif ($step === 2 && $member_id && !empty($candidates)): ?>
    <?php
        $nb_eligible   = count(array_filter($candidates, fn($c) => $c['status'] === 'eligible'));
        $nb_same       = count(array_filter($candidates, fn($c) => $c['status'] === 'already_same'));
        $nb_skip       = count(array_filter($candidates, fn($c) => in_array($c['status'], ['no_classement','no_joueurs','skip'])));
    ?>

    <div style="font-size:16px;font-weight:800;margin-bottom:4px">
        Joueur : <span style="color:var(--blue)"><?php echo h($member_row['pseudo']); ?></span>
    </div>

    <div class="summary-bar">
        <div class="item"><div class="val" style="color:var(--green)"><?php echo $nb_eligible; ?></div><div class="lbl">À calculer</div></div>
        <div class="item"><div class="val" style="color:var(--blue)"><?php echo $nb_same; ?></div><div class="lbl">Déjà à jour</div></div>
        <div class="item"><div class="val" style="color:var(--muted)"><?php echo $nb_skip; ?></div><div class="lbl">Données insuffisantes</div></div>
        <div class="item"><div class="val"><?php echo count($candidates); ?></div><div class="lbl">Participations totales</div></div>
    </div>

    <?php if ($nb_eligible === 0): ?>
        <div class="msg" style="margin-top:14px;color:var(--muted)">Aucune participation éligible à mettre à jour.</div>
        <div class="actions">
            <a href="updatesergioscore.php" class="btn btn-muted">← Recommencer</a>
        </div>
    <?php else: ?>
    <form method="post" id="confirm-form">
        <input type="hidden" name="step" value="3">
        <input type="hidden" name="member_id" value="<?php echo intval($member_id); ?>">
        <input type="hidden" name="pseudo" value="<?php echo h($member_row['pseudo']); ?>">
        <input type="hidden" name="confirmed_ids" id="confirmed_ids" value="">

        <table style="margin-top:16px">
            <thead>
                <tr>
                    <th><input type="checkbox" id="chk-all" title="Tout cocher/décocher"></th>
                    <th>Date</th>
                    <th>Partie</th>
                    <th class="c">Place</th>
                    <th class="c">Joueurs</th>
                    <th class="c">Recaves (joueur)</th>
                    <th class="c">Recaves (activité)</th>
                    <th class="r">Score calculé</th>
                    <th class="r">Score actuel</th>
                    <th class="c">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $c):
                $is_eligible = ($c['status'] === 'eligible');
                $dt = $c['date'] ? date('d/m/Y', strtotime($c['date'])) : '—';
                $score_disp = $c['score_calc'] !== null ? $c['score_calc'] : '—';
                $exist_disp = $c['score_exist'] !== null ? $c['score_exist'] : '—';
                switch ($c['status']) {
                    case 'eligible':      $tag = '<span class="badge tag-eligible">✓ Éligible</span>'; break;
                    case 'already_same':  $tag = '<span class="badge tag-same">= Identique</span>';  break;
                    case 'no_classement': $tag = '<span class="badge tag-no">✗ Pas de classement</span>'; break;
                    case 'no_joueurs':    $tag = '<span class="badge tag-no">✗ &lt; 2 joueurs</span>'; break;
                    default:              $tag = '<span class="badge tag-skip">— Ignoré</span>';
                }
            ?>
            <tr style="<?php echo !$is_eligible ? 'opacity:.45' : ''; ?>">
                <td class="c">
                    <?php if ($is_eligible): ?>
                    <input type="checkbox" class="row-chk" value="<?php echo $c['id_part']; ?>" checked>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);font-size:12px;white-space:nowrap"><?php echo $dt; ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600" title="<?php echo h($c['titre']); ?>"><?php echo h($c['titre']); ?></td>
                <td class="c"><?php echo $c['rank'] > 0 ? $c['rank'] : '—'; ?></td>
                <td class="c" style="color:var(--muted)"><?php echo $c['nb_joueurs']; ?></td>
                <td class="c" style="color:var(--orange)"><?php echo $c['my_rec']; ?></td>
                <td class="c" style="color:var(--muted)"><?php echo $c['total_rec']; ?></td>
                <td class="r"><strong style="color:<?php echo ($c['score_calc'] !== null && $c['score_calc'] >= 15) ? 'var(--green)' : 'var(--muted)'; ?>"><?php echo $score_disp; ?></strong></td>
                <td class="r" style="color:var(--muted)"><?php echo $exist_disp; ?></td>
                <td class="c"><?php echo $tag; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="actions">
            <button type="button" class="btn btn-gold" onclick="submitConfirmed()">💾 Sauvegarder les cochés</button>
            <a href="updatesergioscore.php" class="btn btn-muted">← Recommencer</a>
        </div>
    </form>

    <script>
    document.getElementById('chk-all').addEventListener('change', function(){
        document.querySelectorAll('.row-chk').forEach(c => c.checked = this.checked);
    });
    function submitConfirmed(){
        const ids = Array.from(document.querySelectorAll('.row-chk:checked')).map(c => c.value);
        if (!ids.length){ alert('Aucune ligne cochée.'); return; }
        if (!confirm('Confirmer la sauvegarde de ' + ids.length + ' SergioScore(s) ?')) return;
        document.getElementById('confirmed_ids').value = ids.join(',');
        document.getElementById('confirm-form').submit();
    }
    </script>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════ ÉTAPE 4 : Résultat -->
    <?php elseif ($step === 4): ?>
    <div class="msg" style="border-color:var(--green);color:var(--green);font-size:15px;font-weight:700">
        ✅ <?php echo $saved_count; ?> SergioScore(s) sauvegardé(s) pour <strong><?php echo h($member_row['pseudo'] ?? ''); ?></strong>.
    </div>
    <div class="actions">
        <a href="/panel/sergio.php?mid=<?php echo intval($member_id); ?>" class="btn btn-gold">⭐ Voir l'historique</a>
        <a href="updatesergioscore.php" class="btn btn-muted">← Traiter un autre joueur</a>
    </div>

    <?php elseif ($step === 2 && $member_id && empty($candidates)): ?>
    <div class="msg">Aucune participation trouvée pour ce membre.</div>
    <div class="actions"><a href="updatesergioscore.php" class="btn btn-muted">← Recommencer</a></div>
    <?php endif; ?>

</div>
</body>
</html>
