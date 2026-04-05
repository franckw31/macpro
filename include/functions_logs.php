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
    
    // Robust IP detection
    $ip_address = 'UNKNOWN';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ip_address = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ip_address = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ip_address = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ip_address = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ip_address = $_SERVER['REMOTE_ADDR'];
    
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
