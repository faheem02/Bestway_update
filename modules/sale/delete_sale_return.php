<?php
/**
 * Bestway Wholesale Distribution - Safe Delete Sale Return
 * Revert stock, revert customer balance or cash/bank accounts, and delete return records.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(['admin']);

$id = intval($_GET['id'] ?? 0);
$redirect_inv = intval($_GET['invoice_id'] ?? 0);

if ($db_connected && $pdo && $id > 0) {
    try {
        $pdo->beginTransaction();

        $stmt_ret = $pdo->prepare("SELECT * FROM sale_returns WHERE id = ?");
        $stmt_ret->execute([$id]);
        $ret_data = $stmt_ret->fetch(PDO::FETCH_ASSOC);

        if ($ret_data) {
            // 1. Fetch return items to revert stock
            $stmt_ritems = $pdo->prepare("SELECT product_id, quantity, `condition` FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?");
            $stmt_ritems->execute([$id, $id]);
            $ritems = $stmt_ritems->fetchAll(PDO::FETCH_ASSOC);

            $stmt_sub_stock = $pdo->prepare("UPDATE products SET current_stock = GREATEST(0, current_stock - ?) WHERE id = ?");

            foreach ($ritems as $ri) {
                $cond = $ri['condition'] ?? 'Good';
                if ($cond === 'Good / Resalable' || $cond === 'Good') {
                    $stmt_sub_stock->execute([$ri['quantity'], $ri['product_id']]);
                }
            }

            // 2. Revert refund if cash, bank, or customer balance
            $ret_amt = floatval($ret_data['total_amount']);
            $refund_type = $ret_data['refund_type'] ?? '';
            $cash_acc_id = !empty($ret_data['cash_account_id']) ? intval($ret_data['cash_account_id']) : 0;
            $bank_acc_id = !empty($ret_data['bank_account_id']) ? intval($ret_data['bank_account_id']) : 0;
            $cust_id = intval($ret_data['customer_id'] ?? 0);

            if ($refund_type === 'Cash Refund' && $cash_acc_id > 0) {
                $pdo->prepare("UPDATE cash_accounts SET balance = balance + ? WHERE id = ?")->execute([$ret_amt, $cash_acc_id]);
            } elseif ($refund_type === 'Bank Refund' && $bank_acc_id > 0) {
                $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$ret_amt, $bank_acc_id]);
            } elseif ($refund_type === 'Deduct Balance' || $refund_type === 'Store Credit' || $refund_type === 'Credit Note' || $refund_type === 'Adjust / Deduct Customer Balance') {
                if ($cust_id > 0 && $ret_amt > 0) {
                    $pdo->prepare("UPDATE customers SET current_balance = current_balance + ? WHERE id = ?")->execute([$ret_amt, $cust_id]);
                }
            }

            // 3. Delete items & return
            $pdo->prepare("DELETE FROM sale_return_items WHERE return_id = ? OR sale_return_id = ?")->execute([$id, $id]);
            $pdo->prepare("DELETE FROM sale_returns WHERE id = ?")->execute([$id]);

            $pdo->commit();

            // 4. Sync customer balance & ledger
            if ($cust_id > 0 && function_exists('updateCustomerBalance')) {
                try { updateCustomerBalance($pdo, $cust_id); } catch (Exception $e) {}
            }

            $_SESSION['flash_message'] = "Sale Return #{$ret_data['return_no']} deleted successfully and stock/balance reverted.";
            $_SESSION['flash_type'] = "success";
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_message'] = "Delete error: " . $e->getMessage();
        $_SESSION['flash_type'] = "danger";
    }
}

$redirect_url = "sale_return.php";
if ($redirect_inv > 0) {
    $redirect_url .= "?invoice_id=" . $redirect_inv;
}
header("Location: " . $redirect_url);
exit;
