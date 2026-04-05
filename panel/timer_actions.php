<?php
// On force l'affichage des erreurs pour le debug (à voir dans l'onglet Réseau > Réponse)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 1. Vérification de sécurité : l'utilisateur est-il connecté ?
if (empty($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Session expirée']);
    exit;
}

// 2. Inclusion de la configuration avec chemin absolu pour éviter les erreurs de chemin
// On cherche le fichier config.php par rapport au dossier actuel
$configPath = __DIR__ . '/include/config.php';

if (file_exists($configPath)) {
    require_once($configPath);
} else {
    // Fallback si le dossier include est un niveau au-dessus
    $configPath = __DIR__ . '/../include/config.php';
    if (file_exists($configPath)) {
        require_once($configPath);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        die("Erreur critique : Fichier de configuration introuvable.");
    }
}

// 3. Vérification de la connexion DB
if (!isset($con) || !$con) {
    header('HTTP/1.1 500 Internal Server Error');
    die("Erreur critique : La connexion à la base de données a échoué.");
}

// Vérification des paramètres POST
if (!isset($_POST['action']) || !isset($_POST['uid'])) {
    echo json_encode(['status' => 'error', 'message' => 'Paramètres manquants']);
    exit;
}

$id = intval($_POST['uid']);
$action = $_POST['action'];
$now = time();

// --- LOGIQUE MÉTIER ---

if (in_array($action, ['next_blind', 'prev_blind', 'reset_blind'])) {
    
    $q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
    if (!$q) { die("Erreur SQL Select: " . mysqli_error($con)); }
    
    $blinds = [];
    while($b = mysqli_fetch_assoc($q)) { $blinds[] = $b; }

    $currentIndex = -1;
    foreach($blinds as $k => $b) {
        if (strtotime($b['fin']) > $now) {
            $currentIndex = $k;
            break;
        }
    }

    $targetIndex = -1;

    if ($action == 'next_blind') {
        if ($currentIndex !== -1) {
            $currentId = $blinds[$currentIndex]['id'];
            $pastDate = date("Y-m-d H:i:s", $now - 1);
            mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$pastDate' WHERE `id` = '$currentId'");
            $targetIndex = $currentIndex + 1;
        }
    } elseif ($action == 'prev_blind') {
        if ($currentIndex === -1) $targetIndex = count($blinds) - 1;
        elseif ($currentIndex == 0) $targetIndex = 0;
        else $targetIndex = $currentIndex - 1;
    } elseif ($action == 'reset_blind') {
        $targetIndex = ($currentIndex === -1) ? count($blinds) - 1 : $currentIndex;
    }

    if ($targetIndex >= 0 && $targetIndex < count($blinds)) {
        $runningTime = $now;
        mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '0' WHERE `id-activite` = '$id'");

        foreach($blinds as $k => $b) {
            if ($k < $targetIndex) {
                if (strtotime($b['fin']) > $now) {
                     $past = date("Y-m-d H:i:s", $now - 60);
                     $bid = $b['id'];
                     mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$past' WHERE `id` = '$bid'");
                }
                continue; 
            }
            
            $duration = intval($b['minutes']) * 60;
            $runningTime += $duration;
            $newFin = date("Y-m-d H:i:s", $runningTime);
            $bId = $b['id'];
            mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$newFin' WHERE `id` = '$bId'");
        }
    }
}

if ($action == 'pauseresume') {
    $check_pause = mysqli_query($con, "SELECT `en_pause` FROM `blindes-live` WHERE `id-activite` = '$id' LIMIT 1");
    $row_pause = mysqli_fetch_array($check_pause);
    $etat_actuel = intval($row_pause['en_pause']);
    
    if ($etat_actuel == 0) {
        mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '1' WHERE `id-activite` = '$id'");
    } else {
        mysqli_query($con, "UPDATE `blindes-live` SET `en_pause` = '0' WHERE `id-activite` = '$id'");
        mysqli_query($con, "UPDATE `blindes-live` SET `fin` = DATE_ADD(`fin`, INTERVAL 1 SECOND) WHERE `id-activite` = '$id' AND `fin` > NOW()");
    }
}

if ($action == 'plus' || $action == 'moins') {
    $minutes = ($action == 'plus') ? 2 : -2;
    $seconds = $minutes * 60;
    mysqli_query($con, "UPDATE `blindes-live` SET `fin` = DATE_ADD(`fin`, INTERVAL $seconds SECOND) WHERE `id-activite` = '$id' AND `fin` > NOW()");
}

// Réponse JSON propre
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
?>