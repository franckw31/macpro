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
 * Affiche un tableau des soldes de mouvements par membre.
 */
function afficherTableauSoldesMouvements(mysqli $conn): void {
    echo "<h2 style='color: #1d4ed8;'>Tableau des soldes de mouvements</h2>";

    $sql = "
        SELECT
            COALESCE(m.id_membre, m.`id-membre`) AS id_membre,
            m.pseudo,
            COALESCE(SUM(p.montant), 0) AS solde
        FROM membres m
        INNER JOIN portefeuille p
            ON p.id_mvt_membre = COALESCE(m.id_membre, m.`id-membre`)
        GROUP BY COALESCE(m.id_membre, m.`id-membre`), m.pseudo
        HAVING COALESCE(SUM(p.montant), 0) > 0
        ORDER BY solde DESC, m.pseudo ASC
    ";

    $result = $conn->query($sql);
    if ($result === false) {
        echo "<p style='color: red;'>Erreur SQL (tableau soldes): " . htmlspecialchars($conn->error) . "</p>";
        return;
    }

    if ($result->num_rows === 0) {
        echo "<p style='color: gray;'>Aucune donnée de mouvement trouvée.</p>";
        return;
    }

    echo "<table border='1' cellpadding='6' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
    echo "<thead><tr style='background:#f3f4f6;'><th>ID</th><th>Pseudo</th><th>Solde mouvements (€)</th></tr></thead><tbody>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars((string)$row['id_membre']) . "</td>";
        echo "<td>" . htmlspecialchars((string)$row['pseudo']) . "</td>";
        echo "<td style='text-align:right; font-weight:600;'>" . number_format((float)$row['solde'], 2, ',', ' ') . "</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";
}


// --- EXÉCUTION ---
// ATTENTION: Ce bloc nécessite une connexion de base de données fonctionnelle.
echo "<div style='font-family: Arial, sans-serif; padding: 16px;'>";
afficherTableauSoldesMouvements($conn);

if (isset($_GET['id_membre']) && ctype_digit((string)$_GET['id_membre'])) {
    $idMembre = (int)$_GET['id_membre'];
    afficherSoldeMembre($idMembre, $conn);
}

echo "</div>";
?>