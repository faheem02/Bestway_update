<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
requireRole(['admin']);

$customer_id = (int)($_GET['id'] ?? 0);

if ($customer_id > 0 && $db_connected && $pdo) {
    try {
        // Check if customer has outstanding balance or sale invoices
        $stmt_chk = $pdo->prepare("SELECT current_balance, 
            ((SELECT COUNT(*) FROM sales_invoices WHERE customer_id = :id) + (SELECT COUNT(*) FROM sales WHERE customer_id = :id3)) as sales_count 
            FROM customers WHERE id = :id2");
        $stmt_chk->execute(['id' => $customer_id, 'id2' => $customer_id, 'id3' => $customer_id]);
        $res = $stmt_chk->fetch();

        if ($res && ((float)$res['current_balance'] != 0 || (int)$res['sales_count'] > 0)) {
            // Cannot hard delete customer with balance or sales history - mark inactive for data integrity
            $stmt_inact = $pdo->prepare("UPDATE customers SET status = 'Inactive' WHERE id = :id");
            $stmt_inact->execute(['id' => $customer_id]);
        } else {
            // Safe to delete
            $stmt_del = $pdo->prepare("DELETE FROM customers WHERE id = :id");
            $stmt_del->execute(['id' => $customer_id]);
        }
    } catch (Exception $e) {}
}

header("Location: " . BASE_URL . "modules/customer/customers.php");
exit;
