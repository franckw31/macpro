<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Déterminer le bon chemin vers config.php
$config_path = '../config.php';
if (!file_exists($config_path)) {
    $config_path = '../panel/include/config.php';
}
if (!file_exists($config_path)) {
    $config_path = '../../config.php';
}

if (!file_exists($config_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

include($config_path);

// Force UTF-8 encoding
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

$id = intval($_GET['uid'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID d\'activité invalide'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Récupération des infos de base
$act_query = @mysqli_query($con, "SELECT `titre-activite`, `buyin`, `recave_montant`, `date_depart`, `type` FROM `activite` WHERE `id-activite` = '$id'");
if (!$act_query) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur requête activité'], JSON_UNESCAPED_UNICODE);
    exit;
}

$act_row = mysqli_fetch_array($act_query);

if (!$act_row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Activité non trouvée'], JSON_UNESCAPED_UNICODE);
    exit;
}

$activity_title = $act_row['titre-activite'];
$buyin = intval($act_row['buyin']);
$recave_montant = intval($act_row['recave_montant']);
$activity_type = intval($act_row['type']);
$activity_date_simple = '';
if (!empty($act_row['date_depart'])) {
    $activity_date_simple = date('Y-m-d', strtotime($act_row['date_depart']));
}

$players = [];
$totalPlayers = 0;
$activePlayers = 0;
$totalRecaves = 0;
$pricepool = 0;

// Requête pour récupérer les joueurs
$req = @mysqli_query($con, "SELECT p.* FROM `participation` p WHERE p.`id-activite` = '$id' ORDER BY (p.`classement` = 0 OR p.`classement` IS NULL) DESC, p.`classement` ASC, p.`nom-membre` ASC");
if (!$req) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur requête joueurs'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rankingCounter = 1;

while ($row = mysqli_fetch_array($req)) {
    $totalPlayers++;
    $totalRecaves += intval($row['recave']);
    
    // Récupération ID membre et phonétique
    $membre_id = 0;
    $membre_phonetique = '';
    $pseudo_clean = mysqli_real_escape_string($con, $row['nom-membre']);
    $mq = @mysqli_query($con, "SELECT `id-membre`, `phonetique` FROM `membres` WHERE `pseudo` = '$pseudo_clean' LIMIT 1");
    if ($mq && mysqli_num_rows($mq) > 0) {
        $mr = mysqli_fetch_array($mq);
        $membre_id = intval($mr['id-membre']);
        if (isset($mr['phonetique']) && $mr['phonetique'] !== '') {
            $membre_phonetique = $mr['phonetique'];
        }
    }
    
    // Compter les éliminations (bounty)
    $elimCount = 0;
    $countElimQuery = @mysqli_query($con, "SELECT COUNT(*) as cnt FROM `eliminations` e JOIN `participation` p ON e.`id_participation` = p.`id-participation` WHERE p.`id-activite` = '$id' AND e.`nom_membre` = '" . mysqli_real_escape_string($con, $row['nom-membre']) . "'");
    if ($countElimQuery) {
        $countElimRow = mysqli_fetch_array($countElimQuery);
        $elimCount = intval($countElimRow['cnt']);
    }
    
    // Vérifier les éliminations
    $isEliminated = false;
    $eliminators = [];
    $elim_q = @mysqli_query($con, "SELECT * FROM `eliminations` WHERE `id_participation` = '" . intval($row['id-participation']) . "' ORDER BY created_at ASC");
    
    if ($elim_q) {
        while ($er = mysqli_fetch_array($elim_q)) {
            $eliminators[] = $er['nom_membre'];
            if (intval($er['is_definitive']) === 1) {
                $isEliminated = true;
            }
        }
    }
    
    if (!$isEliminated) {
        $activePlayers++;
    }
    
    // Récupération des tickets ou jetons
    $tickets = '';
    if ($activity_type == 2 || $activity_type == 3) {
        $jetons_column = ($activity_type == 3) ? 'jetons_2' : 'jetons_1';
        if ($membre_id > 0) {
            $jetons_sql = @mysqli_query($con, "SELECT `" . $jetons_column . "` FROM `membres` WHERE `id-membre` = " . intval($membre_id));
            if ($jetons_sql && mysqli_num_rows($jetons_sql) > 0) {
                $jetons_row = mysqli_fetch_array($jetons_sql);
                $jetons_value = intval($jetons_row[$jetons_column]);
                if ($jetons_value > 0) {
                    $tickets = (string)$jetons_value;
                }
            }
        }
    } else {
        $ticketCodes = [];
        if ($membre_id > 0 && !empty($activity_date_simple)) {
            $dateEscaped = mysqli_real_escape_string($con, $activity_date_simple);
            $ticket_sql = @mysqli_query($con, "SELECT c.`nom` AS qrcode FROM `collections-individu` ci JOIN `collections` c ON ci.`id_col` = c.`id_collection` WHERE ci.`id-indiv` = '" . intval($membre_id) . "' AND DATE(ci.`date`) = '" . $dateEscaped . "' AND (ci.`aff_rake` = 0 OR ci.`aff_rake` IS NULL)");
            if ($ticket_sql) {
                while ($trow = mysqli_fetch_array($ticket_sql)) {
                    if (!empty($trow['qrcode'])) {
                        $ticketCodes[] = $trow['qrcode'];
                    }
                }
            }
        }
        if (!empty($ticketCodes)) {
            $tickets = implode(', ', $ticketCodes);
        }
    }
    
    // Déterminer le rang
    $rankDisplay = 0;
    if (!$isEliminated) {
        $rankDisplay = $rankingCounter;
        $rankingCounter++;
    } else {
        if ($row['classement'] > 0) {
            $rankDisplay = $row['classement'];
        }
    }
    
    $players[] = [
        'rank' => (int)$rankDisplay,
        'name' => (string)$row['nom-membre'],
        'member_id' => (int)$membre_id,
        'bounty_count' => (int)$elimCount,
        'recaves' => (int)$row['recave'],
        'eliminated_by' => array_map('strval', $eliminators),
        'tickets' => (string)$tickets,
        'is_eliminated' => (bool)$isEliminated,
        'phonetic_name' => (string)$membre_phonetique,
        'classement' => (int)$row['classement']
    ];
}

$pricepool = ($totalPlayers * $buyin) + ($totalRecaves * $recave_montant);

$response = [
    'success' => true,
    'players' => $players,
    'stats' => [
        'total_players' => (int)$totalPlayers,
        'active_players' => (int)$activePlayers,
        'total_recaves' => (int)$totalRecaves,
        'pricepool' => (int)$pricepool
    ]
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
?>
