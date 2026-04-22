<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $pseudo = isset($_GET['pseudo']) ? trim($_GET['pseudo']) : '';
    if ($pseudo === '') {
        echo json_encode(['success' => false, 'error' => 'pseudo requis']);
        exit;
    }

    // Récupération de l'id-membre via le pseudo
    $stmtM = $pdo->prepare("SELECT `id-membre`, photo FROM membres WHERE pseudo = ? LIMIT 1");
    $stmtM->execute([$pseudo]);
    $member = $stmtM->fetch();
    if (!$member) {
        echo json_encode(['success' => false, 'error' => 'Membre introuvable']);
        exit;
    }
    $memberId = (int)$member['id-membre'];
    $photo    = !empty($member['photo']) ? $member['photo'] : 'avatar.png';

    // Statistiques globales (identiques à la carte "Statistiques" de quickview.php)
    $stmtStats = $pdo->prepare("
        SELECT
            COUNT(*)                                                                                           AS nb_parties,
            COALESCE(SUM(p.gain), 0)                                                                          AS total_gains,
            SUM(CASE WHEN p.gain > 0 THEN 1 ELSE 0 END)                                                      AS nb_gains,
            SUM(
                COALESCE(a.buyin, 0) + COALESCE(a.rake, 0)
                + (COALESCE(p.recave, 0) * COALESCE(a.recave_montant, 0))
                + (COALESCE(p.addon,  0) * COALESCE(a.recave_montant, 0))
            )                                                                                                  AS total_buyins,
            SUM(CASE WHEN p.classement = 1 THEN 1 ELSE 0 END)                                                AS nb_victoires,
            SUM(CASE WHEN p.classement > 0 AND p.classement <= 3 THEN 1 ELSE 0 END)                         AS nb_podiums,
            COALESCE(SUM(p.recave), 0)                                                                        AS total_recaves,
            MAX(p.gain)                                                                                        AS meilleur_gain
        FROM participation p
        JOIN activite a ON p.`id-activite` = a.`id-activite`
        WHERE p.`id-membre` = ?
          AND p.`option` NOT IN ('Desinscrit', 'None')
    ");
    $stmtStats->execute([$memberId]);
    $stats = $stmtStats->fetch();

    $nbParties    = (int)$stats['nb_parties'];
    $totalGains   = (float)$stats['total_gains'];
    $nbGains      = (int)$stats['nb_gains'];
    $totalBuyins  = (float)$stats['total_buyins'];
    $nbVictoires  = (int)$stats['nb_victoires'];
    $nbPodiums    = (int)$stats['nb_podiums'];
    $totalRecaves = (int)$stats['total_recaves'];
    $meilleurGain = (float)$stats['meilleur_gain'];
    // Compute rake sum (exclude activities organized by the member) to match panel/profile.php logic
    $rake_sum = 0;
    // determine possible organizer columns present in `activite`
    $existing_cols = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM activite");
    if ($colStmt) {
        $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) { $existing_cols[] = $c['Field']; }
    }
    $candidates = ['id-membre','id_membre','id_membres','id_membre_organisateur','organisateur'];
    $used = array_values(array_intersect($candidates, $existing_cols));

    $exclude_clause = '';
    $params = [$memberId];
    if (!empty($used)) {
        $parts = [];
        foreach ($used as $col) {
            $parts[] = "a.`" . $col . "` = ?";
            $params[] = $memberId;
        }
        $exclude_clause = ' AND NOT (' . implode(' OR ', $parts) . ')';
    }

    $rakeSql = "SELECT COALESCE(SUM(COALESCE(a.rake,0)),0) AS rake_sum FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = ? AND p.`option` NOT IN ('Desinscrit','None')" . $exclude_clause;
    $rakeStmt = $pdo->prepare($rakeSql);
    $rakeStmt->execute($params);
    $rRow = $rakeStmt->fetch();
    if ($rRow) { $rake_sum = (int)$rRow['rake_sum']; }

    // Match panel logic: net = total_gains - (total_buyins - rake_sum)
    $netResult    = $totalGains - ($totalBuyins - $rake_sum);
    $tauxVictoire = $nbParties > 0 ? round($nbVictoires / $nbParties * 100, 1) : 0;
    $tauxPodium   = $nbParties > 0 ? round($nbPodiums  / $nbParties * 100, 1) : 0;

    // Count tickets (collections-individu) for this member
    $stmtT = $pdo->prepare("SELECT COUNT(*) AS total FROM `collections-individu` WHERE `id-indiv` = ?");
    $stmtT->execute([$memberId]);
    $trow = $stmtT->fetch();
    $ticketsCount = $trow ? (int)$trow['total'] : 0;

    echo json_encode([
        'success'       => true,
        'pseudo'        => $pseudo,
        'photo_url'     => 'https://viendez.com/images/faces/' . $photo,
        'nb_parties'    => $nbParties,
        'total_gains'   => $totalGains,
        'nb_gains'      => $nbGains,
        'total_buyins'  => $totalBuyins,
        'net_result'    => $netResult,
        'nb_victoires'  => $nbVictoires,
        'nb_podiums'    => $nbPodiums,
        'total_recaves' => $totalRecaves,
        'meilleur_gain' => $meilleurGain,
        'taux_victoire' => $tauxVictoire,
        'taux_podium'   => $tauxPodium,
        'member_id'     => $memberId,
        'tickets'       => $ticketsCount,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données']);
}
