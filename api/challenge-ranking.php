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

    // ── Authentification Bearer ──────────────────────────────────
    $token = null;
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!$authHeader && function_exists('apache_request_headers')) {
        $h = apache_request_headers();
        $authHeader = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $token = trim($m[1]);
    }
    if (!$token && isset($_GET['token'])) {
        $token = trim($_GET['token']);
    }
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token manquant']);
        exit;
    }
    $stmtT = $pdo->prepare("SELECT membre_id FROM app_auth_tokens WHERE token = ? AND (expires_at IS NULL OR expires_at > NOW())");
    $stmtT->execute([$token]);
    if (!$stmtT->fetch()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token invalide']);
        exit;
    }

    // ── Déterminer l'id_challenge ────────────────────────────────
    // Priorité : challenge_id explicite → challenge du mois courant → activity_id
    $challengeId = 0;
    $challengeTitle = '';
    $today = date('Y-m-d');

    if (isset($_GET['challenge_id']) && (int)$_GET['challenge_id'] > 0) {
        // Paramètre explicite : on l'utilise tel quel
        $challengeId = (int)$_GET['challenge_id'];
    } else {
        // Challenge du mois courant (prioritaire — même logique que la page web)
        $s = $pdo->query("SELECT id_challenge, titre_challenge FROM challenge
                          WHERE '$today' BETWEEN chal_deb AND chal_fin
                          ORDER BY chal_deb DESC LIMIT 1");
        $r = $s ? $s->fetch() : null;
        if ($r) {
            $challengeId    = (int)$r['id_challenge'];
            $challengeTitle = $r['titre_challenge'];
        }

        // Fallback : challenge lié à l'activité passée en paramètre
        if ($challengeId === 0 && isset($_GET['activity_id']) && (int)$_GET['activity_id'] > 0) {
            $actId = (int)$_GET['activity_id'];
            $s = $pdo->prepare("SELECT id_challenge FROM activite WHERE `id-activite` = ?");
            $s->execute([$actId]);
            $r = $s->fetch();
            $challengeId = $r ? (int)$r['id_challenge'] : 0;
        }
    }

    if ($challengeId === 0) {
        echo json_encode(['success' => false, 'error' => 'Aucun challenge actif trouvé']);
        exit;
    }

    // Récupérer le titre si pas encore renseigné
    if ($challengeTitle === '') {
        $s = $pdo->prepare("SELECT titre_challenge FROM challenge WHERE id_challenge = ?");
        $s->execute([$challengeId]);
        $r = $s->fetch();
        $challengeTitle = $r ? $r['titre_challenge'] : "Challenge #$challengeId";
    }

    // ── Classement (même logique que liste-membres-challenge-itm.php) ──
    $stmt = $pdo->prepare("
        SELECT
            m.`id-membre`                           AS id_membre,
            m.pseudo,
            COUNT(p.`id-participation`)             AS nb_participations,
            COALESCE(SUM(p.tf),   0)                AS tf,
            COALESCE(SUM(p.win),  0)                AS nb_victoires,
            COALESCE(SUM(p.gain) / 10, 0)           AS cagnotte,
            COALESCE(SUM(p.points), 0)              AS points
        FROM membres m
        LEFT JOIN participation p  ON p.`id-membre`    = m.`id-membre`
        LEFT JOIN activite a       ON p.`id-activite`  = a.`id-activite`
        LEFT JOIN challenge c      ON a.`id_challenge` = c.id_challenge
        LEFT JOIN blackliste b     ON m.`id-membre`    = b.id_membre
        WHERE c.id_challenge = ?
          AND a.date_depart  < ?
          AND b.id_membre   IS NULL
          AND p.`option`    NOT IN ('None', 'Desinscrit')
        GROUP BY m.`id-membre`, m.pseudo
        HAVING points > 0
        ORDER BY points DESC, nb_victoires DESC, tf DESC, nb_participations DESC
        LIMIT 500
    ");
    $stmt->execute([$challengeId, $today]);
    $rows = $stmt->fetchAll();

    $ranking = [];
    $rank = 1;
    foreach ($rows as $row) {
        $ranking[] = [
            'rank'              => $rank++,
            'pseudo'            => $row['pseudo'],
            'id_membre'         => (int)$row['id_membre'],
            'nb_participations' => (int)$row['nb_participations'],
            'tf'                => (int)$row['tf'],
            'nb_victoires'      => (int)$row['nb_victoires'],
            'cagnotte'          => round((float)$row['cagnotte'], 2),
            'points'            => (int)$row['points'],
        ];
    }

    echo json_encode([
        'success'         => true,
        'challenge_id'    => $challengeId,
        'challenge_title' => $challengeTitle,
        'count'           => count($ranking),
        'ranking'         => $ranking,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données']);
}
