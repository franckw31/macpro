<?php
/**
 * Script de sauvegarde des valeurs de la colonne jetons
 * depuis la table participation vers jetons_1 dans la table membres
 * 
 * Formule: jetons = 35000 + (cagnotte * 200), max 50000
 * Où cagnotte = SUM(gain) / 10
 */

// Configuration de la base de données
require_once '../config.php';

try {
    // Vérifier la connexion
    if (!$conx) {
        throw new Exception("Erreur de connexion à la base de données");
    }
    
    // 1. Vérifier et créer la colonne jetons_1 si elle n'existe pas
    $checkColumnQuery = "SHOW COLUMNS FROM `membres` LIKE 'jetons_1'";
    $result = $conx->query($checkColumnQuery);
    
    if ($result->num_rows == 0) {
        echo "Création de la colonne jetons_1 dans la table membres...\n";
        $addColumnQuery = "ALTER TABLE `membres` ADD COLUMN `jetons_1` INT(11) DEFAULT NULL AFTER `solde`";
        if ($conx->query($addColumnQuery)) {
            echo "✓ Colonne jetons_1 créée avec succès.\n\n";
        } else {
            throw new Exception("Erreur lors de la création de la colonne: " . $conx->error);
        }
    } else {
        echo "✓ La colonne jetons_1 existe déjà.\n\n";
    }
    
    // 2. Sauvegarder les valeurs de jetons CALCULÉES depuis participation vers jetons_1 dans membres
    // Formule: jetons = 35000 + (cagnotte * 200), max 50000
    // Où cagnotte = SUM(gain) / 10
    // Filtre: UNIQUEMENT les activités avec date_depart < aujourd'hui ET id_challenge = 4
    echo "Sauvegarde des valeurs de jetons (calculées)...\n";
    
    // D'abord récupérer tous les calculs
    $calcQuery = "SELECT p.`id-membre`,
                         m.pseudo,
                         SUM(p.gain) as total_gain,
                         ROUND(SUM(p.gain) / 10, 2) as cagnotte,
                         LEAST(50000, GREATEST(35000, ROUND(35000 + ((SUM(p.gain) / 10) * 200)))) as jetons_calcule
                  FROM `participation` p
                  JOIN `activite` a ON p.`id-activite` = a.`id-activite`
                  JOIN `challenge` c ON a.`id_challenge` = c.id_challenge
                  JOIN `membres` m ON p.`id-membre` = m.`id-membre`
                  WHERE a.`date_depart` < CURDATE()
                  AND c.id_challenge = 4
                  GROUP BY p.`id-membre`, m.pseudo
                  ORDER BY p.`id-membre`";
    
    $calcResult = mysqli_query($conx, $calcQuery);
    if (!$calcResult) {
        throw new Exception("Erreur lors de la récupération des calculs: " . mysqli_error($conx));
    }
    
    echo "=== APERÇU DES CALCULS ===\n";
    $updates = [];
    $count = 0;
    while ($row = mysqli_fetch_assoc($calcResult)) {
        $count++;
        if ($count <= 10 || $row['id-membre'] == 185) {
            echo "ID: " . $row['id-membre'] . " | Pseudo: " . $row['pseudo'] . " | Gain: " . $row['total_gain'] . " | Cagnotte: " . $row['cagnotte'] . " | Jetons: " . $row['jetons_calcule'] . "\n";
        }
        $updates[$row['id-membre']] = $row['jetons_calcule'];
    }
    echo "Total membres à mettre à jour: $count\n\n";
    
    // Maintenant faire les updates
    $affectedRows = 0;
    foreach ($updates as $membre_id => $jetons_value) {
        $updateQuery = "UPDATE `membres` SET `jetons_1` = " . intval($jetons_value) . " WHERE `id-membre` = " . intval($membre_id);
        if (mysqli_query($conx, $updateQuery)) {
            $affectedRows += mysqli_affected_rows($conx);
        } else {
            throw new Exception("Erreur mise à jour membre $membre_id: " . mysqli_error($conx));
        }
    }
    
    echo "✓ Mise à jour réussie: $affectedRows lignes affectées.\n\n";
        
    // Afficher un résumé
    $summaryQuery = "SELECT COUNT(*) as total, 
                           COUNT(CASE WHEN jetons_1 IS NOT NULL THEN 1 END) as with_jetons,
                           COUNT(CASE WHEN jetons_1 IS NULL THEN 1 END) as without_jetons,
                           AVG(jetons_1) as jetons_moyen,
                           MAX(jetons_1) as jetons_max,
                           MIN(jetons_1) as jetons_min
                    FROM `membres`";
    
    $summaryResult = mysqli_query($conx, $summaryQuery);
    $summary = mysqli_fetch_assoc($summaryResult);
    
    echo "=== RÉSUMÉ DE LA SAUVEGARDE ===\n";
    echo "Total de membres: " . $summary['total'] . "\n";
    echo "Membres avec jetons sauvegardés: " . $summary['with_jetons'] . "\n";
    echo "Membres sans jetons: " . $summary['without_jetons'] . "\n";
    echo "Moyenne de jetons: " . round($summary['jetons_moyen'], 2) . "\n";
    echo "Jetons maximum: " . $summary['jetons_max'] . "\n";
    echo "Jetons minimum: " . $summary['jetons_min'] . "\n";
    
} catch (Exception $e) {
    die("❌ Erreur: " . $e->getMessage());
}

mysqli_close($conx);
echo "\n✓ Sauvegarde terminée avec succès!\n";
?>
?>
