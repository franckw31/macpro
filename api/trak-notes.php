<?php
// ============================================================
//  API Trak – notes sur les joueurs
//
//  GET  ?pseudo=XXX             → liste des notes reçues
//  POST {"action":"add",  "pseudo_cible":"…","note":"…","id_activite":0}
//  POST {"action":"delete","id":123}
//
//  POST nécessite Authorization: Bearer <token>
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Créer la table si besoin
    $pdo->exec("CREATE TABLE IF NOT EXISTS `trak` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `id_auteur`   INT NOT NULL,
        `id_cible`    INT NOT NULL,
        `id_activite` INT NOT NULL DEFAULT 0,
        `note`        TEXT NOT NULL,
        `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cible  (`id_cible`),
        INDEX idx_auteur (`id_auteur`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ── Auth helper ───────────────────────────────────────────────
    function requireAuth(PDO $pdo): array {
        // 1) Session PHP (web browser)
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        if (!empty($_SESSION['id'])) {
            $userId = (int)$_SESSION['id'];
            $stmt = $pdo->prepare("SELECT `id-membre` AS membre_id, pseudo FROM membres WHERE `id-membre` = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                $isAdmin = ($userId === 265);
                if (!$isAdmin) {
                    try {
                        $sd = $pdo->prepare("SELECT `droits` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
                        $sd->execute([$userId]);
                        $isAdmin = ((int)$sd->fetchColumn() === 2);
                    } catch (PDOException $e) {}
                }
                $user['is_admin'] = $isAdmin;
                return $user;
            }
        }

        // 2) Bearer token (app mobile)
        $token = '';
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $token = trim(str_ireplace('Bearer', '', $v));
                break;
            }
        }
        if (!$token) {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $token = trim($input['token'] ?? '');
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
        // Déterminer si admin (droits = 2 ou id = 265)
        $mid = (int)$user['membre_id'];
        $isAdmin = ($mid === 265);
        if (!$isAdmin) {
            try {
                $sd = $pdo->prepare("SELECT `droits` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
                $sd->execute([$mid]);
                $isAdmin = ((int)$sd->fetchColumn() === 2);
            } catch (PDOException $e) {}
        }
        $user['is_admin'] = $isAdmin;
        return $user;
    }

    // ── Note serializer ───────────────────────────────────────────
    function serializeNote(array $n): array {
        return [
            'id'             => (int)$n['id'],
            'id_auteur'      => (int)$n['id_auteur'],
            'id_cible'       => (int)$n['id_cible'],
            'id_activite'    => (int)$n['id_activite'],
            'note'           => $n['note'],
            'created_at'     => $n['created_at'],
            'auteur_pseudo'  => $n['auteur_pseudo'],
            'cible_pseudo'   => $n['cible_pseudo'],
            'titre_activite' => $n['titre_activite'] ?? '',
            'date_activite'  => $n['date_activite']  ?? '',
        ];
    }

    // ── Resolve pseudo → id_cible ─────────────────────────────────
    function resolveIdCible(PDO $pdo, string $pseudo, int $id_cible): int {
        if ($pseudo !== '' && $id_cible === 0) {
            $s = $pdo->prepare("SELECT `id-membre` FROM membres WHERE pseudo = ? LIMIT 1");
            $s->execute([$pseudo]);
            $row = $s->fetch();
            return $row ? (int)$row['id-membre'] : 0;
        }
        return $id_cible;
    }

    $method = $_SERVER['REQUEST_METHOD'];


    // ════════════════════════════════════════════════════════════
    //  GET – charger les notes reçues d'un joueur ou toutes mes notes (all=1)
    // ════════════════════════════════════════════════════════════
    if ($method === 'GET') {
        $user     = requireAuth($pdo);
        $userId   = (int)$user['membre_id'];
        $isAdmin  = $user['is_admin'];

        if (!empty($_GET['all'])) {
            // Afficher toutes les notes où je suis auteur ou cible
            $whereClause = '(t.id_auteur = ? OR t.id_cible = ?)';
            $params = [$userId, $userId];
            $stmt = $pdo->prepare("
                SELECT t.id, t.id_auteur, t.id_cible, t.id_activite, t.note, t.created_at,
                       COALESCE(m.pseudo,  'Inconnu')            AS auteur_pseudo,
                       COALESCE(mc.pseudo, 'Inconnu')            AS cible_pseudo,
                       COALESCE(a.`titre-activite`, '')         AS titre_activite,
                       DATE_FORMAT(a.date_depart,'%d/%m/%Y')   AS date_activite
                FROM trak t
                LEFT JOIN membres  m  ON t.id_auteur   = m.`id-membre`
                LEFT JOIN membres  mc ON t.id_cible    = mc.`id-membre`
                LEFT JOIN activite a  ON t.id_activite = a.`id-activite`
                WHERE $whereClause
                ORDER BY t.created_at DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            echo json_encode([
                'success'  => true,
                'is_admin' => $isAdmin,
                'notes'    => array_map('serializeNote', $rows),
            ]);
            exit;
        }

        $pseudo_cible = trim($_GET['pseudo']   ?? '');
        $id_cible     = (int)($_GET['id_cible'] ?? 0);
        $id_cible     = resolveIdCible($pdo, $pseudo_cible, $id_cible);

        if ($id_cible === 0) {
            echo json_encode(['success' => false, 'error' => 'Membre introuvable']);
            exit;
        }

        // Filtre auteur :
        //   admin    → toutes les notes où le joueur est auteur OU cible
        //   non-admin → les notes où je suis impliqué (auteur ou cible) avec ce joueur
        if ($isAdmin) {
            $whereClause  = '(t.id_cible = ? OR t.id_auteur = ?)';
            $params       = [$id_cible, $id_cible];
        } else {
            // Non-admin : seulement les notes écrites par moi à ce joueur
            $whereClause  = '(t.id_auteur = ? AND t.id_cible = ?)';
            $params       = [$userId, $id_cible];
        }

        $stmt = $pdo->prepare("
            SELECT t.id, t.id_auteur, t.id_cible, t.id_activite, t.note, t.created_at,
                   COALESCE(m.pseudo,  'Inconnu')            AS auteur_pseudo,
                   COALESCE(mc.pseudo, 'Inconnu')            AS cible_pseudo,
                   COALESCE(a.`titre-activite`, '')         AS titre_activite,
                   DATE_FORMAT(a.date_depart,'%d/%m/%Y')   AS date_activite
            FROM trak t
            LEFT JOIN membres  m  ON t.id_auteur   = m.`id-membre`
            LEFT JOIN membres  mc ON t.id_cible    = mc.`id-membre`
            LEFT JOIN activite a  ON t.id_activite = a.`id-activite`
            WHERE $whereClause
            ORDER BY t.created_at DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo json_encode([
            'success'  => true,
            'id_cible' => $id_cible,
            'is_admin' => $isAdmin,
            'notes'    => array_map('serializeNote', $rows),
        ]);

    // ════════════════════════════════════════════════════════════
    //  POST – add ou delete
    // ════════════════════════════════════════════════════════════
    } elseif ($method === 'POST') {
        $user   = requireAuth($pdo);
        $userId = (int)$user['membre_id'];
        $input  = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = trim($input['action'] ?? '');

        // ── Ajouter une note ──────────────────────────────────────
        if ($action === 'add') {
            $pseudo_cible = trim($input['pseudo_cible'] ?? '');
            $id_cible     = (int)($input['id_cible']   ?? 0);
            $note_text    = trim($input['note']        ?? '');
            $id_activite  = (int)($input['id_activite'] ?? 0);

            $id_cible = resolveIdCible($pdo, $pseudo_cible, $id_cible);
            if ($id_cible === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Membre cible introuvable']);
                exit;
            }
            if ($note_text === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Note vide']);
                exit;
            }

            $pdo->prepare("
                INSERT INTO trak (id_auteur, id_cible, id_activite, note)
                VALUES (?, ?, ?, ?)
            ")->execute([$userId, $id_cible, $id_activite, $note_text]);

            $newId = (int)$pdo->lastInsertId();

            $stmt2 = $pdo->prepare("
                SELECT t.id, t.id_auteur, t.id_activite, t.note, t.created_at,
                       COALESCE(m.pseudo, 'Inconnu')            AS auteur_pseudo,
                       COALESCE(a.`titre-activite`, '')         AS titre_activite,
                       DATE_FORMAT(a.date_depart,'%d/%m/%Y')   AS date_activite
                FROM trak t
                LEFT JOIN membres  m ON t.id_auteur   = m.`id-membre`
                LEFT JOIN activite a ON t.id_activite = a.`id-activite`
                WHERE t.id = ?
            ");
            $stmt2->execute([$newId]);
            $newNote = $stmt2->fetch();

            echo json_encode([
                'success' => true,
                'note'    => serializeNote($newNote),
            ]);

        // ── Supprimer une note ────────────────────────────────────
        } elseif ($action === 'delete') {
            $id = (int)($input['id'] ?? 0);
            if ($id === 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id_auteur FROM trak WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $note = $stmt->fetch();

            if (!$note) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Note introuvable']);
                exit;
            }
            if ((int)$note['id_auteur'] !== $userId) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Non autorisé']);
                exit;
            }

            $pdo->prepare("DELETE FROM trak WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);

        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Action inconnue: $action"]);
        }

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}
