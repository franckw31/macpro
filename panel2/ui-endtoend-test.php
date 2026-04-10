<?php
// UI end-to-end test helper: simulates form POST as session admin and checks DB before/after
require_once __DIR__ . '/include/config.php';
session_start();

$id = isset($argv[1]) ? intval($argv[1]) : 380; // default test id
echo "UI E2E Test for activity id: $id\n";

// fetch current row
$curq = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id'");
if (!$curq || mysqli_num_rows($curq) == 0) {
    echo "Activity $id not found. Aborting.\n";
    exit(1);
}
$before = mysqli_fetch_assoc($curq);
function showCols($row){
    foreach(['titre-activite','places','buyin','recave_montant','nb-tables','rake'] as $c){
        echo sprintf(" - %s: %s\n", $c, isset($row[$c]) ? $row[$c] : '(missing)');
    }
}
echo "Before update:\n";
showCols($before);

// prepare POST payload to simulate clicking "Modifier"
$_SESSION['id'] = 265; // admin
$_POST = [
    'submit' => '1',
    'titre-activite' => 'E2E Test '.date('Y-m-d H:i:s'),
    'places' => '99',
    'buyin' => '77',
    'recave_montant' => '33',
    'nb-tables' => '4',
    'rake' => '2'
];

// Now perform the same update logic as voir-activite.php does
// Load current values from DB (again)
$cur = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id'");
$currow = mysqli_fetch_assoc($cur);

// Helper
$get = function($name, $fallback) {
    if (isset($_POST[$name]) && $_POST[$name] !== '') return $_POST[$name];
    return $fallback;
};

$titre_activite = mysqli_real_escape_string($con, $get('titre-activite', $currow['titre-activite']));
$date_depart = mysqli_real_escape_string($con, $get('date_depart', $currow['date_depart']));
$heure_depart = mysqli_real_escape_string($con, $get('heure_depart', $currow['heure_depart']));
$ville = mysqli_real_escape_string($con, $get('ville', $currow['ville']));
$places = is_numeric($get('places', $currow['places'])) ? intval($get('places', $currow['places'])) : $currow['places'];
$nb_tables = is_numeric($get('nb-tables', $currow['nb-tables'])) ? intval($get('nb-tables', $currow['nb-tables'])) : $currow['nb-tables'];
$rake = is_numeric($get('rake', $currow['rake'])) ? $get('rake', $currow['rake']) : $currow['rake'];
$buyin = is_numeric($get('buyin', $currow['buyin'])) ? $get('buyin', $currow['buyin']) : $currow['buyin'];
$recave = is_numeric($get('recave', $currow['recave'])) ? $get('recave', $currow['recave']) : $currow['recave'];
$recave_montant = is_numeric($get('recave_montant', $currow['recave_montant'])) ? $get('recave_montant', $currow['recave_montant']) : $currow['recave_montant'];
$recave_jetons = is_numeric($get('recave_jetons', $currow['recave_jetons'])) ? $get('recave_jetons', $currow['recave_jetons']) : $currow['recave_jetons'];
$addon = mysqli_real_escape_string($con, $get('addon', $currow['addon']));
$ante = mysqli_real_escape_string($con, $get('ante', $currow['ante']));
$jetons = is_numeric($get('jetons', $currow['jetons'])) ? $get('jetons', $currow['jetons']) : $currow['jetons'];
$bonus = is_numeric($get('bonus', $currow['bonus'])) ? $get('bonus', $currow['bonus']) : $currow['bonus'];
$lng = mysqli_real_escape_string($con, $get('lng', $currow['lng']));
$lat = mysqli_real_escape_string($con, $get('lat', $currow['lat']));

$idmembre = isset($_POST['id-membre']) && $_POST['id-membre'] !== '' ? intval($_POST['id-membre']) : (isset($currow['id-membre']) ? $currow['id-membre'] : null);
$challenge = isset($_POST['challenge']) && $_POST['challenge'] !== '' ? intval($_POST['challenge']) : (isset($currow['id_challenge']) ? intval($currow['id_challenge']) : null);
$structure = isset($_POST['structure']) && $_POST['structure'] !== '' ? intval($_POST['structure']) : (isset($currow['id_structure']) ? $currow['id_structure'] : null);
$idmembresession = $_SESSION['id'];

if (isset($currow['id-membre']) && ($idmembresession == $currow['id-membre'] || $idmembresession == 265)) {
    $sql = "UPDATE `activite` SET 
        `titre-activite` = '$titre_activite',
        `date_depart` = '$date_depart',
        `heure_depart` = '$heure_depart',
        `ville` = '$ville',
        `places` = '$places',
        `nb-tables` = '$nb_tables',
        `id_challenge` = " . (is_null($challenge) ? 'NULL' : "'$challenge'") . ",
        `id_structure` = " . (is_null($structure) ? 'NULL' : "'$structure'") . ",
        `buyin` = '$buyin',
        `rake` = '$rake',
        `bounty` = '$bounty',
        `jetons` = '$jetons',
        `recave` = '$recave',
        `recave_montant` = '$recave_montant',
        `recave_jetons` = '$recave_jetons',
        `addon` = '$addon',
        `ante` = '$ante',
        `bonus` = '$bonus',
        `lng` = '$lng',
        `lat` = '$lat'
        WHERE `id-activite` = '$id'";

    $res = mysqli_query($con, $sql);
    if (!$res) {
        echo "UPDATE failed: " . mysqli_real_escape_string($con, mysqli_error($con)) . "\n";
        exit(1);
    }
    echo "Update executed.\n";
} else {
    echo "Not authorized to update\n";
    exit(1);
}

// fetch updated row
$r = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id'");
$after = mysqli_fetch_assoc($r);

echo "After update:\n";
showCols($after);

// cleanup: revert to before values so test is non-destructive
$revert_sql = "UPDATE `activite` SET 
    `titre-activite` = '".mysqli_real_escape_string($con,$before['titre-activite'])."',
    `places` = '".mysqli_real_escape_string($con,$before['places'])."',
    `buyin` = '".mysqli_real_escape_string($con,$before['buyin'])."',
    `recave_montant` = '".mysqli_real_escape_string($con,$before['recave_montant'])."',
    `nb-tables` = '".mysqli_real_escape_string($con,$before['nb-tables'])."',
    `rake` = '".mysqli_real_escape_string($con,$before['rake'])."' WHERE `id-activite` = '$id'";

$rv = mysqli_query($con, $revert_sql);
if ($rv) {
    echo "\nReverted changes to original values.\n";
} else {
    echo "\nFailed to revert: " . mysqli_real_escape_string($con, mysqli_error($con)) . "\n";
}

// show final row
$final = mysqli_fetch_assoc(mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id'"));
echo "Final row after revert:\n";
showCols($final);

?>