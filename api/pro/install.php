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

$tables = [

// ── Table des parties Pro ─────────────────────────────────────
'pro_events' => "
CREATE TABLE IF NOT EXISTS `pro_events` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `titre`        VARCHAR(255)    NOT NULL,
    `description`  TEXT            NOT NULL DEFAULT '',
    `lieu`         VARCHAR(255)    NOT NULL,
    `date_event`   DATETIME        NOT NULL,
    `max_joueurs`  INT             NOT NULL DEFAULT 20,
    `buy_in`       DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `devise`       VARCHAR(10)     NOT NULL DEFAULT 'EUR',
    `statut`       ENUM('brouillon','publie','en_cours','termine','annule')
                                   NOT NULL DEFAULT 'brouillon',
    `is_public`    TINYINT(1)      NOT NULL DEFAULT 1,
    `organizer_id` INT             NOT NULL,
    `activity_id`  INT             NULL DEFAULT NULL
                   COMMENT 'Lien optionnel vers une activite CardEvent existante',
    `created_at`   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_organizer` (`organizer_id`),
    INDEX `idx_statut`    (`statut`),
    INDEX `idx_date`      (`date_event`),
    INDEX `idx_public`    (`is_public`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
",

// ── Table des inscriptions à une partie Pro ───────────────────
'pro_registrations' => "
CREATE TABLE IF NOT EXISTS `pro_registrations` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `event_id`   INT  NOT NULL,
    `member_id`  INT  NOT NULL,
    `statut`     ENUM('inscrit','liste_attente','confirme','absent')
                      NOT NULL DEFAULT 'inscrit',
    `inscrit_le` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_registration` (`event_id`, `member_id`),
    INDEX `idx_event`  (`event_id`),
    INDEX `idx_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
",

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
    'success'       => empty($errors),
    'tables_created' => $created,
    'errors'        => $errors,
    'message'       => empty($errors)
        ? 'Installation CardEvent Pro OK ✅'
        : 'Installation partielle — voir errors',
]);
