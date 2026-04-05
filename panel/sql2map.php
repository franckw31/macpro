<?php
header('Content-Type: application/json; charset=utf-8');

$db = "dbs9616600";
$host = "localhost";
$user = 'root';
$pass = 'Kookies7*';
$actu = strtotime(date("Y-m-d"));
//PDO Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Connected successfully";
}
catch(PDOException $e){
    //echo "Connection failed: " . $e->getMessage();
}

function p($arr){
    echo "<pre>";
        print_r($arr);
    echo "</pre>";
}

// $sql = "SELECT * FROM `activite` WHERE ($actu > `date_depart`) ORDER BY `date_depart` ";
  $sql = "SELECT * FROM `activite`";
// $sql = "SELECT * FROM `activite` WHERE `date_depart` <> '0000-00-00' and datediff(`date_depart`,now())>-1 ORDER BY `date_depart` ";
// $sql = "SELECT * FROM `activite` WHERE ($actu > `date_depart`) ORDER BY `date_depart` ";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $key => $eventData) {
        $dateactivitenum=strtotime($eventData['date_depart']);
	    $da=date("Y-m-d");
	    $maintenantnum=strtotime($da);
        if ($maintenantnum < $dateactivitenum) $returnData[] = [
            'type' => 'Feature'.$eventData['id-activite'],
            'properties' => [
         		// 'description' => '<p>'.$eventData['lien'].$eventData['id-activite'].$eventData['lien-texte'].$eventData['date_depart'].' / '.$eventData['titre-activite'].'</p><p>'.$eventData['lien-texte-fin'].$eventData['lien-texte-fin'].'</p>',
                 'description' => '<p>'.$eventData['titre-activite'].'</p>'.'<p>'.$eventData['lien'].$eventData['id-activite'].$eventData['lien-texte'].$eventData['photo'].$eventData['lien-texte-fin'].'</p>',
				
                // 't1' => $eventData['t1'],
				't2' => $eventData['photo'],
                'date_depart' => $eventData['date_depart'],
                'places' => $eventData['places'],
                'buyin' => $eventData['buyin'],
                'icon' => $eventData['icon'],
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$eventData['lat'], $eventData['lng']]
            ],'icon-size' => $eventData['ico-siz'],
        ];
    }
} catch (Exception $e) {
    die($e);
}

echo json_encode($returnData);
