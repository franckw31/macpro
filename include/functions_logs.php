<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log an activity to the database
 * 
 * @param mysqli $conn The database connection
 * @param string $action The action performed
 * @param string $details Additional details about the action
 * @return bool Success or failure
 */
function log_activity($conn, $action, $details = "") {
    $user_id = isset($_SESSION['id']) ? $_SESSION['id'] : 0;
    $username = isset($_SESSION['login']) ? $_SESSION['login'] : (isset($_SESSION['user']) ? $_SESSION['user'] : 'Guest');
    
    // Détection IP réelle : prendre la première IP publique de X-Forwarded-For
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? '';
    if ($forwarded) {
        // X-Forwarded-For peut contenir "IP_client, IP_proxy1, IP_proxy2"
        $ips = array_map('trim', explode(',', $forwarded));
        foreach ($ips as $candidate) {
            // Ignorer les IPs privées/locales
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip_address = $candidate;
                break;
            }
        }
    }
    
    $action = mysqli_real_escape_string($conn, (string)$action);
    $details = mysqli_real_escape_string($conn, (string)$details);
    $username = mysqli_real_escape_string($conn, (string)$username);
    $ip_address = mysqli_real_escape_string($conn, (string)$ip_address);
    $source = isset($_SESSION['login_source']) ? mysqli_real_escape_string($conn, (string)$_SESSION['login_source']) : 'Standard';
    
    $sql = "INSERT INTO activity_logs (user_id, username, action, source, details, ip_address) 
            VALUES ('$user_id', '$username', '$action', '$source', '$details', '$ip_address')";
            
    return mysqli_query($conn, $sql);
}
?>
