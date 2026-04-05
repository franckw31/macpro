<?php
session_start();
error_reporting(0);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$id = intval($_GET['uid']);
if ($id <= 0) {
    header('location:voir-blindes.php');
    exit;
}

// Déterminer si l'utilisateur est admin (droits = 2)
$is_admin = false;
if (isset($_SESSION['id']) && intval($_SESSION['id']) > 0) {
    $q_admin = mysqli_query($con, "SELECT `droits` FROM `membres` WHERE `id-membre` = " . intval($_SESSION['id']));
    if ($q_admin && mysqli_num_rows($q_admin) > 0) {
        $r_admin = mysqli_fetch_array($q_admin);
        if ($r_admin && isset($r_admin['droits']) && intval($r_admin['droits']) === 2) {
            $is_admin = true;
        }
    }
}

$timerUrl = 'fullscreen-cardevent.php?uid=' . urlencode((string)$id);
$playerUrl = ($is_admin ? 'fullscreen-player-simple.php' : 'fullscreen-player.php') . '?uid=' . urlencode((string)$id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fullscreen Combo</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; overflow: hidden; background: #000; }
        body { display: flex; flex-direction: row; }
        .pane { flex: 1 1 50%; height: 100vh; border: none; }
        iframe { width: 100%; height: 100%; border: none; }
        @media (max-width: 1200px) {
            body { flex-direction: column; }
            .pane { flex: 1 1 50vh; height: 50vh; }
        }
    </style>
</head>
<body>
    <div class="pane">
        <iframe src="<?php echo htmlspecialchars($timerUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Timer"></iframe>
    </div>
    <div class="pane">
        <iframe src="<?php echo htmlspecialchars($playerUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Joueurs"></iframe>
    </div>
</body>
</html>
