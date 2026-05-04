<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    $_SESSION['redirect'] = 'panel/tickets_tombolas.php';
    header('Location: logout.php');
    exit;
}

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function strip_parenthesized($text){
    $text = (string) $text;
    $text = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $text);
    return trim(preg_replace('/\s{2,}/u', ' ', $text));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : $uid;
$hasMonthFilter = isset($_GET['month_tom']);
$hasYearFilter  = isset($_GET['year_tom']);

// default values (possibly overridden below with the latest ticket month/year)
$selected_month_tom = $hasMonthFilter ? intval($_GET['month_tom']) : intval(date('m'));
$selected_year_tom  = $hasYearFilter ? intval($_GET['year_tom']) : intval(date('Y'));

if ($selected_month_tom < 1 || $selected_month_tom > 12) $selected_month_tom = intval(date('m'));
$current_year_tom = intval(date('Y'));
if ($selected_year_tom < 2023 || $selected_year_tom > $current_year_tom) $selected_year_tom = $current_year_tom;

// If no filter is explicitly provided, show the most recent month/year where the member has tickets
if ($id > 0 && !empty($con) && (!$hasMonthFilter || !$hasYearFilter)) {
    $latestSql = "SELECT `date`
                  FROM `collections-individu`
                  WHERE `id-indiv` = ? AND `date` IS NOT NULL
                  ORDER BY `date` DESC
                  LIMIT 1";
    if ($latestStmt = @mysqli_prepare($con, $latestSql)) {
        mysqli_stmt_bind_param($latestStmt, 'i', $id);
        mysqli_stmt_execute($latestStmt);
        $latestRes = mysqli_stmt_get_result($latestStmt);
        if ($latestRes && ($latestRow = mysqli_fetch_assoc($latestRes)) && !empty($latestRow['date'])) {
            $ts = strtotime($latestRow['date']);
            if ($ts !== false) {
                $selected_month_tom = intval(date('m', $ts));
                $selected_year_tom  = intval(date('Y', $ts));
            }
        }
        mysqli_stmt_close($latestStmt);
    }
}

$rows = [];
if ($id > 0 && !empty($con)) {
    $q = "SELECT ci.*, c.nom AS collection_nom, c.valeur AS collection_valeur
          FROM `collections-individu` ci
          LEFT JOIN `collections` c ON ci.`id_col` = c.`id_collection`
          WHERE ci.`id-indiv` = ?
            AND MONTH(ci.`date`) = ?
            AND YEAR(ci.`date`) = ?
          ORDER BY ci.`date` DESC";
    if ($stmt = @mysqli_prepare($con, $q)) {
        mysqli_stmt_bind_param($stmt, 'iii', $id, $selected_month_tom, $selected_year_tom);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) { $rows[] = $r; }
        }
        mysqli_stmt_close($stmt);
    }
}

// get member pseudo for breadcrumb/title if available
$member_pseudo = '';
if ($id > 0) {
    $mr = @mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = " . intval($id) . " LIMIT 1");
    if ($mr && ($mrow = mysqli_fetch_assoc($mr))) $member_pseudo = $mrow['pseudo'];
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tickets Tombolas</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
        body{background:rgba(0,0,0,0.85);font-family:system-ui, -apple-system, 'Segoe UI', Roboto, Arial;margin:0;color:#e6eef8}
        .sheet{max-width:980px;margin:10px auto;border-radius:12px;overflow:hidden;background:#0b1220;color:#e6eef8;padding-bottom:18px}
        .header{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid rgba(255,255,255,0.04);background:linear-gradient(180deg, rgba(255,255,255,0.01), rgba(255,255,255,0.005))}
        .back{height:36px;border-radius:8px;background:transparent;border:1px solid rgba(255,157,59,0.12);display:flex;align-items:center;justify-content:center;padding:6px 10px;font-size:14px;cursor:pointer;color:#ff9d3b;font-weight:700}
        .title{font-weight:800;font-size:15px;text-align:center;flex:1;color:#16a34a}
        .card{padding:12px;overflow:auto}
        /* Color the 'QRcode' column (1st column) yellow */
        table.dataTable tbody td:nth-child(1), table.dataTable thead th:nth-child(1) { color: #ffd100; font-weight:700; }
        /* Color the 'Titre Activité' column (3rd column) blue */
        table.dataTable tbody td:nth-child(3), table.dataTable thead th:nth-child(3) { color: #3CA6FF; font-weight:700; }
        /* Ensure last column (Réduction Rake) remains visible but compact */
        table.dataTable tbody td:last-child, table.dataTable thead th:last-child {
            min-width:56px;
            max-width:56px;
            white-space:nowrap;
            overflow:hidden;
            text-overflow:ellipsis;
            text-align:center;
        }
        table.dataTable thead th{color:#9fd}
        .filters{padding:12px 16px}
    </style>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript">
    $(document).ready(function(){
        $('#example4').DataTable({
            pageLength: 4,
            ordering: false,
            lengthChange: false,
            searching: false,
            info: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json' }
        });
    });
    </script>
</head>
<body>
    <div class="sheet" role="application">
        <div class="header">
            <div class="title">Tickets de Tombolas <?php echo $member_pseudo ? '– '.esc($member_pseudo) : ''; ?></div>
            <button class="back" onclick="history.back();" aria-label="Fermer">Fermer</button>
        </div>
        <div class="filters">
            <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <input type="hidden" name="id" value="<?php echo intval($id); ?>">
                <label style="color:#9aa6b1">Mois:</label>
                <select name="month_tom" class="form-control" style="height:30px">
                    <?php for ($m=1;$m<=12;$m++){ $sel = ($selected_month_tom===$m)?'selected':''; $months_fr = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre']; echo "<option value='$m' $sel>".$months_fr[$m]."</option>"; } ?>
                </select>
                <label style="color:#9aa6b1">Année:</label>
                <select name="year_tom" class="form-control" style="height:30px">
                    <?php for ($y=$current_year_tom;$y>=2023;$y--){ $sel = ($selected_year_tom===$y)?'selected':''; echo "<option value='$y' $sel>$y</option>"; } ?>
                </select>
                <button class="btn btn-primary" style="height:30px">Filtrer</button>
            </form>
        </div>
        <div class="card">
            <table id="example4" class="display" style="width:100%">
                <thead>
                    <tr>
                        <th>QRcode</th>
                        <th>Date</th>
                        <th>Titre Activité</th>
                        <th>Rake</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if (empty($rows)) {
                    echo '<tr style="color:#888;text-align:center">';
                    echo '<td>Aucun ticket trouvé.</td>';
                    echo '<td></td><td></td><td></td>';
                    echo '</tr>';
                } else {
                    foreach ($rows as $row) {
                        // try to find activity title for the collection date
                        $activite_titre = '-';
                        if (!empty($row['date'])) {
                            $dq = @mysqli_query($con, "SELECT `titre-activite` FROM `activite` WHERE DATE(`date_depart`) = DATE('".mysqli_real_escape_string($con,$row['date'])."') LIMIT 1");
                            if ($dq && mysqli_num_rows($dq)>0) { $ar = mysqli_fetch_assoc($dq); $activite_titre = $ar['titre-activite']; }
                        }
                        echo '<tr>';
                        echo '<td>'.esc($row['collection_nom']).'</td>';
                        echo '<td>'.esc(date('d/m/Y', strtotime($row['date']))).'</td>';
                        echo '<td>'.esc(strip_parenthesized($activite_titre ?: '-')).'</td>';
                        $checked = (intval($row['aff_rake'])===1)?'checked':'';
                        echo '<td><input type="checkbox" disabled '. $checked .' ></td>';
                        echo '</tr>';
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
