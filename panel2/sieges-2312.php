<?php
include_once ('include/config.php');
echo ":..................:";
$id_activite = intval($_GET['ac']);
echo $id_activite;
$ret = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = $id_activite ");
while ($row = mysqli_fetch_array($ret)) {
  $nb_tables = $row["nb-tables"];
  $pointeur = $row["id-activite"];
  echo $pointeur;
  $pointeur_position = 0;
  $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Option')
                 OR (`id-activite` = '$pointeur' AND `option` LIKE 'Reservation') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') ORDER BY RAND()");
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
    $nb_tables = $row["nb-tables"];
    echo "/--" . $activi . "--/";
  }
  ;
  echo "*" . $nb_tables . "*";
  // $nb_tables=2;
  $latable = "";
  $pointeur = $res2["id-activite"];
  $sql3 = mysqli_query($con, "SELECT * FROM `participation` WHERE ( (`id-activite` = '$pointeur' AND `option` NOT LIKE  'Annule') AND (`id-activite` = '$pointeur' AND `option` NOT LIKE  'Elimine') ) ");
  $nb = mysqli_num_rows($sql3);
  $t1max = $nb / $nb_tables;
  $t1max = ceil($t1max);
  $lesiegetable1 = "";

  $nb = $nb - $t1max;
  echo $nb;
  if ($nb > 0) {
    $t2max = ($nb / (($nb_tables) - 1));
    $t2max = (ceil($t2max));
  } else
    $t2max = 0;
  $lesiegetable2 = "";

  $nb = $nb - $t2max;
  if ($nb > 0) {
    $t3max = ($nb / (($nb_tables) - 2));
    $t3max = (ceil($t3max));
  } else
    $t3max = 0;
  $lesiegetable3 = "";

  $nb = $nb - $t3max;
  echo $nb;
  if ($nb > 0) {
    $t4max = ($nb / (($nb_tables) - 3));
    $t4max = (ceil($t4max));
  } else
    $t4max = 0;
  $lesiegetable4 = "";

  echo "----------------" . $t1max . '+' . $t2max . '+' . $t3max . '+' . $t4max . '+';
  while ($res3 = mysqli_fetch_array($sql3)) {
    $id = $res3["id-participation"];
    if ($res3['position'] <= $t1max) {
      $latable = "1";
      (int) $lesiegetable2 = "";
      (int) $lesiegetable1 = $res3['position'];
      $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable1 WHERE `id-participation` = $id ");
      $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
      echo "fini82";
    } else {
      if ($res3['position'] <= ((int) $t2max + (int) $t1max)) {
        $latable = "2";
        (int) $lesiegetable1 = "";
        (int) $lesiegetable2 = ($res3['position'] - (int) $t1max);
        $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable2 WHERE `id-participation` = $id ");
        $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
        echo "fini89";
      } else {
        if ($res3['position'] <= ((int) $t3max + (int) $t2max) + (int) $t1max) {
          $latable = "3";
          (int) $lesiegetable1 = "";
          (int) $lesiegetable2 = "";
          (int) $lesiegetable3 = ($res3['position'] - (int) $t2max - (int) $t1max);
          $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable3 WHERE `id-participation` = $id ");
          $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
          echo "fini 97";
        } else {
          if ($res3['position'] <= ((int) $t4max + (int) $t3max + (int) $t2max + (int) $t1max)) {
            $latable = "4";
            (int) $lesiegetable1 = "";
            (int) $lesiegetable2 = "";
            (int) $lesiegetable3 = "";
            (int) $lesiegetable4 = ($res3['position'] - (int) $t3max - (int) $t2max - (int) $t1max);
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
  echo ":..................:";
}
;
echo "fin128";
// read optional source return path
$source = isset($_GET['sou']) && $_GET['sou'] !== '' ? $_GET['sou'] : '/panel/voir-activite.php';
// build redirect url safely
$redirect = $source;
if (strpos($redirect, '?') === false) {
    $redirect .= '?uid=' . urlencode($id_activite);
} else {
    $redirect .= '&uid=' . urlencode($id_activite);
}
// perform redirect (JS since output already sent)
echo '<script type="text/javascript">window.location.replace("' . htmlspecialchars($redirect, ENT_QUOTES) . '");</script>';
exit;