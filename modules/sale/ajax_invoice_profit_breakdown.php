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
ensureSaleItemCostColumn($pdo); // batch-wise costing column (sale_items.cost_price)

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode(['error' => 'Invalid invoice ID.']); exit; }

try {
    $stmt = $pdo->prepare("
        SELECT si.*, c.name AS customer_name, c.shop_name AS customer_shop, c.phone AS customer_phone,
               c.area AS customer_area, b.full_name AS order_taker_name /* employees has full_name, not name (query always failed) */
        FROM sales_invoices si
        LEFT JOIN customers c ON c.id = si.customer_id
        LEFT JOIN employees b ON b.id = si.booker_id
        WHERE si.id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) { echo json_encode(['error' => 'Invoice not found.']); exit; }

    $it_stmt = $pdo->prepare("
        SELECT si.product_id, si.item_name AS product_name, COALESCE(p.product_code, '') AS product_code,
               si.quantity, si.bonus_quantity, si.unit_price AS sale_price, si.total_amount AS sale_subtotal,
               si.cost_price AS batch_cost, COALESCE(p.stock_unit, 'Unit') AS stock_unit
        FROM sale_items si
        LEFT JOIN products p ON p.id = si.product_id
        WHERE si.invoice_id = ?
        ORDER BY si.id ASC
    ");
    $it_stmt->execute([$id]);
    $items = $it_stmt->fetchAll();

    // Fetch returns against this invoice
    $ret_stmt = $pdo->prepare("
        SELECT sri.product_id,
               COALESCE(SUM(sri.quantity), 0) AS returned_qty,
               COALESCE(SUM(sri.total_price), 0) AS returned_amount
        FROM sale_returns sr
        JOIN sale_return_items sri ON sri.return_id = sr.id
        WHERE (sr.sale_id = ? OR (sr.sale_id IS NULL AND sr.invoice_id = ?))
          AND sr.status = 'Completed'
        GROUP BY sri.product_id
    ");
    $ret_stmt->execute([$id, $id]);
    $returns_by_product = [];
    $total_returned_sale = 0;
    while ($rr = $ret_stmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$rr['product_id'];
        $returns_by_product[$pid] = [
            'qty'         => (int)$rr['returned_qty'],
            'amount'      => (float)$rr['returned_amount'],
            'remaining'   => (int)$rr['returned_qty'],
            'unit_amount' => ((int)$rr['returned_qty'] > 0) ? (float)$rr['returned_amount'] / (int)$rr['returned_qty'] : 0.0
        ];
        $total_returned_sale += (float)$rr['returned_amount'];
    }

    // Order-level discount factor: lines are stored before the invoice discount,
    // grand_total is after it. Profit must be measured on what the customer pays.
    $items_net_sum = 0.0;
    foreach ($items as $it) { $items_net_sum += (float)$it['sale_subtotal']; }
    $order_factor = ($items_net_sum > 0) ? min(1.0, (float)$invoice['grand_total'] / $items_net_sum) : 1.0;

    $total_cost = 0;
    $cost_missing_amount = 0.0; // net sale value whose batch cost is not recorded
    $response = [];
    foreach ($items as $it) {
        $pid           = (int)($it['product_id'] ?? 0);
        $qty           = (int)$it['quantity'];
        $sale_subtotal = round((float)$it['sale_subtotal'] * $order_factor, 2);
        // Batch-wise costing: cost of the batch this line was sold from, captured at sale time
        $cost_known    = ($it['batch_cost'] !== null && (float)$it['batch_cost'] > 0);
        $cost_per_unit = $cost_known ? (float)$it['batch_cost'] : 0.0;

        // Deduct this product's returns only ONCE, even if the product appears on several rows:
        // consume the remaining returned qty row by row at the per-unit return value.
        $ret_qty = 0; $ret_amt = 0.0;
        if (isset($returns_by_product[$pid]) && $returns_by_product[$pid]['remaining'] > 0) {
            $ret_qty = min($qty, $returns_by_product[$pid]['remaining']);
            $ret_amt = min($sale_subtotal, round($ret_qty * $returns_by_product[$pid]['unit_amount'], 2));
            $returns_by_product[$pid]['remaining'] -= $ret_qty;
        }

        // Net quantity and net sale value after returns
        $net_qty       = max(0, $qty - $ret_qty);
        $net_subtotal  = max(0.0, $sale_subtotal - $ret_amt);
        $cost_total    = $cost_per_unit * $net_qty;
        $profit        = $cost_known ? ($net_subtotal - $cost_total) : 0.0;
        $margin_pct    = ($cost_known && $net_subtotal > 0) ? round(($profit / $net_subtotal) * 100, 1) : 0;
        $total_cost   += $cost_total;
        if (!$cost_known) $cost_missing_amount += $net_subtotal;

        $response['items'][] = [
            'product_name'    => (string)($it['product_name'] ?: '(Item)'),
            'product_code'    => (string)$it['product_code'],
            'quantity'        => $qty,
            'returned_qty'    => $ret_qty,
            'returned_amount' => $ret_amt,
            'net_qty'         => $net_qty,
            'packaging_label' => trim(($it['bonus_quantity'] > 0 ? $qty . ' + ' . (int)$it['bonus_quantity'] . ' bonus ' : $qty . ' ') . $it['stock_unit']),
            'sale_price'      => (float)$it['sale_price'],
            'sale_subtotal'   => $net_subtotal,
            'gross_subtotal'  => $sale_subtotal,
            'purchase_price'  => $cost_per_unit,
            'cost_per_unit'   => $cost_per_unit,
            'cost_total'      => $cost_total,
            'cost_missing'    => !$cost_known,
            'profit'          => $profit,
            'margin_pct'      => $margin_pct,
        ];
    }

    $gross_sale  = (float)$invoice['grand_total'];
    $total_sale  = max(0.0, $gross_sale - $total_returned_sale);
    $discount    = (float)$invoice['discount_amount'];
    // Profit only over lines whose batch cost is known (never guess a cost)
    $costed_sale = max(0.0, $total_sale - $cost_missing_amount);
    $net_profit  = $costed_sale - $total_cost;
    $margin      = $costed_sale > 0 ? round(($net_profit / $costed_sale) * 100, 1) : 0;
    $response['cost_missing_amount'] = round($cost_missing_amount, 2);

    $response['invoice_no']      = $invoice['invoice_no'];
    $response['sale_date']       = $invoice['invoice_date'];
    $response['customer_name']   = $invoice['customer_name'] ?: ($invoice['customer_shop'] ?: 'Walk-in Customer');
    $response['customer_phone']  = $invoice['customer_phone'];
    $response['customer_area']   = $invoice['customer_area'];
    $response['order_taker_name']= $invoice['order_taker_name'] ?: $invoice['booker_name'] ?: '—';
    $response['route_name']      = $invoice['route_name'];
    $response['gross_sale']      = $gross_sale;
    $response['total_returned']  = $total_returned_sale;
    $response['total_sale']      = $total_sale;
    $response['total_cost']      = $total_cost;
    $response['net_profit']      = $net_profit;
    $response['margin_pct']      = $margin;
    $response['discount_amount'] = $discount;

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error loading profit details.']);
}