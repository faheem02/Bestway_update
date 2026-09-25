<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Pay Supplier';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);

try { $bank_accounts = $pdo->query("SELECT id, account_title AS account_name, bank_name FROM bank_accounts WHERE status='Active' ORDER BY id")->fetchAll(); }
catch (Exception $e) { $bank_accounts = []; }

try { $suppliers = $pdo->query("SELECT id, name, current_balance FROM suppliers WHERE status=1 ORDER BY name")->fetchAll(); }
catch (Exception $e) { $suppliers = []; }

try {
    $payments = $pdo->query("SELECT r.*, s.name AS supp_name, ba.account_title AS account_name, p.bill_no AS inv_no
        FROM supplier_payments r
        JOIN suppliers s ON s.id = r.supplier_id
        LEFT JOIN bank_accounts ba ON ba.id = r.bank_account_id
        LEFT JOIN purchases p ON p.id = r.purchase_id
        ORDER BY r.payment_date DESC, r.id DESC")->fetchAll();
} catch (Exception $e) { $payments = []; }

$preselect = (int)($_GET['supplier_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $tdate = $_POST['transaction_date'] ?: date('Y-m-d');
    $payment_method = ($_POST['payment_method'] ?? 'cash') === 'bank' ? 'Bank Transfer' : 'Cash';
    $bank_id = $payment_method === 'Bank Transfer' ? ($_POST['bank_account_id'] ?: null) : null;
    $remarks = trim($_POST['description'] ?? '') ?: 'Supplier payment';
    $purchase_id = (int)($_POST['purchase_id'] ?? 0) ?: null;

    if (!$supplier_id) { redirect('pay_amount.php', 'Select a supplier', 'error'); }
    if ($amount <= 0) { redirect('pay_amount.php', 'Enter a valid amount', 'error'); }

    $supplier = getById('suppliers', $supplier_id);
    if (!$supplier) { redirect('pay_amount.php', 'Supplier not found', 'error'); }

    $voucher = 'PAY-' . str_pad((countRows('supplier_payments') + 1), 4, '0', STR_PAD_LEFT);

    $pdo->beginTransaction();
    try {
        $pid = insert('supplier_payments', [
            'voucher_no'      => $voucher,
            'payment_date'    => $tdate,
            'supplier_id'     => $supplier_id,
            'purchase_id'     => $purchase_id,
            'amount'          => $amount,
            'payment_method'  => $payment_method,
            'bank_account_id' => $bank_id,
            'remarks'         => $remarks,
            'created_by'      => $_SESSION['user_id'] ?? 1,
        ]);
        if ($purchase_id > 0) {
            // Recompute invoice paid/balance from ALL payments recorded against this bill
            $paidTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE purchase_id = " . (int)$purchase_id)->fetchColumn();
            $pdo->prepare("UPDATE purchases SET paid_amount = ?, balance_amount = GREATEST(0, grand_total - ?), payment_status = CASE WHEN GREATEST(0, grand_total - ?) <= 0.001 THEN 'Paid' WHEN ? > 0 THEN 'Partial' ELSE 'Unpaid' END WHERE id = ?")
                ->execute([$paidTotal, $paidTotal, $paidTotal, $paidTotal, $purchase_id]);
        }
        syncSupplierLedger($pdo, $supplier_id);

        $desc = 'Supplier payment: ' . $supplier['name'] . ' (PKR ' . formatCurrency($amount) . ')';
        if ($bank_id) {
            recordBankOutflow($pdo, $tdate, $amount, $desc, 'supplier_payment', $pid, $_SESSION['user_id'] ?? 1, $bank_id);
        } else {
            recordCashOutflow($pdo, $tdate, $amount, $desc, 'supplier_payment', $pid, $_SESSION['user_id'] ?? 1);
        }

        $pdo->commit();
        redirect('pay_amount.php', 'Paid PKR ' . formatCurrency($amount) . ' to ' . $supplier['name']);
    } catch (Exception $e) {
        $pdo->rollBack();
        redirect('pay_amount.php', 'Error: ' . $e->getMessage(), 'error');
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="row mb-3 d-print-none">
  <div class="col-md-8">
    <div class="alert alert-danger alert-dismissible fade show py-2 mb-0" role="alert">
      <i class="fas fa-arrow-up"></i> <strong>Pay Supplier</strong> &nbsp;Record payment for goods purchased from the supplier.
      <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
    </div>
  </div>
  <div class="col-md-4 text-md-right">
    <button type="button" class="btn btn-outline-secondary shadow-sm mr-2" onclick="window.print()">
      <i class="fas fa-print"></i> Print
    </button>
    <button type="button" class="btn btn-danger shadow-sm" data-toggle="modal" data-target="#payModal">
      <i class="fas fa-money-bill-alt"></i> Pay Amount
    </button>
  </div>
</div>

<!-- Printable header -->
<div class="d-none d-print-block mb-3 text-center">
  <h4 class="font-weight-bold mb-0" style="color:#0f172a;">Bestway Distribution</h4>
  <small class="text-muted">Wholesale Medicine &amp; Pharma Distribution</small>
  <h5 class="font-weight-bold text-primary mt-2 mb-0">SUPPLIER PAYMENTS</h5>
  <small>Printed on <?=formatDate(date('Y-m-d'))?></small>
</div>

<div class="modal fade" id="payModal" tabindex="-1" role="dialog" aria-labelledby="payModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title" id="payModalLabel"><i class="fas fa-arrow-up text-danger"></i> Pay Supplier</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Select Supplier *</label>
              <div class="ac-wrap">
                <input type="text" id="supplierSearch" class="form-control" placeholder="Type supplier name to search..." autocomplete="off">
                <input type="hidden" name="supplier_id" id="supplier_id">
                <div class="ac-list" id="supplierList"></div>
              </div>
              <small class="text-danger d-none" id="supplierError"><i class="fas fa-exclamation-circle"></i> Please select a supplier from the suggestions.</small>
              <small class="text-muted font-weight-bold d-block mt-1" id="partyBalance"></small>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Amount (PKR) *</label>
              <input type="number" name="amount" step="0.01" min="0.01" class="form-control" required placeholder="0.00">
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label">Date *</label>
              <input type="date" name="transaction_date" class="form-control datepicker" value="<?=date('Y-m-d')?>" required>
            </div>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label class="form-label">Method</label>
              <select name="payment_method" id="payMethod" class="form-control">
                <option value="cash">Cash</option>
                <option value="bank">Bank</option>
              </select>
            </div>
            <div class="col-md-4 mb-3" id="bankDiv" style="display:none;">
              <label class="form-label">Bank Account</label>
              <select name="bank_account_id" class="form-control">
                <?php foreach ($bank_accounts as $ba): ?>
                <option value="<?=$ba['id']?>"><?=htmlspecialchars($ba['account_name'])?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 mb-3">
              <label class="form-label">Notes / Description</label>
              <input type="text" name="description" class="form-control" value="Supplier payment">
            </div>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Pay Against Invoice <small class="text-muted">(optional)</small></label>
              <select name="purchase_id" id="purchaseSelect" class="form-control">
                <option value="">— General / No invoice —</option>
              </select>
              <small class="text-muted" id="purchaseHint" style="display:none;">Selecting an invoice pre-fills its due amount.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger"><i class="fas fa-check"></i> Confirm Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="card shadow">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-list"></i> Suppliers with Balance (To Pay)</h6>
    <input type="text" id="suppSearch" class="form-control form-control-sm d-print-none" placeholder="Search supplier" style="max-width:240px;">
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered table-hover" id="suppBalanceTable">
        <thead>
          <tr><th>Supplier</th><th>Phone</th><th>Address</th><th class="text-right">Payable (PAY)</th><th class="d-print-none"></th></tr>
        </thead>
        <tbody>
          <?php $has = false; foreach ($suppliers as $s) { if ((float)$s['current_balance'] <= 0) continue; $has = true; $supp = getById('suppliers',$s['id']); ?>
            <tr>
              <td class="font-weight-bold"><?=htmlspecialchars($s['name'])?></td>
              <td><?=htmlspecialchars($supp['phone'] ?? '-')?></td>
              <td><?=htmlspecialchars($supp['address'] ?? '-')?></td>
              <td class="text-right text-danger font-weight-bold">PKR <?=formatCurrency($s['current_balance'])?></td>
              <td class="text-right d-print-none"><a href="#" data-id="<?=$s['id']?>" data-name="<?=htmlspecialchars($s['name'])?>" class="btn btn-sm btn-outline-danger pick-party"><i class="fas fa-arrow-up"></i> Pay</a></td>
            </tr>
          <?php } if (!$has): ?><tr><td colspan="5" class="text-center text-muted py-3">No supplier payable balance. Everything is settled.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card shadow mt-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-history"></i> All Payments (<?=count($payments)?>)</h6>
    <input type="text" id="paySearch" class="form-control form-control-sm d-print-none" placeholder="Search supplier / date / remarks" style="max-width:260px;">
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered" id="payHistoryTable">
        <thead>
          <tr><th>#</th><th>Date</th><th>Supplier</th><th>Invoice</th><th>Description</th><th>Method</th><th class="text-right">Amount</th></tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No payments made yet.</td></tr>
          <?php else: $i = 0; foreach ($payments as $r): $i++; ?>
            <tr>
              <td><?=$i?></td>
              <td><?=formatDate($r['payment_date'])?></td>
              <td class="font-weight-bold"><?=htmlspecialchars($r['supp_name'])?></td>
              <td><?= $r['inv_no'] ? '<code>' . htmlspecialchars($r['inv_no']) . '</code>' : '<span class="text-muted">&mdash;</span>' ?></td>
              <td><?=htmlspecialchars($r['remarks'] ?? '-')?></td>
              <td>
                <?php if (strtolower((string)$r['payment_method']) == 'bank transfer'): ?>
                  <span class="badge badge-info">Bank</span> <?=htmlspecialchars($r['account_name'] ?? '')?>
                <?php else: ?>
                  <span class="badge badge-danger">Cash</span>
                <?php endif; ?>
              </td>
              <td class="text-right text-danger font-weight-bold">PKR <?=formatCurrency($r['amount'])?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function mtEsc(s){
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function mtHideList($list){ $list.empty().hide(); }
function mtShowBalance(bal){
  bal = Number(bal);
  if (bal === 0) { $('#partyBalance').html('<span class="badge badge-secondary">Balance: Settled (PKR 0.00)</span>'); return; }
  $('#partyBalance').html(bal > 0
    ? '<span class="badge badge-danger">Payable: PKR ' + bal.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>'
    : '<span class="badge badge-success">Advance: PKR ' + Math.abs(bal).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) + '</span>');
}
function mtRenderList($list, items){
  $list.empty();
  if (!items || !items.length) {
    $list.append('<div class="ac-item ac-empty">No matching record found</div>');
  } else {
    $.each(items, function(i, it){
      var sub = [];
      if (it.phone) sub.push('Phone: ' + mtEsc(it.phone));
      if (it.address) sub.push(mtEsc(it.address));
      $list.append($('<div class="ac-item" data-id="' + it.id + '">' +
        '<span class="ac-name">' + mtEsc(it.name) + '</span>' +
        (sub.length ? '<small class="ac-sub">' + sub.join(' &middot; ') + '</small>' : '') +
        '</div>'));
    });
  }
  $list.show();
}

$(document).ready(function(){
  var preselectId = <?= (int)$preselect ?: 0 ?>;
  if (preselectId) { $('#payModal').modal('show'); }

  function pickSupplier(id, name){
    $('#supplier_id').val(id);
    $('#supplierSearch').val(name);
    $('#supplierError').addClass('d-none');
    mtHideList($('#supplierList'));
    var $sel = $('#purchaseSelect');
    var prev = $sel.val();
    $sel.empty().append('<option value="">— General / No invoice —</option>');
    $('#purchaseHint').hide();
    $.get('ajax_supplier_balance.php', {id: id}, function(data){
      mtShowBalance(data);
      // Fully settled / advance supplier has no bills to pay against
      if (Number(data) <= 0) {
        $('#purchaseHint').html('<em>Supplier balance is settled / in advance &mdash; no bills to pay against.</em>').show();
        return;
      }
      // Load this supplier's open purchase invoices
      $.getJSON('ajax_supplier_purchase_due.php', {id: id}, function(invs){
        $.each(invs, function(i, p){
          $sel.append('<option value="' + p.id + '" data-due="' + p.due_amount + '">' + mtEsc(p.invoice_no) + ' (Due: PKR ' + Number(p.due_amount).toLocaleString('en-US', {minimumFractionDigits:2}) + ')</option>');
        });
        if (prev && $sel.find('option[value="' + prev + '"]').length) {
          $sel.val(prev);
        } else {
          $sel.val('');
        }
        $sel.trigger('change');
      });
    });
  }

  $('#purchaseSelect').on('change', function(){
    var due = $(this).find(':selected').data('due');
    if (due !== undefined && due > 0) {
      $('input[name="amount"]').val(due);
      $('#purchaseHint').show();
    } else {
      $('#purchaseHint').hide();
    }
  });

  $('#payMethod').change(function(){ $('#bankDiv').toggle(this.value === 'bank'); });

  var supTimer = null;
  $('#supplierSearch').on('input', function(){
    var q = $.trim(this.value);
    clearTimeout(supTimer);
    if (!q) {
      $('#supplier_id').val('');
      $('#partyBalance').empty();
      $('#supplierError').addClass('d-none');
      mtHideList($('#supplierList'));
      $('#purchaseSelect').empty().append('<option value="">— General / No invoice —</option>');
      return;
    }
    supTimer = setTimeout(function(){
      $.get('ajax_supplier_search.php', {q: q}, function(data){
        mtRenderList($('#supplierList'), data);
      });
    }, 250);
  });

  $('#supplierList').on('mousedown click', '.ac-item', function(e){
    e.preventDefault();
    if ($(this).hasClass('ac-empty')) return;
    pickSupplier($(this).data('id'), $(this).find('.ac-name').text());
  });

  $(document).on('keydown', '#supplierSearch', function(e){
    var $list = $('#supplierList');
    var items = $list.find('.ac-item:not(.ac-empty)');
    if (!$list.is(':visible') || !items.length) return;
    var idx = items.index(items.filter('.active'));
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      var dir = e.key === 'ArrowDown' ? 1 : -1;
      idx = (idx + dir + items.length) % items.length;
      items.removeClass('active').eq(idx).addClass('active');
    } else if (e.key === 'Enter') {
      e.preventDefault();
      var target = idx >= 0 ? items.eq(idx) : items.first();
      if (target.length) target.trigger('mousedown');
    } else if (e.key === 'Escape') {
      mtHideList($list);
    }
  });

  $(document).on('mouseover', '.ac-item', function(){
    $(this).addClass('active').siblings().removeClass('active');
  });
  $(document).on('mousedown', function(e){
    if (!$(e.target).closest('.ac-wrap').length) {
      $('.ac-list').empty().hide();
    }
  });

  // Preselect from ?supplier_id= (e.g. from Supplier Ledger "Pay" links)
  if (preselectId) {
    <?php $sel_s = getById('suppliers', $preselect); ?>
    pickSupplier(preselectId, <?= json_encode($sel_s['name'] ?? '') ?>);
  }

  // "To Pay" table rows
  $('.pick-party').click(function(e){
    e.preventDefault();
    pickSupplier($(this).data('id'), $(this).data('name'));
    $('#payModal').modal('show');
  });

  $('#payModal form').on('submit', function(e){
    if (!$('#supplier_id').val()) {
      e.preventDefault();
      $('#supplierError').removeClass('d-none');
      $('#supplierSearch').focus();
    }
  });

  $('#suppSearch').on('keyup', function(){
    var q = $(this).val().toLowerCase();
    $('#suppBalanceTable tbody tr').each(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
    });
  });
  $('#paySearch').on('keyup', function(){
    var q = $(this).val().toLowerCase();
    $('#payHistoryTable tbody tr').each(function(){
      $(this).toggle($(this).text().toLowerCase().indexOf(q) > -1);
    });
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>