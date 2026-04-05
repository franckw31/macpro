<?php
/**
 * register-activity.php - Activity registration API (v4 - Simplified & Robust)
 * GET: Check registration status
 * POST: Register/unregister for activity
 */

// Set error handling FIRST before anything else
error_reporting(E_ALL);
ini_set('display_errors', '0');  // Don't output errors to HTTP response
ini_set('log_errors', '1');       // Log to error_log instead

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// MAIN HANDLER - Everything wrapped in try-catch
// ============================================================
try {
    error_log("[reg-api] ========== REQUEST START ==========");
    error_log("[reg-api] Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("[reg-api] Time: " . date('Y-m-d H:i:s'));
    
    // ---- STEP 1: Initialize session and get user ID ----
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $userId = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    error_log("[reg-api] Session user ID: $userId");
    
    // ---- STEP 2: If no session, try Bearer token ----
    $token = null;
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$userId && !empty($authHeader)) {
        error_log("[reg-api] HTTP_AUTHORIZATION found");
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) {
            $token = $m[1];
            error_log("[reg-api] Bearer token extracted: " . substr($token, 0, 20) . "...");
        }
    }

    // Fallback: Check token in POST payload or GET
    if (!$userId && empty($token)) {
        $body = file_get_contents('php://input');
        $input = json_decode($body, true);
        if (isset($input['token'])) {
            $token = $input['token'];
            error_log("[reg-api] Token found in JSON payload: " . substr($token, 0, 20) . "...");
        } else if (isset($_GET['token'])) {
            $token = $_GET['token'];
            error_log("[reg-api] Token found in GET params: " . substr($token, 0, 20) . "...");
        }
    }
    
    // ---- STEP 3: Connect to database ----
    error_log("[reg-api] Connecting to database...");
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    error_log("[reg-api] Database connected successfully");
    
    // ---- STEP 4: Validate Bearer token if present ----
    if (!$userId && isset($token)) {
        error_log("[reg-api] Validating Bearer token...");
        $stmt = $pdo->prepare("SELECT `membre_id` FROM `app_auth_tokens` WHERE `token` = ? AND `expires_at` > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        if ($row) {
            $userId = (int)$row['membre_id'];
            error_log("[reg-api] Token valid, user ID: $userId");
        } else {
            error_log("[reg-api] Token not found or expired in database");
        }
    }
    
    // ---- STEP 5: Check authentication ----
    if (!$userId) {
        error_log("[reg-api] User not authenticated");
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    // ---- STEP 6: Get activity ID ----
    $actId = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $actId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    } else {
        $body = file_get_contents('php://input');
        $input = json_decode($body, true);
        $actId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
    }
    
    if (!$actId) {
        error_log("[reg-api] Missing activity_id");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing activity_id']);
        exit;
    }
    
    error_log("[reg-api] Activity ID: $actId, User ID: $userId");
    
    // ---- STEP 7: Verify activity exists ----
    $stmt = $pdo->prepare("SELECT 1 FROM `activite` WHERE `id-activite` = ? LIMIT 1");
    $stmt->execute([$actId]);
    if (!$stmt->fetch()) {
        error_log("[reg-api] Activity $actId not found");
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Activity not found']);
        exit;
    }
    error_log("[reg-api] Activity verified");
    
    // ---- STEP 8: Check current registration ----
    $stmt = $pdo->prepare("SELECT `option` FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ? LIMIT 1");
    $stmt->execute([$userId, $actId]);
    $row = $stmt->fetch();
    $currentStatus = $row ? $row['option'] : 'None';
    $isRegistered = in_array($currentStatus, ['Inscrit', 'Option', 'Réservation', 'Reservation', 'Présent', 'Present', 'Confirmé', 'Confirme', 'Eliminé', 'Elimine']);
    error_log("[reg-api] Current status: $currentStatus, registered: " . ($isRegistered ? 'yes' : 'no'));
    
    // ---- STEP 9: Handle GET request ----
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        error_log("[reg-api] GET request - returning status");
        echo json_encode([
            'success' => true,
            'registered' => $isRegistered,
            'status' => $currentStatus,
            'activity_id' => $actId
        ]);
        exit;
    }
    
    // ---- STEP 10: Handle POST request ----
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $body = file_get_contents('php://input');
        $input = json_decode($body, true);
        $action = isset($input['action']) ? $input['action'] : null;
        
        error_log("[reg-api] POST action: $action");
        
        if ($action === 'toggle') {
            error_log("[reg-api] Processing toggle...");
            
            if ($isRegistered) {
                // Delete participation
                $stmt = $pdo->prepare("DELETE FROM `participation` WHERE `id-membre` = ? AND `id-activite` = ?");
                $stmt->execute([$userId, $actId]);
                error_log("[reg-api] Deleted participation record");
                $newStatus = 'None';
                $newIsRegistered = false;
            } else {
                // Insert participation as 'Inscrit'
                $stmt = $pdo->prepare("SELECT `pseudo` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
                $stmt->execute([$userId]);
                $member = $stmt->fetch();
                $pseudo = $member ? ($member['pseudo'] ?? "User$userId") : "User$userId";
                
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(`ordre`), 0) as maxord FROM `participation` WHERE `id-activite` = ?");
                $stmt->execute([$actId]);
                $ordData = $stmt->fetch();
                $nextOrdre = intval($ordData['maxord'] ?? 0) + 1;
                
                error_log("[reg-api] Inserting: user=$userId, act=$actId, pseudo=$pseudo, ordre=$nextOrdre");
                $stmt = $pdo->prepare("INSERT INTO `participation` (`id-membre`, `id-activite`, `nom-membre`, `option`, `ordre`, `ds`) VALUES (?, ?, ?, 'Inscrit', ?, NOW())");
                $stmt->execute([$userId, $actId, $pseudo, $nextOrdre]);
                error_log("[reg-api] Inserted new participation record");
                $newStatus = 'Inscrit';
                $newIsRegistered = true;
            }
            
            echo json_encode([
                'success' => true,
                'registered' => $newIsRegistered,
                'status' => $newStatus,
                'activity_id' => $actId
            ]);
            error_log("[reg-api] Toggle complete");
            exit;
        }
        
        elseif ($action === 'register') {
            error_log("[reg-api] Processing register...");
            $isOpt = isset($input['is_option']) ? $input['is_option'] : false;
            $latereg = isset($input['latereg']) ? $input['latereg'] : false;
            $newStatus = $isOpt ? 'Option' : ($latereg ? 'Réservation' : 'Inscrit');
            error_log("[reg-api] Status: $newStatus (is_option=$isOpt, latereg=$latereg)");
            
            if ($isRegistered) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE `participation` SET `option` = ?, `ds` = NOW() WHERE `id-membre` = ? AND `id-activite` = ?");
                $stmt->execute([$newStatus, $userId, $actId]);
                error_log("[reg-api] Updated existing participation to $newStatus");
            } else {
                // Insert new
                $stmt = $pdo->prepare("SELECT `pseudo` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
                $stmt->execute([$userId]);
                $member = $stmt->fetch();
                $pseudo = $member ? ($member['pseudo'] ?? "User$userId") : "User$userId";
                
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(`ordre`), 0) as maxord FROM `participation` WHERE `id-activite` = ?");
                $stmt->execute([$actId]);
                $ordData = $stmt->fetch();
                $nextOrdre = intval($ordData['maxord'] ?? 0) + 1;
                
                error_log("[reg-api] Inserting: user=$userId, act=$actId, pseudo=$pseudo, ordre=$nextOrdre, status=$newStatus");
                $stmt = $pdo->prepare("INSERT INTO `participation` (`id-membre`, `id-activite`, `nom-membre`, `option`, `ordre`, `ds`) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$userId, $actId, $pseudo, $newStatus, $nextOrdre]);
                error_log("[reg-api] Inserted new participation with status $newStatus");
            }
            
            $newIsRegistered = in_array($newStatus, ['Inscrit', 'Option', 'Réservation', 'Reservation', 'Présent', 'Present', 'Confirmé', 'Confirme', 'Eliminé', 'Elimine']);
            echo json_encode([
                'success' => true,
                'registered' => $newIsRegistered,
                'status' => $newStatus,
                'activity_id' => $actId
            ]);
            error_log("[reg-api] Register complete");
            exit;
        }
        
        else {
            error_log("[reg-api] Unknown action: $action");
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
            exit;
        }
    }
    
    // Should not reach here
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    error_log("[reg-api] Method not allowed");

} catch (PDOException $e) {
    error_log("[reg-api] PDO EXCEPTION: " . $e->getMessage());
    error_log("[reg-api] Error info: " . json_encode($e->errorInfo));
    error_log("[reg-api] File: " . $e->getFile() . ", Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);

} catch (Exception $e) {
    error_log("[reg-api] EXCEPTION: " . $e->getMessage());
    error_log("[reg-api] File: " . $e->getFile() . ", Line: " . $e->getLine());
    error_log("[reg-api] Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);

} catch (Throwable $e) {
    error_log("[reg-api] FATAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Fatal error']);
}

error_log("[reg-api] ========== REQUEST END ==========");
?>
