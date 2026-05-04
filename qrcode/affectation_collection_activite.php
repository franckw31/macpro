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

function columnExists(mysqli $db, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '::' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $allowedTables = ['collections', 'collections-individu'];
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
    $sql = 'SELECT p.`id-membre`, p.option, p.`nom-membre`, m.pseudo
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
            'option' => $row['option'] ?? ''
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
    $sql = 'SELECT DISTINCT p.`id-membre`, COALESCE(m.pseudo, p.`nom-membre`) AS pseudo
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
                'pseudo' => $row['pseudo'] ?: ('Membre #' . $memberId)
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

        // Vérifier collection disponible (non utilisée)
        $hasCollectionValeur = columnExists($conx, 'collections', 'valeur');
        $selectCollectionValeur = $hasCollectionValeur ? 'c.valeur' : '1 AS valeur';
        $stmtCol = $conx->prepare('SELECT c.id_collection, c.nom, ' . $selectCollectionValeur . ' FROM collections c LEFT JOIN `collections-individu` ci ON ci.id_col = c.id_collection AND ci.`id-indiv` IS NOT NULL AND ci.`id-indiv` > 0 WHERE c.id_collection = ? AND ci.id IS NULL LIMIT 1');
        if (!$stmtCol) {
            throw new RuntimeException('Erreur SQL collection: ' . $conx->error);
        }
        $stmtCol->bind_param('i', $collectionId);
        $stmtCol->execute();
        $colRes = $stmtCol->get_result();
        $collection = $colRes ? $colRes->fetch_assoc() : null;
        $stmtCol->close();

        if (!$collection) {
            throw new RuntimeException('La collection choisie est déjà utilisée ou introuvable.');
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

            $selectCollectionValeur = $hasCollectionValeur ? 'c.valeur' : '1 AS valeur';
            $stmtCol = $conx->prepare('SELECT c.id_collection, c.nom, ' . $selectCollectionValeur . ' FROM collections c LEFT JOIN `collections-individu` ci ON ci.id_col = c.id_collection AND ci.`id-indiv` IS NOT NULL AND ci.`id-indiv` > 0 WHERE ci.id IS NULL ORDER BY c.id_collection ASC LIMIT 1');
            if (!$stmtCol) {
                throw new RuntimeException('Erreur SQL collection disponible: ' . $conx->error);
            }
            $stmtCol->execute();
            $collection = $stmtCol->get_result()->fetch_assoc();
            $stmtCol->close();

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

if (isset($_GET['activity_id']) && (int) $_GET['activity_id'] > 0) {
    $selectedActivityId = (int) $_GET['activity_id'];
}

$activities = getActivities($conx);

if ($selectedActivityId > 0) {
    $selectedActivity = getActivityById($conx, $selectedActivityId);
    if ($selectedActivity) {
        $participants = getParticipantsByActivity($conx, $selectedActivityId);
        $availableCollections = getAvailableCollections($conx);
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
            $availableCollections = $selectedActivity ? getAvailableCollections($conx) : [];
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affectation collection à participant</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6fb; margin:0; padding:20px; color:#1f2937; }
        .container { max-width:900px; margin:0 auto; background:#fff; border-radius:12px; padding:22px; box-shadow:0 8px 30px rgba(0,0,0,.08); }
        h1 { margin-top:0; font-size:24px; }
        .card { border:1px solid #e5e7eb; border-radius:10px; padding:16px; margin-bottom:16px; background:#fafbff; }
        label { display:block; margin-bottom:8px; font-weight:600; }
        select, button { width:100%; padding:12px; border-radius:8px; border:1px solid #d1d5db; font-size:14px; }
        button { cursor:pointer; border:none; background:#2563eb; color:#fff; font-weight:700; margin-top:10px; }
        button:hover { background:#1d4ed8; }
        ul { margin:10px 0 0 18px; }
        .muted { color:#6b7280; font-size:13px; }
        .flash { border-radius:8px; padding:12px; margin-bottom:14px; font-weight:600; }
        .flash.success { background:#dcfce7; color:#166534; border:1px solid #86efac; }
        .flash.error { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        @media (max-width: 700px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nouvelle affectation : activité → participant → collection</h1>

        <?php if ($flash): ?>
            <div class="flash <?php echo h($flash['type'] ?? 'error'); ?>">
                <?php echo h($flash['text'] ?? ''); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="post" action="">
                <label for="activity_id">1) Choisir une activité (la plus récente en premier)</label>
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
                <button type="submit">Afficher participants + collections disponibles</button>
            </form>
        </div>

        <?php if ($selectedActivity): ?>
            <div class="card">
                <strong>Activité sélectionnée :</strong>
                #<?php echo (int) $selectedActivity['id-activite']; ?> — <?php echo h($selectedActivity['titre-activite']); ?>
                <div class="muted"><?php echo h($selectedActivity['date_depart']); ?> <?php echo h($selectedActivity['heure_depart']); ?> · <?php echo h($selectedActivity['ville']); ?></div>

                <h3>2) Liste des participants</h3>
                <?php if (empty($participants)): ?>
                    <p class="muted">Aucun participant valide pour cette activité.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($participants as $p): ?>
                            <li>
                                #<?php echo (int) $p['id-membre']; ?> — <?php echo h($p['pseudo']); ?>
                                <?php if (!empty($p['option'])): ?>
                                    <span class="muted">(<?php echo h($p['option']); ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>

                        <p class="muted" style="margin-top:10px;">
                            Collections non affectées à un joueur (toutes activités confondues) :
                            <strong><?php echo (int) count($availableCollections); ?></strong>
                        </p>
                    </ul>

                    <div style="margin-top:14px; border-top:1px solid #dbeafe; padding-top:12px;">
                        <h3 style="margin:0 0 8px 0; font-size:16px;">Confirmation joueur par joueur</h3>
                        <?php if (empty($participantsWithoutCollection)): ?>
                            <p class="muted">Tous les participants ont déjà une collection.</p>
                        <?php else: ?>
                            <form method="post" action="" onsubmit="return confirm('Confirmer l\'attribution pour les joueurs cochés ?');">
                                <input type="hidden" name="action" value="assign_selected_missing">
                                <input type="hidden" name="activity_id" value="<?php echo (int) $selectedActivity['id-activite']; ?>">

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                    <?php foreach ($participantsWithoutCollection as $pm): ?>
                                        <label style="display:flex; align-items:center; gap:8px; margin:0; font-weight:normal; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:8px 10px;">
                                            <input type="checkbox" name="member_ids[]" value="<?php echo (int) $pm['id-membre']; ?>" checked>
                                            <span>#<?php echo (int) $pm['id-membre']; ?> — <?php echo h($pm['pseudo']); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <button type="submit" style="background:#0ea5e9; margin-top:10px;">
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
