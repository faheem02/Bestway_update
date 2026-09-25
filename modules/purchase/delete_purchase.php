<?php
/**
 * Bestway Wholesale Distribution - Safe Delete Purchase Bill
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$id = intval($_GET['id'] ?? 0);

if ($db_connected && $pdo && $id > 0) {
    try {
        $pdo->beginTransaction();

        $stmt_p = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
        $stmt_p->execute([$id]);
        $pur = $stmt_p->fetch();

        if ($pur) {
            // 1. Revert product stocks & batches
            $stmt_items = $pdo->prepare("SELECT product_id, batch_no, quantity, bonus_quantity FROM purchase_items WHERE purchase_id = ?");
            $stmt_items->execute([$id]);
            $items = $stmt_items->fetchAll();

            $stmt_revert_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");
            $stmt_revert_batch = $pdo->prepare("UPDATE product_batches SET current_stock = GREATEST(0, current_stock - ?) WHERE product_id = ? AND batch_no = ?");

            foreach ($items as $it) {
                $tot_qty = intval($it['quantity']) + intval($it['bonus_quantity']);
                $stmt_revert_stock->execute([$tot_qty, $it['product_id']]);
                $stmt_revert_batch->execute([$tot_qty, $it['product_id'], $it['batch_no']]);
            }

            // 2. Revert Supplier Balance
            $sup_id = intval($pur['supplier_id']);
            $balance_impact = floatval($pur['balance_amount']);
            if ($balance_impact > 0 && $sup_id > 0) {
                $pdo->prepare("UPDATE suppliers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ?")->execute([$balance_impact, $sup_id]);
            }

            // 3. Delete from ledgers & payments
            $pdo->prepare("DELETE FROM supplier_ledgers WHERE reference_no = ? AND supplier_id = ?")->execute([$pur['bill_no'], $sup_id]);
            $pdo->prepare("DELETE FROM supplier_payments WHERE purchase_id = ?")->execute([$id]);

            // 4. Delete items and purchase
            $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$id]);

            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

header("Location: purchases.php");
exit;
