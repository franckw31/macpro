<?php
/**
 * register-activity.php - Activity registration API
 * GET: Check registration status
 * POST: Register/unregister for activity
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_log("[reg-api] REQUEST: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

try {
    // Start session
    error_log("[reg-api] Starting request: " . date('Y-m-d H:i:s'));
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    
    // Get user ID from session or Bearer token
    $userId = 0;
    
    // First try session
    if (isset($_SESSION['id'])) {
        $userId = (int)$_SESSION['id'];
        error_log("[reg-api] User ID from session: $userId");
    } 
    
    // If no session, try Bearer token
    if (!$userId) {
        $authHeader = null;
        
        // Try different header names (different servers use different ones)
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            error_log("[reg-api] Found Authorization in HTTP_AUTHORIZATION");
        } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            error_log("[reg-api] Found Authorization in REDIRECT_HTTP_AUTHORIZATION");
        } elseif (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
                error_log("[reg-api] Found Authorization via getallheaders()");
            } else {
                error_log("[reg-api] getallheaders() returned: " . json_encode(array_keys($headers ?: [])));
            }
        } else {
            error_log("[reg-api] getallheaders() not available");
        }
        
        if ($authHeader && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            error_log("[reg-api] Extracted Bearer token: " . substr($token, 0, 20) . "...");
            
            // We'll validate the token against the app_auth_tokens table
            // But first we need a database connection
            // Will set up database and validate below
        } elseif ($authHeader) {
            error_log("[reg-api] Authorization header found but doesn't match Bearer pattern: " . substr($authHeader, 0, 50));
        } else {
            error_log("[reg-api] No Authorization header found");
        }
    }
    
    // Connect to database
    error_log("[reg-api] Attempting database connection to localhost/dbs9616600");
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    error_log("[reg-api] Database connection successful");
    
    // Extract Bearer token if present
    $bearerToken = null;
    if (!$userId && isset($authHeader) && preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
        $bearerToken = $matches[1];
        error_log("[reg-api] Validating Bearer token: " . substr($bearerToken, 0, 20) . "...");
        
        // Look up the token in app_auth_tokens table
        try {
            $tokenStmt = $pdo->prepare("
                SELECT `membre_id` FROM `app_auth_tokens` 
                WHERE `token` = ? AND `expires_at` > NOW()
                LIMIT 1
            ");
            $tokenStmt->execute([$bearerToken]);
            $tokenRow = $tokenStmt->fetch();
            
            if ($tokenRow) {
                $userId = (int)$tokenRow['membre_id'];
                error_log("[reg-api] Bearer token validated, user ID: $userId");
            } else {
                error_log("[reg-api] Bearer token not found in app_auth_tokens or expired");
            }
        } catch (Exception $e) {
            error_log("[reg-api] Token validation failed: " . $e->getMessage());
        }
    }
    
    if (!$userId) {
        http_response_code(401);
        $details = "No session id and ";
        if (!isset($_SERVER['HTTP_AUTHORIZATION']) && !isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $details .= "no Authorization header found";
        } else {
            $details .= ($bearerToken ? "Bearer token validation failed" : "invalid authorization format");
        }
        echo json_encode(['success' => false, 'error' => 'Not authenticated: ' . $details]);
        error_log("[reg-api] Not authenticated - " . $details);
        exit;
    }
    
    error_log("[reg-api] Authenticated user ID: $userId");
    
    // Get activity ID
    $actId = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $actId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $actId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
    }
    
    if (!$actId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing activity_id']);
        error_log("[reg-api] Missing activity_id");
        exit;
    }
    
    error_log("[reg-api] Activity ID: $actId");
    
    // Check activity exists
    error_log("[reg-api] Checking if activity exists...");
    $actStmt = $pdo->prepare("SELECT `id-activite` FROM activite WHERE `id-activite` = ? LIMIT 1");
    if (!$actStmt) {
        throw new Exception("Failed to prepare activity check statement");
    }
    $actStmt->execute([$actId]);
    if (!$actStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Activity not found']);
        error_log("[reg-api] Activity $actId not found in database");
        exit;
    }
    error_log("[reg-api] Activity $actId verified");
    
    // Check current registration
    error_log("[reg-api] Checking current registration status...");
    $stmt = $pdo->prepare("SELECT `option` FROM participation WHERE `id-membre` = ? AND `id-activite` = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception("Failed to prepare participation check statement");
    }
    $stmt->execute([$userId, $actId]);
    $row = $stmt->fetch();
    $currentStatus = $row ? $row['option'] : 'None';
    
    error_log("[reg-api] Current status: $currentStatus");
    
    $registeredStatuses = ['Inscrit', 'Option', 'Réservation', 'Reservation', 'Présent', 'Present', 'Confirmé', 'Confirme', 'Eliminé', 'Elimine'];
    $isRegistered = in_array($currentStatus, $registeredStatuses);
    
    // Handle GET request
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success' => true,
            'registered' => $isRegistered,
            'status' => $currentStatus,
            'activity_id' => $actId
        ]);
        exit;
    }
    
    // Handle POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $input['action'] ?? null;
        
        error_log("[reg-api] POST action: $action");
        
        if ($action === 'toggle') {
            // Toggle registration
            error_log("[reg-api] Processing toggle action. Currently registered: " . ($isRegistered ? 'true' : 'false'));
            if ($isRegistered) {
                // Unregister
                error_log("[reg-api] User is registered, deleting participation record...");
                $delStmt = $pdo->prepare("DELETE FROM participation WHERE `id-membre` = ? AND `id-activite` = ?");
                if (!$delStmt) {
                    throw new Exception("Failed to prepare delete statement");
                }
                $delStmt->execute([$userId, $actId]);
                $newStatus = 'None';
                $newIsRegistered = false;
                error_log("[reg-api] Deleted participation for user $userId, activity $actId");
            } else {
                // Register as "Inscrit"
                error_log("[reg-api] User is not registered, inserting new participation...");
                $memStmt = $pdo->prepare("SELECT `pseudo` FROM membres WHERE `id-membre` = ? LIMIT 1");
                if (!$memStmt) {
                    throw new Exception("Failed to prepare member select statement");
                }
                $memStmt->execute([$userId]);
                $mem = $memStmt->fetch();
                $pseudo = ($mem && isset($mem['pseudo'])) ? $mem['pseudo'] : "User$userId";
                error_log("[reg-api] Found pseudo: $pseudo");
                
                // Get next ordre
                $ordStmt = $pdo->prepare("SELECT COALESCE(MAX(`ordre`), 0) as max_o FROM participation WHERE `id-activite` = ?");
                if (!$ordStmt) {
                    throw new Exception("Failed to prepare ordre select statement");
                }
                $ordStmt->execute([$actId]);
                $ordRow = $ordStmt->fetch();
                $nextOrdre = (int)($ordRow['max_o'] ?? 0) + 1;
                error_log("[reg-api] Calculated next ordre: $nextOrdre");
                
                error_log("[reg-api] Inserting: userId=$userId, actId=$actId, pseudo=$pseudo, ordre=$nextOrdre");
                
                $insStmt = $pdo->prepare("INSERT INTO participation (`id-membre`, `id-activite`, `nom-membre`, `option`, `ordre`, `ds`) VALUES (?, ?, ?, 'Inscrit', ?, NOW())");
                if (!$insStmt) {
                    throw new Exception("Failed to prepare insert statement");
                }
                $insStmt->execute([$userId, $actId, $pseudo, $nextOrdre]);
                $newStatus = 'Inscrit';
                $newIsRegistered = true;
                error_log("[reg-api] Inserted participation for user $userId, activity $actId");
            }
        } elseif ($action === 'register') {
            // Register with options
            error_log("[reg-api] Processing register action...");
            $isOpt = $input['is_option'] ?? false;
            $latereg = $input['latereg'] ?? false;
            error_log("[reg-api] Options: is_option=$isOpt, latereg=$latereg");
            $newStatus = $isOpt ? 'Option' : ($latereg ? 'Réservation' : 'Inscrit');
            error_log("[reg-api] Calculated status: $newStatus");
            
            if ($isRegistered) {
                // Update
                error_log("[reg-api] User already registered, updating to $newStatus...");
                $upStmt = $pdo->prepare("UPDATE participation SET `option` = ?, `ds` = NOW() WHERE `id-membre` = ? AND `id-activite` = ?");
                if (!$upStmt) {
                    throw new Exception("Failed to prepare update statement");
                }
                $upStmt->execute([$newStatus, $userId, $actId]);
                error_log("[reg-api] Updated participation for user $userId, activity $actId, new status: $newStatus");
            } else {
                // Insert
                error_log("[reg-api] User not registered, inserting with $newStatus...");
                $memStmt = $pdo->prepare("SELECT `pseudo` FROM membres WHERE `id-membre` = ? LIMIT 1");
                if (!$memStmt) {
                    throw new Exception("Failed to prepare member select statement");
                }
                $memStmt->execute([$userId]);
                $mem = $memStmt->fetch();
                $pseudo = ($mem && isset($mem['pseudo'])) ? $mem['pseudo'] : "User$userId";
                error_log("[reg-api] Found pseudo: $pseudo");
                
                $ordStmt = $pdo->prepare("SELECT COALESCE(MAX(`ordre`), 0) as max_o FROM participation WHERE `id-activite` = ?");
                if (!$ordStmt) {
                    throw new Exception("Failed to prepare ordre select statement");
                }
                $ordStmt->execute([$actId]);
                $ordRow = $ordStmt->fetch();
                $nextOrdre = (int)($ordRow['max_o'] ?? 0) + 1;
                error_log("[reg-api] Calculated next ordre: $nextOrdre");
                
                error_log("[reg-api] Inserting: userId=$userId, actId=$actId, pseudo=$pseudo, status=$newStatus, ordre=$nextOrdre");
                
                $insStmt = $pdo->prepare("INSERT INTO participation (`id-membre`, `id-activite`, `nom-membre`, `option`, `ordre`, `ds`) VALUES (?, ?, ?, ?, ?, NOW())");
                if (!$insStmt) {
                    throw new Exception("Failed to prepare insert statement");
                }
                $insStmt->execute([$userId, $actId, $pseudo, $newStatus, $nextOrdre]);
                error_log("[reg-api] Inserted participation for user $userId, activity $actId, status: $newStatus");
            }
            
            $newIsRegistered = in_array($newStatus, $registeredStatuses);
        } else {
            error_log("[reg-api] Unknown action: $action");
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'registered' => $newIsRegistered,
            'status' => $newStatus,
            'activity_id' => $actId
        ]);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    
} catch (PDOException $e) {
    error_log("[reg-api] PDO EXCEPTION: " . $e->getMessage());
    error_log("[reg-api] Error Code: " . $e->getCode());
    error_log("[reg-api] SQL State: " . $e->errorInfo[0]);
    error_log("[reg-api] Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("[reg-api] GENERAL EXCEPTION: " . $e->getMessage());
    error_log("[reg-api] File: " . $e->getFile() . ", Line: " . $e->getLine());
    error_log("[reg-api] Trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
