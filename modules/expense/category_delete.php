<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $name = '';
    $cat = getById('expense_categories', $id);
    if ($cat) $name = $cat['name'];
    $pdo->prepare("DELETE FROM expense_categories WHERE id = ?")->execute([$id]);
    logActivity($pdo, 'delete', 'expense_category', $id, 'Deleted expense category: ' . ($name ?: $id));
    redirect('categories.php', 'Category deleted.');
}
redirect('categories.php');