<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée'], JSON_UNESCAPED_UNICODE);
    exit;
}

function qvm_get(array $row, array $keys, $default = null) {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) {
            return $row[$key];
        }
    }
    return $default;
}

try {
    require_once __DIR__ . '/../include/env.php';
    $db = cardevent_pdo_config();
    $pdo = new PDO($db['dsn'], $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $token = '';
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? ($headers['Authorization'] ?? ($headers['authorization'] ?? ''));
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $token = trim($m[1]);
    } elseif (!empty($_GET['token'])) {
        $token = trim($_GET['token']);
    }
    if ($token === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token manquant'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT t.membre_id, m.pseudo
        FROM app_auth_tokens t
        JOIN membres m ON m.`id-membre` = t.membre_id
        WHERE t.token = ? AND t.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userId = (int)$user['membre_id'];
    $pseudo = $user['pseudo'];

    $actId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    if ($actId === 0) {
        $row = $pdo->query("SELECT `id-activite` FROM activite WHERE date_depart >= NOW() ORDER BY date_depart ASC LIMIT 1")->fetch();
        if (!$row) {
            $row = $pdo->query("SELECT `id-activite` FROM activite ORDER BY date_depart DESC LIMIT 1")->fetch();
        }
        $actId = $row ? (int)$row['id-activite'] : 0;
    }
    if ($actId === 0) {
        echo json_encode(['success' => false, 'error' => 'Aucune activité trouvée'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $actStmt = $pdo->prepare("SELECT * FROM activite WHERE `id-activite` = ? LIMIT 1");
    $actStmt->execute([$actId]);
    $act = $actStmt->fetch();
    if (!$act) {
        echo json_encode(['success' => false, 'error' => 'Activité introuvable'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cntStmt = $pdo->prepare("
        SELECT COUNT(*) FROM participation
        WHERE `id-activite` = ?
          AND COALESCE(`option`, 'None') NOT IN ('None', 'Desinscrit')
    ");
    $cntStmt->execute([$actId]);
    $participantsCount = (int)$cntStmt->fetchColumn();

    $location = qvm_get($act, ['ville', 'lieu', 'adresse', 'location', 'place']);
    $phone = qvm_get($act, ['telephone', 'tel', 'phone', 'tel_lieu', 'contact_tel', 'num_tel']);
    $tables = qvm_get($act, ['nb-tables', 'tables', 'nb_tables']);
    $maxParticipants = qvm_get($act, ['places', 'max_places', 'max_participants']);
    $startChips = qvm_get($act, ['jetons_depart', 'jetons', 'start_chips']);
    $structureId = qvm_get($act, ['id_structure', 'id-structure']);
    $organizerId = qvm_get($act, ['id-membre', 'id_membre']);
    $challengeId = qvm_get($act, ['id_challenge', 'id-challenge']);

    $organizer = null;
    if ($organizerId !== null) {
        $o = $pdo->prepare("SELECT pseudo FROM membres WHERE `id-membre` = ? LIMIT 1");
        $o->execute([(int)$organizerId]);
        $organizer = $o->fetchColumn() ?: null;
    }

    $structureDetail = null;
    if ($structureId !== null) {
        $s = $pdo->prepare("SELECT Detail FROM structure_modele WHERE id_modele_structure = ? LIMIT 1");
        $s->execute([(int)$structureId]);
        $structureDetail = $s->fetchColumn() ?: null;
    }

    $levels = [];
    $lvl = $pdo->prepare("SELECT `ordre`,`nom`,`sb`,`bb`,`ante`,`minutes` FROM `blindes-live` WHERE `id-activite` = ? ORDER BY `ordre` ASC");
    $lvl->execute([$actId]);
    foreach ($lvl->fetchAll() as $line) {
        $levels[] = [
            'ordre' => (int)$line['ordre'],
            'nom' => $line['nom'] ?? null,
            'sb' => (int)$line['sb'],
            'bb' => (int)$line['bb'],
            'ante' => (int)$line['ante'],
            'minutes' => (int)$line['minutes'],
        ];
    }

    $displayDate = $act['date_depart'];
    $dateOnly = '—';
    $timeOnly = '';
    if (!empty($act['date_depart'])) {
        $dt = new DateTime($act['date_depart'], new DateTimeZone('Europe/Paris'));
        $jours = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
        $mois = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
        $dateOnly = $jours[$dt->format('l')] . ' ' . $dt->format('j') . ' ' . $mois[$dt->format('F')];
        $timeOnly = $dt->format('H:i');
        $displayDate = $dateOnly . ' ' . $timeOnly;
    }

    $participation = null;
    $pStmt = $pdo->prepare("SELECT `option`, COALESCE(`anonyme`,0) AS anonyme, COALESCE(`latereg`,0) AS latereg FROM participation WHERE `id-membre` = ? AND `id-activite` = ? LIMIT 1");
    $pStmt->execute([$userId, $actId]);
    if ($row = $pStmt->fetch()) {
        $participation = [
            'status' => $row['option'],
            'anonyme' => (int)$row['anonyme'],
            'latereg' => (int)$row['latereg'],
        ];
    }

    $allActivities = [];
    $qa = $pdo->query("SELECT * FROM activite ORDER BY date_depart DESC LIMIT 500");
    $joursAll = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
    $moisAll = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
    foreach ($qa->fetchAll() as $ra) {
        if (empty($ra['date_depart'])) continue;
        $dt = new DateTime($ra['date_depart'], new DateTimeZone('Europe/Paris'));
        $title = qvm_get($ra, ['titre-activite', 'titre_activite', 'title', 'titre'], '');
        $allActivities[] = [
            'id' => (int)$ra['id-activite'],
            'date' => $ra['date_depart'],
            'label' => $joursAll[$dt->format('l')] . ' ' . $dt->format('j') . ' ' . $moisAll[$dt->format('F')] . ' – ' . $dt->format('H:i'),
            'ts' => (int)$dt->getTimestamp(),
            'titre' => $title,
            'buyin' => (int)(qvm_get($ra, ['buyin', 'buy_in', 'buy-in'], 0)),
            'rake' => (int)(qvm_get($ra, ['rake'], 0)),
            'bounty' => (int)(qvm_get($ra, ['bounty'], 0)),
            'recave' => (int)(qvm_get($ra, ['recave'], 0)),
        ];
    }

    $lastGamePayes = [];
    $lg = $pdo->query("
        SELECT a.`id-activite`, a.`titre-activite`, a.`date_depart`
        FROM activite a
        JOIN participation p ON p.`id-activite` = a.`id-activite`
        WHERE p.classement = 1
        ORDER BY a.date_depart DESC
        LIMIT 1
    ")->fetch();
    if ($lg) {
        $lgStmt = $pdo->prepare("
            SELECT p.classement, COALESCE(m.pseudo, p.`nom-membre`) AS pseudo, COALESCE(p.gain, 0) AS gain
            FROM participation p
            LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
            WHERE p.`id-activite` = ? AND p.gain > 0
            ORDER BY p.classement ASC
        ");
        $lgStmt->execute([(int)$lg['id-activite']]);
        $lastGamePayes = $lgStmt->fetchAll();
    }

    $avatarStmt = $pdo->prepare("
        SELECT photo
        FROM membres
        WHERE `id-membre` = ?
        LIMIT 1
    ");
    $avatarStmt->execute([$userId]);
    $avatarFile = trim((string)$avatarStmt->fetchColumn());

    $avatarUrl = $avatarFile !== ''
        ? 'https://viendez.com/images/faces/' . ltrim($avatarFile, '/')
        : 'https://viendez.com/panel/images/noprofil.jpg';

    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $userId,
            'pseudo' => $pseudo,
            'avatar_url' => $avatarUrl,
        ],
        'activity' => [
            'id' => (int)$actId,
            'title' => qvm_get($act, ['titre-activite', 'titre_activite'], ''),
            'date' => $act['date_depart'],
            'display_date' => $displayDate,
            'date_only' => $dateOnly,
            'time_only' => $timeOnly,
            'buyin' => (int)(qvm_get($act, ['buyin'], 0)),
            'rake' => (int)(qvm_get($act, ['rake'], 0)),
            'bounty' => (int)(qvm_get($act, ['bounty'], 0)),
            'recave' => (int)(qvm_get($act, ['recave'], 0)),
            'recave_montant' => (int)(qvm_get($act, ['recave_montant'], 0)),
            'recave_jetons' => (int)(qvm_get($act, ['recave_jetons'], 0)),
            'participants_count' => $participantsCount,
            'max_participants' => $maxParticipants !== null ? (int)$maxParticipants : null,
            'organizer' => $organizer,
            'organizer_id' => $organizerId !== null ? (int)$organizerId : null,
            'challenge_id' => $challengeId !== null ? (int)$challengeId : null,
            'location' => $location,
            'phone' => $phone,
            'tables' => $tables !== null ? (int)$tables : null,
            'start_chips' => $startChips !== null ? (int)$startChips : null,
            'structure_detail' => $structureDetail,
            'structure_id' => $structureId !== null ? (int)$structureId : null,
            'structure_levels' => $levels,
        ],
        'participation' => $participation,
        'all_activities' => $allActivities,
        'last_game_payes' => $lastGamePayes,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
}