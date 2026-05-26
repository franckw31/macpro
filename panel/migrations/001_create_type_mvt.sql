-- Migration: create type_mvt table and seed default movement types
-- Run this on your MySQL server (adjust table/column types if needed)

CREATE TABLE IF NOT EXISTS `type_mvt` (
  `id_type_mvt` INT NOT NULL PRIMARY KEY,
  `label` VARCHAR(255) NOT NULL,
  `direction` ENUM('debit','credit') NOT NULL DEFAULT 'credit',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default movement types (matching existing application defaults)
INSERT INTO `type_mvt` (`id_type_mvt`, `label`, `direction`) VALUES
(1, 'Débit Buyin', 'debit'),
(2, 'Débit Rake', 'debit'),
(3, 'Débit Gestion', 'debit'),
(4, 'Crédit Gain', 'credit'),
(5, 'Crédit Gestion', 'credit'),
(6, 'Crédit Tombola', 'credit')
ON DUPLICATE KEY UPDATE label = VALUES(label), direction = VALUES(direction);

-- Optional: example to add a new type later
-- INSERT INTO `type_mvt` (id_type_mvt, label, direction) VALUES (7, 'Autre Crédit', 'credit');
