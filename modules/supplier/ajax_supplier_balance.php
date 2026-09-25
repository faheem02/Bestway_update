<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo '0.00'; exit; }

$s = getById('suppliers', $id);
echo $s ? number_format((float)$s['current_balance'], 2) : '0.00';