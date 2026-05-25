<?php
session_start();
include('include/config.php');
if (!function_exists('log_activity') && file_exists(__DIR__ . '/../include/functions_logs.php')) {
    include_once __DIR__ . '/../include/functions_logs.php';
}

$db = null;
if (isset($con)) {
    $db = $con;
} elseif (isset($conn)) {
    $db = $conn;
}

if (!$db) {
    http_response_code(500);
    echo 'Database unavailable';
    exit();
}

function has_column($db, $table, $column) {
    $res = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($db, $column) . "'");
    return $res && mysqli_num_rows($res) > 0;
}

function build_redirect_target($basePath, $activityId) {
    $target = is_string($basePath) && $basePath !== '' ? $basePath : '/panel/cardevent.php';
    if (strpos($target, '/panel/') !== 0) {
        $target = '/panel/cardevent.php';
    }
    if ($activityId > 0 && strpos($target, 'uid=') === false) {
        $target .= (strpos($target, '?') === false ? '?' : '&') . 'uid=' . $activityId;
    }
    return $target;
}

function finish_request($success, $message, $activityId, $participation, $redirectBase) {
    $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $target = build_redirect_target($redirectBase, $activityId);
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'activity_id' => $activityId,
            'participation' => $participation,
            'redirect' => $target,
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    header('Location: ' . $target);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_reg'])) {
    $userId = intval($_SESSION['id'] ?? ($_POST['logext'] ?? 0));
    $activityId = intval($_POST['uid'] ?? $_GET['uid'] ?? 0);
    $redirectBase = trim($_POST['redirect'] ?? '/panel/cardevent.php');

    if ($userId <= 0 || $activityId <= 0) {
        finish_request(false, 'Utilisateur ou activité invalide.', $activityId, null, $redirectBase);
    }

    $statusRaw = trim((string)($_POST['status'] ?? 'Inscrit'));
    $status = ($statusRaw === 'None') ? 'Desinscrit' : ($statusRaw !== '' ? $statusRaw : 'Inscrit');
    $anonyme = !empty($_POST['anonyme']) ? 1 : 0;
    $latereg = !empty($_POST['latereg']) ? 1 : 0;
    $optionChapitre = trim((string)($_POST['option_chapitre'] ?? ''));

    $hasAnonyme = has_column($db, 'participation', 'anonyme');
    $hasLatereg = has_column($db, 'participation', 'latereg');
    $hasOptionChapitre = has_column($db, 'participation', 'option_chapitre');
    $hasClassement = has_column($db, 'participation', 'classement');
    $hasValide = has_column($db, 'participation', 'valide');
    $hasNomMembre = has_column($db, 'participation', 'nom-membre');
    $hasDs = has_column($db, 'participation', 'ds');

    $memberQuery = mysqli_query($db, "SELECT pseudo FROM membres WHERE `id-membre` = '" . intval($userId) . "' LIMIT 1");
    $memberRow = $memberQuery ? mysqli_fetch_assoc($memberQuery) : null;
    $memberName = $memberRow && isset($memberRow['pseudo']) ? mysqli_real_escape_string($db, $memberRow['pseudo']) : '';

    // Fetch activity defaults for bonus calculation
    $actJetons = 0;
    $actDateDepart = "";
    $actRake = 0;
    $actQuery = mysqli_query($db, "SELECT jetons, date_depart, rake FROM activite WHERE `id-activite` = '" . intval($activityId) . "' LIMIT 1");
    if ($actQuery && ($actRow = mysqli_fetch_assoc($actQuery))) {
        $actJetons = intval($actRow['jetons'] ?? 0);
        $actDateDepart = $actRow['date_depart'] ?? "";
        $actRake = floatval($actRow['rake'] ?? 0);
    }

    $existsQuery = mysqli_query($db, "SELECT `id-participation` FROM participation WHERE `id-membre` = '" . intval($userId) . "' AND `id-activite` = '" . intval($activityId) . "' LIMIT 1");
    $existing = $existsQuery ? mysqli_fetch_assoc($existsQuery) : null;

    if ($existing) {
        $updates = ["`option` = '" . mysqli_real_escape_string($db, $status) . "'"];
        if ($hasValide) {
            $updates[] = "`valide` = '" . ($status === 'Desinscrit' ? 'Inactif' : 'Actif') . "'";
        }
        if ($hasAnonyme) {
            $updates[] = "`anonyme` = '$anonyme'";
        }
        if ($hasLatereg) {
            $updates[] = "`latereg` = '$latereg'";
        }
        if ($hasOptionChapitre) {
            $updates[] = "`option_chapitre` = '" . mysqli_real_escape_string($db, $optionChapitre) . "'";
        }
        if ($hasDs) {
            $updates[] = "`ds` = NOW()";
        }

        // Recalculate bonus if re-registering
        if ($status !== 'Desinscrit') {
            $bonus_ins = 0;
            if (!empty($actDateDepart)) {
                $diff_minutes = abs(strtotime($actDateDepart) - time()) / 60;
                $bonus_ins = min(5000, 200 * floor($diff_minutes / 60));
            }
            $jetons_total = $actJetons + $bonus_ins;
            $updates[] = "`jetons_bonus_ins` = '$bonus_ins'";
            $updates[] = "`jetons_total` = '$jetons_total'";
            $updates[] = "`rake` = '$actRake'";

            // Tombolas: give 1 if registering more than 24 hours before start
            if (has_column($db, 'participation', 'tombolas')) {
                $tombolas = 0;
                if (!empty($actDateDepart)) {
                    $timeUntil = strtotime($actDateDepart) - time();
                    if ($timeUntil > 24 * 3600) {
                        $tombolas = 1;
                    }
                }
                $updates[] = "`tombolas` = '$tombolas'";
            }
        }

        mysqli_query($db, "UPDATE participation SET " . implode(', ', $updates) . " WHERE `id-participation` = '" . intval($existing['id-participation']) . "'");
        if (function_exists('log_activity')) {
            $logAction = ($status === 'Desinscrit') ? 'desinscription' : 'modification_inscription';
            $logDetails = "Activite #$activityId | Statut: $status" . ($anonyme ? ' | Anonyme' : '') . ($latereg ? ' | Latereg' : '') . ($optionChapitre ? " | Chapitre: $optionChapitre" : '');
            log_activity($db, $logAction, $logDetails);
        }
    } elseif ($status !== 'Desinscrit') {
        $orderQuery = mysqli_query($db, "SELECT MAX(ordre) AS max_o FROM participation WHERE `id-activite` = '" . intval($activityId) . "'");
        $orderRow = $orderQuery ? mysqli_fetch_assoc($orderQuery) : null;
        $nextOrder = intval($orderRow['max_o'] ?? 0) + 1;

        // Calculate bonus values
        $bonus_ins = 0;
        if (!empty($actDateDepart)) {
            $diff_minutes = abs(strtotime($actDateDepart) - time()) / 60;
            $bonus_ins = min(5000, 200 * floor($diff_minutes / 60));
        }
        $jetons_total = $actJetons + $bonus_ins;

        // Tombolas: give 1 if registering more than 24 hours before start
        $tombolas = 0;
        if (has_column($db, 'participation', 'tombolas') && !empty($actDateDepart)) {
            $timeUntil = strtotime($actDateDepart) - time();
            if ($timeUntil > 24 * 3600) {
                $tombolas = 1;
            }
        }

        $columns = ['`id-membre`', '`id-activite`', '`ordre`', '`id-siege`', '`id-table`', '`option`', '`rake`', '`jetons_bonus_ins`', '`jetons_total`'];
        $values = ["'" . intval($userId) . "'", "'" . intval($activityId) . "'", "'" . intval($nextOrder) . "'", "'0'", "'0'", "'" . mysqli_real_escape_string($db, $status) . "'", "'$actRake'", "'$bonus_ins'", "'$jetons_total'"];

        if ($hasNomMembre) {
            $columns[] = '`nom-membre`';
            $values[] = "'" . $memberName . "'";
        }
        if ($hasAnonyme) {
            $columns[] = '`anonyme`';
            $values[] = "'$anonyme'";
        }
        if ($hasLatereg) {
            $columns[] = '`latereg`';
            $values[] = "'$latereg'";
        }
        if (has_column($db, 'participation', 'tombolas')) {
            $columns[] = '`tombolas`';
            $values[] = "'$tombolas'";
        }
        if ($hasOptionChapitre) {
            $columns[] = '`option_chapitre`';
            $values[] = "'" . mysqli_real_escape_string($db, $optionChapitre) . "'";
        }
        if ($hasClassement) {
            $columns[] = '`classement`';
            $values[] = "'0'";
        }
        if ($hasValide) {
            $columns[] = '`valide`';
            $values[] = "'Actif'";
        }
        if ($hasDs) {
            $columns[] = '`ds`';
            $values[] = 'NOW()';
        }

        mysqli_query($db, "INSERT INTO participation (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");
        if (function_exists('log_activity')) {
            $logDetails = "Activite #$activityId | Statut: $status" . ($anonyme ? ' | Anonyme' : '') . ($latereg ? ' | Latereg' : '') . ($optionChapitre ? " | Chapitre: $optionChapitre" : '');
            log_activity($db, 'nouvelle_inscription', $logDetails);
        }
    }

    $participation = [
        'status' => $status === 'Desinscrit' ? 'Desinscrit' : $status,
        'anonyme' => $anonyme,
        'latereg' => $latereg,
        'option_chapitre' => $optionChapitre,
    ];

    finish_request(true, 'Inscription mise à jour.', $activityId, $participation, $redirectBase);
}

$activityId = intval($_GET['uid'] ?? 0);
$externalLoginId = intval($_GET['logext'] ?? 0);
$userId = intval($_SESSION['id'] ?? 0);
if ($userId === 0) {
    $userId = $externalLoginId;
}

if ($userId > 0 && $activityId > 0) {
    $check = mysqli_query($db, "SELECT `id-participation` FROM participation WHERE `id-membre` = '" . intval($userId) . "' AND `id-activite` = '" . intval($activityId) . "' LIMIT 1");
    if ($check && mysqli_num_rows($check) === 0) {
        $orderQuery = mysqli_query($db, "SELECT MAX(ordre) AS max_o FROM participation WHERE `id-activite` = '" . intval($activityId) . "'");
        $orderRow = $orderQuery ? mysqli_fetch_assoc($orderQuery) : null;
        $nextOrder = intval($orderRow['max_o'] ?? 0) + 1;
        $memberQuery = mysqli_query($db, "SELECT pseudo FROM membres WHERE `id-membre` = '" . intval($userId) . "' LIMIT 1");
        $memberRow = $memberQuery ? mysqli_fetch_assoc($memberQuery) : null;
        $memberName = $memberRow && isset($memberRow['pseudo']) ? mysqli_real_escape_string($db, $memberRow['pseudo']) : '';

        // Determine tombolas for auto-URL registration (>24h before start)
        $tombolas = 0;
        $actQuery = mysqli_query($db, "SELECT date_depart FROM activite WHERE `id-activite` = '" . intval($activityId) . "' LIMIT 1");
        if ($actQuery && ($actRow = mysqli_fetch_assoc($actQuery)) && has_column($db, 'participation', 'tombolas')) {
            $actDate = $actRow['date_depart'] ?? '';
            if (!empty($actDate)) {
                $timeUntil = strtotime($actDate) - time();
                if ($timeUntil > 24 * 3600) {
                    $tombolas = 1;
                }
            }
        }

        $columns = ['`id-membre`', '`id-activite`', '`ordre`', '`id-siege`', '`id-table`'];
        $values = ["'" . intval($userId) . "'", "'" . intval($activityId) . "'", "'" . intval($nextOrder) . "'", "'0'", "'0'"];
        if (has_column($db, 'participation', 'nom-membre')) {
            $columns[] = '`nom-membre`';
            $values[] = "'" . $memberName . "'";
        }
        if (has_column($db, 'participation', 'option')) {
            $columns[] = '`option`';
            $values[] = "'Inscrit'";
        }
        if (has_column($db, 'participation', 'classement')) {
            $columns[] = '`classement`';
            $values[] = "'0'";
        }
        if (has_column($db, 'participation', 'tombolas')) {
            $columns[] = '`tombolas`';
            $values[] = "'$tombolas'";
        }
        mysqli_query($db, "INSERT INTO participation (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")");
        if (function_exists('log_activity')) {
            $logDetails = "Activite #$activityId | Statut: Inscrit (auto via URL)";
            log_activity($db, 'nouvelle_inscription', $logDetails);
        }
    }
}

header('Location: /panel/voir-activite.php?uid=' . $activityId);
exit();