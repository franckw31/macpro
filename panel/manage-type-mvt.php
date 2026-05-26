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
        body{background:#071019;color:#eef6fb;font-family:system-ui;padding:18px}
        .sheet{max-width:900px;margin:18px auto;background:#08131a;padding:16px;border-radius:12px}
        .btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#08b0ff;color:#04131d;text-decoration:none;font-weight:800}
        .danger{background:#ff6b6b;color:#fff}
        .table-wrap{overflow-x:auto}
        table{width:100%;min-width:640px;border-collapse:collapse}
        th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);vertical-align:middle}
        input,select{padding:8px;border-radius:6px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
        form.inline{display:inline-flex;gap:6px;align-items:center;margin:0}
        .actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
        .flash{padding:8px;margin-bottom:10px;border-radius:8px}
        .success{background:rgba(22,163,74,0.12);color:#7cf0a8}
        .error{background:rgba(255,77,77,0.12);color:#ff9c9c}

        @media (max-width: 720px){
            body{padding:12px}
            .sheet{padding:12px;border-radius:10px}
            .btn{width:100%;display:inline-block;text-align:center}
            form.inline{display:block}
            form.inline input, form.inline select, form.inline button{width:100%;margin:6px 0}
            .actions{flex-direction:column;align-items:stretch}
            table{min-width:0}
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
</body>
</html>
