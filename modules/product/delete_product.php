<?php
/**
 * Bestway Distribution - Delete Product Handler
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

$id = intval($_GET['id'] ?? 0);

if ($id > 0 && $db_connected && $pdo) {
    try {
        // Check if used in sales
        $chk = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE product_id = ?");
        $chk->execute([$id]);
        if ($chk->fetchColumn() > 0) {
            header("Location: view_product_list.php?msg=used");
            exit;
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM opening_stock_logs WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM product_batches WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $pdo->commit();
        header("Location: view_product_list.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

header("Location: view_product_list.php");
exit;
