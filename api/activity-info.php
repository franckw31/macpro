<?php
header('Content-Type: application/json');
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

    $actId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

    if ($actId === 0) {
        // Prochaine activité à venir ou la plus récente
        $stmt = $pdo->query("
            SELECT `id-activite` FROM activite
            WHERE date_depart >= DATE_SUB(NOW(), INTERVAL 2 DAY)
            ORDER BY date_depart ASC LIMIT 1
        ");
        $row = $stmt->fetch();
        if (!$row) {
            $stmt = $pdo->query("SELECT `id-activite` FROM activite ORDER BY date_depart DESC LIMIT 1");
            $row = $stmt->fetch();
        }
        $actId = $row ? (int)$row['id-activite'] : 0;
    }

    if ($actId === 0) {
        echo json_encode(['success' => false, 'error' => 'Aucune activité trouvée']);
        exit;
    }

    // Infos activité + organisateur + rake label
    $stmt = $pdo->prepare("
        SELECT
            a.`id-activite`         AS id,
            a.`titre-activite`      AS title,
            a.`date_depart`         AS date,
            a.`ville`               AS lieu,
            a.`rue`                 AS rue,
            a.`buyin`,
            a.`rake`,
            a.`bounty`,
            a.`recave`,
            a.`recave_montant`,
            a.`recave_jetons`,
            a.`jetons`,
            a.`places`              AS max_joueurs,
            a.`nb-tables`           AS nb_tables,
            a.`id_structure`        AS id_structure,
            a.`id_rake`             AS id_rake,
            m.pseudo                AS organisateur,
            r.nom                   AS rake_label,
            sm.nom                  AS structure_nom,
            sm.Detail               AS structure_detail
        FROM activite a
        LEFT JOIN membres m ON m.`id-membre` = a.`id-membre`
        LEFT JOIN rake r ON r.id_rake = a.id_rake
        LEFT JOIN structure_modele sm ON sm.id_modele_structure = a.id_structure
        WHERE a.`id-activite` = ?
    ");
    $stmt->execute([$actId]);
    $act = $stmt->fetch();

    if (!$act) {
        echo json_encode(['success' => false, 'error' => 'Activité introuvable']);
        exit;
    }

    // Structure de blindes
    $stmtS = $pdo->prepare("
        SELECT s.ordre, b.`val-sb` AS sb, b.`val-bb` AS bb, b.`ante`, b.`pause`, s.duree
        FROM structure s
        JOIN blindes b ON b.`id-blinde` = s.`id-blinde`
        WHERE s.`id-structure` = ?
        ORDER BY s.ordre ASC
    ");
    $stmtS->execute([$act['id_structure']]);
    $levels = $stmtS->fetchAll();

    $structure = [];
    foreach ($levels as $l) {
        $structure[] = [
            'ordre' => (int)$l['ordre'],
            'sb'    => (int)$l['val-sb'],
            'bb'    => (int)$l['val-bb'],
            'ante'  => $l['ante'],
            'duree' => (int)$l['duree'],
            'pause' => (int)$l['pause'],
        ];
    }

    // Nombre d'inscrits
    $stmtC = $pdo->prepare("
        SELECT COUNT(*) AS cnt FROM participation
        WHERE `id-activite` = ?
          AND COALESCE(`option`, 'None') NOT IN ('None', 'Desinscrit')
    ");
    $stmtC->execute([$actId]);
    $cnt = (int)$stmtC->fetch()['cnt'];

    echo json_encode([
        'success'        => true,
        'id'             => (int)$act['id'],
        'title'          => $act['title'],
        'date'           => $act['date'],
        'lieu'           => $act['lieu'],
        'rue'            => $act['rue'],
        'organisateur'   => $act['organisateur'],
        'buyin'          => (int)$act['buyin'],
        'rake'           => (int)$act['rake'],
        'rake_label'     => $act['rake_label'] ?? '',
        'bounty'         => (int)$act['bounty'],
        'recave'         => (int)$act['recave'],
        'recave_montant' => (int)$act['recave_montant'],
        'recave_jetons'  => (int)$act['recave_jetons'],
        'jetons'         => (int)$act['jetons'],
        'max_joueurs'    => (int)$act['max_joueurs'],
        'nb_tables'      => (int)$act['nb_tables'],
        'inscrits'       => $cnt,
        'structure_nom'  => $act['structure_nom'] ?? '',
        'structure_detail' => $act['structure_detail'] ?? '',
        'structure'      => $structure,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}
?>
