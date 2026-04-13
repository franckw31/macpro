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

// Même timezone que timer-api.php
date_default_timezone_set('Europe/Paris');

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

    $now  = time();
    $actu = date("Y-m-d H:i:s", $now);

    // ── Lire l'état de pause depuis la ligne ordre=1 (comme timer-api.php) ──
    $stmtPause = $pdo->prepare(
        "SELECT `en_pause`, `heure_pause`
           FROM `blindes-live`
          WHERE `id-activite` = ? AND `ordre` = 1
          LIMIT 1"
    );
    $stmtPause->execute([$eventId]);
    $pauseRow = $stmtPause->fetch();

    $isPaused   = $pauseRow && intval($pauseRow['en_pause']) == 1;
    $heurePause = $pauseRow['heure_pause'] ?? null;

    // Calcul du temps de référence gelé (identique à timer-api.php)
    $pauseElapsed = 0;
    if ($isPaused && !empty($heurePause)) {
        $pe = $now - strtotime($heurePause);
        if ($pe > 0) $pauseElapsed = $pe;
    }
    // nowRef = heure_pause si en pause (temps gelé), sinon now
    $nowRef     = $now - $pauseElapsed;
    $nowRefDate = date("Y-m-d H:i:s", $nowRef);

    // =================================================================
    // ── pauseresume ───────────────────────────────────────────────────
    // =================================================================
    if ($action === 'pauseresume') {

        if (!$isPaused) {
            // ── MISE EN PAUSE : calqué sur en-pause.php ──────────────
            // Enregistrer heure_pause sur ordre=1 (comme en-pause.php)
            $pdo->prepare(
                "UPDATE `blindes-live`
                    SET `heure_pause` = ?,
                        `delta`       = 0,
                        `en_pause`    = 1
                  WHERE `ordre`       = 1
                    AND `id-activite` = ?"
            )->execute([$actu, $eventId]);

            echo json_encode(['success' => true, 'paused' => true]);

        } else {
            // ── REPRISE : calqué sur de-pause.php ────────────────────
            // Calculer le delta (durée de la pause en secondes)
            $delta = 0;
            if (!empty($heurePause)) {
                $delta = $now - strtotime($heurePause);
                if ($delta < 0) $delta = 0;
            }

            // Marquer la reprise sur ordre=1 (comme de-pause.php)
            $pdo->prepare(
                "UPDATE `blindes-live`
                    SET `heure_depause` = ?,
                        `delta`         = ?,
                        `en_pause`      = 0
                  WHERE `ordre`         = 1
                    AND `id-activite`   = ?"
            )->execute([$actu, $delta, $eventId]);

            // Décaler TOUTES les fins par le delta de la pause (comme de-pause.php)
            $shift = max(1, $delta);
            $pdo->prepare(
                "UPDATE `blindes-live`
                    SET `fin` = DATE_ADD(`fin`, INTERVAL ? SECOND)
                  WHERE `id-activite` = ?"
            )->execute([$shift, $eventId]);

            echo json_encode(['success' => true, 'paused' => false]);
        }
        exit;
    }

    // =================================================================
    // ── plus / moins (±1 minute) ──────────────────────────────────────
    // Utilise nowRefDate pour gérer l'état pause (fin gelée < now réel)
    // =================================================================
    if ($action === 'plus' || $action === 'moins') {
        $seconds = ($action === 'plus') ? 60 : -60;

        $pdo->prepare(
            "UPDATE `blindes-live`
                SET `fin` = DATE_ADD(`fin`, INTERVAL ? SECOND)
              WHERE `id-activite` = ?
                AND `fin` > ?"
        )->execute([$seconds, $eventId, $nowRefDate]);

        echo json_encode(['success' => true]);
        exit;
    }

    // =================================================================
    // ── next_blind / prev_blind ───────────────────────────────────────
    // Utilise nowRef (calqué sur timer-api.php) pour trouver le niveau
    // en cours même si le timer est en pause depuis longtemps.
    // =================================================================
    if ($action === 'next_blind' || $action === 'prev_blind') {

        // Charger toute la structure dans l'ordre
        $stmt = $pdo->prepare(
            "SELECT * FROM `blindes-live` WHERE `id-activite` = ? ORDER BY `ordre` ASC"
        );
        $stmt->execute([$eventId]);
        $blinds = $stmt->fetchAll();

        if (empty($blinds)) {
            echo json_encode(['success' => false, 'message' => 'Aucune structure de blindes']);
            exit;
        }

        // ── Trouver le niveau en cours via nowRef (même logique que timer-api.php) ──
        $currentIndex = -1;
        foreach ($blinds as $k => $b) {
            if (!empty($b['fin']) && strtotime($b['fin']) > $nowRef) {
                $currentIndex = $k;
                break;
            }
        }

        $targetIndex = -1;

        if ($action === 'next_blind') {
            if ($currentIndex !== -1) {
                // Expirer immédiatement le niveau en cours
                $pastDate = date("Y-m-d H:i:s", $now - 1);
                $pdo->prepare(
                    "UPDATE `blindes-live` SET `fin` = ? WHERE `id` = ?"
                )->execute([$pastDate, $blinds[$currentIndex]['id']]);
                $targetIndex = $currentIndex + 1;
            }
            // Si currentIndex === -1, le tournoi est terminé : rien à faire

        } elseif ($action === 'prev_blind') {
            if ($currentIndex === -1)   $targetIndex = count($blinds) - 1;
            elseif ($currentIndex == 0) $targetIndex = 0;
            else                        $targetIndex = $currentIndex - 1;
        }

        if ($targetIndex >= 0 && $targetIndex < count($blinds)) {

            // Réinitialiser la pause sur ordre=1 (efface heure_pause)
            $pdo->prepare(
                "UPDATE `blindes-live`
                    SET `en_pause`    = 0,
                        `heure_pause` = NULL,
                        `delta`       = 0
                  WHERE `ordre`       = 1
                    AND `id-activite` = ?"
            )->execute([$eventId]);

            // Recalculer toutes les fins depuis now
            // • niveaux < targetIndex  → mis dans le passé (si encore "gelés")
            // • niveaux >= targetIndex → durées recalculées à partir de now
            $runningTime = $now;

            foreach ($blinds as $k => $b) {
                if ($k < $targetIndex) {
                    // Passer au passé seulement si encore dans le futur gelé
                    if (!empty($b['fin']) && strtotime($b['fin']) > $nowRef) {
                        $past = date("Y-m-d H:i:s", $now - 60);
                        $pdo->prepare(
                            "UPDATE `blindes-live` SET `fin` = ? WHERE `id` = ?"
                        )->execute([$past, $b['id']]);
                    }
                    continue;
                }

                $duration    = intval($b['minutes']) * 60;
                $runningTime += $duration;
                $newFin      = date("Y-m-d H:i:s", $runningTime);

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
