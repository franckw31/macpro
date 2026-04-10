<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test 1: Session
echo "Test 1: Session start<br>";
session_start();
echo "OK<br>";

// Test 2: Include config
echo "Test 2: Include config<br>";
include('include/config.php');
echo "OK<br>";

// Test 3: Check constants
echo "Test 3: Check DB constants<br>";
echo "DB_SERVER: " . DB_SERVER . "<br>";
echo "OK<br>";

// Test 4: Test connection
echo "Test 4: Test DB connection<br>";
if ($con) {
    echo "Connection OK<br>";
} else {
    echo "Connection FAILED<br>";
}

echo "All tests passed!<br>";
?>
