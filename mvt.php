<?php
// Inclusion of the database configuration
require_once 'config.php';

// Check if connection was successful before proceeding
if (!isset($db_connection) || $db_connection === false) {
    die("Cannot access the database connection. Check config.php.");
}

$conn = $db_connection;

/**
 * Récupère et affiche les informations générales des membres.
 * @param mysqli $conn La connexion mysqli.
 */
function afficherMembres(mysqli $conn): void {
    echo "<h2 style='color: blue;'>Liste des Membres</h2>";
    // Selon le dump SQL: `membres` contient `id_membre` et `id-membre`
    $sql = "SELECT COALESCE(id_membre, `id-membre`) AS id_membre, pseudo, email FROM membres LIMIT 10";
    $result = $conn->query($sql);

    if ($result === false) {
        echo "<p style='color: red;'>Erreur lors de la récupération des membres : " . mysqli_error($conn) . "</p>";
        return;
    }

    echo "<table border='1' cellpadding='5' cellspacing='0' style='width: 60%;'>\n";
    echo "<thead><tr><th>ID Membre</th><th>Nom</th><th>Email</th><th>...</th></tr></thead><tbody>\n";
    
    while ($membre = $result->fetch_assoc()) {
        // ATTENTION : Assurez-vous que les clés correspondent aux colonnes de votre DB
        echo "<tr>";
        echo "<td>" . htmlspecialchars($membre['id_membre']) . "</td>";
        echo "<td>" . htmlspecialchars($membre['pseudo']) . "</td>";
        echo "<td>" . htmlspecialchars($membre['email']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

/**
 * Affiche le solde cumulé d'un membre spécifique.
 */
function afficherSoldeMembre(int $membreId, mysqli $conn): void {
    echo "<h3>Solde de l'utilisateur ID: " . htmlspecialchars($membreId) . "</h3>";
    
    // Selon le dump SQL: la clé membre dans `portefeuille` est `id_mvt_membre`
    $sql = "SELECT SUM(montant) AS montant FROM portefeuille WHERE id_mvt_membre = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $membreId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $solde = $row['montant'];

        if ($solde === null) {
            echo "<p style='color: gray;'>Aucune transaction trouvée pour ce membre.</p>";
        } else {
            // Formatage monétaire
            echo "<p style='font-size: 1.5em; font-weight: bold;'>Solde Total: " . number_format($solde, 2, ',') . " €</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>Erreur lors de la préparation de la requête de solde.</p>";
    }
}

/**
 * Affiche tous les mouvements détaillés avec un récapitulatif pour le solde de chaque membre.
 */
function afficherMouvementsEtSoldes(mysqli $conn): void {
    echo "<h2 style='color: #1d4ed8;'>Détail des mouvements et Soldes par Membre</h2>";

    $sql = "
        SELECT
            COALESCE(m.id_membre, m.`id-membre`) AS id_membre,
            m.pseudo,
            p.id_mvt,
            p.date_mvt,
            p.montant
        FROM membres m
        INNER JOIN portefeuille p
            ON p.id_mvt_membre = COALESCE(m.id_membre, m.`id-membre`)
        ORDER BY m.pseudo ASC, p.date_mvt DESC
    ";

    $result = $conn->query($sql);
    if ($result === false) {
        echo "<p style='color: red;'>Erreur SQL (mouvements): " . htmlspecialchars($conn->error) . "</p>";
        return;
    }

    if ($result->num_rows === 0) {
        echo "<p style='color: gray;'>Aucune donnée de mouvement trouvée.</p>";
        return;
    }

    $currentMembre = null;
    $soldeTotal = 0.0;
    
    while ($row = $result->fetch_assoc()) {
        if ($currentMembre !== $row['id_membre']) {
            // Fermer le tableau du membre précédent s'il y en a un
            if ($currentMembre !== null) {
                echo "<tr><td colspan='2' style='text-align:right; font-weight:bold; background:#e5e7eb;'>SOLDE TOTAL :</td>";
                echo "<td style='text-align:right; font-weight:bold; background:#e5e7eb;'>" . number_format($soldeTotal, 2, ',', ' ') . " €</td></tr>";
                echo "</tbody></table><br/>";
            }
            
            // Initialiser un nouveau membre
            $currentMembre = $row['id_membre'];
            $soldeTotal = 0.0;
            
            echo "<h3 style='margin-bottom: 8px; color: #374151;'>👤 " . htmlspecialchars((string)$row['pseudo']) . " (ID: " . htmlspecialchars((string)$row['id_membre']) . ")</h3>";
            echo "<table border='1' cellpadding='6' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
            echo "<thead><tr style='background:#f3f4f6;'><th>Date du mouvement</th><th>N° Transaction</th><th>Montant (€)</th></tr></thead><tbody>";
        }
        
        $montant = (float)$row['montant'];
        $soldeTotal += $montant;
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars((string)$row['date_mvt']) . "</td>";
        echo "<td>" . htmlspecialchars((string)$row['id_mvt']) . "</td>";
        echo "<td style='text-align:right;;'>" . number_format($montant, 2, ',', ' ') . "</td>";
        echo "</tr>";
    }

    // Fermer le dernier tableau
    if ($currentMembre !== null) {
        echo "<tr><td colspan='2' style='text-align:right; font-weight:bold; background:#e5e7eb;'>SOLDE TOTAL :</td>";
        echo "<td style='text-align:right; font-weight:bold; background:#e5e7eb;'>" . number_format($soldeTotal, 2, ',', ' ') . " €</td></tr>";
        echo "</tbody></table>";
    }
}


// --- EXÉCUTION ---
// ATTENTION: Ce bloc nécessite une connexion de base de données fonctionnelle.
echo "<div style='font-family: Arial, sans-serif; padding: 16px;'>";
afficherMouvementsEtSoldes($conn);

if (isset($_GET['id_membre']) && ctype_digit((string)$_GET['id_membre'])) {
    $idMembre = (int)$_GET['id_membre'];
    afficherSoldeMembre($idMembre, $conn);
}

echo "</div>";
?>