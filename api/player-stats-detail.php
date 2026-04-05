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

    $pseudo = trim($_GET['pseudo'] ?? '');
    $type   = trim($_GET['type']   ?? '');  // buyins | gains | victoires | podiums | recaves | meilleur_gain

    if (!$pseudo || !$type) {
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }

    // Récupérer l'id-membre
    $s = $pdo->prepare("SELECT `id-membre` FROM membres WHERE pseudo = ?");
    $s->execute([$pseudo]);
    $m = $s->fetch();
    if (!$m) {
        echo json_encode(['success' => false, 'error' => 'Membre introuvable']);
        exit;
    }
    $memberId = (int)$m['id-membre'];

    // Base : toutes les participations valides
    $baseWhere = "
        WHERE p.`id-membre` = :mid
          AND COALESCE(p.option, 'None') NOT IN ('None', 'Desinscrit')
    ";

    switch ($type) {

        case 'buyins':
            $sql = "
                SELECT
                    DATE_FORMAT(a.date_depart, '%d/%m/%Y') AS date_partie,
                    a.`titre-activite`                     AS titre,
                    COALESCE(a.buyin, 0) + COALESCE(a.rake, 0)
                        + (COALESCE(p.recave, 0) * COALESCE(a.buyin, 0)) AS total_buyin,
                    COALESCE(a.buyin, 0)                   AS buyin,
                    COALESCE(a.rake, 0)                    AS rake,
                    COALESCE(p.recave, 0)                  AS recaves,
                    COALESCE(p.classement, 0)              AS classement,
                    COALESCE(p.gain, 0)                    AS gain,
                    (
                        SELECT COUNT(*)
                        FROM participation p2
                        WHERE p2.`id-activite` = a.`id-activite`
                          AND COALESCE(p2.option, 'None') NOT IN ('None', 'Desinscrit')
                    )                                      AS nb_joueurs
                FROM participation p
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                $baseWhere
                ORDER BY a.date_depart DESC
            ";
            break;

        case 'gains':
            $sql = "
                SELECT
                    DATE_FORMAT(a.date_depart, '%d/%m/%Y') AS date_partie,
                    a.`titre-activite`                     AS titre,
                    COALESCE(p.gain, 0)                    AS gain,
                    COALESCE(p.classement, 0)              AS classement
                FROM participation p
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                $baseWhere
                  AND COALESCE(p.gain, 0) > 0
                ORDER BY a.date_depart DESC
            ";
            break;

        case 'meilleur_gain':
            $sql = "
                SELECT
                    DATE_FORMAT(a.date_depart, '%d/%m/%Y') AS date_partie,
                    a.`titre-activite`                     AS titre,
                    COALESCE(p.gain, 0)                    AS gain,
                    COALESCE(p.classement, 0)              AS classement
                FROM participation p
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                $baseWhere
                  AND COALESCE(p.gain, 0) > 0
                ORDER BY a.date_depart DESC
                LIMIT 20
            ";
            break;

        case 'victoires':
            $sql = "
                SELECT
                    DATE_FORMAT(a.date_depart, '%d/%m/%Y') AS date_partie,
                    a.`titre-activite`                     AS titre,
                    COALESCE(p.gain, 0)                    AS gain,
                    COALESCE(p.classement, 0)              AS classement
                FROM participation p
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                $baseWhere
                  AND COALESCE(p.classement, 0) = 1
                ORDER BY a.date_depart DESC
            ";
            break;

        case 'podiums':
            $sql = "
                SELECT
                    DATE_FORMAT(a.date_depart, '%d/%m/%Y') AS date_partie,
                    a.`titre-activite`                     AS titre,
                    COALESCE(p.gain, 0)                    AS gain,
                    COALESCE(p.classement, 0)              AS classement
                FROM participation p
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                $baseWhere
                  AND COALESCE(p.classement, 0) BETWEEN 1 AND 3
                ORDER BY a.date_depart DESC
            ";
            break;

        case 'recaves':
            $sql = "
                SELECT
                    DATE_FORMAT(a.date_depart, '%d/%m/%Y') AS date_partie,
                    a.`titre-activite`                     AS titre,
                    COALESCE(p.recave, 0)                  AS recaves,
                    COALESCE(a.buyin, 0)                   AS buyin,
                    COALESCE(p.gain, 0)                    AS gain,
                    COALESCE(p.classement, 0)              AS classement
                FROM participation p
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                $baseWhere
                  AND COALESCE(p.recave, 0) > 0
                ORDER BY a.date_depart DESC
            ";
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Type inconnu']);
            exit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':mid', $memberId, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Normaliser les types numériques
    $items = array_map(function($r) {
        foreach ($r as $k => $v) {
            if (is_numeric($v)) {
                $r[$k] = strpos($v, '.') !== false ? (float)$v : (int)$v;
            }
        }
        return $r;
    }, $rows);

    echo json_encode([
        'success' => true,
        'pseudo'  => $pseudo,
        'type'    => $type,
        'count'   => count($items),
        'items'   => $items,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données']);
}
