<?php
// Database connection (temporarily set by assistant to run update script).
// Host/user/password/db provided by user for this session.
$db_host = 'viendez.com';
$db_user = 'root';
$db_pass = 'Kookies7*';
$db_name = 'dbs9616600';

$con = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (! $con) {
	// Keep a non-null $con value for scripts expecting it; but surface errors for CLI.
	error_log('MySQL connection error: ' . mysqli_connect_error());
	// For CLI usage, print to STDERR
	if (php_sapi_name() === 'cli') {
		fwrite(STDERR, "MySQL connection error: " . mysqli_connect_error() . "\n");
	}
	$con = null;
} else {
	mysqli_set_charset($con, 'utf8mb4');
}

?>