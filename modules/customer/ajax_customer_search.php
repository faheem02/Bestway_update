<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

$like = '%' . $q . '%';
$stmt = $pdo->prepare("
    SELECT id, name, phone, area, current_balance
    FROM customers
    WHERE name LIKE ? OR phone LIKE ? OR customer_code LIKE ? OR area LIKE ?
    ORDER BY name ASC LIMIT 8
");
$stmt->execute([$like, $like, $like, $like]);
echo json_encode($stmt->fetchAll());