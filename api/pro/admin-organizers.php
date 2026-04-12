<?php
// ============================================================
//  admin-organizers.php — Gestion des organisateurs
//  Réservé à l'administrateur (is_admin = 1 ou member_id = 265)
//
//  GET  ?action=list  → liste tous les demandeurs / organisateurs
//  POST { action: "approve"|"revoke"|"add", member_id }
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    // Réservé admin
    if (!$authUser['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès réservé à l\'administrateur']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET : liste des organisateurs ─────────────────────────
    if ($method === 'GET') {
        $filter = $_GET['filter'] ?? 'all'; // all | verified | pending

        $sql = "
            SELECT o.id, o.member_id, o.is_verified, o.note_admin, o.created_at,
                   m.pseudo, m.email,
                   COUNT(DISTINCT e.id) AS nb_events
            FROM pro_organizers o
            JOIN membres m ON m.`id-membre` = o.member_id
            LEFT JOIN pro_events e ON e.organizer_id = o.member_id
        ";
        $params = [];

        if ($filter === 'verified') {
            $sql .= " WHERE o.is_verified = 1";
        } elseif ($filter === 'pending') {
            $sql .= " WHERE o.is_verified = 0";
        }

        $sql .= " GROUP BY o.id ORDER BY o.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $organizers = array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'member_id'  => (int)$r['member_id'],
            'pseudo'     => $r['pseudo'],
            'email'      => $r['email'],
            'is_verified'=> (bool)$r['is_verified'],
            'note_admin' => $r['note_admin'],
            'nb_events'  => (int)$r['nb_events'],
            'created_at' => $r['created_at'],
        ], $rows);

        echo json_encode(['success' => true, 'organizers' => $organizers, 'total' => count($organizers)]);
        exit;
    }

    // ── POST : approuver / révoquer / ajouter ─────────────────
    if ($method === 'POST') {
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $action   = trim($body['action']  ?? '');
        $memberId = (int)($body['member_id'] ?? 0);
        $note     = trim($body['note_admin'] ?? '');

        if (!in_array($action, ['approve', 'revoke', 'add', 'remove'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action invalide (approve|revoke|add|remove)']);
            exit;
        }
        if ($memberId <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'member_id manquant']);
            exit;
        }

        // Vérifier que le membre existe
        $stmtM = $pdo->prepare("SELECT pseudo FROM membres WHERE `id-membre` = ? LIMIT 1");
        $stmtM->execute([$memberId]);
        $membre = $stmtM->fetch();
        if (!$membre) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Membre introuvable']);
            exit;
        }

        switch ($action) {
            case 'add':
            case 'approve':
                $pdo->prepare("
                    INSERT INTO pro_organizers (member_id, is_verified, note_admin)
                    VALUES (?, 1, ?)
                    ON DUPLICATE KEY UPDATE is_verified = 1, note_admin = VALUES(note_admin)
                ")->execute([$memberId, $note]);
                $message = "Organisateur {$membre['pseudo']} approuvé";
                break;

            case 'revoke':
                $pdo->prepare("
                    UPDATE pro_organizers SET is_verified = 0, note_admin = ?
                    WHERE member_id = ?
                ")->execute([$note, $memberId]);
                $message = "Droits de {$membre['pseudo']} révoqués";
                break;

            case 'remove':
                $pdo->prepare("DELETE FROM pro_organizers WHERE member_id = ?")
                    ->execute([$memberId]);
                $message = "Organisateur {$membre['pseudo']} supprimé";
                break;
        }

        // Log
        try {
            $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,NULL,?,?,?)")
                ->execute([$authUser['member_id'], "admin_$action", "target=$memberId | note=$note", $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (\Exception $e) {}

        echo json_encode(['success' => true, 'message' => $message ?? 'OK']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/admin-organizers] ' . $e->getMessage());
}
