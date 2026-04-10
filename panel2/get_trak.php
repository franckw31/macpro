<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

include('include/config.php');

$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Connexion BDD échouée']);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

// Créer la table trak si elle n'existe pas encore
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `trak` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `id_auteur`   INT NOT NULL COMMENT 'id-membre du joueur qui écrit la note',
    `id_cible`    INT NOT NULL COMMENT 'id-membre du participant noté',
    `id_activite` INT NOT NULL DEFAULT 0 COMMENT 'Activité concernée (0 = global)',
    `note`        TEXT NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cible_activite (`id_cible`, `id_activite`),
    INDEX idx_auteur (`id_auteur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$id_cible    = isset($_GET['id_cible'])    ? (int)$_GET['id_cible']    : 0;
$id_activite = isset($_GET['id_activite']) ? (int)$_GET['id_activite'] : 0;

if (!$id_cible) {
    echo json_encode(['success' => false, 'error' => 'id_cible manquant']);
    exit;
}

$where_activite = $id_activite > 0 ? "AND t.id_activite = $id_activite" : "";

$sql = "SELECT
            t.id,
            t.note,
            t.created_at,
            COALESCE(m.pseudo, 'Inconnu') AS auteur_pseudo
        FROM trak t
        LEFT JOIN membres m ON t.id_auteur = m.`id-membre`
        WHERE t.id_cible = $id_cible $where_activite
        ORDER BY t.created_at DESC";

$result = mysqli_query($conn, $sql);
$notes  = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notes[] = $row;
    }
}

echo json_encode(['success' => true, 'notes' => $notes]);

$conn->close();
