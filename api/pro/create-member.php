<?php
// ============================================================
//  create-member.php — Créer un nouveau joueur dans membres
//  POST { pseudo, fname?, lname?, email? }
//  Authorization: Bearer <token> (organisateur requis)
//  Utilisé quand le joueur n'existe pas encore dans la base
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

    if (!$authUser['is_organizer']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès réservé aux organisateurs']);
        exit;
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $pseudo     = trim($body['pseudo']     ?? '');
    $fname      = trim($body['fname']      ?? '');
    $lname      = trim($body['lname']      ?? '');
    $email      = trim($body['email']      ?? '');
    $visibility = trim($body['visibility'] ?? 'organizers');
    if (!in_array($visibility, ['public', 'organizers', 'private'], true)) {
        $visibility = 'organizers';
    }

    // ── Validation ─────────────────────────────────────────────
    if ($pseudo === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le pseudo est obligatoire']);
        exit;
    }
    if (mb_strlen($pseudo) < 2 || mb_strlen($pseudo) > 30) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pseudo invalide (2-30 caractères)']);
        exit;
    }

    // Pseudo déjà utilisé ?
    $stmtChk = $pdo->prepare("SELECT `id-membre` FROM `membres` WHERE `pseudo` = ? LIMIT 1");
    $stmtChk->execute([$pseudo]);
    if ($stmtChk->fetch()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => "Le pseudo \"$pseudo\" est déjà utilisé"]);
        exit;
    }

    // Email déjà utilisé ?
    if ($email !== '') {
        $stmtEmail = $pdo->prepare("SELECT `id-membre` FROM `membres` WHERE `email` = ? LIMIT 1");
        $stmtEmail->execute([$email]);
        if ($stmtEmail->fetch()) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
            exit;
        }
    }

    // Générer un mot de passe temporaire — le joueur pourra réinitialiser plus tard
    $tempPwd   = bin2hex(random_bytes(8));
    $hashedPwd = password_hash($tempPwd, PASSWORD_DEFAULT);
    $fnameVal  = $fname !== '' ? $fname : $pseudo;

    // Si email fourni, l'utiliser ; sinon on utilisera pseudo.{id}@viendez.com après INSERT
    $emailInsert = $email !== '' ? $email : ('tmp_' . uniqid() . '@viendez.com');

    $stmt = $pdo->prepare("
        INSERT INTO `membres`
            (`pseudo`, `fname`, `lname`, `email`, `password`, `droits`, `telephone`, `verification`,
             `pro_created_by`, `pro_visibility`)
        VALUES
            (:pseudo, :fname, :lname, :email, :pwd, '1', '0000000000', 0, :created_by, :visibility)
    ");
    $stmt->execute([
        ':pseudo'      => $pseudo,
        ':fname'       => $fnameVal,
        ':lname'       => $lname,
        ':email'       => $emailInsert,
        ':pwd'         => $hashedPwd,
        ':created_by'  => $authUser['member_id'],
        ':visibility'  => $visibility,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Si pas d'email fourni, mettre à jour avec pseudo.id@viendez.com
    if ($email === '') {
        $emailFinal = strtolower($pseudo) . '.' . $newId . '@viendez.com';
        $pdo->prepare("UPDATE `membres` SET `email` = ? WHERE `id-membre` = ?")
            ->execute([$emailFinal, $newId]);
    } else {
        $emailFinal = $email;
    }

    // Log
    $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,?,?,?,?)")
        ->execute([
            $authUser['member_id'],
            0,
            'create_member',
            "pseudo: $pseudo | created_id: $newId | visibility: $visibility",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

    echo json_encode([
        'success'   => true,
        'member_id' => $newId,
        'pseudo'    => $pseudo,
        'message'   => 'Joueur créé avec succès',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/create-member] ' . $e->getMessage());
}
