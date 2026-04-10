<?php
include_once ('include/config.php');
echo ":..................:";
$id_activite = intval($_GET['ac']);
echo $id_activite."---------------" ;
$ret = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = $id_activite ");
while ($row = mysqli_fetch_array($ret)) {
  $nb_tables = $row["nb-tables"];
  $pointeur = $row["id-activite"];
  echo $pointeur;
  $pointeur_position = 0;
  $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = '$pointeur' AND `option` IN ('Inscrit','Option','Reservation') ORDER BY RAND()");
  while ($row2 = mysqli_fetch_array($ret2)) {
    $id = $row2['id-participation'];
    echo $row2['id-participation'];
    $pointeur_position = $pointeur_position + 1;
    $modif = mysqli_query($con, "UPDATE `participation` SET `position` = '$pointeur_position' WHERE `id-participation` = '$id'");
  }
  ;
}
;

// libere les sieges";
$sql2 = mysqli_query($con, "SELECT * FROM `activite` ");
while ($res2 = mysqli_fetch_array($sql2)) {
  $activi = $res2['id-activite'];
  echo "+" . $activi . "+";
  $ret = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$activi' ");
  echo "--" . $activi . "--";
  while ($row = mysqli_fetch_array($ret)) {
    $modif = mysqli_query($con, "UPDATE `participation` SET `id-table` = '0' , `id-siege` = '0'  WHERE `id-activite` = '$activi'");
    $nb_tables = (int)$row["nb-tables"];
    if ($nb_tables <= 0) {
      // nothing to arrange for this activity
      continue;
    }
    echo "/--" . $activi . "--/";
  }
  ;
  echo "*" . $nb_tables . "*";
  // $nb_tables=2;
  $latable = "";
  $pointeur = $res2["id-activite"];
  $sql3 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = '$pointeur' AND `option` NOT IN ('Annule','Elimine') ORDER BY `position` ASC");
  $nb = mysqli_num_rows($sql3);
  $t1max = ($nb_tables > 0) ? ceil($nb / $nb_tables) : 0;
  $lesiegetable1 = "";

  $nb = $nb - $t1max;
  echo $nb;
  if ($nb > 0 && ($nb_tables - 1) > 0) {
    $t2max = ceil($nb / ($nb_tables - 1));
  } else {
    $t2max = 0;
  }
  $lesiegetable2 = "";

  $nb = $nb - $t2max;
  if ($nb > 0 && ($nb_tables - 2) > 0) {
    $t3max = ceil($nb / ($nb_tables - 2));
  } else {
    $t3max = 0;
  }
  $lesiegetable3 = "";

  $nb = $nb - $t3max;
  echo $nb;
  if ($nb > 0 && ($nb_tables - 3) > 0) {
    $t4max = ceil($nb / ($nb_tables - 3));
  } else {
    $t4max = 0;
  }
  $lesiegetable4 = "";

  echo "----------------" . $t1max . '+' . $t2max . '+' . $t3max . '+' . $t4max . '+';
  while ($res3 = mysqli_fetch_array($sql3)) {
    $id = $res3["id-participation"];
    if ($res3['position'] <= $t1max) {
      $latable = "1";
      $lesiegetable2 = "";
      $lesiegetable1 = (int)$res3['position'];
      $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable1 WHERE `id-participation` = $id ");
      $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
      echo "fini82";
    } else {
      if ($res3['position'] <= ((int) $t2max + (int) $t1max)) {
        $latable = "2";
        $lesiegetable1 = "";
        $lesiegetable2 = (int)($res3['position'] - (int)$t1max);
        $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable2 WHERE `id-participation` = $id ");
        $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
        echo "fini89";
      } else {
        if ($res3['position'] <= ((int) $t3max + (int) $t2max) + (int) $t1max) {
          $latable = "3";
          $lesiegetable1 = "";
          $lesiegetable2 = "";
          $lesiegetable3 = (int)($res3['position'] - (int)$t2max - (int)$t1max);
          $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable3 WHERE `id-participation` = $id ");
          $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
          echo "fini 97";
        } else {
          if ($res3['position'] <= ((int) $t4max + (int) $t3max + (int) $t2max + (int) $t1max)) {
            $latable = "4";
            $lesiegetable1 = "";
            $lesiegetable2 = "";
            $lesiegetable3 = "";
            $lesiegetable4 = (int)($res3['position'] - (int)$t3max - (int)$t2max - (int)$t1max);
            $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable4 WHERE `id-participation` = $id ");
            $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
            echo "fin116";
          } else {
          }
          echo "fin109";
        }
        ;
      }
      ;
    }
    ;
    echo "fin123";
    // echo $res3["id-activite"]."-".$res3["id-membre"]."-".$res3["position"]."/"."{table=".$latable."-siege=".$lesiegetable1.$lesiegetable2."}";
  }
  ;
  echo ":..................x:";
}
;
echo "fin128";
echo $id_activite."---------------" ;
?>
<!-- INSERT INTO `activite` (SELECT NULL, `id-structure-buyin`, `id-membre`, `titre-activite`, `date_depart`, `heure_depart`, `ville`, `rue`, `lng`, `lat`, `icon`, `ico-siz`, `photo`, `lien`, `lien-id`, `lien-texte`, `lien-texte-fin`, `places`, `reserves`, `options`, `libre`, `commentaire`, `buyin`, `rake`, `bounty`, `jetons`, `recave`, `addon`, `ante`, `bonus`, `nb-tables`, `taille-table1`, `id-table1`, `taille-table2`, `id-table2`, `taille-table3`, `id-table3`, `taille-table4`, `id-table4`, `taille-table5`, `id-table5`, `taille-table6`, `id-table6` FROM `activite` WHERE `id-activite` = 30) -->
<script type="text/javascript">
  window.location.replace("voir-activite.php?uid=<?php echo $id_activite; ?>");
</script>