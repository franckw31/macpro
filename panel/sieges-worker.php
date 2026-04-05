<?php
// Background worker for assigning sieges (run by CLI)
// Usage: php sieges-worker.php <activityId>

chdir(__DIR__);
require_once __DIR__ . '/include/config.php';
// expose function to perform the assignment (callable from both web and CLI)
function assign_sieges($activity) {
    global $con; // use the global mysqli connection from include/config.php
    $result = ['processed' => 0, 'positions' => 0, 'activity' => intval($activity)];
    if (!$activity) return $result;
    file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | ASSIGN_START act=" . $activity . "\n", FILE_APPEND);

    $processed = 0;
    $positions_updated = 0;

    $ret = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = " . intval($activity));
    while ($row = mysqli_fetch_array($ret)) {
        $nb_tables = max(1, intval($row['nb-tables']));
        $pointeur = $row['id-activite'];
        $pointeur_position = 0;
        $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Option') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Reservation') ORDER BY RAND()");
        while ($row2 = mysqli_fetch_array($ret2)) {
            $id = $row2['id-participation'];
            $pointeur_position++;
            $modif = mysqli_query($con, "UPDATE `participation` SET `position` = '$pointeur_position' WHERE `id-participation` = '$id'");
            if ($modif) $positions_updated++; else file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | POS_UPDATE_ERR id=$id err=" . mysqli_error($con) . "\n", FILE_APPEND);
        }
        // now assign tables
        $sql3 = mysqli_query($con, "SELECT * FROM `participation` WHERE ( (`id-activite` = '$pointeur' AND `option` NOT LIKE  'Annule') AND (`id-activite` = '$pointeur' AND `option` NOT LIKE  'Elimine') ) ");
        $nb = mysqli_num_rows($sql3);
        if ($nb <= 0) continue;
        $t1max = ceil($nb / $nb_tables);
        $nb_rem = $nb - $t1max;
        $t2max = $nb_rem > 0 ? ceil($nb_rem / max(1, ($nb_tables - 1))) : 0;
        $nb_rem = $nb_rem - $t2max;
        $t3max = $nb_rem > 0 ? ceil($nb_rem / max(1, ($nb_tables - 2))) : 0;
        $nb_rem = $nb_rem - $t3max;
        $t4max = $nb_rem > 0 ? ceil($nb_rem / max(1, ($nb_tables - 3))) : 0;

        // iterate and assign
        $pos = 0;
        mysqli_data_seek($sql3, 0);
        // total for progress tracking
        $total_for_table = $nb;
        while ($res3 = mysqli_fetch_array($sql3)) {
            $pos++;
            $idp = $res3['id-participation'];
            $table = 0; $siege = 0;
            if ($pos <= $t1max) { $table = 1; $siege = $pos; }
            else if ($pos <= $t1max + $t2max) { $table = 2; $siege = $pos - $t1max; }
            else if ($pos <= $t1max + $t2max + $t3max) { $table = 3; $siege = $pos - $t1max - $t2max; }
            else if ($pos <= $t1max + $t2max + $t3max + $t4max) { $table = 4; $siege = $pos - $t1max - $t2max - $t3max; }
            $u1 = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $siege WHERE `id-participation` = $idp");
            $u2 = mysqli_query($con, "UPDATE `participation` SET `id-table` = $table WHERE `id-participation` = $idp");
            if ($u1 && $u2) {
                $processed++; 
                // write progress after each processed row
                $percent = $total_for_table ? round(100 * $processed / $total_for_table) : 100;
                $progressData = ['activity'=>$activity,'processed'=>intval($processed),'positions'=>intval($positions_updated),'percent'=>intval($percent),'last_id'=>$idp,'status'=>'running'];
                @file_put_contents(__DIR__ . '/tmp/sieges-' . $activity . '.progress', json_encode($progressData));
            } else file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | ASSIGN_ERR id=$idp err1=" . mysqli_error($con) . "\n", FILE_APPEND);
        }
    }

    
        // write progress final
        $progressData = ['activity'=>$activity,'processed'=>intval($processed),'positions'=>intval($positions_updated),'percent'=>100,'status'=>'done'];
        @file_put_contents(__DIR__ . '/tmp/sieges-' . $activity . '.progress', json_encode($progressData) );

    file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | ASSIGN_DONE act=" . $activity . " processed=" . intval($processed) . " positions=" . intval($positions_updated) . "\n", FILE_APPEND);

    // remove running marker and create a done marker summarizing this run
    @unlink(__DIR__ . '/tmp/sieges-' . $activity . '.running');
    @file_put_contents(__DIR__ . '/tmp/sieges-' . $activity . '.done', date('c') . ' | DONE processed=' . intval($processed) . ' positions=' . intval($positions_updated) . "\n", FILE_APPEND);

    $result['processed'] = intval($processed);
    $result['positions'] = intval($positions_updated);
    return $result;
}

// If called via CLI, run assign_sieges
if (php_sapi_name() === 'cli') {
    $arg = isset($argv[1]) ? intval($argv[1]) : 0;
    if (!$arg) {
        file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | WORKER_NO_ACT\n", FILE_APPEND);
        exit(0);
    }
    $activity = $arg;
    file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | WORKER_START act=" . $activity . "\n", FILE_APPEND);
    // create running marker
    $pid = function_exists('getmypid') ? getmypid() : 0;
    @file_put_contents(__DIR__ . '/tmp/sieges-' . $activity . '.running', date('c') . ' | START pid=' . intval($pid) . "\n", FILE_APPEND);
    @file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | WORKER_ALIVE act=" . $activity . " pid=" . intval($pid) . "\n", FILE_APPEND);

    $res = assign_sieges($activity);

    file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . " | WORKER_DONE act=" . $activity . " processed=" . intval($res['processed']) . " positions=" . intval($res['positions']) . "\n", FILE_APPEND);

    // remove running marker and write done marker (assign_sieges already does this but be safe)
    @unlink(__DIR__ . '/tmp/sieges-' . $activity . '.running');
    @file_put_contents(__DIR__ . '/tmp/sieges-' . $activity . '.done', date('c') . ' | DONE processed=' . intval($res['processed']) . ' positions=' . intval($res['positions']) . "\n", FILE_APPEND);

    exit(0);
}
?>      