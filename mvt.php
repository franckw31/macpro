<?php
// Inclusion of the database configuration
require_once 'config.php';

// Check if connection was successful before proceeding
if (!isset($db_connection) || $db_connection === false) {
    die("Cannot access the database connection. Check config.php.");
}

$conn = $db_connection;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Mouvements et Soldes</title>
<style>
/* ─── RESET & BASE ─── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#f2f2f7;
  --card:#ffffff;
  --border:rgba(0,0,0,0.1);
  --green:#34c759;
  --orange:#ff9f0a;
  --blue:#007aff;
  --cyan:#32ade6;
  --muted:#8e8e93;
  --text:#000000;
  --text2:#3a3a3c;
  --label:#8e8e93;
  --radius:16px;
  --radius-sm:12px;
  --danger:#ff3b30;
  --table-header:#e5e5ea;
  --table-border:#c7c7cc;
}
@media (prefers-color-scheme: dark) {
  :root{
    --bg:#0a0d14;
    --card:#111822;
    --border:rgba(255,255,255,0.06);
    --green:#30d158;
    --orange:#ff9f0a;
    --blue:#0a84ff;
    --cyan:#64d2ff;
    --muted:#6b7a8f;
    --text:#ffffff;
    --text2:#c8d6e5;
    --label:#8e9bae;
    --danger:#ff453a;
    --table-header:#1c2533;
    --table-border:#1c2533;
  }
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;}
.page{max-width:800px;margin:0 auto;padding:20px 20px 90px 20px;display:flex;flex-direction:column;gap:16px}

/* ─── CARD BASE ─── */
.v2-card{background:var(--card);border-radius:var(--radius);padding:18px;box-shadow:0 2px 10px rgba(0,0,0,0.02);border:1px solid var(--border);margin-bottom:16px;}
.v2-title{font-size:22px;font-weight:700;margin-bottom:16px;color:var(--text);text-align:center;}
.v2-subtitle{font-size:16px;font-weight:600;margin-bottom:12px;color:var(--blue);display:flex;align-items:center;gap:6px;}

/* ─── TABLE ─── */
.v2-table{width:100%;border-collapse:collapse;margin-bottom:8px;font-size:14px;}
.v2-table th, .v2-table td{padding:10px 12px;text-align:left;border-bottom:1px solid var(--table-border);}
.v2-table th{background:var(--table-header);color:var(--text);font-weight:600;}
.v2-table td{color:var(--text2);}
.v2-table tr:last-child td{border-bottom:none;}
.v2-table td.amount{text-align:right;font-weight:600;white-space:nowrap;}
.text-green{color:var(--green) !important;}
.text-red{color:var(--danger) !important;}

/* ─── SUMMARY ─── */
.v2-total-row th, .v2-total-row td{background:var(--table-header);font-weight:700;}
.v2-general-card{text-align:center;padding:24px;border:2px solid var(--border);}
.v2-general-title{font-size:18px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:8px;}
.v2-general-amount{font-size:36px;font-weight:800;}

/* RESPONSIVE */
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
@media (max-width:600px) {
  .page { padding: 10px; }
  .v2-card { padding: 14px; }
}
</style>
</head>
<body>
<div class="page">
<?php

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
    $sql = "SELECT SUM(CASE WHEN id_type_mvt IN (1, 2, 3) THEN -montant ELSE montant END) AS montant FROM portefeuille WHERE id_mvt_membre = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $membreId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $solde = $row['montant'];

        echo "<div class='v2-card v2-general-card'>";
        echo "<div class='v2-general-title'>Solde Utilisateur ID: " . htmlspecialchars((string)$membreId) . "</div>";

        if ($solde === null) {
            echo "<p style='color:var(--muted);'>Aucune transaction trouvée pour ce membre.</p>";
        } else {
            // Formatage monétaire
            $soldeColorClass = $solde >= 0 ? 'text-green' : 'text-red';
            echo "<div class='v2-general-amount " . $soldeColorClass . "'>" . number_format((float)$solde, 2, ',', ' ') . " €</div>";
        }
        echo "</div>";
        $stmt->close();
    } else {
        echo "<div class='v2-card text-red'>Erreur lors de la préparation de la requête de solde.</div>";
    }
}

/**
 * Affiche tous les mouvements détaillés avec un récapitulatif pour le solde de chaque membre.
 */
function afficherMouvementsEtSoldes(mysqli $conn): void {
    echo "<h2 class='v2-title'>Détail des mouvements et Soldes par Membre</h2>";

    $sql = "
        SELECT
            COALESCE(m.id_membre, m.`id-membre`) AS id_membre,
            m.pseudo,
            p.id_mvt,
            p.date_mvt,
            p.montant,
            p.id_type_mvt,
            t.label AS type_label
        FROM membres m
        INNER JOIN portefeuille p
            ON p.id_mvt_membre = COALESCE(m.id_membre, m.`id-membre`)
        LEFT JOIN type_mvt t
            ON p.id_type_mvt = t.id_type_mvt
        ORDER BY m.pseudo ASC, p.date_mvt DESC
    ";

    $result = $conn->query($sql);
    if ($result === false) {
        echo "<div class='v2-card text-red'>Erreur SQL (mouvements): " . htmlspecialchars($conn->error) . "</div>";
        return;
    }

    if ($result->num_rows === 0) {
        echo "<div class='v2-card' style='color:var(--muted);'>Aucune donnée de mouvement trouvée.</div>";
        return;
    }

    $currentMembre = null;
    $soldeTotal = 0.0;
    $soldeGeneral = 0.0;
    
    while ($row = $result->fetch_assoc()) {
        if ($currentMembre !== $row['id_membre']) {
            // Fermer le tableau du membre précédent s'il y en a un
            if ($currentMembre !== null) {
                $soldeColorClass = $soldeTotal >= 0 ? 'text-green' : 'text-red';
                echo "<tr class='v2-total-row'><td colspan='3' style='text-align:right;'>SOLDE TOTAL :</td>";
                echo "<td class='amount " . $soldeColorClass . "'>" . number_format($soldeTotal, 2, ',', ' ') . " €</td></tr>";
                echo "</tbody></table></div></div>";
            }
            
            // Initialiser un nouveau membre
            $currentMembre = $row['id_membre'];
            $soldeTotal = 0.0;
            
            echo "<div class='v2-card'>";
            echo "<h3 class='v2-subtitle'>👤 " . htmlspecialchars((string)$row['pseudo']) . " <span style='color:var(--muted);font-weight:400;font-size:12px;'>(ID: " . htmlspecialchars((string)$row['id_membre']) . ")</span></h3>";
            echo "<div class='table-responsive'><table class='v2-table'>";
            echo "<thead><tr><th>Date</th><th>N°</th><th>Type</th><th style='text-align:right;'>Montant</th></tr></thead><tbody>";
        }
        
        $montant = (float)$row['montant'];
        $typeMvt = (int)$row['id_type_mvt'];
        
        // Déduction selon id_type_mvt: 1, 2, 3 = débit (sortie), autres = crédit (entrée)
        if (in_array($typeMvt, [1, 2, 3])) {
            $soldeTotal -= $montant;
            $soldeGeneral -= $montant;
            $montantFormat = "<span class='text-red'>- " . number_format($montant, 2, ',', ' ') . "</span>";
        } else {
            $soldeTotal += $montant;
            $soldeGeneral += $montant;
            $montantFormat = "<span class='text-green'>+ " . number_format($montant, 2, ',', ' ') . "</span>";
        }
        
        $typeLabel = isset($row['type_label']) && $row['type_label'] !== null ? $row['type_label'] : "Inconnu (Type $typeMvt)";

        echo "<tr>";
        echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime((string)$row['date_mvt']))) . "</td>";
        echo "<td>" . htmlspecialchars((string)$row['id_mvt']) . "</td>";
        echo "<td>" . htmlspecialchars($typeLabel) . "</td>";
        echo "<td class='amount'>" . $montantFormat . "</td>";
        echo "</tr>";
    }

    // Fermer le dernier tableau
    if ($currentMembre !== null) {
        $soldeColorClass = $soldeTotal >= 0 ? 'text-green' : 'text-red';
        echo "<tr class='v2-total-row'><td colspan='3' style='text-align:right;'>SOLDE TOTAL :</td>";
        echo "<td class='amount " . $soldeColorClass . "'>" . number_format($soldeTotal, 2, ',', ' ') . " €</td></tr>";
        echo "</tbody></table></div></div>";
    }

    // Affichage du solde général
    $soldeGeneral *= -1;
    $soldeGeneralColorClass = $soldeGeneral >= 0 ? 'text-green' : 'text-red';
    echo "<div class='v2-card v2-general-card'>";
    echo "<div class='v2-general-title'>SOLDE GÉNÉRAL GLOBAL</div>";
    echo "<div class='v2-general-amount " . $soldeGeneralColorClass . "'>" . number_format($soldeGeneral, 2, ',', ' ') . " €</div>";
    echo "</div>";
}


// --- EXÉCUTION ---
// ATTENTION: Ce bloc nécessite une connexion de base de données fonctionnelle.
afficherMouvementsEtSoldes($conn);

if (isset($_GET['id_membre']) && ctype_digit((string)$_GET['id_membre'])) {
    $idMembre = (int)$_GET['id_membre'];
    afficherSoldeMembre($idMembre, $conn);
}

?>
</div>
</body>
</html>