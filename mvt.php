<?php
require_once 'config.php';

// Check if connection was successful before proceeding
if (!isset($db_connection) || $db_connection === false) {
    die("Cannot access the database connection. Check config.php.\n");
}

$conn = $db_connection;

/**
 * Récupère et affiche les informations générales des membres.
 * @param mysqli $conn La connexion mysqli.
 */
function afficherMembres(mysqli $conn): void {
    echo "<h2>Liste des Membres</h2>";
    // IMPORTANT: Ajustez cette requête en fonction de la structure de votre table 'membres'
    // Exemple de requête (ajustez les noms de colonnes : id_membre, nom, email)
    $sql = "SELECT id_membre, nom, email FROM membres LIMIT 650";
    $result = $conn->query($sql);

    if ($result === false) {
        echo "<p>Erreur lors de la récupération des membres : " . mysqli_error($conn) . "</p>";
        return;
    }

    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<thead><tr><th>ID Membres</th><th>Nom</th><th>Email</th><th>...</th></tr></thead><tbody>";

    while ($membre = $result->fetch_assoc()) {
        // ATTENTION : Assurez-vous que les clés correspondent aux colonnes réelles.
        echo "<tr>";
        echo "<td>" . htmlspecialchars($membre['id_membre'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($membre['pseudo'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($membre['email'] ?? 'N/A') . "</td>";
        echo "<td>...</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
}

/**
 * Récupère et affiche le solde de portefeuille pour un membre donné.
 * @param mysqli $conn La connexion mysqli.
 * @param int $memberId L'ID du membre à vérifier.
 */
function afficherPortefeuille(mysqli $conn, int $memberId): void {
    echo "<h2>Portefeuille du Membre ID: " . htmlspecialchars($memberId) . "</h2>";

    // !!! IMPORTANT: ADAPTEZ CETTE QUERY !!!
    // Ceci suppose que 'portefuille' a une colonne 'membre_id' et 'solde_total'.
    $sql = "SELECT * FROM portefeuille WHERE id_mvt_membre = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $portefeuille = $result->fetch_assoc();

    if (!$portefeuille) {
        echo "<p>Aucun enregistrement de portefeuille trouvé pour ce membre.</p>";
        return;
    }

    // ATTENTION : Ajustez les clés ($portefeuille['colonne']) ci-dessous !
    echo "<p>Solde actuel : <strong >" . htmlspecialchars($portefeuille['montant'] ?? '0.00') . "</strong></p>";
    
}

// ======================================================================
// LOGIQUE PRINCIPALE D'EXÉCUTION
// ========================================================

// 1. Afficher tous les membres
afficherMembres($conn);

echo "<hr>";

// 2. Exemple d'utilisation : Afficher le portefeuille d'un membre spécifique (remplacez 1 par un ID réel)
$testMemberId = 2;
afficherPortefeuille($conn, $testMemberId);

?>

