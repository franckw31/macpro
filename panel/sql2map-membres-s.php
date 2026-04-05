<?php
header('Content-Type: application/json; charset=utf-8');

$db = "dbs9616600";
$host = "localhost";
$user = 'root';
$pass = 'Kookies7*';

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

$sql = "SELECT * FROM `membres` WHERE `type` LIKE 'S'";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as $key => $eventData) {
        $returnData[] = [
            'type' => 'Feature'.$eventData['id-membre'],
            'properties' => [
         		'description' => '<p>'.$eventData['lien'].$eventData['id-membre'].$eventData['lien-texte'].$eventData['pseudo'].'</p><p>'.$eventData['lien-texte-fin'].'</p>',
				't2' => $eventData['photo-map'],
                'icon' => $eventData['icon'],
                'ico-siz' => $eventData['ico-siz'],
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$eventData['latitude'], $eventData['longitude']]
            ],'ico-siz' => $eventData['ico-siz'],
        ];
    }
} catch (Exception $e) {
    die($e);
}

echo json_encode($returnData);
