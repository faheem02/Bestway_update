<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
$page_title = 'Supplier Ledger';
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . BASE_URL . 'login.php'); exit;
}
requireRole(['admin']);
$id = (int)($_GET['id'] ?? 0);
$supplier = $id ? getById('suppliers', $id) : null;

$ledger = [];
if ($supplier) {
    syncSupplierLedger($pdo, $id);
    $supplier = getById('suppliers', $id);

    try {
        $stmt_l = $pdo->prepare("SELECT * FROM supplier_ledgers WHERE supplier_id = ? ORDER BY transaction_date ASC, id ASC");
        $stmt_l->execute([$id]);
        $ledger_rows = $stmt_l->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { $ledger_rows = []; }

    $entries = [];
    $opening = (float)($supplier['opening_balance'] ?? 0);
    if ($opening != 0) {
        $op_date = (!empty($supplier['created_at']) && $supplier['created_at'] !== '0000-00-00 00:00:00') ? date('Y-m-d', strtotime($supplier['created_at'])) : '-';
        $entries[] = [
            'date'    => $op_date,
            'type'    => 'opening',
            'ref'     => 'Opening Balance B/F',
            'debit'   => $opening < 0 ? abs($opening) : 0,
            'credit'  => $opening > 0 ? $opening : 0,
            'balance' => $opening
        ];
    }

    foreach ($ledger_rows as $row) {
        $entries[] = [
            'date'    => $row['transaction_date'],
            'type'    => ($row['transaction_type'] === 'Purchase Bill' ? 'purchase' : 'payment'),
            'ref'     => $row['reference_no'] . (!empty($row['description']) ? ' (' . $row['description'] . ')' : ''),
            'debit'   => (float)$row['debit_amount'],
            'credit'  => (float)$row['credit_amount'],
            'balance' => (float)$row['running_balance']
        ];
    }
    $ledger = $entries;
}
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="card shadow mb-3 d-print-none">
  <div class="card-body py-2">
    <form method="get" class="form-inline" id="ledgerForm">
      <label class="mr-2 font-weight-bold">Select Supplier:</label>
      <div class="ac-wrap" style="min-width:340px;">
        <input type="hidden" name="id" id="ledgerId" value="<?= $id ?>">
        <input type="text" id="ledgerSearch" class="form-control" placeholder="Type supplier name / code / phone to search..." autocomplete="off" value="<?= $supplier ? htmlspecialchars($supplier['name'] . ' (' . ($supplier['supplier_code'] ?? '') . ')') : '' ?>">
        <div class="ac-list" id="ledgerList"></div>
      </div>
    </form>
  </div>
</div>

<?php if ($supplier): ?>
<div class="card shadow">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h6 class="mb-0"><i class="fas fa-book mr-1"></i> Ledger — <?= htmlspecialchars($supplier['name']) ?></h6>
    <div>
      <a href="pay_amount.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-sm btn-success mr-2 d-print-none"><i class="fas fa-money-bill-alt mr-1"></i> Pay Amount</a>
      <button class="btn btn-sm btn-outline-secondary d-print-none" onclick="window.print()"><i class="fas fa-print mr-1"></i> Print</button>
    </div>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-4"><strong>Phone:</strong> <?= htmlspecialchars($supplier['phone'] ?? '-') ?></div>
      <div class="col-md-4"><strong>Address:</strong> <?= htmlspecialchars($supplier['address'] ?? '-') ?></div>
      <div class="col-md-4">
        <strong>Balance:</strong>
        <?php $bal=(float)$supplier['current_balance']; ?>
        <span class="font-weight-bold <?= $bal>0?'text-danger':($bal<0?'text-success':'text-muted') ?>">
          PKR <?= formatCurrency(abs($bal)) ?> <?= $bal>0?'(Payable)':($bal<0?'(Advance)':'(Settled)') ?>
        </span>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-bordered table-hover mb-0">
        <thead>
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th class="text-right">Debit / Dr (Payment)</th>
            <th class="text-right">Credit / Cr (Purchase)</th>
            <th class="text-right">Balance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ledger as $e): ?>
          <tr>
            <td><?= $e['date'] === '-' ? '<span class="badge badge-light border">Initial</span>' : formatDate($e['date']) ?></td>
            <td>
              <?php if ($e['type']==='purchase'): ?><span class="badge badge-warning mr-1">Purchase</span>
              <?php elseif (in_array($e['type'],['receipt','payment'])): ?><span class="badge badge-success mr-1">Payment</span>
              <?php else: ?><span class="badge badge-secondary mr-1">Opening</span><?php endif; ?>
              <?= htmlspecialchars($e['ref']) ?>
            </td>
            <td class="text-right text-success"><?= $e['debit']>0?'PKR '.formatCurrency($e['debit']):'-' ?></td>
            <td class="text-right text-danger"><?= $e['credit']>0?'PKR '.formatCurrency($e['credit']):'-' ?></td>
            <td class="text-right font-weight-bold <?= $e['balance']>0?'text-danger':($e['balance']<0?'text-success':'text-muted') ?>">
              PKR <?= formatCurrency(abs($e['balance'])) ?> <?= $e['balance']>0?'Cr':($e['balance']<0?'Dr':'') ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($ledger)): ?><tr><td colspan="5" class="text-center text-muted py-4">No transactions yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function lgEsc(s){
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
$(document).ready(function(){
  var $input = $('#ledgerSearch');
  var $id = $('#ledgerId');
  var $list = $('#ledgerList');
  var timer = null;

  $input.on('input', function(){
    var q = $.trim(this.value);
    clearTimeout(timer);
    $id.val('');
    if (!q) { $list.empty().hide(); return; }
    timer = setTimeout(function(){
      $.get('ajax_supplier_search.php', {q: q}, function(data){
        $list.empty();
        if (!data || !data.length) {
          $list.append('<div class="ac-item ac-empty">No matching record found</div>');
        } else {
          $.each(data, function(i, it){
            var sub = [];
            if (it.phone) sub.push('Phone: ' + lgEsc(it.phone));
            if (it.address) sub.push(lgEsc(it.address));
            var bal = Number(it.current_balance || 0);
            if (bal !== 0) {
              sub.push((bal > 0 ? 'Payable: PKR ' : 'Advance: PKR ') + Math.abs(bal).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}));
            }
            $list.append($('<div class="ac-item" data-id="' + it.id + '">' +
              '<span class="ac-name">' + lgEsc(it.name) + '</span>' +
              (sub.length ? '<small class="ac-sub">' + sub.join(' &middot; ') + '</small>' : '') +
              '</div>'));
          });
        }
        $list.show();
      });
    }, 250);
  });

  $list.on('mousedown click', '.ac-item', function(e){
    e.preventDefault();
    if ($(this).hasClass('ac-empty')) return;
    $id.val($(this).data('id'));
    $input.val($(this).find('.ac-name').text());
    $list.empty().hide();
    $('#ledgerForm').trigger('submit');
  });

  $input.on('keydown', function(e){
    if (e.key === 'Enter') {
      e.preventDefault();
      var items = $list.find('.ac-item:not(.ac-empty)');
      if ($list.is(':visible') && items.length) {
        var idx = items.index(items.filter('.active'));
        var target = idx >= 0 ? items.eq(idx) : items.first();
        target.trigger('mousedown');
      }
      return;
    }
    var items = $list.find('.ac-item:not(.ac-empty)');
    if (!$list.is(':visible') || !items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      var dir = e.key === 'ArrowDown' ? 1 : -1;
      var idx = items.index(items.filter('.active'));
      idx = (idx + dir + items.length) % items.length;
      items.removeClass('active').eq(idx).addClass('active');
    } else if (e.key === 'Escape') {
      $list.empty().hide();
    }
  });

  $(document).on('mouseover', '.ac-item', function(){
    $(this).addClass('active').siblings().removeClass('active');
  });
  $(document).on('mousedown', function(e){
    if (!$(e.target).closest('.ac-wrap').length) $list.empty().hide();
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
