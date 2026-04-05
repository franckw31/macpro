<?php
require_once 'config.php';

/**
 * Script pour copier les valeurs de 'id-membre' vers 'id_membre'
 * dans toutes les tables qui possèdent 'id-membre'.
 */

// Liste des tables susceptibles de contenir 'id-membre'
$tables = ['membres', 'activite', 'participation'];

foreach ($tables as $table) {
    echo "Traitement de la table : <strong>$table</strong>...<br>";
    
    // 1. Vérifier si 'id-membre' existe dans cette table
    $check_source = mysqli_query($conx, "SHOW COLUMNS FROM `$table` LIKE 'id-membre'");
    if (mysqli_num_rows($check_source) == 0) {
        echo "La colonne source 'id-membre' n'existe pas dans '$table'. Passage à la table suivante.<br><hr>";
        continue;
    }

    // 2. Vérifier si 'id_membre' existe, sinon le créer
    $check_target = mysqli_query($conx, "SHOW COLUMNS FROM `$table` LIKE 'id_membre'");
    if (mysqli_num_rows($check_target) == 0) {
        echo "La colonne cible 'id_membre' n'existe pas dans '$table'. Création en cours...<br>";
        $alter_sql = "ALTER TABLE `$table` ADD `id_membre` INT NULL AFTER `id-membre`";
        if (!mysqli_query($conx, $alter_sql)) {
            echo "Erreur lors de la création de la colonne : " . mysqli_error($conx) . "<br>";
            continue;
        }
        echo "Colonne 'id_membre' créée avec succès.<br>";
    }

    // 3. Copier les données de 'id-membre' vers 'id_membre'
    // On ne met à jour que si id_membre est vide (NULL ou 0) pour éviter d'écraser des données existantes
    $update_sql = "UPDATE `$table` SET `id_membre` = `id-membre` WHERE `id_membre` IS NULL OR `id_membre` = 0";
    if (mysqli_query($conx, $update_sql)) {
        echo "Mise à jour réussie. Lignes affectées : " . mysqli_affected_rows($conx) . "<br>";
    } else {
        echo "Erreur lors de la mise à jour : " . mysqli_error($conx) . "<br>";
    }

    // 4. Spécifique à la table 'membres' : Calcul de password_ext
    if ($table === 'membres') {
        echo "Calcul de 'password_ext' pour la table membres...<br>";
        
        // Vérifier si 'password_ext' existe, sinon le créer
        $check_pwd_ext = mysqli_query($conx, "SHOW COLUMNS FROM `membres` LIKE 'password_ext'");
        if (mysqli_num_rows($check_pwd_ext) == 0) {
            $alter_pwd = "ALTER TABLE `membres` ADD `password_ext` BIGINT NULL";
            mysqli_query($conx, $alter_pwd);
            echo "Colonne 'password_ext' créée.<br>";
        }

        // Mise à jour de password_ext = (id_membre * id_membre * id_membre) + 777
        $update_pwd_sql = "UPDATE `membres` SET `password_ext` = (POW(`id_membre`, 3) + 777) WHERE `id_membre` IS NOT NULL AND `id_membre` > 0";
        if (mysqli_query($conx, $update_pwd_sql)) {
            echo "Calcul de 'password_ext' terminé. Lignes affectées : " . mysqli_affected_rows($conx) . "<br>";
        } else {
            echo "Erreur lors du calcul de 'password_ext' : " . mysqli_error($conx) . "<br>";
        }

        // 5. Recopier pseudo vers fname si fname est vide ou nul
        echo "Copie de 'pseudo' vers 'fname' pour la table membres...<br>";
        $update_fname_sql = "UPDATE `membres` SET `fname` = `pseudo` WHERE `fname` IS NULL OR `fname` = ''";
        if (mysqli_query($conx, $update_fname_sql)) {
            echo "Copie de 'pseudo' vers 'fname' terminée. Lignes affectées : " . mysqli_affected_rows($conx) . "<br>";
        } else {
            echo "Erreur lors de la copie de 'pseudo' vers 'fname' : " . mysqli_error($conx) . "<br>";
        }

        // 6. Remplacer '/photos/photo.jpg' par 'man.png' dans le champ photo
        echo "Mise à jour des photos par défaut dans la table membres...<br>";
        $update_photo_sql = "UPDATE `membres` SET `photo` = 'man.png' WHERE `photo` = '/photos/photo.jpg'";
        if (mysqli_query($conx, $update_photo_sql)) {
            echo "Mise à jour des photos terminée. Lignes affectées : " . mysqli_affected_rows($conx) . "<br>";
        } else {
            echo "Erreur lors de la mise à jour des photos : " . mysqli_error($conx) . "<br>";
        }
    }
    echo "<hr>";
}

mysqli_close($conx);
echo "<strong>Migration terminée.</strong>";
?>
