-- Script SQL to create or update eliminations table
-- Run this on the database: mysql -u root -pKookies7* dbs9616600 < this_file.sql

CREATE TABLE IF NOT EXISTS `eliminations` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_participation` INT NOT NULL,
  `nom_membre` VARCHAR(255) NOT NULL COMMENT 'Nom du joueur qui a éliminé',
  `id_member_eliminator` INT DEFAULT 0 COMMENT 'ID du joueur qui a éliminé',
  `is_definitive` TINYINT(1) DEFAULT 0 COMMENT '1 = Bust définitif, 0 = Recave',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_id_participation` (`id_participation`),
  INDEX `idx_nom_membre` (`nom_membre`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify columns exist
ALTER TABLE `eliminations` 
  ADD COLUMN IF NOT EXISTS `id` INT NOT NULL AUTO_INCREMENT UNIQUE,
  ADD COLUMN IF NOT EXISTS `id_participation` INT NOT NULL,
  ADD COLUMN IF NOT EXISTS `nom_membre` VARCHAR(255) NOT NULL,
  ADD COLUMN IF NOT EXISTS `id_member_eliminator` INT DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `is_definitive` TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
