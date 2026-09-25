<?php
/**
 * Bestway Wholesale Distribution - AJAX Invoice Profit Breakdown
 * Per-invoice item-level profit margin details (admin only).
 */
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) { http_response_code(401); exit; }
header('Content-Type: application/json');

if (!isAdmin()) { echo json_encode(['error' => 'Admin access required.']); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['error' => 'Invalid invoice ID.']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT si.*, c.name AS customer_name, c.shop_name AS customer_shop, c.phone AS customer_phone,
               c.area AS customer_area, b.name AS order_taker_name
        FROM sales_invoices si
        LEFT JOIN customers c ON c.id = si.customer_id
        LEFT JOIN employees b ON b.id = si.booker_id
        WHERE si.id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) { echo json_encode(['error' => 'Invoice not found.']); exit; }

    $it_stmt = $pdo->prepare("
        SELECT si.item_name AS product_name, COALESCE(p.product_code, '') AS product_code,
               si.quantity, si.bonus_quantity, si.unit_price AS sale_price, si.total_amount AS sale_subtotal,
               COALESCE(NULLIF(p.purchase_price, 0), 0) AS purchase_price, COALESCE(p.stock_unit, 'Unit') AS stock_unit
        FROM sale_items si
        LEFT JOIN products p ON p.id = si.product_id
        WHERE si.invoice_id = ?
        ORDER BY si.id ASC
    ");
    $it_stmt->execute([$id]);
    $items = $it_stmt->fetchAll();

    $total_cost = 0;
    $response = [];
    foreach ($items as $it) {
        $qty          = (int)$it['quantity'];
        $sale_subtotal = (float)$it['sale_subtotal'];
        $cost_per_unit = (float)$it['purchase_price'];
        $cost_total    = $cost_per_unit * $qty;
        $profit        = $sale_subtotal - $cost_total;
        $margin_pct    = $sale_subtotal > 0 ? round(($profit / $sale_subtotal) * 100, 1) : 0;
        $total_cost   += $cost_total;

        $response['items'][] = [
            'product_name'    => (string)($it['product_name'] ?: '(Item)'),
            'product_code'    => (string)$it['product_code'],
            'quantity'        => $qty,
            'packaging_label' => trim(($it['bonus_quantity'] > 0 ? $qty . ' + ' . (int)$it['bonus_quantity'] . ' bonus ' : $qty . ' ') . $it['stock_unit']),
            'sale_price'      => (float)$it['sale_price'],
            'sale_subtotal'   => $sale_subtotal,
            'purchase_price'  => $cost_per_unit,
            'cost_per_unit'   => $cost_per_unit,
            'cost_total'      => $cost_total,
            'profit'          => $profit,
            'margin_pct'      => $margin_pct,
        ];
    }

    $total_sale  = (float)$invoice['grand_total'];
    $discount    = (float)$invoice['discount_amount'];
    $net_profit  = $total_sale - $total_cost;
    $margin      = $total_sale > 0 ? round(($net_profit / $total_sale) * 100, 1) : 0;

    $response['invoice_no']      = $invoice['invoice_no'];
    $response['sale_date']       = $invoice['invoice_date'];
    $response['customer_name']   = $invoice['customer_name'] ?: ($invoice['customer_shop'] ?: 'Walk-in Customer');
    $response['customer_phone']  = $invoice['customer_phone'];
    $response['customer_area']   = $invoice['customer_area'];
    $response['order_taker_name']= $invoice['order_taker_name'] ?: $invoice['booker_name'] ?: '—';
    $response['route_name']      = $invoice['route_name'];
    $response['total_sale']      = $total_sale;
    $response['total_cost']      = $total_cost;
    $response['net_profit']      = $net_profit;
    $response['margin_pct']      = $margin;
    $response['discount_amount'] = $discount;

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error loading profit details.']);
}