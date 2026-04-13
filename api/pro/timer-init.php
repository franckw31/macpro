<?php
// ============================================================
//  timer-init.php — (Re)démarre l'horloge blindes (CardEvent Pro)
//  Reproduit exactement creation-blindes.php (zero=1) :
//    • Supprime les blindes-live existantes
//    • Recrée la structure depuis la table `structure`
//      liée à l'activité, en partant de NOW()
//
//  POST https://viendez.com/api/pro/timer-init.php
//  Authorization: Bearer <token>
//  Body JSON : { "event_id": int }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Même timezone que timer-api.php
date_default_timezone_set('Europe/Paris');

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int)($body['event_id'] ?? 0);

    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id invalide']);
        exit;
    }

    // ── Vérifier appartenance ──────────────────────────────────────────
    $stmtCheck = $pdo->prepare(
        "SELECT `id-membre` AS organizer_id, `id_structure`
           FROM `activite`
          WHERE `id-activite` = ?
          LIMIT 1"
    );
    $stmtCheck->execute([$eventId]);
    $activite = $stmtCheck->fetch();

    if (!$activite) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partie introuvable']);
        exit;
    }
    if (!$authUser['is_admin'] && (int)$activite['organizer_id'] !== $authUser['member_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }

    $structureId = (int)$activite['id_structure'];
    if ($structureId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Aucune structure de blindes assignée à cette partie']);
        exit;
    }

    // ── Supprimer les blindes-live existantes (comme creation-blindes.php) ──
    $pdo->prepare("DELETE FROM `blindes-live` WHERE `id-activite` = ?")->execute([$eventId]);

    // ── Charger la structure dans l'ordre (comme creation-blindes.php) ──
    $stmtStr = $pdo->prepare(
        "SELECT s.`ordre`, s.`id-blinde`, s.`duree`, s.`ante` AS ante_structure,
                b.`nom`, b.`val-sb` AS sb, b.`val-bb` AS bb, b.`ante` AS ante_blinde
           FROM `structure` s
           JOIN `blindes`   b ON b.`id-blinde` = s.`id-blinde`
          WHERE s.`id-structure` = ?
          ORDER BY s.`ordre` ASC"
    );
    $stmtStr->execute([$structureId]);
    $levels = $stmtStr->fetchAll();

    if (empty($levels)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => "Structure $structureId vide ou introuvable"]);
        exit;
    }

    // ── Reconstruire les fins à partir de NOW() (zero=1 mode) ────────────
    $runningTs = time();   // heure de départ = maintenant

    $stmtInsert = $pdo->prepare(
        "INSERT INTO `blindes-live`
            (`id-activite`, `ordre`, `nom`, `sb`, `bb`, `minutes`, `fin`, `ante`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    foreach ($levels as $level) {
        // duree dans structure est en secondes (ex: 1200 = 20 min)
        $dureeSecCondes = (int)$level['duree'];
        $dureeMinutes   = $dureeSecCondes / 60;   // stocké en minutes dans blindes-live

        $runningTs += $dureeSecCondes;
        $fin        = date("Y-m-d H:i:s", $runningTs);

        // ante : utilise celle de `blindes` (comme creation-blindes.php)
        $ante = $level['ante_blinde'];

        $stmtInsert->execute([
            $eventId,
            (int)$level['ordre'],
            $level['nom'],
            (int)$level['sb'],
            (int)$level['bb'],
            $dureeMinutes,
            $fin,
            $ante
        ]);
    }

    echo json_encode([
        'success'     => true,
        'levels_created' => count($levels),
        'structure_id'   => $structureId,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}
?>
