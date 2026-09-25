<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }
header('Content-Type: application/json');

$customer_id = (int)($_GET['customer_id'] ?? 0);
if (!$customer_id) { echo json_encode([]); exit; }

// Only return invoices that still have a remaining balance, so already-paid
// invoices never appear in the "Against Invoice" dropdown.
$stmt = $pdo->prepare("
    SELECT t.id, t.type, t.invoice_no, t.sale_date, t.total_amount, t.paid_amount, t.due_amount
    FROM (
        SELECT id, 'invoice' AS type, invoice_no, invoice_date AS sale_date,
               net_payable AS total_amount, paid_amount,
               GREATEST(0, net_payable - paid_amount) AS due_amount
        FROM sales_invoices
        WHERE customer_id = ?
        UNION ALL
        SELECT id, 'legacy' AS type, invoice_no, sale_date,
               grand_total AS total_amount, paid_amount,
               GREATEST(0, grand_total - paid_amount) AS due_amount
        FROM sales
        WHERE customer_id = ? AND delivery_status <> 'Cancelled'
    ) t
    WHERE t.due_amount > 0.001
    ORDER BY t.due_amount DESC, t.sale_date ASC, t.id ASC
");
$stmt->execute([$customer_id, $customer_id]);
echo json_encode($stmt->fetchAll());