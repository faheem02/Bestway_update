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

            $sup_id = intval($pur['supplier_id']);

            // 2. Undo the money paid against this bill in the cash book / bank book
            //    (payment at purchase time is booked as 'purchase', later payments as 'supplier_payment')
            require_once __DIR__ . '/../../includes/functions.php';
            $pay_ids = $pdo->prepare("SELECT id FROM supplier_payments WHERE purchase_id = ?");
            $pay_ids->execute([$id]);
            $refs = [['purchase', $id]];
            foreach ($pay_ids->fetchAll(PDO::FETCH_COLUMN) as $spid) { $refs[] = ['supplier_payment', (int)$spid]; }
            foreach ($refs as $rf) {
                reverseBookEntries($pdo, $rf[0], $rf[1]); // removes cash_book / bank_transactions rows for this reference
            }

            // 3. Delete payments, items and purchase
            $pdo->prepare("DELETE FROM supplier_payments WHERE purchase_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM purchases WHERE id = ?")->execute([$id]);

            // 4. Rebuild supplier ledger & balance from what remains (the old manual
            //    "balance - balance_amount" ignored payments/returns and was clamped at 0)
            if ($sup_id > 0) syncSupplierLedger($pdo, $sup_id);

            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}

header("Location: purchases.php");
exit;
