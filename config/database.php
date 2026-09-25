<?php
require_once __DIR__ . '/config.php';

// Database configuration
// Update these credentials when deploying to live cPanel / hosting
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bestway_wholesale2';

$db_connected = false;
$pdo = null;
$conn = null;

try {
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Automatically remove ONLY_FULL_GROUP_BY for shared hosting compatibility
    try {
        $pdo->exec("SET SESSION sql_mode = (SELECT REPLACE(REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY,', ''), 'ONLY_FULL_GROUP_BY', ''))");
    } catch (Exception $e) {}

    $db_connected = true;

    // MySQLi connection for legacy module scripts
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if ($conn && !$conn->connect_error) {
        $conn->set_charset("utf8mb4");
        @$conn->query("SET SESSION sql_mode = (SELECT REPLACE(REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY,', ''), 'ONLY_FULL_GROUP_BY', ''))");
    } else {
        $conn = null;
    }
} catch (Exception $e) {
    // Database or MySQL server offline or invalid credentials
    $db_connected = false;
    $pdo = null;
    $conn = null;
}

