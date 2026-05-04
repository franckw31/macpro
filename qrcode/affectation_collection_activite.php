<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_path = dirname(__DIR__) . '/config.php';
if (!file_exists($config_path)) {
    die('Config introuvable : ' . htmlspecialchars($config_path, ENT_QUOTES, 'UTF-8'));
}

$conx = null;
require_once $config_path; // $conx
if (!$conx) {
    die('Erreur connexion MySQL : ' . mysqli_connect_error());
}

mysqli_set_charset($conx, 'utf8mb4');

function h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function formatActivityMonthLabel(?string $activityDate): string
{
    if (empty($activityDate)) {
        return 'ce mois';
    }

    $timestamp = strtotime($activityDate);
    if ($timestamp === false) {
        return 'ce mois';
    }

    $months = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre'
    ];

    $month = (int) date('n', $timestamp);
    $year = date('Y', $timestamp);

    return ($months[$month] ?? 'mois') . ' ' . $year;
}

function columnExists(mysqli $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '::' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $allowedTables = ['collections', 'collections-individu', 'participation'];
    if (!in_array($table, $allowedTables, true)) {
        $cache[$key] = false;
        return false;
    }

    $sql = 'SELECT COUNT(*) AS c
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $exists = $row && (int) ($row['c'] ?? 0) > 0;
    $stmt->close();

    $cache[$key] = $exists;
    return $exists;
}

function getActivities(mysqli $db): array
{
    $sql = 'SELECT `id-activite`, `titre-activite`, `date_depart`, `heure_depart`, `ville`
            FROM activite
            WHERE `date_depart` IS NOT NULL
            ORDER BY `date_depart` DESC, `heure_depart` DESC';

    $res = $db->query($sql);
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function getActivityById(mysqli $db, int $activityId): ?array
{
    $stmt = $db->prepare('SELECT `id-activite`, `titre-activite`, `date_depart`, `heure_depart`, `ville` FROM activite WHERE `id-activite` = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function getParticipantsByActivity(mysqli $db, int $activityId): array
{
        $hasJetonsBonusIns = columnExists($db, 'participation', 'jetons_bonus_ins');
        $selectJetonsBonusIns = $hasJetonsBonusIns ? 'COALESCE(p.jetons_bonus_ins, 0)' : '0';

    $sql = 'SELECT p.`id-membre`, p.option, p.`nom-membre`, m.pseudo
                        , ' . $selectJetonsBonusIns . ' AS jetons_bonus_ins
            FROM participation p
            LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
            WHERE p.`id-activite` = ?
              AND (p.option IS NULL OR p.option NOT IN ("Annule", "Desinscrit", "None", "Option"))
            ORDER BY COALESCE(m.pseudo, p.`nom-membre`) ASC';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id-membre' => (int) ($row['id-membre'] ?? 0),
            'pseudo' => $row['pseudo'] ?: ($row['nom-membre'] ?: ('Membre #' . (int) ($row['id-membre'] ?? 0))),
            'option' => $row['option'] ?? '',
            'jetons_bonus_ins' => (int) ($row['jetons_bonus_ins'] ?? 0)
        ];
    }

    $stmt->close();
    return $rows;
}

function getAvailableCollections(mysqli $db): array
{
    $hasCollectionValeur = columnExists($db, 'collections', 'valeur');
    $selectValeur = $hasCollectionValeur ? 'c.valeur' : '1 AS valeur';

    $sql = 'SELECT c.id_collection, c.nom, ' . $selectValeur . '
            FROM collections c
            LEFT JOIN `collections-individu` ci ON ci.id_col = c.id_collection AND ci.`id-indiv` IS NOT NULL AND ci.`id-indiv` > 0
            WHERE ci.id IS NULL
            ORDER BY c.id_collection ASC';

    $res = $db->query($sql);
    if (!$res) {
        return [];
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id_collection' => (int) $row['id_collection'],
            'nom' => $row['nom'] ?? '',
            'valeur' => isset($row['valeur']) ? (int) $row['valeur'] : 1
        ];
    }

    return $rows;
}

function countAvailableCollectionsForActivityMonth(mysqli $db, ?string $activityDate): int
{
    if (empty($activityDate)) {
        return count(getAvailableCollections($db));
    }

    $hasIndividuDate = columnExists($db, 'collections-individu', 'date');
    if (!$hasIndividuDate) {
        return count(getAvailableCollections($db));
    }

    $timestamp = strtotime($activityDate);
    if ($timestamp === false) {
        return count(getAvailableCollections($db));
    }

    $month = (int) date('n', $timestamp);
    $year = (int) date('Y', $timestamp);

    $sql = 'SELECT COUNT(*) AS c
            FROM collections c
            WHERE NOT EXISTS (
                SELECT 1
                FROM `collections-individu` ci
                WHERE ci.id_col = c.id_collection
                  AND ci.`id-indiv` IS NOT NULL
                  AND ci.`id-indiv` > 0
                  AND MONTH(ci.`date`) = ?
                  AND YEAR(ci.`date`) = ?
            )';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return count(getAvailableCollections($db));
    }

    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['c'] ?? 0);
}

function getAvailableCollectionsForActivityMonth(mysqli $db, ?string $activityDate): array
{
    $hasCollectionValeur = columnExists($db, 'collections', 'valeur');
    $selectValeur = $hasCollectionValeur ? 'c.valeur' : '1 AS valeur';

    if (empty($activityDate) || !columnExists($db, 'collections-individu', 'date')) {
        return getAvailableCollections($db);
    }

    $timestamp = strtotime($activityDate);
    if ($timestamp === false) {
        return getAvailableCollections($db);
    }

    $month = (int) date('n', $timestamp);
    $year = (int) date('Y', $timestamp);

    $sql = 'SELECT c.id_collection, c.nom, ' . $selectValeur . '
            FROM collections c
            WHERE NOT EXISTS (
                SELECT 1
                FROM `collections-individu` ci
                WHERE ci.id_col = c.id_collection
                  AND ci.`id-indiv` IS NOT NULL
                  AND ci.`id-indiv` > 0
                  AND MONTH(ci.`date`) = ?
                  AND YEAR(ci.`date`) = ?
            )
            ORDER BY c.id_collection ASC';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return getAvailableCollections($db);
    }

    $stmt->bind_param('ii', $month, $year);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'id_collection' => (int) $row['id_collection'],
            'nom' => $row['nom'] ?? '',
            'valeur' => isset($row['valeur']) ? (int) $row['valeur'] : 1
        ];
    }
    $stmt->close();

    return $rows;
}

function memberHasCollectionForActivity(mysqli $db, int $memberId, int $activityId, ?string $activityDate): bool
{
    $hasIndividuDate = columnExists($db, 'collections-individu', 'date');

    if ($hasIndividuDate && !empty($activityDate)) {
        $stmt = $db->prepare('SELECT COUNT(*) AS c FROM `collections-individu` WHERE `id-indiv` = ? AND DATE(`date`) = DATE(?)');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('is', $memberId, $activityDate);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return !empty($row) && (int) ($row['c'] ?? 0) > 0;
    }

    $pattern1 = '%activité #' . $activityId . '%';
    $pattern2 = '%activite #' . $activityId . '%';
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM `collections-individu` WHERE `id-indiv` = ? AND (co LIKE ? OR co LIKE ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iss', $memberId, $pattern1, $pattern2);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return !empty($row) && (int) ($row['c'] ?? 0) > 0;
}

function getParticipantsWithoutCollectionForActivity(mysqli $db, int $activityId, ?string $activityDate): array
{
        $hasJetonsBonusIns = columnExists($db, 'participation', 'jetons_bonus_ins');
        $selectJetonsBonusIns = $hasJetonsBonusIns ? 'COALESCE(p.jetons_bonus_ins, 0)' : '0';

    $sql = 'SELECT DISTINCT p.`id-membre`, COALESCE(m.pseudo, p.`nom-membre`) AS pseudo
                        , ' . $selectJetonsBonusIns . ' AS jetons_bonus_ins
            FROM participation p
            LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
            WHERE p.`id-activite` = ?
              AND (p.option IS NULL OR p.option NOT IN ("Annule", "Desinscrit", "None", "Option"))
            ORDER BY COALESCE(m.pseudo, p.`nom-membre`) ASC';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $activityId);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $memberId = (int) ($row['id-membre'] ?? 0);
        if ($memberId <= 0) {
            continue;
        }

        if (!memberHasCollectionForActivity($db, $memberId, $activityId, $activityDate)) {
            $rows[] = [
                'id-membre' => $memberId,
                'pseudo' => $row['pseudo'] ?: ('Membre #' . $memberId),
                'jetons_bonus_ins' => (int) ($row['jetons_bonus_ins'] ?? 0)
            ];
        }
    }

    $stmt->close();
    return $rows;
}

$flash = $_SESSION['flash_affectation_collection_activite'] ?? null;
unset($_SESSION['flash_affectation_collection_activite']);

$selectedActivityId = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;
$selectedActivity = null;
$participants = [];
$availableCollections = [];
$availableCollectionsMonthCount = 0;
$activityMonthLabel = 'ce mois';
$participantsWithoutCollection = [];
$pendingAssignment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_assignment') {
    $activityId = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;
    $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    $collectionId = isset($_POST['collection_id']) ? (int) $_POST['collection_id'] : 0;
    $confirmed = isset($_POST['confirm']) && $_POST['confirm'] === '1';

    if ($activityId <= 0 || $memberId <= 0 || $collectionId <= 0 || !$confirmed) {
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Validation incomplète : activité, participant, collection et confirmation sont obligatoires.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $activity = getActivityById($conx, $activityId);
    if (!$activity) {
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Activité introuvable.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $conx->begin_transaction();

    try {
        // Vérifier participant dans l'activité
        $stmtPart = $conx->prepare('SELECT COUNT(*) AS c FROM participation WHERE `id-activite` = ? AND `id-membre` = ? AND (option IS NULL OR option NOT IN ("Annule", "Desinscrit", "None", "Option"))');
        if (!$stmtPart) {
            throw new RuntimeException('Erreur SQL participant: ' . $conx->error);
        }
        $stmtPart->bind_param('ii', $activityId, $memberId);
        $stmtPart->execute();
        $participantCheck = $stmtPart->get_result()->fetch_assoc();
        $stmtPart->close();

        if (empty($participantCheck) || (int) $participantCheck['c'] <= 0) {
            throw new RuntimeException('Le participant sélectionné n\'est pas valide pour cette activité.');
        }

        $collection = null;
        foreach (getAvailableCollectionsForActivityMonth($conx, $activity['date_depart'] ?? null) as $availableCollection) {
            if ((int) $availableCollection['id_collection'] === $collectionId) {
                $collection = $availableCollection;
                break;
            }
        }

        if (!$collection) {
            throw new RuntimeException('La collection choisie n\'est pas disponible pour le mois de cette activité.');
        }

        $coLabel = 'Affectation activité #' . (int) $activity['id-activite'] . ' - ' . ($activity['titre-activite'] ?? '');
        $dateValue = $activity['date_depart'] ?? date('Y-m-d');
        $valeur = isset($collection['valeur']) ? (int) $collection['valeur'] : 1;

        $hasIndividuValeur = columnExists($conx, 'collections-individu', 'valeur');
        $hasIndividuDate = columnExists($conx, 'collections-individu', 'date');

        $insertColumns = ['id_col', '`id-indiv`', 'co'];
        $insertPlaceholders = ['?', '?', '?'];

        if ($hasIndividuValeur) {
            $insertColumns[] = 'valeur';
            $insertPlaceholders[] = '?';
        }

        if ($hasIndividuDate) {
            $insertColumns[] = '`date`';
            $insertPlaceholders[] = '?';
        }

        $insertSql = 'INSERT INTO `collections-individu` (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
        $stmtIns = $conx->prepare($insertSql);
        if (!$stmtIns) {
            throw new RuntimeException('Erreur SQL insert: ' . $conx->error);
        }

        $bound = false;
        if ($hasIndividuValeur && $hasIndividuDate) {
            $bound = $stmtIns->bind_param('iisis', $collectionId, $memberId, $coLabel, $valeur, $dateValue);
        } elseif ($hasIndividuValeur && !$hasIndividuDate) {
            $bound = $stmtIns->bind_param('iisi', $collectionId, $memberId, $coLabel, $valeur);
        } elseif (!$hasIndividuValeur && $hasIndividuDate) {
            $bound = $stmtIns->bind_param('iiss', $collectionId, $memberId, $coLabel, $dateValue);
        } else {
            $bound = $stmtIns->bind_param('iis', $collectionId, $memberId, $coLabel);
        }

        if (!$bound) {
            throw new RuntimeException('Bind impossible: ' . $stmtIns->error);
        }

        if (!$stmtIns->execute()) {
            throw new RuntimeException('Insertion impossible: ' . $stmtIns->error);
        }

        $stmtIns->close();

        $conx->commit();

        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'success',
            'text' => 'Affectation enregistrée avec succès (activité #' . (int) $activity['id-activite'] . ', participant #' . $memberId . ', collection #' . $collectionId . ').'
        ];

        header('Location: ' . $_SERVER['PHP_SELF'] . '?activity_id=' . $activityId);
        exit;
    } catch (Throwable $e) {
        $conx->rollback();

        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => $e->getMessage() . ' (mysqli errno: ' . (int) $conx->errno . ', error: ' . $conx->error . ')'
        ];

        header('Location: ' . $_SERVER['PHP_SELF'] . '?activity_id=' . $activityId);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_selected_missing') {
    $activityId = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;
    $selectedMembers = isset($_POST['member_ids']) && is_array($_POST['member_ids']) ? $_POST['member_ids'] : [];
    $selectedMembers = array_values(array_unique(array_map('intval', $selectedMembers)));
    $selectedMembers = array_values(array_filter($selectedMembers, function ($v) {
        return $v > 0;
    }));

    if ($activityId <= 0) {
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Activité invalide pour l\'attribution.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if (empty($selectedMembers)) {
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Aucun joueur coché. Coche au moins un participant à confirmer.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?activity_id=' . $activityId);
        exit;
    }

    $activity = getActivityById($conx, $activityId);
    if (!$activity) {
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Activité introuvable.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $activityDate = $activity['date_depart'] ?? null;
    $participantsAuto = getParticipantsWithoutCollectionForActivity($conx, $activityId, $activityDate);
    if (empty($participantsAuto)) {
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Aucun participant sans collection pour cette activité.'
        ];
        header('Location: ' . $_SERVER['PHP_SELF'] . '?activity_id=' . $activityId);
        exit;
    }

    $allowedMemberIds = [];
    foreach ($participantsAuto as $pa) {
        $allowedMemberIds[(int) $pa['id-membre']] = true;
    }

    $conx->begin_transaction();

    try {
        $hasCollectionValeur = columnExists($conx, 'collections', 'valeur');
        $hasIndividuValeur = columnExists($conx, 'collections-individu', 'valeur');
        $hasIndividuDate = columnExists($conx, 'collections-individu', 'date');

        $assigned = 0;
        $notEligible = 0;
        $noCollectionLeft = 0;

        foreach ($selectedMembers as $memberId) {
            if ($memberId <= 0) {
                continue;
            }

            if (!isset($allowedMemberIds[$memberId])) {
                $notEligible++;
                continue;
            }

            if (memberHasCollectionForActivity($conx, $memberId, $activityId, $activityDate)) {
                $notEligible++;
                continue;
            }

            $availableForMonth = getAvailableCollectionsForActivityMonth($conx, $activityDate);
            $collection = !empty($availableForMonth) ? $availableForMonth[0] : null;

            if (!$collection) {
                $noCollectionLeft++;
                break;
            }

            $collectionId = (int) $collection['id_collection'];
            $dateValue = $activity['date_depart'] ?? date('Y-m-d');
            $coLabel = 'Auto activité #' . (int) $activity['id-activite'] . ' - ' . ($activity['titre-activite'] ?? '');
            $valeur = isset($collection['valeur']) ? (int) $collection['valeur'] : 1;

            $insertColumns = ['id_col', '`id-indiv`', 'co'];
            $insertPlaceholders = ['?', '?', '?'];

            if ($hasIndividuValeur) {
                $insertColumns[] = 'valeur';
                $insertPlaceholders[] = '?';
            }

            if ($hasIndividuDate) {
                $insertColumns[] = '`date`';
                $insertPlaceholders[] = '?';
            }

            $insertSql = 'INSERT INTO `collections-individu` (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
            $stmtIns = $conx->prepare($insertSql);
            if (!$stmtIns) {
                throw new RuntimeException('Erreur SQL insert auto: ' . $conx->error);
            }

            $bound = false;
            if ($hasIndividuValeur && $hasIndividuDate) {
                $bound = $stmtIns->bind_param('iisis', $collectionId, $memberId, $coLabel, $valeur, $dateValue);
            } elseif ($hasIndividuValeur && !$hasIndividuDate) {
                $bound = $stmtIns->bind_param('iisi', $collectionId, $memberId, $coLabel, $valeur);
            } elseif (!$hasIndividuValeur && $hasIndividuDate) {
                $bound = $stmtIns->bind_param('iiss', $collectionId, $memberId, $coLabel, $dateValue);
            } else {
                $bound = $stmtIns->bind_param('iis', $collectionId, $memberId, $coLabel);
            }

            if (!$bound) {
                throw new RuntimeException('Bind auto impossible: ' . $stmtIns->error);
            }

            if (!$stmtIns->execute()) {
                throw new RuntimeException('Insertion auto impossible: ' . $stmtIns->error);
            }
            $stmtIns->close();

            $assigned++;
        }

        $conx->commit();

        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'success',
            'text' => 'Attribution terminée : ' . $assigned . ' ajouté(s), ' . $notEligible . ' ignoré(s), ' . $noCollectionLeft . ' arrêt(s) faute de collection libre.'
        ];
    } catch (Throwable $e) {
        $conx->rollback();
        $_SESSION['flash_affectation_collection_activite'] = [
            'type' => 'error',
            'text' => 'Échec attribution auto: ' . $e->getMessage() . ' (mysqli errno: ' . (int) $conx->errno . ', error: ' . $conx->error . ')'
        ];
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?activity_id=' . $activityId);
    exit;
}

if ($selectedActivityId <= 0 && isset($_GET['activity_id']) && (int) $_GET['activity_id'] > 0) {
    $selectedActivityId = (int) $_GET['activity_id'];
}

$activities = getActivities($conx);

if ($selectedActivityId > 0) {
    $selectedActivity = getActivityById($conx, $selectedActivityId);
    if ($selectedActivity) {
        $participants = getParticipantsByActivity($conx, $selectedActivityId);
        $availableCollections = getAvailableCollectionsForActivityMonth($conx, $selectedActivity['date_depart'] ?? null);
        $activityMonthLabel = formatActivityMonthLabel($selectedActivity['date_depart'] ?? null);
        $availableCollectionsMonthCount = countAvailableCollectionsForActivityMonth($conx, $selectedActivity['date_depart'] ?? null);
        $participantsWithoutCollection = getParticipantsWithoutCollectionForActivity($conx, $selectedActivityId, $selectedActivity['date_depart'] ?? null);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_validation') {
    $activityId = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;
    $memberId = isset($_POST['member_id']) ? (int) $_POST['member_id'] : 0;
    $collectionId = isset($_POST['collection_id']) ? (int) $_POST['collection_id'] : 0;

    if ($activityId <= 0 || $memberId <= 0 || $collectionId <= 0) {
        $flash = [
            'type' => 'error',
            'text' => 'Veuillez choisir une activité, un participant et une collection avant validation.'
        ];
    } else {
        if ($selectedActivityId !== $activityId) {
            $selectedActivityId = $activityId;
            $selectedActivity = getActivityById($conx, $selectedActivityId);
            $participants = $selectedActivity ? getParticipantsByActivity($conx, $selectedActivityId) : [];
            $availableCollections = $selectedActivity ? getAvailableCollectionsForActivityMonth($conx, $selectedActivity['date_depart'] ?? null) : [];
        }

        $selectedParticipant = null;
        foreach ($participants as $p) {
            if ((int) $p['id-membre'] === $memberId) {
                $selectedParticipant = $p;
                break;
            }
        }

        $selectedCollection = null;
        foreach ($availableCollections as $c) {
            if ((int) $c['id_collection'] === $collectionId) {
                $selectedCollection = $c;
                break;
            }
        }

        if (!$selectedActivity || !$selectedParticipant || !$selectedCollection) {
            $flash = [
                'type' => 'error',
                'text' => 'Impossible de préparer la validation. Vérifiez les éléments sélectionnés.'
            ];
        } else {
            $pendingAssignment = [
                'activity_id' => $activityId,
                'activity_title' => $selectedActivity['titre-activite'] ?? '',
                'member_id' => $memberId,
                'member_pseudo' => $selectedParticipant['pseudo'] ?? ('Membre #' . $memberId),
                'collection_id' => $collectionId,
                'collection_nom' => $selectedCollection['nom'] ?? '',
                'collection_valeur' => (int) ($selectedCollection['valeur'] ?? 1)
            ];
        }
    }
}

$displayUser = 'Visiteur';
if (!empty($_SESSION['login'])) {
    $displayUser = $_SESSION['login'];
} elseif (!empty($_SESSION['user'])) {
    $displayUser = $_SESSION['user'];
} elseif (!empty($_COOKIE['uname'])) {
    $displayUser = $_COOKIE['uname'];
}
$displayUser = h($displayUser);

$avatar_url = 'https://viendez.com/images/noprofil.jpg';
try {
    if (!empty($_SESSION['id'])) {
        $uid = (int) $_SESSION['id'];
        $stmtPhoto = $conx->prepare('SELECT photo FROM membres WHERE `id-membre` = ? LIMIT 1');
        if ($stmtPhoto) {
            $stmtPhoto->bind_param('i', $uid);
            $stmtPhoto->execute();
            $rowPhoto = $stmtPhoto->get_result()->fetch_assoc();
            $stmtPhoto->close();
            if (!empty($rowPhoto['photo'])) {
                $avatar_url = 'https://viendez.com/images/faces/' . rawurlencode(basename($rowPhoto['photo']));
            }
        }
    }
} catch (Throwable $e) {
    // fallback noprofil
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affectation collection à participant</title>
    <style>
        *,*::before,*::after { box-sizing:border-box; }
        :root {
            --bg:#0a0d14;
            --card:#111822;
            --card2:#141e2b;
            --border:rgba(255,255,255,0.06);
            --blue:#0a84ff;
            --cyan:#30d5c8;
            --green:#34c759;
            --danger:#ff453a;
            --text:#ffffff;
            --text2:#c8d6e5;
            --muted:#8e9bae;
            --radius:16px;
            --radius-sm:12px;
        }
        html, body {
            margin:0;
            min-height:100%;
            background:var(--bg);
            color:var(--text);
            font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','Helvetica Neue',Arial,sans-serif;
            -webkit-font-smoothing:antialiased;
        }
        .page {
            max-width:440px;
            margin:0 auto;
            min-height:100vh;
            padding:14px 12px 92px;
            display:flex;
            flex-direction:column;
            gap:0;
        }
        .title {
            margin:4px 2px 12px;
            font-size:20px;
            font-weight:800;
            letter-spacing:.2px;
        }
        .v2-header{display:flex;align-items:center;justify-content:space-between;padding:10px 2px 12px;gap:12px}
        .v2-header-left{display:flex;align-items:center;gap:12px;min-width:0}
        .v2-logo{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
        .v2-logo img{width:100%;height:100%;object-fit:cover;display:block}
        .v2-app-name{font-size:18px;font-weight:800;letter-spacing:-0.3px;white-space:nowrap}
        .v2-app-name span{color:var(--blue)}
        .v2-version{background:rgba(10,132,255,0.18);color:var(--blue);font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px;margin-left:6px;vertical-align:middle}
        .v2-greeting{font-size:13px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .v2-greeting .name{color:var(--text2);font-weight:600;max-width:150px;overflow:hidden;text-overflow:ellipsis;display:inline-block;vertical-align:bottom}
        .v2-greeting .chev{color:var(--blue);font-weight:700}
        .v2-avatar{width:54px;height:54px;border-radius:50%;overflow:hidden;border:3px solid rgba(255,255,255,0.18);flex-shrink:0}
        .v2-avatar img{width:100%;height:100%;object-fit:cover;display:block}
        .card {
            border:1px solid var(--border);
            border-radius:var(--radius);
            padding:16px;
            margin-bottom:12px;
            background:linear-gradient(180deg,var(--card),var(--card2));
            box-shadow:0 10px 28px rgba(0,0,0,.25);
        }
        .section-title {
            margin:0 0 10px;
            font-size:15px;
            font-weight:700;
            color:var(--text2);
        }
        label {
            display:block;
            margin-bottom:8px;
            font-weight:600;
            color:var(--text2);
        }
        select, button {
            width:100%;
            min-height:46px;
            padding:11px 12px;
            border-radius:12px;
            border:1px solid var(--border);
            font-size:14px;
            color:var(--text);
            background:#0f1621;
        }
        button {
            cursor:pointer;
            background:linear-gradient(90deg,var(--blue),#0070dd);
            font-weight:700;
            margin-top:10px;
            border:none;
        }
        button:hover { filter:brightness(1.06); }
        .muted { color:var(--muted); font-size:13px; }
        .metric {
            margin-top:10px;
            color:var(--text2);
            background:#0f1621;
            border:1px solid var(--border);
            border-radius:10px;
            padding:10px;
            font-size:14px;
        }
        .metric strong { color:var(--cyan); }
        .flash {
            border-radius:12px;
            padding:12px;
            margin-bottom:12px;
            font-weight:700;
            border:1px solid transparent;
        }
        .flash.success { background:rgba(52,199,89,.18); color:#9ff0b2; border-color:rgba(52,199,89,.35); }
        .flash.error { background:rgba(255,69,58,.16); color:#ffb4ad; border-color:rgba(255,69,58,.35); }
        .confirm-wrap {
            margin-top:14px;
            border-top:1px solid var(--border);
            padding-top:12px;
        }
        .confirm-grid {
            display:grid;
            grid-template-columns:1fr;
            gap:8px;
        }
        .choice {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:8px;
            margin:0;
            font-weight:500;
            color:var(--text2);
            background:#0f1621;
            border:1px solid var(--border);
            border-radius:10px;
            padding:9px 10px;
        }
        .choice-main {
            display:flex;
            align-items:center;
            gap:8px;
            min-width:0;
            flex:1;
        }
        .choice-label {
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        }
        .choice-bonus {
            flex-shrink:0;
            color:var(--cyan);
            font-size:12px;
            font-weight:700;
            padding-left:8px;
        }
        .btn-cyan { background:linear-gradient(90deg,var(--cyan),#00b3b3); color:#032027; }
        @media (max-width: 780px) {
            .page { padding:12px 10px 92px; }
            .title { font-size:19px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="v2-header">
            <div class="v2-header-left">
                <div class="v2-logo">
                    <img src="/qrcode/joker_bg.jpg" alt="CardEvent">
                </div>
                <div>
                    <div class="v2-app-name">Card<span>Event</span><span class="v2-version">V 3.0</span></div>
                    <div class="v2-greeting">Bonjour, <span class="name"><?php echo $displayUser; ?></span> <span class="chev">›</span></div>
                </div>
            </div>
            <a href="/panel/profile.php" aria-label="Profil">
                <div class="v2-avatar">
                    <img src="<?php echo h($avatar_url); ?>" alt="avatar">
                </div>
            </a>
        </header>

        <h1 class="title">Affectation Ticket Tombola - Participant</h1>

        <?php if ($flash): ?>
            <div class="flash <?php echo h($flash['type'] ?? 'error'); ?>">
                <?php echo h($flash['text'] ?? ''); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="">
                <label for="activity_id">1) Choix de l'activité :</label>
                <select name="activity_id" id="activity_id" required>
                    <option value="">-- Sélectionner une activité --</option>
                    <?php foreach ($activities as $activity): ?>
                        <?php $aid = (int) $activity['id-activite']; ?>
                        <option value="<?php echo $aid; ?>" <?php echo ($selectedActivityId === $aid ? 'selected' : ''); ?>>
                            #<?php echo $aid; ?> — <?php echo h($activity['titre-activite']); ?>
                            (<?php echo h($activity['date_depart']); ?> <?php echo h($activity['heure_depart']); ?>, <?php echo h($activity['ville']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Valider</button>
            </form>
        </div>

        <?php if ($selectedActivity): ?>
            <div class="card">
                <h3 class="section-title">2) Confirmation joueur par joueur</h3>
                <?php if (empty($participants)): ?>
                    <p class="muted">Aucun participant valide pour cette activité.</p>
                <?php else: ?>
                    <p class="metric">
                        Collections non affectées à un joueur pour <?php echo h($activityMonthLabel); ?>
                        (tous joueurs et toutes parties confondus) :
                        <strong><?php echo (int) $availableCollectionsMonthCount; ?></strong>
                    </p>

                    <div class="confirm-wrap">
                        <?php if (empty($participantsWithoutCollection)): ?>
                            <p class="muted">Tous les participants ont déjà une collection.</p>
                        <?php else: ?>
                            <form method="post" action="" onsubmit="return confirm('Confirmer l\'attribution pour les joueurs cochés ?');">
                                <input type="hidden" name="action" value="assign_selected_missing">
                                <input type="hidden" name="activity_id" value="<?php echo (int) $selectedActivity['id-activite']; ?>">

                                <div class="confirm-grid">
                                    <?php foreach ($participantsWithoutCollection as $pm): ?>
                                        <label class="choice">
                                            <span class="choice-main">
                                                <input type="checkbox" name="member_ids[]" value="<?php echo (int) $pm['id-membre']; ?>" checked>
                                                <span class="choice-label">#<?php echo (int) $pm['id-membre']; ?> — <?php echo h($pm['pseudo']); ?></span>
                                            </span>
                                            <span class="choice-bonus"><?php echo (int) ($pm['jetons_bonus_ins'] ?? 0); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" class="btn-cyan">
                                    Attribuer une collection aux joueurs cochés
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
