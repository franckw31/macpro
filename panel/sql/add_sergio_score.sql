-- Ajoute la colonne sergio_score à la table participation
-- À exécuter une seule fois sur le serveur (local et production)

ALTER TABLE `participation`
    ADD COLUMN IF NOT EXISTS `sergio_score` DECIMAL(6,2) DEFAULT NULL
        COMMENT 'Score Sergio calculé à la fin de la partie : ceil((1 - rank/denom) * 20)';

-- Index utile pour les requêtes historiques par membre et par date
ALTER TABLE `participation`
    ADD INDEX IF NOT EXISTS `idx_sergio_score` (`sergio_score`);
