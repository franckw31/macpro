<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // ── Authentification Bearer token ────────────────────────────
    $token = null;
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $headers['Authorization']
        ?? $headers['authorization']
        ?? '';
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

    $stmt = $pdo->prepare("
        SELECT at.membre_id, m.pseudo
        FROM app_auth_tokens at
        JOIN membres m ON m.`id-membre` = at.membre_id
        WHERE at.token = ?
          AND (at.expires_at IS NULL OR at.expires_at > NOW())
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        exit;
    }
    $userId = (int)$user['membre_id'];

    // ── Log de consultation ──────────────────────────────────────
    $actIdForLog = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    $logDetails = $actIdForLog ? "Activite #$actIdForLog" : 'Activite auto';
    $logIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, source, details, ip_address) VALUES (?, ?, 'vue_liste_participants', 'iOS App', ?, ?)")
        ->execute([$userId, $user['pseudo'], $logDetails, $logIp]);

    $actId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;

    if ($actId === 0) {
        // 1) Activité en cours ou récente (≤ 2 jours)
        $r = $pdo->query("
            SELECT `id-activite`, `titre-activite`, `date_depart`, buyin, rake
            FROM activite
            WHERE date_depart >= (NOW() - INTERVAL 2 DAY)
            ORDER BY date_depart ASC LIMIT 1
        ")->fetch();
        if (!$r) {
            // 2) Fallback : dernière passée
            $r = $pdo->query("
                SELECT `id-activite`, `titre-activite`, `date_depart`, buyin, rake
                FROM activite
                ORDER BY date_depart DESC LIMIT 1
            ")->fetch();
        }
        if (!$r) {
            echo json_encode(['success' => false, 'error' => 'Aucune activité trouvée']);
            exit;
        }
        $actId    = (int)$r['id-activite'];
        $actTitle = $r['titre-activite'];
        $actDate  = $r['date_depart'];
        $actBuyin = (int)$r['buyin'];
        $actRake  = (int)$r['rake'];
    } else {
        $r = $pdo->prepare("SELECT `titre-activite`, `date_depart`, buyin, rake FROM activite WHERE `id-activite` = ?");
        $r->execute([$actId]);
        $row = $r->fetch();
        $actTitle = $row['titre-activite'] ?? '';
        $actDate  = $row['date_depart'] ?? '';
        $actBuyin = (int)($row['buyin'] ?? 0);
        $actRake  = (int)($row['rake'] ?? 0);
    }

    // ── Classement Challenge ─────────────────────────────────────
    // Priorité : challenge du mois courant → puis challenge de l'activité
    // (même logique que liste-membres-challenge-itm.php et challenge-ranking.php)
    $challengeId = 0;
    $challengeRanks = [];
    try {
        $today = date('Y-m-d');

        // 1) Challenge du mois courant (prioritaire — comme la page web)
        $cfStmt = $pdo->query("SELECT id_challenge FROM challenge WHERE '$today' BETWEEN chal_deb AND chal_fin ORDER BY chal_deb DESC LIMIT 1");
        if ($cfRow = $cfStmt ? $cfStmt->fetch() : null) {
            $challengeId = (int)$cfRow['id_challenge'];
        }

        // 2) Fallback : challenge lié à l'activité courante
        if ($challengeId === 0) {
            $chStmt = $pdo->prepare("SELECT id_challenge FROM activite WHERE `id-activite` = ?");
            $chStmt->execute([$actId]);
            if ($chRow = $chStmt->fetch()) {
                $challengeId = (int)$chRow['id_challenge'];
            }
        }

        // Calculer le rang de chaque membre dans le challenge (même logique que challenge-ranking.php)
        if ($challengeId > 0) {
            $rankStmt = $pdo->prepare("
                SELECT m.`id-membre`,
                       COALESCE(SUM(p.points), 0)              AS total_points,
                       COALESCE(SUM(p.win),    0)              AS nb_victoires,
                       COALESCE(SUM(p.tf),     0)              AS tf,
                       COUNT(p.`id-participation`)             AS nb_participations
                FROM membres m
                LEFT JOIN participation p  ON p.`id-membre`   = m.`id-membre`
                LEFT JOIN activite a       ON p.`id-activite` = a.`id-activite`
                LEFT JOIN challenge c      ON a.`id_challenge` = c.id_challenge
                LEFT JOIN blackliste b     ON b.id_membre     = m.`id-membre`
                WHERE c.id_challenge = ?
                  AND a.date_depart  < ?
                  AND b.id_membre IS NULL
                  AND p.`option` NOT IN ('None', 'Desinscrit')
                GROUP BY m.`id-membre`
                HAVING total_points > 0
                ORDER BY total_points DESC, nb_victoires DESC, tf DESC, nb_participations DESC
            ");
            $rankStmt->execute([$challengeId, $today]);
            $rank = 1;
            foreach ($rankStmt->fetchAll() as $rr) {
                $mid = isset($rr['id-membre']) ? (int)$rr['id-membre'] : 0;
                if ($mid > 0) {
                    $challengeRanks[$mid] = $rank++;
                }
            }
        }
    } catch (Exception $e) {
        // Pas de challenge ou colonne manquante — on continue sans rang challenge
        $challengeRanks = [];
    }

    // ── Liste des participants ────────────────────────────────────
    // Exclure uniquement Desinscrit et None
    $pStmt = $pdo->prepare("
        SELECT
            p.`id-membre`,
            m.pseudo,
            COALESCE(p.`option`, 'None') AS statut,
            COALESCE(p.recave, 0)        AS recave,
            COALESCE(p.jetons, 0)        AS jetons,
            COALESCE(p.jetons_bonus_ins, 0) AS jetons_bonus_ins,
            COALESCE(p.jetons_bonus_arrivee, 0) AS jetons_bonus_arrivee,
            (COALESCE(p.jetons, 0) + COALESCE(p.jetons_bonus_ins, 0) + COALESCE(p.jetons_bonus_arrivee, 0)) AS jetons_total,
            COALESCE(p.anonyme, 0)       AS anonyme,
            COALESCE(p.latereg, 0)       AS latereg,
            COALESCE(p.classement, 0)    AS classement,
            COALESCE(p.gain, 0)          AS gain,
            COUNT(e.id)                  AS bounty_count,
            p.heure_arrivee,
            p.ds
        FROM participation p
        JOIN membres m ON p.`id-membre` = m.`id-membre`
        LEFT JOIN eliminations e
            ON e.nom_membre = m.pseudo
            AND e.id_participation IN (
                SELECT `id-participation` FROM participation WHERE `id-activite` = ?
            )
        WHERE p.`id-activite` = ?
          AND COALESCE(p.`option`, 'None') NOT IN ('None', 'Desinscrit')
        GROUP BY p.`id-participation`, p.`id-membre`, m.pseudo, p.`option`, p.recave,
                 p.jetons, p.jetons_bonus_ins, p.jetons_bonus_arrivee, p.anonyme, p.latereg,
                 p.classement, p.gain, p.heure_arrivee, p.ds
        ORDER BY p.ds ASC
    ");
    $pStmt->execute([$actId, $actId]);
    $rows = $pStmt->fetchAll();

    $participants = [];
    foreach ($rows as $row) {
        $pseudo = ($row['anonyme'] == 1 && (int)$row['id-membre'] !== $userId) ? 'Anonyme' : $row['pseudo'];
        // Formater la date d'inscription
        $dsFormatted = '';
        if (!empty($row['ds']) && $row['ds'] !== '0000-00-00 00:00:00') {
            $date = new DateTime($row['ds']);
            $months = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Jun',
                       7=>'Jul',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];
            $dsFormatted = $date->format('j') . ' ' . $months[(int)$date->format('n')] . ' ' . $date->format('H:i');
        }
        $participants[] = [
            'pseudo'            => $pseudo,
            'statut'            => $row['statut'],
            'latereg'           => (int)$row['latereg'],
            'recave'            => (int)$row['recave'],
            'jetons_total'      => (int)$row['jetons_total'],
            'is_me'             => ((int)$row['id-membre'] === $userId),
            'date_inscription'  => $dsFormatted,
            'bonus1'            => (int)$row['jetons_bonus_ins'],
            'classement'        => (int)$row['classement'],
            'gain'              => (int)$row['gain'],
            'bounty'            => (int)$row['bounty_count'],
            'challenge_rank'    => $challengeRanks[(int)$row['id-membre']] ?? 0,
        ];
    }

    echo json_encode([
        'success'       => true,
        'activity_id'   => $actId,
        'activity_title'=> $actTitle,
        'activity_date' => $actDate,
        'buyin'         => $actBuyin,
        'rake'          => $actRake,
        'count'         => count($participants),
        'participants'  => $participants,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données']);
}
?>
