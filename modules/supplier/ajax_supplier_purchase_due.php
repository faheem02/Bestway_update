<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode([]); exit; }

// Compute remaining due from the ACTUAL payments recorded against each bill,
// so fully-paid / stale invoices never show up here.
$rows = $pdo->prepare("
    SELECT p.id,
           p.bill_no AS invoice_no,
           p.purchase_date,
           p.grand_total AS total_amount,
           COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.purchase_id = p.id), 0) AS paid_amount,
           GREATEST(0, p.grand_total - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.purchase_id = p.id), 0)) AS due_amount
    FROM purchases p
    WHERE p.supplier_id = ?
      AND p.grand_total - COALESCE((SELECT SUM(sp.amount) FROM supplier_payments sp WHERE sp.purchase_id = p.id), 0) > 0.001
    ORDER BY p.purchase_date ASC, p.id ASC
");
$rows->execute([$id]);
echo json_encode($rows->fetchAll());