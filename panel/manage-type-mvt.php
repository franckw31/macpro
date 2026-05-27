<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

// Only allow admin users 2 and 265
$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if (!in_array($uid, [2,265], true)) {
    $_SESSION['error'] = 'Permission refusée';
    header('Location: /panel/money.php');
    exit;
}

// Handle POST actions: add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $id = intval($_POST['id_type_mvt'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $direction = in_array(strtolower(trim($_POST['direction'] ?? '')), ['debit','credit']) ? strtolower(trim($_POST['direction'])) : 'credit';
            if ($label === '') throw new Exception('Label requis');
            // If no id provided, pick next available id
            if ($id <= 0) {
                $rq = @mysqli_query($con, "SELECT COALESCE(MAX(id_type_mvt),0)+1 AS nextid FROM type_mvt");
                if ($rq && ($row = mysqli_fetch_assoc($rq))) {
                    $id = intval($row['nextid']);
                } else {
                    $id = 1;
                }
            }
            $stmt = mysqli_prepare($con, "INSERT INTO type_mvt (id_type_mvt,label,direction) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label), direction = VALUES(direction)");
            if (!$stmt) throw new Exception('Prepare failed: ' . mysqli_error($con));
            mysqli_stmt_bind_param($stmt, 'iss', $id, $label, $direction);
            if (!mysqli_stmt_execute($stmt)) throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
            $_SESSION['msg'] = 'Type ajouté / mis à jour.';
        } elseif ($action === 'edit') {
            $id = intval($_POST['id_type_mvt'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $direction = in_array(strtolower(trim($_POST['direction'] ?? '')), ['debit','credit']) ? strtolower(trim($_POST['direction'])) : 'credit';
            if ($id <= 0 || $label === '') throw new Exception('ID et label requis');
            $stmt = mysqli_prepare($con, "UPDATE type_mvt SET label = ?, direction = ? WHERE id_type_mvt = ?");
            if (!$stmt) throw new Exception('Prepare failed: ' . mysqli_error($con));
            mysqli_stmt_bind_param($stmt, 'ssi', $label, $direction, $id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
            $_SESSION['msg'] = 'Type mis à jour.';
        } elseif ($action === 'delete') {
            $id = intval($_POST['id_type_mvt'] ?? 0);
            if ($id <= 0) throw new Exception('ID requis');
            $stmt = mysqli_prepare($con, "DELETE FROM type_mvt WHERE id_type_mvt = ?");
            if (!$stmt) throw new Exception('Prepare failed: ' . mysqli_error($con));
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (!mysqli_stmt_execute($stmt)) throw new Exception('Execute failed: ' . mysqli_stmt_error($stmt));
            $_SESSION['msg'] = 'Type supprimé.';
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header('Location: /panel/manage-type-mvt.php');
    exit;
}

// Fetch types
$types = [];
$tq = @mysqli_query($con, "SELECT id_type_mvt, label, direction FROM type_mvt ORDER BY id_type_mvt ASC");
if ($tq) while ($r = mysqli_fetch_assoc($tq)) $types[] = $r;

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Gérer types Mouvements</title>
    <style>
    body{background:linear-gradient(180deg,#051018 0%, rgba(2,8,12,0.85) 100%);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;padding:18px;color:#eef6fb}
    .sheet{max-width:980px;margin:18px auto;background:linear-gradient(180deg,#071019,#08131a);color:#eef6fb;border-radius:14px;padding:18px;box-shadow:0 14px 50px rgba(0,0,0,0.6)}
    .top{display:grid;grid-template-columns:1fr 320px;gap:14px;align-items:start}
    .card{background:linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:14px;border-radius:12px;margin-top:8px;border:1px solid rgba(255,255,255,0.04)}
    .label{color:#9aa6b1;font-weight:700;font-size:13px}
    .value{font-weight:800;color:#eef6fb}
    .table{width:100%;border-collapse:collapse}
    .table-wrap{overflow-x:auto}
    form.inline{display:inline-flex;gap:8px;align-items:center;flex-wrap:nowrap}
    .table td{vertical-align:middle}
    /* Force ID cell to one line, truncate and use minimal width */
    .table td:first-child{white-space:nowrap;width:44px;min-width:44px;overflow:hidden;text-overflow:ellipsis;text-align:center;padding-right:8px;padding-left:8px}
    .table thead th:first-child{text-align:center;width:44px;min-width:44px;padding-right:8px;padding-left:8px}
    /* Allow actions cell content to wrap when needed */
    .table td:last-child{white-space:normal}
    .inline input[type="text"], .inline input[type="number"]{min-width:120px;max-width:220px}
    .inline select{min-width:90px;max-width:140px}
    .inline button{white-space:nowrap}
    .table-wrap{overflow-x:auto}
    .table th,.table td{padding:10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:14px}
    .table thead th{color:#a9c2d6;text-align:left;font-weight:800}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#08b0ff;color:#04131d;text-decoration:none;font-weight:800}
    .btn .icon{display:inline-block;width:16px;height:16px;vertical-align:middle;margin-right:8px;fill:currentColor}
    .btn .btn-text{display:inline-block;vertical-align:middle}
    .btn.small{padding:4px;font-size:13px;border-radius:8px;min-width:34px;width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center}
    .btn.small .icon{width:16px;height:16px;margin-right:0}
    .btn.small .btn-text{display:none}
    .btn.secondary{background:#16a34a;color:#071017}
    .btn.orange{background:#ff8a00;color:#fff;border:1px solid rgba(0,0,0,0.08)}
    .form-control{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
    .muted{color:#9aa6b1;font-size:13px;display:inline-block;vertical-align:middle;margin-right:10px}
    .danger{background:#ff6b6b;color:#fff}
    .actions{display:flex;gap:8px;align-items:center}
    .flash{padding:10px;border-radius:8px;margin-bottom:10px}
    .success{background:#163b5a;color:#aef0c9}
    .error{background:#5a1616;color:#ffb6b6}

    @media (max-width: 880px){
        .top{grid-template-columns:1fr}
        .sheet{padding:12px}
    }
    @media (max-width: 720px){
        body{padding:12px}
        .sheet{padding:12px;border-radius:10px}
        .btn{display:inline-block;text-align:center;padding:8px 10px}
        .actions{flex-direction:column;align-items:stretch}
        form.inline{flex-wrap:wrap}
        form.inline input, form.inline select, form.inline button{width:100%;box-sizing:border-box}
        .table td{white-space:normal}
    }

    @media (max-width: 480px){
        .btn{padding:8px 10px}
        .btn .btn-text{display:none}
        .btn .icon{margin-right:0}
    }
    /* Keep rows single-line on small screens; allow horizontal scroll */
    @media (max-width: 720px){
        .table-wrap{overflow-x:auto}
        .table{min-width:640px}
        .table td{white-space:nowrap}
        form.inline{flex-wrap:nowrap}
        .btn.small{width:34px;height:34px}
    }
    </style>
</head>
<body>
    <div class="sheet">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <h2 style="margin:0">Gérer types Mouvements</h2>
            <a class="btn" href="/panel/money.php">Retour portefeuille</a>
        </div>
        <?php if (!empty($_SESSION['msg'])) { echo '<div class="flash success">' . htmlspecialchars($_SESSION['msg']) . '</div>'; unset($_SESSION['msg']); }
              if (!empty($_SESSION['error'])) { echo '<div class="flash error">' . htmlspecialchars($_SESSION['error']) . '</div>'; unset($_SESSION['error']); } ?>

        <h3>Ajouter</h3>
        <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
            <input type="hidden" name="action" value="add">
            <input name="label" type="text" placeholder="Libellé" required style="flex:1">
            <select name="direction">
                <option value="credit">Crédit</option>
                <option value="debit">Débit</option>
            </select>
            <button class="btn" type="submit"><svg class="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/></svg><span class="btn-text">Ajouter</span></button>
        </form>

        <h3>Types existants</h3>
        <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ID</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (count($types) === 0) echo '<tr><td colspan="2">Aucun type enregistré</td></tr>';
            foreach ($types as $t) {
                echo '<tr>';
                echo '<td style="width:80px">' . intval($t['id_type_mvt']) . '</td>';
                echo '<td>';
                // show label/direction briefly for context inside actions (inline)
                echo '<span class="muted">' . htmlspecialchars($t['label']) . ' — ' . htmlspecialchars($t['direction']) . '</span>';
                echo '<div class="actions">';
                // edit form
                echo '<form method="post" class="inline">';
                echo '<input type="hidden" name="action" value="edit">';
                echo '<input type="hidden" name="id_type_mvt" value="' . intval($t['id_type_mvt']) . '">';
                echo '<input name="label" value="' . htmlspecialchars($t['label']) . '" required style="width:180px"> ';
                echo '<select name="direction" style="width:110px">';
                echo '<option value="credit"' . ($t['direction']==='credit' ? ' selected' : '') . '>Crédit</option>';
                echo '<option value="debit"' . ($t['direction']==='debit' ? ' selected' : '') . '>Débit</option>';
                echo '</select> ';
                echo '<button class="btn small" type="submit" title="Modifier"><svg class="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1.003 1.003 0 0 0 0-1.42l-2.34-2.34a1.003 1.003 0 0 0-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.82z"/></svg><span class="btn-text">Modifier</span></button>';
                echo '</form>';
                // delete form
                echo '<form method="post" class="inline" onsubmit="return confirm(\'Supprimer ce type ?\');">';
                echo '<input type="hidden" name="action" value="delete">';
                echo '<input type="hidden" name="id_type_mvt" value="' . intval($t['id_type_mvt']) . '">';
                echo '<button class="btn danger small" type="submit" title="Supprimer"><svg class="icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg><span class="btn-text">Sup</span></button>';
                echo '</form>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
