<?php
/**
 * register-activity-noauth.php - Registration API without authentication (for testing)
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("[reg-noauth] FATAL: " . $error['message']);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal: ' . $error['message']]);
    }
});

try {
    error_log("[reg-noauth] REQUEST START");
    
    // For testing, use hardcoded user ID (Admin = 265)
    $userId = 265;
    
    // Get activity ID
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $actId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $actId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
    }
    
    if (!$actId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing activity_id']);
        exit;
    }
    
    error_log("[reg-noauth] actId=$actId, userId=$userId");
    
    // Connect
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    // Check activity
    $stmt = $pdo->prepare("SELECT 1 FROM `activite` WHERE `id-activite` = ? LIMIT 1");
    $stmt->execute([$actId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Activity not found']);
        exit;
    }
    
    // Get registration status
    $stmt = $pdo->prepare("SELECT `option` FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ? LIMIT 1");
    $stmt->execute([$userId, $actId]);
    $row = $stmt->fetch();
    $currentStatus = $row ? $row['option'] : 'None';
    $isRegistered = in_array($currentStatus, ['Inscrit', 'Option', 'Réservation']);
    
    // GET - return status
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'registered' => $isRegistered,
            'status' => $currentStatus
        ]);
        exit;
    }
    
    // POST - handle action
    $input = json_decode(file_get_contents('php://input'), true);
    $action = isset($input['action']) ? $input['action'] : null;
    
    if ($action === 'toggle') {
        if ($isRegistered) {
            // Delete
            $stmt = $pdo->prepare("DELETE FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?");
            $stmt->execute([$userId, $actId]);
            $newStatus = 'None';
            $newRegistered = false;
        } else {
            // Insert
            $stmt = $pdo->prepare("SELECT `pseudo` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
            $stmt->execute([$userId]);
            $member = $stmt->fetch();
            $pseudo = $member ? ($member['pseudo'] ?? "User$userId") : "User$userId";
            
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(`ordre`), 0) as maxord FROM `participation` WHERE `id-activite` = ?");
            $stmt->execute([$actId]);
            $ordData = $stmt->fetch();
            $nextOrdre = intval($ordData['maxord'] ?? 0) + 1;
            
            $stmt = $pdo->prepare("INSERT INTO `participation` (`id-membre`, `id-activite`, `nom-membre`, `option`, `ordre`, `ds`) VALUES (?, ?, ?, 'Inscrit', ?, NOW())");
            $stmt->execute([$userId, $actId, $pseudo, $nextOrdre]);
            $newStatus = 'Inscrit';
            $newRegistered = true;
        }
        
        echo json_encode([
            'success' => true,
            'registered' => $newRegistered,
            'status' => $newStatus
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action']);

} catch (PDOException $e) {
    error_log("[reg-noauth] PDOException: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[reg-noauth] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
