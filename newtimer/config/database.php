<?php
$db_config = [
    'host' => 'localhost',
    'dbname' => 'dsb9616600',
    'user' => 'root',
    'pass' => 'Kookies7*'
];

function getDbConnection($config) {
    try {
        $conn = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']}",
            $config['user'],
            $config['pass']
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}