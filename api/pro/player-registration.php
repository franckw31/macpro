<?php
// ============================================================
//  player-registration.php — Inscription / désinscription d'un joueur
//  POST https://viendez.com/api/pro/player-registration.php
//  Authorization: Bearer <token>
//  Body JSON : { event_id, member_id?, action }
//    action : "register" | "unregister" | "confirm" | "set_absent"
//
//  Si member_id absent → utilise l'utilisateur connecté (auto-inscription)
//  L'organisateur peut inscrire/désincrire n'importe quel joueur.
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

    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId  = (int)($body['event_id']  ?? 0);
    $memberId = isset($body['member_id']) ? (int)$body['member_id'] : $authUser['member_id'];
    $action   = trim($body['action'] ?? 'register');

    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id manquant']);
        exit;
    }
    if (!in_array($action, ['register', 'unregister', 'confirm', 'set_absent'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Action inconnue : $action"]);
        exit;
    }

    // ── Vérifier la partie ────────────────────────────────────
    $stmtEvent = $pdo->prepare("
        SELECT `id-membre` AS organizer_id, COALESCE(`statut`,'publie') AS statut,
               COALESCE(`places`,16) AS max_joueurs
        FROM `activite` WHERE `id-activite` = ? LIMIT 1
    ");
    $stmtEvent->execute([$eventId]);
    $event = $stmtEvent->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partie introuvable']);
        exit;
    }

    $isOwner  = (int)$event['organizer_id'] === $authUser['member_id'];
    $isSelf   = $memberId === $authUser['member_id'];

    // Actions sur un autre joueur : réservé à l'organisateur ou admin
    if (!$isSelf && !$isOwner && !$authUser['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Seul l\'organisateur peut agir sur les inscriptions des autres joueurs']);
        exit;
    }

    // Confirm/set_absent : réservé à l'organisateur
    if (in_array($action, ['confirm', 'set_absent']) && !$isOwner && !$authUser['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Seul l\'organisateur peut confirmer ou marquer absent']);
        exit;
    }

    // ── Exécution ─────────────────────────────────────────────
    switch ($action) {

        case 'register':
            // Vérifier que la partie accepte des inscriptions
            if (!in_array($event['statut'], ['publie', 'en_cours'])) {
                echo json_encode(['success' => false, 'message' => 'Les inscriptions sont fermées pour cette partie']);
                exit;
            }

            // Compter les inscrits (hors absents)
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) AS nb FROM pro_registrations
                WHERE event_id = ? AND statut IN ('inscrit','confirme')
            ");
            $stmtCount->execute([$eventId]);
            $nbInscrits = (int)$stmtCount->fetchColumn();

            $statut = $nbInscrits >= (int)$event['max_joueurs'] ? 'liste_attente' : 'inscrit';

            // Insérer ou réactiver
            $pdo->prepare("
                INSERT INTO pro_registrations (event_id, member_id, statut)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE statut = VALUES(statut), inscrit_le = CURRENT_TIMESTAMP
            ")->execute([$eventId, $memberId, $statut]);

            $message = $statut === 'liste_attente'
                ? 'Ajouté en liste d\'attente (partie complète)'
                : 'Inscription confirmée';

            proLog($pdo, $authUser['member_id'], $eventId, 'register', "member_id=$memberId | statut=$statut");
            break;

        case 'unregister':
            $pdo->prepare("DELETE FROM pro_registrations WHERE event_id = ? AND member_id = ?")
                ->execute([$eventId, $memberId]);

            // Promouvoir le premier de la liste d'attente si une place se libère
            $stmtWait = $pdo->prepare("
                SELECT id, member_id FROM pro_registrations
                WHERE event_id = ? AND statut = 'liste_attente'
                ORDER BY inscrit_le ASC LIMIT 1
            ");
            $stmtWait->execute([$eventId]);
            $next = $stmtWait->fetch();
            if ($next) {
                $pdo->prepare("UPDATE pro_registrations SET statut = 'inscrit' WHERE id = ?")
                    ->execute([$next['id']]);
                // TODO: envoyer une push notification au joueur promu
            }

            $message = 'Désinscription effectuée';
            proLog($pdo, $authUser['member_id'], $eventId, 'unregister', "member_id=$memberId");
            break;

        case 'confirm':
            $pdo->prepare("
                UPDATE pro_registrations SET statut = 'confirme'
                WHERE event_id = ? AND member_id = ?
            ")->execute([$eventId, $memberId]);
            $message = 'Joueur confirmé';
            proLog($pdo, $authUser['member_id'], $eventId, 'confirm', "member_id=$memberId");
            break;

        case 'set_absent':
            $pdo->prepare("
                UPDATE pro_registrations SET statut = 'absent'
                WHERE event_id = ? AND member_id = ?
            ")->execute([$eventId, $memberId]);
            $message = 'Joueur marqué absent';
            proLog($pdo, $authUser['member_id'], $eventId, 'set_absent', "member_id=$memberId");
            break;
    }

    // Retourner le statut courant de l'inscription
    $stmtReg = $pdo->prepare("
        SELECT r.*, m.pseudo FROM pro_registrations r
        JOIN membres m ON m.`id-membre` = r.member_id
        WHERE r.event_id = ? AND r.member_id = ? LIMIT 1
    ");
    $stmtReg->execute([$eventId, $memberId]);
    $reg = $stmtReg->fetch();

    echo json_encode([
        'success'      => true,
        'message'      => $message ?? 'OK',
        'registration' => $reg ? [
            'id'         => (int)$reg['id'],
            'event_id'   => (int)$reg['event_id'],
            'member_id'  => (int)$reg['member_id'],
            'pseudo'     => $reg['pseudo'],
            'statut'     => $reg['statut'],
            'inscrit_le' => $reg['inscrit_le'],
        ] : null,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/player-registration] ' . $e->getMessage());
}

function proLog(PDO $pdo, int $memberId, int $eventId, string $action, string $details): void {
    try {
        $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,?,?,?,?)")
            ->execute([$memberId, $eventId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (\Exception $e) {
        error_log('[pro_log] ' . $e->getMessage());
    }
}
