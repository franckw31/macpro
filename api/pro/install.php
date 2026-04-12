<?php
// ============================================================
//  install.php — Crée les tables Pro si elles n'existent pas
//  Appeler une seule fois (ou protéger par IP/token admin)
//  GET https://viendez.com/api/pro/install.php?secret=XXXXX
// ============================================================

header('Content-Type: application/json');

$secret = 'CardEventPro2026';   // ← changez après installation
if (($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

require_once __DIR__ . '/_db.php';

$created = [];
$errors  = [];

// ── 1. Ajout des colonnes Pro dans `activite` (IF NOT EXISTS = sans risque) ──
$alterResults = [];
foreach ([
    'statut'          => "ALTER TABLE `activite` ADD COLUMN IF NOT EXISTS `statut` VARCHAR(20) NOT NULL DEFAULT 'publie' COMMENT 'Pro: brouillon|publie|en_cours|termine|annule'",
    'is_public'       => "ALTER TABLE `activite` ADD COLUMN IF NOT EXISTS `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Pro: partie publique'",
    'description'     => "ALTER TABLE `activite` ADD COLUMN IF NOT EXISTS `description` TEXT NULL COMMENT 'Pro: description libre'",
    'devise'          => "ALTER TABLE `activite` ADD COLUMN IF NOT EXISTS `devise` VARCHAR(10) NOT NULL DEFAULT 'EUR' COMMENT 'Pro: devise du buy-in'",
    'part_anonyme'    => "ALTER TABLE `participation` ADD COLUMN IF NOT EXISTS `anonyme` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Inscription privée (visible uniquement par lorganisateur)'",
    'mbr_created_by'  => "ALTER TABLE `membres` ADD COLUMN IF NOT EXISTS `pro_created_by` INT NULL DEFAULT NULL COMMENT 'ID organisateur ayant créé ce joueur via CardEventPro'",
    'mbr_visibility'  => "ALTER TABLE `membres` ADD COLUMN IF NOT EXISTS `pro_visibility` VARCHAR(20) NOT NULL DEFAULT 'public' COMMENT 'public|organizers|private'",
] as $col => $sql) {
    try { $pdo->exec($sql); $alterResults[$col] = 'OK'; }
    catch (PDOException $e) { $alterResults[$col] = 'SKIP: ' . $e->getMessage(); }
}

// ── 2. Tables Pro ─────────────────────────────────────────────
$tables = [

// ── Table des organisateurs habilités ────────────────────────
'pro_organizers' => "
CREATE TABLE IF NOT EXISTS `pro_organizers` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `member_id`   INT         NOT NULL UNIQUE,
    `is_verified` TINYINT(1)  NOT NULL DEFAULT 0
                  COMMENT '0=en attente, 1=validé par admin',
    `note_admin`  VARCHAR(255) DEFAULT NULL,
    `created_at`  TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
",

// ── Journal des actions Pro (audit) ───────────────────────────
'pro_logs' => "
CREATE TABLE IF NOT EXISTS `pro_logs` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `member_id`   INT          NOT NULL,
    `event_id`    INT          NULL,
    `action`      VARCHAR(64)  NOT NULL,
    `details`     TEXT         NULL,
    `ip`          VARCHAR(64)  NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_member` (`member_id`),
    INDEX `idx_event`  (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
",

];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        $created[] = $name;
    } catch (PDOException $e) {
        $errors[] = "$name: " . $e->getMessage();
    }
}

// Ajout de la colonne is_organizer dans app_auth_tokens si absente
try {
    $pdo->exec("ALTER TABLE `app_auth_tokens` ADD COLUMN IF NOT EXISTS `is_organizer` TINYINT(1) NOT NULL DEFAULT 0");
} catch (PDOException $e) {
    // Ignore : colonne peut déjà exister ou syntaxe non supportée
}

echo json_encode([
    'success'        => empty($errors),
    'alterations'    => $alterResults,
    'tables_created' => $created,
    'errors'         => $errors,
    'message'        => empty($errors)
        ? 'Installation CardEvent Pro OK — table activite etendue, tables Pro creees'
        : 'Installation partielle — voir errors',
]);
