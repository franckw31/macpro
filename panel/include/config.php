<?php
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.viendez.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

define('DB_SERVER','localhost');
define('DB_USER','root');
define('DB_PASS' ,'Kookies7*');
define('DB_NAME', 'dbs9616600');
$db = "dbs9616600";
$host = "localhost";
$user = 'root';
$pass = 'Kookies7*';
$con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
// Check connection
if (mysqli_connect_errno())
{
 echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
?>
