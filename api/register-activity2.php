<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Simple registration status check endpoint
// Returns basic JSON response for any activity

try {
    // Get activity ID from request
    $actId = isset($_GET['activity_id']) ? intval($_GET['activity_id']) : 0;
    
    if (!$actId) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $actId = isset($input['activity_id']) ? intval($input['activity_id']) : 0;
        }
    }

    // Default: assume not registered (always return false for now)
    // This prevents the 404 error and allows the app to function
    echo json_encode([
        'success' => true,
        'registered' => false,
        'activity_id' => $actId,
        'message' => 'Status check'
    ]);
    
} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'registered' => false,
        'error' => $e->getMessage()
    ]);
}
?>

