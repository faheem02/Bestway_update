<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
$id = (int)($_GET['id'] ?? 0);
$emp = getById('employees', $id);
if (!$emp) { redirect('index.php', 'Employee not found.', 'error'); }
try {
    if ($emp['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$emp['user_id']]);
    }
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
    redirect('index.php', 'Employee deleted.');
} catch (Exception $e) {
    redirect('index.php', 'Error: ' . $e->getMessage(), 'error');
}
