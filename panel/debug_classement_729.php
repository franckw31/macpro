<?php
// Fichier de debug temporaire — à supprimer après usage
include __DIR__ . '/include/config.php';
header('Content-Type: text/plain; charset=utf-8');

$id = isset($_GET['act']) ? intval($_GET['act']) : 729;

echo "=== PARTICIPATION brute (activité $id) ===\n\n";

// 1. Données brutes participation
$stmt = mysqli_query($con, "
  SELECT p.`id-participation` AS id_part, m.pseudo, p.`option`, 
         p.classement, p.ds
  FROM participation p
  LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
  WHERE p.`id-activite` = $id
  ORDER BY p.classement ASC, p.`id-participation` ASC
");
$total = 0;
while ($r = mysqli_fetch_assoc($stmt)) {
    $total++;
    printf("id_part=%-6d  pseudo=%-18s  option=%-15s  classement=%d  ds=%s\n",
        $r['id_part'], $r['pseudo'], $r['option'], $r['classement'], $r['ds']);
}
echo "\nTotal participation: $total\n\n";

// 2. Données eliminations
echo "=== ELIMINATIONS (activité $id) ===\n\n";
$stmt2 = mysqli_query($con, "
  SELECT e.id, e.id_participation, e.nom_membre_victime AS victime,
         e.is_definitive, e.created_at,
         (SELECT p.classement FROM participation p WHERE p.`id-participation` = e.id_participation LIMIT 1) AS classement_part
  FROM eliminations e
  WHERE e.id_activite = $id
  ORDER BY e.is_definitive DESC, e.created_at ASC
");
$elims = 0;
while ($r = mysqli_fetch_assoc($stmt2)) {
    $elims++;
    printf("elim_id=%-5d  id_part=%-6d  victime=%-18s  definitif=%d  classement_part=%d  at=%s\n",
        $r['id'], $r['id_participation'], $r['victime'], $r['is_definitive'],
        $r['classement_part'] ?? 0, $r['created_at']);
}
echo "\nTotal eliminations: $elims\n\n";

// 3. Vérification cohérence : joueurs avec elim_definitive mais classement=0
echo "=== INCOHÉRENCES (elim définitive SANS classement) ===\n\n";
$stmt3 = mysqli_query($con, "
  SELECT p.`id-participation` AS id_part, m.pseudo, p.classement,
         COUNT(e.id) AS nb_elim_def
  FROM participation p
  LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
  LEFT JOIN eliminations e ON e.id_participation = p.`id-participation` AND e.is_definitive = 1
  WHERE p.`id-activite` = $id
  GROUP BY p.`id-participation`, m.pseudo, p.classement
  HAVING nb_elim_def > 0 AND (p.classement = 0 OR p.classement IS NULL)
");
$inc = 0;
while ($r = mysqli_fetch_assoc($stmt3)) {
    $inc++;
    printf("id_part=%-6d  pseudo=%-18s  classement=%d  nb_elim_def=%d  ← MANQUANT\n",
        $r['id_part'], $r['pseudo'], $r['classement'], $r['nb_elim_def']);
}
if ($inc === 0) echo "(aucune incohérence)\n";

// 4. Recalcul du classement attendu selon l'ordre chronologique des éliminations
echo "\n=== RECALCUL classement attendu (chrono des elims définitives) ===\n\n";
$total_joueurs_stmt = mysqli_query($con, "SELECT COUNT(*) AS c FROM participation WHERE `id-activite` = $id");
$total_joueurs = mysqli_fetch_assoc($total_joueurs_stmt)['c'];

$stmt4 = mysqli_query($con, "
  SELECT e.id_participation, e.nom_membre_victime AS victime, e.created_at,
         p.classement AS classement_actuel
  FROM eliminations e
  LEFT JOIN participation p ON p.`id-participation` = e.id_participation
  WHERE e.id_activite = $id AND e.is_definitive = 1
  ORDER BY e.created_at ASC
");
$eliminated_so_far = 0;
echo sprintf("  Total joueurs: %d\n\n", $total_joueurs);
$rows4 = [];
while ($r = mysqli_fetch_assoc($stmt4)) $rows4[] = $r;

$n = count($rows4);
foreach ($rows4 as $i => $r) {
    $classement_attendu = $total_joueurs - $i;   // 1er éliminé → dernier rang
    printf("  #%-2d  id_part=%-6d  victime=%-18s  classement_actuel=%-4d  attendu=%-4d%s  at=%s\n",
        $i+1, $r['id_participation'], $r['victime'], $r['classement_actuel'],
        $classement_attendu,
        ($r['classement_actuel'] == $classement_attendu ? '' : '  ← DIFF'),
        $r['created_at']);
}
// Vainqueur attendu = rang 1
echo "\n  Vainqueur attendu (rang 1) = joueur NON présent dans les elims définitives\n";
$stmt5 = mysqli_query($con, "
  SELECT m.pseudo, p.`id-participation`, p.classement
  FROM participation p
  LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
  LEFT JOIN eliminations e ON e.id_participation = p.`id-participation` AND e.is_definitive = 1
  WHERE p.`id-activite` = $id AND e.id IS NULL
");
while ($r = mysqli_fetch_assoc($stmt5)) {
    printf("  → pseudo=%-18s  id_part=%d  classement_actuel=%d\n",
        $r['pseudo'], $r['id-participation'], $r['classement']);
}
?>
