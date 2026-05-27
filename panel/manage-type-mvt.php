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
            if ($id <= 0 || $label === '') throw new Exception('ID et label requis');
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
    .table th,.table td{padding:10px;border-bottom:1px solid rgba(255,255,255,0.04);font-size:14px}
    .table thead th{color:#a9c2d6;text-align:left;font-weight:800}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#08b0ff;color:#04131d;text-decoration:none;font-weight:800}
    .btn.secondary{background:#16a34a;color:#071017}
    .btn.orange{background:#ff8a00;color:#fff;border:1px solid rgba(0,0,0,0.08)}
    .form-control{width:100%;padding:10px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
    .muted{color:#9aa6b1;font-size:13px}
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

        <h3>Ajouter / Mettre à jour</h3>
        <form method="post" style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
            <input type="hidden" name="action" value="add">
            <input name="id_type_mvt" type="number" placeholder="ID (ex: 7)" required style="width:100px">
            <input name="label" type="text" placeholder="Libellé" required style="flex:1">
            <select name="direction">
                <option value="credit">Crédit</option>
                <option value="debit">Débit</option>
            </select>
            <button class="btn" type="submit">Ajouter / Mettre à jour</button>
        </form>

        <h3>Types existants</h3>
        <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Libellé</th><th>Direction</th><th>Actions</th></tr></thead>
            <tbody>
            <?php if (count($types) === 0) echo '<tr><td colspan="4">Aucun type enregistré</td></tr>';
            foreach ($types as $t) {
                echo '<tr>';
                echo '<td>' . intval($t['id_type_mvt']) . '</td>';
                echo '<td>' . htmlspecialchars($t['label']) . '</td>';
                echo '<td>' . htmlspecialchars($t['direction']) . '</td>';
                echo '<td>';
                // edit form
                echo '<form method="post" class="inline" style="margin-right:6px">';
                echo '<input type="hidden" name="action" value="edit">';
                echo '<input type="hidden" name="id_type_mvt" value="' . intval($t['id_type_mvt']) . '">';
                echo '<input name="label" value="' . htmlspecialchars($t['label']) . '" required style="width:220px"> ';
                echo '<select name="direction">';
                echo '<option value="credit"' . ($t['direction']==='credit' ? ' selected' : '') . '>Crédit</option>';
                echo '<option value="debit"' . ($t['direction']==='debit' ? ' selected' : '') . '>Débit</option>';
                echo '</select> ';
                echo '<button class="btn" type="submit">Mettre à jour</button>';
                echo '</form>';
                // delete form
                echo '<form method="post" class="inline" onsubmit="return confirm(\'Supprimer ce type ?\');">';
                echo '<input type="hidden" name="action" value="delete">';
                echo '<input type="hidden" name="id_type_mvt" value="' . intval($t['id_type_mvt']) . '">';
                echo '<button class="btn danger" type="submit">Supprimer</button>';
                echo '</form>';
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
