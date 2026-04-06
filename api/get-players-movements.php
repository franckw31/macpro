<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // Connexion à la base de données (même que auth.php)
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $id = intval($_GET['uid'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID d\'activité invalide'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Récupération des infos de base
    $stmt = $pdo->prepare("SELECT `titre-activite`, `buyin`, `recave_montant`, `date_depart`, `type` FROM `activite` WHERE `id-activite` = ?");
    $stmt->execute([$id]);
    $act_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
    $stmt = $pdo->prepare("SELECT p.* FROM `participation` p WHERE p.`id-activite` = ? ORDER BY (p.`classement` = 0 OR p.`classement` IS NULL) DESC, p.`classement` ASC, p.`nom-membre` ASC");
    $stmt->execute([$id]);
    $players_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $rankingCounter = 1;

    foreach ($players_raw as $row) {
        $totalPlayers++;
        $totalRecaves += intval($row['recave']);
        
        // Récupération ID membre et phonétique
        $membre_id = 0;
        $membre_phonetique = '';
        $stmt_mem = $pdo->prepare("SELECT `id-membre`, `phonetique` FROM `membres` WHERE `pseudo` = ?");
        $stmt_mem->execute([$row['nom-membre']]);
        $member_data = $stmt_mem->fetch(PDO::FETCH_ASSOC);
        if ($member_data) {
            $membre_id = intval($member_data['id-membre']);
            $membre_phonetique = $member_data['phonetique'] ?? '';
        }
        
        // Compter les éliminations (bounty)
        $stmt_elim = $pdo->prepare("SELECT COUNT(*) as cnt FROM `eliminations` e JOIN `participation` p ON e.`id_participation` = p.`id-participation` WHERE p.`id-activite` = ? AND e.`nom_membre` = ?");
        $stmt_elim->execute([$id, $row['nom-membre']]);
        $elim_count_row = $stmt_elim->fetch(PDO::FETCH_ASSOC);
        $elimCount = intval($elim_count_row['cnt'] ?? 0);
        
        // Vérifier les éliminations
        $isEliminated = false;
        $eliminators = [];
        $stmt_elim_detail = $pdo->prepare("SELECT * FROM `eliminations` WHERE `id_participation` = ? ORDER BY created_at ASC");
        $stmt_elim_detail->execute([intval($row['id-participation'])]);
        $eliminations = $stmt_elim_detail->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($eliminations as $er) {
            $eliminators[] = $er['nom_membre'];
            if (intval($er['is_definitive'] ?? 0) === 1) {
                $isEliminated = true;
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
                $stmt_jetons = $pdo->prepare("SELECT ? FROM `membres` WHERE `id-membre` = ?");
                // Note: can't use ? for column names, need to be careful
                $sql = "SELECT `" . ($jetons_column == 'jetons_2' ? 'jetons_2' : 'jetons_1') . "` FROM `membres` WHERE `id-membre` = ?";
                $stmt_jetons = $pdo->prepare($sql);
                $stmt_jetons->execute([$membre_id]);
                $jetons_row = $stmt_jetons->fetch(PDO::FETCH_ASSOC);
                if ($jetons_row) {
                    $jetons_value = intval($jetons_row[$jetons_column] ?? 0);
                    if ($jetons_value > 0) {
                        $tickets = (string)$jetons_value;
                    }
                }
            }
        } else {
            $ticketCodes = [];
            if ($membre_id > 0 && !empty($activity_date_simple)) {
                $stmt_tickets = $pdo->prepare("SELECT c.`nom` AS qrcode FROM `collections-individu` ci JOIN `collections` c ON ci.`id_col` = c.`id_collection` WHERE ci.`id-indiv` = ? AND DATE(ci.`date`) = ? AND (ci.`aff_rake` = 0 OR ci.`aff_rake` IS NULL)");
                $stmt_tickets->execute([$membre_id, $activity_date_simple]);
                $ticket_rows = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);
                foreach ($ticket_rows as $trow) {
                    if (!empty($trow['qrcode'])) {
                        $ticketCodes[] = $trow['qrcode'];
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

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
