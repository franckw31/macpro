<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer dummy-token';
$json = json_encode(['action' => 'register', 'activity_id' => 1, 'anonyme' => true, 'is_option' => false, 'latereg' => false]);
$_POST['json'] = $json; // We will use STDIN for real test, so let's mock file_get_contents.
