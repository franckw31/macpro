<?php
// ============================================================
//  timer-control.php — Contrôle du timer live (CardEvent Pro)
//  POST https://viendez.com/api/pro/timer-control.php
//  Authorization: Bearer <token>
//  Body JSON : { "event_id": int, "action": string }
//
//  Actions supportées :
//    pauseresume  → bascule pause/reprise
//    plus         → +1 minute au niveau en cours
//    moins        → -1 minute au niveau en cours
//    next_blind   → passe au niveau suivant
//    prev_blind   → retourne au niveau précédent
//
//  ⚠  Logique calquée EXACTEMENT sur :
//     - en-pause.php / de-pause.php  (gestion heure_pause / delta)
//     - timer-api.php                (now_adjusted = heure_pause gelé)
//     - panel/timer_actions.php      (navigation next/prev)
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

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int)($body['event_id'] ?? 0);
    $action  = trim($body['action']    ?? '');

    $validActions = ['pauseresume', 'plus', 'moins', 'next_blind', 'prev_blind'];
    if ($eventId <= 0 || !in_array($action, $validActions, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides (event_id ou action)']);
        exit;
    }

    // ── Vérifier que la partie appartient à cet organisateur ──────────
    $stmtCheck = $pdo->prepare(
        "SELECT `id-membre` AS organizer_id FROM `activite` WHERE `id-activite` = ? LIMIT 1"
    );
    $stmtCheck->execute([$eventId]);
    $existing = $stmtCheck->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partie introuvable']);
        exit;
    }
    if (!$authUser['is_admin'] && (int)$existing['organizer_id'] !== $authUser['member_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé : vous n\'êtes pas l\'organisateur']);
        exit;
    }

    $now = time();

    // ── pauseresume ───────────────────────────────────────────────────
    if ($action === 'pauseresume') {
        $stmt = $pdo->prepare(
            "SELECT `en_pause` FROM `blindes-live` WHERE `id-activite` = ? LIMIT 1"
        );
        $stmt->execute([$eventId]);
        $row = $stmt->fetch();
        $etatActuel = (int)($row['en_pause'] ?? 0);

        if ($etatActuel == 0) {
            // Mettre en pause
            $pdo->prepare(
                "UPDATE `blindes-live` SET `en_pause` = 1 WHERE `id-activite` = ?"
            )->execute([$eventId]);
            echo json_encode(['success' => true, 'paused' => true]);
        } else {
            // Reprendre + décaler fin de 1 seconde pour compenser la latence
            $pdo->prepare(
                "UPDATE `blindes-live` SET `en_pause` = 0 WHERE `id-activite` = ?"
            )->execute([$eventId]);
            $pdo->prepare(
                "UPDATE `blindes-live`
                    SET `fin` = DATE_ADD(`fin`, INTERVAL 1 SECOND)
                  WHERE `id-activite` = ? AND `fin` > NOW()"
            )->execute([$eventId]);
            echo json_encode(['success' => true, 'paused' => false]);
        }
        exit;
    }

    // ── plus / moins (±1 minute) ──────────────────────────────────────
    if ($action === 'plus' || $action === 'moins') {
        $seconds = ($action === 'plus') ? 60 : -60;
        $pdo->prepare(
            "UPDATE `blindes-live`
                SET `fin` = DATE_ADD(`fin`, INTERVAL ? SECOND)
              WHERE `id-activite` = ? AND `fin` > NOW()"
        )->execute([$seconds, $eventId]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ── next_blind / prev_blind ───────────────────────────────────────
    if ($action === 'next_blind' || $action === 'prev_blind') {
        $stmt = $pdo->prepare(
            "SELECT * FROM `blindes-live` WHERE `id-activite` = ? ORDER BY `ordre` ASC"
        );
        $stmt->execute([$eventId]);
        $blinds = $stmt->fetchAll();

        // Trouver le niveau en cours (premier dont fin > maintenant)
        $currentIndex = -1;
        foreach ($blinds as $k => $b) {
            if (strtotime($b['fin']) > $now) {
                $currentIndex = $k;
                break;
            }
        }

        $targetIndex = -1;

        if ($action === 'next_blind') {
            if ($currentIndex !== -1) {
                // Expirer le niveau en cours immédiatement
                $pastDate = date("Y-m-d H:i:s", $now - 1);
                $pdo->prepare(
                    "UPDATE `blindes-live` SET `fin` = ? WHERE `id` = ?"
                )->execute([$pastDate, $blinds[$currentIndex]['id']]);
                $targetIndex = $currentIndex + 1;
            }
        } elseif ($action === 'prev_blind') {
            if ($currentIndex === -1)    $targetIndex = count($blinds) - 1;
            elseif ($currentIndex == 0) $targetIndex = 0;
            else                        $targetIndex = $currentIndex - 1;
        }

        if ($targetIndex >= 0 && $targetIndex < count($blinds)) {
            // Dégeler le timer
            $pdo->prepare(
                "UPDATE `blindes-live` SET `en_pause` = 0 WHERE `id-activite` = ?"
            )->execute([$eventId]);

            // Recalculer toutes les fins à partir du niveau cible
            $runningTime = $now;
            foreach ($blinds as $k => $b) {
                if ($k < $targetIndex) {
                    // Passer les niveaux précédents au passé
                    if (strtotime($b['fin']) > $now) {
                        $past = date("Y-m-d H:i:s", $now - 60);
                        $pdo->prepare(
                            "UPDATE `blindes-live` SET `fin` = ? WHERE `id` = ?"
                        )->execute([$past, $b['id']]);
                    }
                    continue;
                }
                $duration     = intval($b['minutes']) * 60;
                $runningTime += $duration;
                $newFin       = date("Y-m-d H:i:s", $runningTime);
                $pdo->prepare(
                    "UPDATE `blindes-live` SET `fin` = ? WHERE `id` = ?"
                )->execute([$newFin, $b['id']]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Action non traitée']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur : ' . $e->getMessage()]);
}
?>
