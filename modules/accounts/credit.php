<?php
$page_title = "Credit Book (Udhaar)";
require_once __DIR__ . '/../../includes/header.php';

$success_msg = "";
$error_msg = "";

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_credit') {
        $id = (int)($_POST['id'] ?? 0);
        $party_name = trim($_POST['party_name'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0.00);
        $type = trim($_POST['type'] ?? 'Given'); // Given or Received
        $credit_date = trim($_POST['credit_date'] ?? date('Y-m-d'));
        $description = trim($_POST['description'] ?? '');

        if (empty($party_name) || $amount <= 0) {
            $error_msg = "Please enter Party Name and valid Amount.";
        } else {
            if ($db_connected && $pdo) {
                try {
                    $invoice_no = trim($_POST['invoice_no'] ?? '') ?: null;
                    if ($id > 0) {
                        // Update
                        $stmt = $pdo->prepare("UPDATE udhaar_book SET party_name=:pn, invoice_no=:inv, amount=:amt, type=:typ, credit_date=:cd, description=:desc WHERE id=:id");
                        $stmt->execute([
                            'pn' => $party_name,
                            'inv' => $invoice_no,
                            'amt' => $amount,
                            'typ' => $type,
                            'cd' => $credit_date,
                            'desc' => $description,
                            'id' => $id
                        ]);
                        $success_msg = "Credit record updated successfully.";
                    } else {
                        // Insert
                        $stmt = $pdo->prepare("INSERT INTO udhaar_book (party_name, invoice_no, amount, type, credit_date, description) VALUES (:pn, :inv, :amt, :typ, :cd, :desc)");
                        $stmt->execute([
                            'pn' => $party_name,
                            'inv' => $invoice_no,
                            'amt' => $amount,
                            'typ' => $type,
                            'cd' => $credit_date,
                            'desc' => $description
                        ]);
                        $success_msg = "Credit record saved successfully.";
                    }
                } catch (Exception $e) {
                    $error_msg = "Error saving record: " . $e->getMessage();
                }
            }
        }
    } elseif ($_POST['action'] === 'delete_credit') {
        $id = (int)($_POST['delete_id'] ?? 0);
        if ($id > 0 && $db_connected && $pdo) {
            try {
                $pdo->prepare("DELETE FROM udhaar_book WHERE id = :id")->execute(['id' => $id]);
                $success_msg = "Credit record deleted.";
            } catch (Exception $e) {
                $error_msg = "Error deleting record: " . $e->getMessage();
            }
        }
    }
}

// Fetch Records
$records = [];
if ($db_connected && $pdo) {
    try {
        $records = $pdo->query("SELECT * FROM udhaar_book ORDER BY credit_date DESC, id DESC")->fetchAll();
    } catch (Exception $e) {}
}

?>

<!-- Custom Styles based on screenshot -->
<style>
    body { background-color: #f4f6f9; }
    .card-panel { background: #fff; border-radius: 8px; border: 1px solid #e3e6f0; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.02); }
    .panel-title { font-size: 1.1rem; font-weight: 700; color: #2e384d; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
    
    .form-control-custom { border-radius: 6px; border: 1px solid #ced4da; padding: 10px 12px; }
    .form-label-custom { font-size: 0.85rem; font-weight: 600; color: #495057; margin-bottom: 0.4rem; }
    
    .btn-save-credit { background-color: #3b82f6; color: #fff; border: none; font-weight: 600; padding: 12px; width: 100%; border-radius: 6px; }
    .btn-save-credit:hover { background-color: #2563eb; color: #fff; }
    
    .table-udhaar th { color: #6c757d; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; border-bottom: 2px solid #f1f3f5; padding-bottom: 1rem; }
    .table-udhaar td { vertical-align: middle; padding: 1rem 0.5rem; border-bottom: 1px solid #f1f3f5; }
    
    .badge-given { background-color: #ffe5e5; color: #ef4444; padding: 5px 12px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; }
    .badge-received { background-color: #d1fae5; color: #10b981; padding: 5px 12px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; }
    
    .text-amount-given { color: #ef4444; font-weight: 700; }
    .text-amount-received { color: #10b981; font-weight: 700; }
    
    .btn-action-view { background-color: #3b82f6; color: #fff; width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-action-view:hover { background-color: #2563eb; color: #fff; transform: translateY(-1px); }
    .btn-action-delete { background-color: #ef4444; color: #fff; width: 32px; height: 32px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px; border: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-action-delete:hover { background-color: #dc2626; color: #fff; transform: translateY(-1px); }
    
    .btn-top { border: 1px solid #dee2e6; background-color: #fff; color: #495057; font-weight: 600; padding: 6px 16px; border-radius: 4px; font-size: 0.85rem; }
    .btn-top:hover { background-color: #f8f9fa; }
</style>

<!-- Top Header -->
<div class="d-flex align-items-center justify-content-between mb-4 no-print">
    <h3 class="fw-bold mb-0 text-dark">Credit Book (Udhaar)</h3>
    <div class="d-flex gap-2">
        <button class="btn-top shadow-sm" onclick="history.back()"><i class="fa-solid fa-arrow-left me-2"></i>Go Back</button>
        <a href="<?php echo BASE_URL; ?>dashboard.php" class="btn-top shadow-sm text-decoration-none">Dashboard</a>
    </div>
</div>

<?php if (!empty($success_msg)): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fa-solid fa-check-circle me-2"></i><?php echo $success_msg; ?><button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fa-solid fa-exclamation-triangle me-2"></i><?php echo $error_msg; ?><button type="button" class="close ml-auto" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Left Column: Add New Credit Form -->
    <div class="col-12 col-lg-4">
        <div class="card-panel p-4 h-100">
            <h5 class="panel-title text-dark"><i class="fa-solid fa-circle-plus text-muted fs-6"></i> <span id="formTitle">Add New Credit</span></h5>
            
            <form method="POST" action="" id="creditForm">
                <input type="hidden" name="action" value="save_credit">
                <input type="hidden" name="id" id="f_id" value="0">
                <input type="hidden" name="invoice_no" id="f_invoice_no" value="">

                <div class="mb-3">
                    <label class="form-label-custom">Name / Party</label>
                    <input type="text" name="party_name" id="f_party_name" class="form-control form-control-custom" placeholder="Enter name" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label-custom">Amount (Rs.)</label>
                    <input type="number" step="0.01" name="amount" id="f_amount" class="form-control form-control-custom" placeholder="0.00" required>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom">Type</label>
                    <select name="type" id="f_type" class="form-select form-control-custom fw-semibold text-dark">
                        <option value="Given">Given (I gave Udhaar)</option>
                        <option value="Received">Received (I took Udhaar)</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom">Date</label>
                    <input type="date" name="credit_date" id="f_credit_date" class="form-control form-control-custom" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="mb-4">
                    <label class="form-label-custom">Description / Notes</label>
                    <textarea name="description" id="f_description" rows="3" class="form-control form-control-custom" placeholder="Optional details..."></textarea>
                </div>

                <button type="submit" class="btn btn-save-credit shadow-sm" id="btnSubmitForm">Save Credit</button>
                <button type="button" class="btn btn-light w-100 mt-2 d-none fw-bold text-muted border" id="btnCancelEdit" onclick="cancelEdit()">Cancel Edit</button>
            </form>
        </div>
    </div>

    <!-- Right Column: Recent Records -->
    <div class="col-12 col-lg-8">
        <div class="card-panel p-4 h-100">
            <h5 class="panel-title text-dark"><i class="fa-solid fa-list text-muted fs-6"></i> Recent Credit Records</h5>
            
            <div class="position-relative mb-4" style="max-width: 350px;">
                <i class="fa-solid fa-search position-absolute text-muted" style="top: 12px; left: 15px;"></i>
                <input type="text" id="searchInput" class="form-control form-control-custom ps-5" placeholder="Search credit records..." onkeyup="filterTable()">
            </div>

            <div class="table-responsive">
                <table class="table table-udhaar mb-0" id="creditTable">
                    <thead>
                        <tr>
                            <th style="width: 12%;">DATE</th>
                            <th style="width: 25%;">NAME / PARTY</th>
                            <th style="width: 30%;">DESCRIPTION</th>
                            <th style="width: 10%;">TYPE</th>
                            <th class="text-end" style="width: 13%;">AMOUNT</th>
                            <th class="text-center" style="width: 10%;">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No credit records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr>
                                    <td>
                                        <div class="text-muted small fw-semibold">
                                            <?php echo date('d M', strtotime($r['credit_date'])); ?><br>
                                            <?php echo date('Y', strtotime($r['credit_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="text-dark fs-6"><?php echo htmlspecialchars($r['party_name']); ?></strong>
                                        <?php if (!empty($r['invoice_no'])): ?>
                                            <br>
                                            <a href="<?php echo BASE_URL; ?>modules/sale/print_invoice.php?invoice_no=<?php echo urlencode($r['invoice_no']); ?>" target="_blank" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 text-decoration-none mt-1 py-1 px-2" title="Click to view Invoice">
                                                <i class="fa-solid fa-file-invoice me-1"></i><?php echo htmlspecialchars($r['invoice_no']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted small"><?php echo htmlspecialchars($r['description']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($r['type'] === 'Given'): ?>
                                            <span class="badge-given">Given</span>
                                        <?php else: ?>
                                            <span class="badge-received">Received</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="<?php echo ($r['type'] === 'Given') ? 'text-amount-given' : 'text-amount-received'; ?>">
                                            Rs. <?php echo format_currency($r['amount']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <button type="button" class="btn-action-view shadow-sm" title="View Credit Details" onclick='viewCredit(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8"); ?>)'>
                                                <i class="fa-solid fa-eye fs-7"></i>
                                            </button>
                                            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this credit record?');" class="m-0 d-inline">
                                                <input type="hidden" name="action" value="delete_credit">
                                                <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
                                                <button type="submit" class="btn-action-delete shadow-sm" title="Delete Record">
                                                    <i class="fa-solid fa-trash fs-7"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Credit Details Modal -->
<div class="modal fade" id="viewCreditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
            <div class="modal-header border-bottom py-3 px-4 bg-light">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2 mb-0">
                    <i class="fa-solid fa-receipt text-primary"></i> Credit / Udhaar Details
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center p-3 rounded-3 mb-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <div>
                        <span class="text-muted small d-block">Party / Customer</span>
                        <h5 class="fw-bold text-dark mb-0" id="modalPartyName">-</h5>
                    </div>
                    <div class="text-end">
                        <span class="text-muted small d-block">Credit Type</span>
                        <span id="modalTypeBadge" class="badge bg-danger px-3 py-1">-</span>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <div class="p-3 rounded-3 border" style="background: #fff;">
                            <span class="text-muted small d-block">Amount</span>
                            <h4 class="fw-bold text-danger mb-0" id="modalAmount">Rs. 0.00</h4>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded-3 border" style="background: #fff;">
                            <span class="text-muted small d-block">Date</span>
                            <h6 class="fw-bold text-dark mb-0 mt-1" id="modalDate">-</h6>
                        </div>
                    </div>
                </div>

                <div class="p-3 rounded-3 border mb-3" style="background: #fff;" id="modalInvoiceRow">
                    <span class="text-muted small d-block">Associated Sales Invoice</span>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <strong class="text-primary font-monospace fs-6" id="modalInvoiceNo">-</strong>
                        <a href="#" id="modalInvoiceLink" target="_blank" class="btn btn-sm btn-primary px-3 shadow-sm">
                            <i class="fa-solid fa-file-invoice me-1"></i> Print / View Invoice
                        </a>
                    </div>
                </div>

                <div class="p-3 rounded-3 border" style="background: #f8fafc;">
                    <span class="text-muted small d-block mb-1">Description / Notes</span>
                    <p class="mb-0 text-dark small" id="modalDescription">-</p>
                </div>
            </div>
            <div class="modal-footer border-top py-2 px-4 bg-light">
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-semibold" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewCredit(record) {
    document.getElementById('modalPartyName').textContent = record.party_name;
    document.getElementById('modalAmount').textContent = 'Rs. ' + Number(record.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('modalDate').textContent = record.credit_date;
    document.getElementById('modalDescription').textContent = record.description || 'No additional notes provided.';

    const typeBadge = document.getElementById('modalTypeBadge');
    if (record.type === 'Given') {
        typeBadge.className = 'badge bg-danger px-3 py-1';
        typeBadge.textContent = 'Given (Udhaar Diya)';
    } else {
        typeBadge.className = 'badge bg-success px-3 py-1';
        typeBadge.textContent = 'Received (Udhaar Liya)';
    }

    const invRow = document.getElementById('modalInvoiceRow');
    const invLink = document.getElementById('modalInvoiceLink');
    if (record.invoice_no) {
        invRow.classList.remove('d-none');
        document.getElementById('modalInvoiceNo').textContent = record.invoice_no;
        invLink.href = '<?php echo BASE_URL; ?>modules/sale/print_invoice.php?invoice_no=' + encodeURIComponent(record.invoice_no);
    } else {
        invRow.classList.add('d-none');
    }

    const myModal = { show: () => jQuery('#viewCreditModal').modal('show'), hide: () => jQuery('#viewCreditModal').modal('hide'), toggle: () => jQuery('#viewCreditModal').modal('toggle') };
    myModal.show();
}

function filterTable() {
    const input = document.getElementById("searchInput");
    const filter = input.value.toLowerCase();
    const table = document.getElementById("creditTable");
    const tr = table.getElementsByTagName("tr");

    for (let i = 1; i < tr.length; i++) { // Skip header row
        const tdParty = tr[i].getElementsByTagName("td")[1];
        const tdDesc = tr[i].getElementsByTagName("td")[2];
        if (tdParty || tdDesc) {
            const partyText = tdParty.textContent || tdParty.innerText;
            const descText = tdDesc.textContent || tdDesc.innerText;
            if (partyText.toLowerCase().indexOf(filter) > -1 || descText.toLowerCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }       
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
