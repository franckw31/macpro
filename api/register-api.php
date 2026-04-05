<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    session_start();
    
    // Get activity ID
    $actId = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $actId = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $actId = isset($input['activity_id']) ? intval($input['activity_id']) : 0;
    }

    if (!$actId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing activity_id']);
        exit;
    }

    $stateKey = "activity_" . $actId . "_registered";
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Read from session
        $isRegistered = isset($_SESSION[$stateKey]) ? $_SESSION[$stateKey] : false;
        
        echo json_encode([
            'success' => true,
            'registered' => $isRegistered,
            'activity_id' => $actId
        ]);
        exit;
    }

    // Handle POST (action: toggle, register, etc)
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : 'toggle';

    $isRegistered = isset($_SESSION[$stateKey]) ? $_SESSION[$stateKey] : false;
    
    if ($action === 'toggle') {
        $isRegistered = !$isRegistered;
    } else {
        $isRegistered = true;
    }
    
    $_SESSION[$stateKey] = $isRegistered;

    echo json_encode([
        'success' => true,
        'registered' => $isRegistered,
        'activity_id' => $actId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
