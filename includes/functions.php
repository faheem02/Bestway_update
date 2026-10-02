<?php
// ===== BESTWAY DISTRIBUTION - HELPER FUNCTIONS =====

// ---- Currency & Date ----
if (!function_exists('format_currency')) {
    function format_currency($amount) {
        return 'PKR ' . number_format((float)$amount, 2);
    }
}
if (!function_exists('formatCurrency')) {
    function formatCurrency($amount) {
        return number_format((float)$amount, 2);
    }
}
if (!function_exists('format_date')) {
    function format_date($dateStr) {
        if (!$dateStr) return '-';
        return date('d-M-Y', strtotime($dateStr));
    }
}
if (!function_exists('formatDate')) {
    function formatDate($dateStr) {
        if (!$dateStr) return '-';
        return date('d-M-Y', strtotime($dateStr));
    }
}

// ---- Redirect ----
if (!function_exists('redirect')) {
    function redirect($url, $message = '', $type = 'success') {
        if ($message !== '') {
            $_SESSION[$type] = $message;
        }
        header('Location: ' . $url);
        exit;
    }
}

// ---- Session helpers ----
if (!function_exists('currentBranchId')) {
    function currentBranchId($pdo) {
        return $_SESSION['branch_id'] ?? 1;
    }
}

// ---- DB helpers ----
if (!function_exists('insert')) {
    function insert($table, array $data) {
        global $pdo;
        $cols = implode(', ', array_keys($data));
        $phs  = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($phs)");
        $stmt->execute(array_values($data));
        return $pdo->lastInsertId();
    }
}
if (!function_exists('update')) {
    function update($table, array $data, $id) {
        global $pdo;
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $stmt = $pdo->prepare("UPDATE `$table` SET $set WHERE id = ?");
        $stmt->execute([...array_values($data), $id]);
    }
}
if (!function_exists('getById')) {
    function getById($table, $id) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
if (!function_exists('countRows')) {
    function countRows($table, $col = null, $val = null) {
        global $pdo;
        if ($col && $val !== null) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
            $stmt->execute([$val]);
        } else {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        }
        return (int)$stmt->fetchColumn();
    }
}

// ---- Auto-number generators ----
if (!function_exists('getActiveBookers')) {
    function getActiveBookers($pdo) {
        try {
            return $pdo->query("SELECT id, full_name AS name, emp_code AS booker_code, commission_rate, area FROM employees WHERE (employee_type = 'salesman' OR employee_type = 'order_booker') AND status = 1 ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }
}
if (!function_exists('generateEmployeeCode')) {
    function generateEmployeeCode() {
        global $pdo;
        try {
            $last = $pdo->query("SELECT emp_code FROM employees ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                return 'EMP-' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
            }
        } catch (Exception $e) {}
        return 'EMP-0001';
    }
}
if (!function_exists('generateCustomerCode')) {
    function generateCustomerCode() {
        global $pdo;
        try {
            $last = $pdo->query("SELECT customer_code FROM customers ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                return 'C-' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
            }
        } catch (Exception $e) {}
        return 'C-0001';
    }
}
if (!function_exists('generateExpenseNo')) {
    function generateExpenseNo() {
        global $pdo;
        try {
            $last = $pdo->query("SELECT voucher_no FROM expenses ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                return 'EXP-' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
            }
        } catch (Exception $e) {}
        return 'EXP-0001';
    }
}
if (!function_exists('generateSaleNo')) {
    function generateSaleNo() {
        global $pdo;
        try {
            $last = $pdo->query("SELECT invoice_no FROM sales ORDER BY id DESC LIMIT 1")->fetchColumn();
            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                return 'INV-' . str_pad((int)$m[1] + 1, 4, '0', STR_PAD_LEFT);
            }
        } catch (Exception $e) {}
        return 'INV-0001';
    }
}

// ---- Cashbook / Bank helpers ----
if (!function_exists('recordCashInflow')) {
    function recordCashInflow($pdo, $date, $amount, $desc, $ref_type, $ref_id, $user_id) {
        try {
            $day = $pdo->prepare("SELECT id FROM cash_book_daily WHERE date = ?");
            $day->execute([$date]);
            $daily_id = $day->fetchColumn();
            if (!$daily_id) {
                $daily_id = insert('cash_book_daily', [
                    'date' => $date, 'opening_balance' => 0,
                    'total_inflow' => 0, 'total_outflow' => 0,
                    'closing_balance' => 0, 'status' => 'open',
                    'created_by' => $user_id, 'created_at' => date('Y-m-d'),
                ]);
            }
            insert('cash_book', [
                'daily_id' => $daily_id, 'transaction_date' => $date,
                'transaction_type' => 'inflow', 'amount' => $amount,
                'description' => $desc, 'reference_type' => $ref_type,
                'reference_id' => $ref_id, 'created_by' => $user_id,
                'created_at' => date('Y-m-d'),
            ]);
            recomputeCashDayTotals($pdo, (int)$daily_id);
        } catch (Exception $e) {}
    }
}
if (!function_exists('recordCashOutflow')) {
    function recordCashOutflow($pdo, $date, $amount, $desc, $ref_type, $ref_id, $user_id) {
        try {
            $day = $pdo->prepare("SELECT id FROM cash_book_daily WHERE date = ?");
            $day->execute([$date]);
            $daily_id = $day->fetchColumn();
            if (!$daily_id) {
                $daily_id = insert('cash_book_daily', [
                    'date' => $date, 'opening_balance' => 0,
                    'total_inflow' => 0, 'total_outflow' => 0,
                    'closing_balance' => 0, 'status' => 'open',
                    'created_by' => $user_id, 'created_at' => date('Y-m-d'),
                ]);
            }
            insert('cash_book', [
                'daily_id' => $daily_id, 'transaction_date' => $date,
                'transaction_type' => 'outflow', 'amount' => $amount,
                'description' => $desc, 'reference_type' => $ref_type,
                'reference_id' => $ref_id, 'created_by' => $user_id,
                'created_at' => date('Y-m-d'),
            ]);
            recomputeCashDayTotals($pdo, (int)$daily_id);
        } catch (Exception $e) {}
    }
}
if (!function_exists('recordBankInflow')) {
    function recordBankInflow($pdo, $date, $amount, $desc, $ref_type, $ref_id, $user_id, $bank_id) {
        try {
            insert('bank_transactions', [
                'bank_account_id' => $bank_id, 'transaction_date' => $date,
                'transaction_type' => 'deposit', 'amount' => $amount,
                'description' => $desc, 'reference_type' => $ref_type,
                'reference_id' => $ref_id, 'created_by' => $user_id,
                'created_at' => date('Y-m-d'),
            ]);
            $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance + ? WHERE id = ?")
                ->execute([$amount, $bank_id]);
        } catch (Exception $e) {}
    }
}
if (!function_exists('recordBankOutflow')) {
    function recordBankOutflow($pdo, $date, $amount, $desc, $ref_type, $ref_id, $user_id, $bank_id) {
        try {
            insert('bank_transactions', [
                'bank_account_id' => $bank_id, 'transaction_date' => $date,
                'transaction_type' => 'withdrawal', 'amount' => $amount,
                'description' => $desc, 'reference_type' => $ref_type,
                'reference_id' => $ref_id, 'created_by' => $user_id,
                'created_at' => date('Y-m-d'),
            ]);
            $pdo->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?")
                ->execute([$amount, $bank_id]);
        } catch (Exception $e) {}
    }
}
if (!function_exists('recomputeCashDayTotals')) {
    function recomputeCashDayTotals($pdo, $daily_id) {
        try {
            $r = $pdo->prepare("SELECT opening_balance FROM cash_book_daily WHERE id = ?");
            $r->execute([$daily_id]);
            $opening = (float)$r->fetchColumn();
            $in_s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_book WHERE daily_id = ? AND transaction_type = 'inflow'");
            $in_s->execute([$daily_id]); $total_in = (float)$in_s->fetchColumn();
            $out_s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM cash_book WHERE daily_id = ? AND transaction_type = 'outflow'");
            $out_s->execute([$daily_id]); $total_out = (float)$out_s->fetchColumn();
            $pdo->prepare("UPDATE cash_book_daily SET total_inflow=?, total_outflow=?, closing_balance=? WHERE id=?")
                ->execute([$total_in, $total_out, $opening + $total_in - $total_out, $daily_id]);
        } catch (Exception $e) {}
    }
}
if (!function_exists('recomputeCashDailyFrom')) {
    function recomputeCashDailyFrom($pdo, $from_date) {
        try {
            $rows = $pdo->prepare("SELECT id FROM cash_book_daily WHERE date >= ? ORDER BY date ASC");
            $rows->execute([$from_date]);
            foreach ($rows->fetchAll() as $r) recomputeCashDayTotals($pdo, (int)$r['id']);
        } catch (Exception $e) {}
    }
}

// ---- Customer balance & Ledger Sync ----
if (!function_exists('syncCustomerLedger')) {
    function syncCustomerLedger($pdo, $customer_id) {
        if (!$pdo || $customer_id <= 0) return 0.0;
        try {
            // Get customer details
            $stmt_c = $pdo->prepare("SELECT id, name, opening_balance, created_at FROM customers WHERE id = ? LIMIT 1");
            $stmt_c->execute([$customer_id]);
            $customer = $stmt_c->fetch(PDO::FETCH_ASSOC);
            if (!$customer) return 0.0;

            $opening = (float)($customer['opening_balance'] ?? 0);

            // Payments already linked to an invoice appear as their own ledger
            // row below, so subtract them here to avoid double counting.
            $linked_pmt = [];
            $stmt_pl = $pdo->prepare("SELECT sale_id, COALESCE(SUM(amount),0) AS amt FROM customer_payments WHERE customer_id = ? AND sale_id IS NOT NULL AND sale_id > 0 GROUP BY sale_id");
            $stmt_pl->execute([$customer_id]);
            foreach ($stmt_pl->fetchAll(PDO::FETCH_ASSOC) as $lr) $linked_pmt[(int)$lr['sale_id']] = (float)$lr['amt'];

            // Fetch sales invoices
            $stmt_inv = $pdo->prepare("SELECT id, invoice_no, invoice_date, grand_total, paid_amount, balance_due, notes FROM sales_invoices WHERE customer_id = ? ORDER BY invoice_date ASC, id ASC");
            $stmt_inv->execute([$customer_id]);
            $invoices = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

            // Fetch legacy sales (if any)
            $stmt_s = $pdo->prepare("SELECT id, invoice_no, sale_date, grand_total, paid_amount, balance_amount, notes FROM sales WHERE customer_id = ? ORDER BY sale_date ASC, id ASC");
            $stmt_s->execute([$customer_id]);
            $legacy_sales = $stmt_s->fetchAll(PDO::FETCH_ASSOC);

            // Fetch payments received
            $stmt_p = $pdo->prepare("SELECT id, voucher_no, payment_date, amount, payment_method, remarks FROM customer_payments WHERE customer_id = ? ORDER BY payment_date ASC, id ASC");
            $stmt_p->execute([$customer_id]);
            $payments = $stmt_p->fetchAll(PDO::FETCH_ASSOC);

            // Build chronological transactions
            $txns = [];
            foreach ($invoices as $inv) {
                $txns[] = [
                    'date'        => $inv['invoice_date'],
                    'created_id'  => (int)$inv['id'],
                    'sort_order'  => 10,
                    'type'        => 'Sale Invoice',
                    'ref'         => $inv['invoice_no'],
                    'debit'       => (float)$inv['grand_total'],
                    'credit'      => 0.0,
                    'description' => 'Sale Invoice #' . $inv['invoice_no'] . (!empty($inv['notes']) ? ' — ' . $inv['notes'] : '')
                ];
                $invoice_credit = (float)$inv['paid_amount'] - ($linked_pmt[(int)$inv['id']] ?? 0);
                if ($invoice_credit > 0) {
                    $txns[] = [
                        'date'        => $inv['invoice_date'],
                        'created_id'  => (int)$inv['id'],
                        'sort_order'  => 11,
                        'type'        => 'Payment Received',
                        'ref'         => 'PAY-' . $inv['invoice_no'],
                        'debit'       => 0.0,
                        'credit'      => $invoice_credit,
                        'description' => 'Payment received at invoice #' . $inv['invoice_no']
                    ];
                }
            }

            foreach ($legacy_sales as $ls) {
                $txns[] = [
                    'date'        => $ls['sale_date'],
                    'created_id'  => (int)$ls['id'],
                    'sort_order'  => 10,
                    'type'        => 'Sale Invoice',
                    'ref'         => $ls['invoice_no'],
                    'debit'       => (float)$ls['grand_total'],
                    'credit'      => 0.0,
                    'description' => 'Sale #' . $ls['invoice_no'] . (!empty($ls['notes']) ? ' — ' . $ls['notes'] : '')
                ];
                if ((float)$ls['paid_amount'] > 0) {
                    $txns[] = [
                        'date'        => $ls['sale_date'],
                        'created_id'  => (int)$ls['id'],
                        'sort_order'  => 11,
                        'type'        => 'Payment Received',
                        'ref'         => 'PAY-' . $ls['invoice_no'],
                        'debit'       => 0.0,
                        'credit'      => (float)$ls['paid_amount'],
                        'description' => 'Payment received at sale #' . $ls['invoice_no']
                    ];
                }
            }

            foreach ($payments as $pmt) {
                $vouch = !empty($pmt['voucher_no']) ? $pmt['voucher_no'] : ('RCPT-' . str_pad($pmt['id'], 4, '0', STR_PAD_LEFT));
                $txns[] = [
                    'date'        => $pmt['payment_date'],
                    'created_id'  => (int)$pmt['id'],
                    'sort_order'  => 20,
                    'type'        => 'Payment Received',
                    'ref'         => $vouch,
                    'debit'       => 0.0,
                    'credit'      => (float)$pmt['amount'],
                    'description' => 'Customer payment (' . ($pmt['payment_method'] ?? 'Cash') . ')' . (!empty($pmt['remarks']) ? ' — ' . $pmt['remarks'] : '')
                ];
            }

            // Sort chronologically by date, then sort_order, then id
            usort($txns, function($a, $b) {
                $cmp = strcmp($a['date'], $b['date']);
                if ($cmp !== 0) return $cmp;
                if ($a['sort_order'] !== $b['sort_order']) return $a['sort_order'] <=> $b['sort_order'];
                return $a['created_id'] <=> $b['created_id'];
            });

            // Re-populate customer_ledgers table for this customer
            $pdo->prepare("DELETE FROM customer_ledgers WHERE customer_id = ?")->execute([$customer_id]);
            $stmt_ins = $pdo->prepare("INSERT INTO customer_ledgers (customer_id, transaction_date, transaction_type, reference_no, debit_amount, credit_amount, running_balance, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $running = $opening;
            foreach ($txns as $tx) {
                $running += ($tx['debit'] - $tx['credit']);
                $stmt_ins->execute([
                    $customer_id,
                    $tx['date'],
                    $tx['type'],
                    $tx['ref'],
                    $tx['debit'],
                    $tx['credit'],
                    $running,
                    $tx['description']
                ]);
            }

            // Sync current balance in customers table
            $pdo->prepare("UPDATE customers SET current_balance = ? WHERE id = ?")->execute([$running, $customer_id]);
            return $running;
        } catch (Exception $e) {
            return 0.0;
        }
    }
}

if (!function_exists('updateCustomerBalance')) {
    function updateCustomerBalance($pdo, $customer_id) {
        return syncCustomerLedger($pdo, $customer_id);
    }
}

// ---- Supplier balance & Ledger Sync ----
if (!function_exists('syncSupplierLedger')) {
    function syncSupplierLedger($pdo, $supplier_id) {
        if (!$pdo || $supplier_id <= 0) return 0.0;
        try {
            $stmt_s = $pdo->prepare("SELECT id, name, opening_balance, created_at FROM suppliers WHERE id = ? LIMIT 1");
            $stmt_s->execute([$supplier_id]);
            $supplier = $stmt_s->fetch(PDO::FETCH_ASSOC);
            if (!$supplier) return 0.0;

            $opening = (float)($supplier['opening_balance'] ?? 0);

            // Payments already linked to a purchase appear as their own ledger
            // row below, so subtract them here to avoid double counting.
            $linked_pmt = [];
            $stmt_pl = $pdo->prepare("SELECT purchase_id, COALESCE(SUM(amount),0) AS amt FROM supplier_payments WHERE supplier_id = ? AND purchase_id IS NOT NULL AND purchase_id > 0 GROUP BY purchase_id");
            $stmt_pl->execute([$supplier_id]);
            foreach ($stmt_pl->fetchAll(PDO::FETCH_ASSOC) as $lr) $linked_pmt[(int)$lr['purchase_id']] = (float)$lr['amt'];

            // Fetch purchases
            $stmt_pur = $pdo->prepare("SELECT id, bill_no, purchase_date, grand_total, paid_amount, balance_amount, payment_type, notes FROM purchases WHERE supplier_id = ? ORDER BY purchase_date ASC, id ASC");
            $stmt_pur->execute([$supplier_id]);
            $purchases = $stmt_pur->fetchAll(PDO::FETCH_ASSOC);

            // Fetch supplier payments
            $stmt_pmt = $pdo->prepare("SELECT id, voucher_no, payment_date, amount, payment_method, remarks FROM supplier_payments WHERE supplier_id = ? ORDER BY payment_date ASC, id ASC");
            $stmt_pmt->execute([$supplier_id]);
            $payments = $stmt_pmt->fetchAll(PDO::FETCH_ASSOC);

            // Build chronological transactions
            // Standard Accounting for Supplier (Account Payable):
            // Credit (Cr) = Purchases (increases what we owe)
            // Debit (Dr)  = Payments made to supplier (decreases what we owe)
            // Running balance = Previous + Credit - Debit
            $txns = [];
            foreach ($purchases as $pur) {
                $txns[] = [
                    'date'        => $pur['purchase_date'],
                    'created_id'  => (int)$pur['id'],
                    'sort_order'  => 10,
                    'type'        => 'Purchase Bill',
                    'ref'         => $pur['bill_no'],
                    'debit'       => 0.0,
                    'credit'      => (float)$pur['grand_total'],
                    'description' => 'Purchase Bill #' . $pur['bill_no'] . (!empty($pur['notes']) ? ' — ' . $pur['notes'] : '')
                ];
                $purchase_debit = (float)$pur['paid_amount'] - ($linked_pmt[(int)$pur['id']] ?? 0);
                if ($purchase_debit > 0) {
                    $txns[] = [
                        'date'        => $pur['purchase_date'],
                        'created_id'  => (int)$pur['id'],
                        'sort_order'  => 11,
                        'type'        => 'Payment Made',
                        'ref'         => 'PAY-' . $pur['bill_no'],
                        'debit'       => $purchase_debit,
                        'credit'      => 0.0,
                        'description' => 'Payment made with bill #' . $pur['bill_no']
                    ];
                }
            }

            foreach ($payments as $pmt) {
                $vouch = !empty($pmt['voucher_no']) ? $pmt['voucher_no'] : ('PAY-' . str_pad($pmt['id'], 4, '0', STR_PAD_LEFT));
                $pdate = (!empty($pmt['payment_date']) && $pmt['payment_date'] !== '0000-00-00') ? $pmt['payment_date'] : date('Y-m-d');
                $txns[] = [
                    'date'        => $pdate,
                    'created_id'  => (int)$pmt['id'],
                    'sort_order'  => 20,
                    'type'        => 'Payment Made',
                    'ref'         => $vouch,
                    'debit'       => (float)$pmt['amount'],
                    'credit'      => 0.0,
                    'description' => 'Supplier payment (' . ($pmt['payment_method'] ?? 'Cash') . ')' . (!empty($pmt['remarks']) ? ' — ' . $pmt['remarks'] : '')
                ];
            }

            usort($txns, function($a, $b) {
                $cmp = strcmp($a['date'], $b['date']);
                if ($cmp !== 0) return $cmp;
                if ($a['sort_order'] !== $b['sort_order']) return $a['sort_order'] <=> $b['sort_order'];
                return $a['created_id'] <=> $b['created_id'];
            });

            // Re-populate supplier_ledgers table for this supplier
            $pdo->prepare("DELETE FROM supplier_ledgers WHERE supplier_id = ?")->execute([$supplier_id]);
            $stmt_ins = $pdo->prepare("INSERT INTO supplier_ledgers (supplier_id, transaction_date, transaction_type, reference_no, debit_amount, credit_amount, running_balance, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            $running = $opening;
            foreach ($txns as $tx) {
                $running += ($tx['credit'] - $tx['debit']);
                $stmt_ins->execute([
                    $supplier_id,
                    $tx['date'],
                    $tx['type'],
                    $tx['ref'],
                    $tx['debit'],
                    $tx['credit'],
                    $running,
                    $tx['description']
                ]);
            }

            // Sync current balance in suppliers table
            $pdo->prepare("UPDATE suppliers SET current_balance = ? WHERE id = ?")->execute([$running, $supplier_id]);
            return $running;
        } catch (Exception $e) {
            return 0.0;
        }
    }
}

if (!function_exists('updateSupplierBalance')) {
    function updateSupplierBalance($pdo, $supplier_id) {
        return syncSupplierLedger($pdo, $supplier_id);
    }
}

// ---- Area helpers ----
if (!function_exists('currentUserAreas')) {
    function currentUserAreas($pdo) {
        return null; // Admin sees all
    }
}
if (!function_exists('currentUserArea')) {
    function currentUserArea($pdo) {
        return '';
    }
}
if (!function_exists('allKnownAreas')) {
    function allKnownAreas($pdo) {
        try {
            return $pdo->query("SELECT name FROM areas WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) { return []; }
    }
}

// ---- Role helpers ----
if (!function_exists('isAdmin')) {
    function isAdmin() {
        $role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
        return in_array($role, ['admin'], true);
    }
}
if (!function_exists('isSalesTeam')) {
    function isSalesTeam() {
        $role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
        return in_array($role, ['manager', 'operator', 'salesman'], true);
    }
}
if (!function_exists('isSalesman')) {
    function isSalesman() {
        $role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
        return in_array($role, ['salesman', 'salesperson', 'order_booker', 'booker'], true);
    }
}
if (!function_exists('canAddCustomer')) {
    function canAddCustomer() {
        $role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
        return in_array($role, ['admin', 'manager', 'operator', 'salesman', 'salesperson', 'order_booker', 'booker'], true);
    }
}
if (!function_exists('requireRole')) {
    function requireRole(array $roles = ['admin']) {
        $role = strtolower($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
        $allowed = array_map('strtolower', (array)$roles);
        if (!in_array($role, $allowed, true)) {
            $_SESSION['error'] = 'Access denied. You do not have permission to access this page.';
            $target = defined('BASE_URL') ? BASE_URL . 'index.php' : 'index.php';
            header('Location: ' . $target);
            exit;
        }
    }
}

if (!function_exists('roleLabel')) {
    function roleLabel($role = null) {
        if ($role === null) $role = $_SESSION['user_role'] ?? 'Admin';
        $labels = [
            'admin'        => 'Admin',
            'manager'      => 'Manager',
            'cashier'      => 'Cashier',
            'salesperson'  => 'Salesperson',
            'accountant'   => 'Accountant',
            'operator'     => 'Operator',
            'salesman'     => 'Salesman',
            'general'      => 'General',
        ];
        $key = strtolower($role);
        return         $labels[$key] ?? ucfirst(str_replace('_', ' ', $role));
    }
}

if (!function_exists('getReturnNetJoinSql')) {
    /**
     * LEFT JOIN fragment that attaches a per-invoice `returned_amount` to a
     * `sales_invoices <alias>` query.
     *
     * Commission is earned on NET sales, so completed returns must be deducted,
     * otherwise a salesman keeps commission on goods they already returned.
     *
     * Only 'Good / Resalable' items count — damaged/defective goods never get
     * re-sold, so they must not reduce a commission that was genuinely earned.
     *
     * NB: sale_return_items.return_id is the column the app actually writes to;
     * sale_return_id is legacy and always NULL.
     *
     * Usage:  ... FROM sales_invoices si <this> WHERE ...
     *         then read: COALESCE(ri.returned, 0) AS returned_amount
     */
    function getReturnNetJoinSql($alias = 'si') {
        return "LEFT JOIN (
                    SELECT sr.sale_id, SUM(sri.total_price) AS returned
                    FROM sale_returns sr
                    JOIN sale_return_items sri ON sri.return_id = sr.id
                    WHERE sr.status = 'Completed' AND sri.`condition` = 'Good / Resalable'
                    GROUP BY sr.sale_id
                 ) ri ON ri.sale_id = {$alias}.id";
    }
}

if (!function_exists('netSaleAmount')) {
    /**
     * Commission base for a single invoice: gross minus returned, floored at 0
     * so an over-return can never manufacture a negative commission.
     */
    function netSaleAmount($gross, $returned) {
        return max(0.0, (float)$gross - (float)$returned);
    }
}



if (!function_exists('currentSalesmanForUser')) {
    function currentSalesmanForUser($pdo, $user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0 || !$pdo) return null;
        try {
            $st = $pdo->prepare("SELECT id, full_name, commission_rate FROM employees WHERE user_id = :uid AND employee_type = 'salesman' AND status = 1 LIMIT 1");
            $st->execute(['uid' => $user_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (Exception $e) { return null; }
    }
}

if (!function_exists('currentBookerId')) {
    function currentBookerId($pdo) {
        if (isAdmin()) return null;
        static $cached_id = false;
        if ($cached_id === false) {
            $cached_id = null;
            $uid = (int)($_SESSION['user_id'] ?? 0);
            if ($uid > 0 && $pdo) {
                try {
                    $st = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? LIMIT 1");
                    $st->execute([$uid]);
                    $emp_id = $st->fetchColumn();
                    if ($emp_id) {
                        $cached_id = (int)$emp_id;
                    }
                } catch (Exception $e) {}
            }
        }
        return $cached_id;
    }
}

if (!function_exists('currentSalesmanForUser')) {
    function currentSalesmanForUser($pdo, $user_id) {
        $user_id = (int)$user_id;
        if ($user_id <= 0 || !$pdo) return null;
        try {
            $st = $pdo->prepare("SELECT id, full_name, commission_rate FROM employees WHERE user_id = :uid AND employee_type = 'salesman' AND status = 1 LIMIT 1");
            $st->execute(['uid' => $user_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ? $row : null;
        } catch (Exception $e) { return null; }
    }
}

// ---- Activity log ----
if (!function_exists('logActivity')) {
    function logActivity($pdo, $action, $module, $ref_id, $description) {
        try {
            $pdo->prepare("INSERT INTO activity_logs (user_id, action, module, reference_id, description, created_at) VALUES (?,?,?,?,?,?) ")
                ->execute([$_SESSION['user_id'] ?? 0, $action, $module, $ref_id, $description, date('Y-m-d H:i:s')]);
        } catch (Exception $e) {}
    }
}

// ---- Expiry badge ----
if (!function_exists('get_expiry_badge')) {
    function get_expiry_badge($expiryDate) {
        $today = new DateTime();
        $expiry = new DateTime($expiryDate);
        $days = (int)$today->diff($expiry)->format('%r%a');
        if ($days < 0) return '<span class="badge badge-danger">Expired</span>';
        if ($days <= 30) return '<span class="badge badge-danger">' . $days . 'd left</span>';
        if ($days <= 90) return '<span class="badge badge-warning">' . $days . 'd left</span>';
        return '<span class="badge badge-success">' . formatDate($expiryDate) . '</span>';
    }
}

// ---- role_map for employee types ----
$role_map = [
    'salesman' => 'salesman',
    'general'  => 'Operator',
];

// ---- Delivery Challan helpers ----
if (!function_exists('createDeliveryChallanForInvoice')) {
    function createDeliveryChallanForInvoice($pdo, $invoice_id) {
        if (!$pdo || $invoice_id <= 0) return null;
        try {
            // Check if already exists
            $chk = $pdo->prepare("SELECT id FROM delivery_challans WHERE invoice_id = ? LIMIT 1");
            $chk->execute([$invoice_id]);
            $existing_id = $chk->fetchColumn();
            if ($existing_id) return $existing_id;

            $stmt_inv = $pdo->prepare("
                SELECT si.*, c.phone as cust_phone, c.address as cust_address 
                FROM sales_invoices si 
                LEFT JOIN customers c ON c.id = si.customer_id 
                WHERE si.id = ? LIMIT 1
            ");
            $stmt_inv->execute([$invoice_id]);
            $inv = $stmt_inv->fetch(PDO::FETCH_ASSOC);
            if (!$inv) return null;

            $inv_no = $inv['invoice_no'];
            $ch_no  = 'DC-' . str_replace('INV-', '', $inv_no);

            // Fetch items
            $stmt_items = $pdo->prepare("SELECT product_id, item_name, batch_no, quantity FROM sale_items WHERE invoice_id = ?");
            $stmt_items->execute([$invoice_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

            $total_items = count($items);
            $total_qty   = array_sum(array_column($items, 'quantity'));
            $cartons     = max(1, (int)ceil($total_qty / 50));

            $stmt_ins = $pdo->prepare("
                INSERT INTO delivery_challans (
                    challan_no, invoice_id, invoice_no, challan_date, 
                    customer_name, customer_phone, delivery_address, 
                    route_name, booker_name, vehicle_no, driver_name, driver_phone, 
                    total_cartons, total_items, total_quantity, delivery_status, notes, created_by
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, 'Delivery Van', 'Logistics Rider', '',
                    ?, ?, ?, 'Dispatched', ?, ?
                )
            ");
            $stmt_ins->execute([
                $ch_no, $invoice_id, $inv_no, $inv['invoice_date'],
                $inv['customer_name'], $inv['cust_phone'] ?? '', $inv['cust_address'] ?? '',
                $inv['route_name'] ?? 'Local', $inv['booker_name'] ?? 'Direct',
                $cartons, $total_items, $total_qty, $inv['notes'] ?? '', $inv['created_by'] ?? 1
            ]);
            $ch_id = $pdo->lastInsertId();

            $stmt_item_ins = $pdo->prepare("
                INSERT INTO delivery_challan_items (challan_id, product_id, item_name, batch_no, quantity, unit_type, remarks)
                VALUES (?, ?, ?, ?, ?, 'Pack', '')
            ");
            foreach ($items as $it) {
                $stmt_item_ins->execute([
                    $ch_id, $it['product_id'] ?? null, $it['item_name'], $it['batch_no'] ?? '', $it['quantity']
                ]);
            }
            return $ch_id;
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('syncDeliveryChallansFromInvoices')) {
    function syncDeliveryChallansFromInvoices($pdo) {
        if (!$pdo) return;
        try {
            $stmt = $pdo->query("
                SELECT id FROM sales_invoices 
                WHERE id NOT IN (SELECT COALESCE(invoice_id, 0) FROM delivery_challans WHERE invoice_id IS NOT NULL)
                ORDER BY id ASC
            ");
            $missing = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($missing as $inv_id) {
                createDeliveryChallanForInvoice($pdo, (int)$inv_id);
            }
        } catch (Exception $e) {}
    }
}

