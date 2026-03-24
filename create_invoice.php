<?php
require_once 'config/bootstrap.php';
requireAuth();
include 'includes/layout.php';

$pdo = db();

// ── Edit mode ──────────────────────────────────
$editMode = false;
$inv      = null;
$items    = [];

if (!empty($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $inv  = $stmt->fetch();

    if ($inv) {
        $editMode = true;
        $stmt     = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
    }
}

// ── Next invoice number ────────────────────────
if (!$editMode) {
    $prefix = 'INV';
    $year   = date('Y');

    $pdo->exec("INSERT IGNORE INTO invoice_sequences (prefix, year, next_no) VALUES ('$prefix', $year, 1)");
    $seq    = $pdo->query("SELECT next_no FROM invoice_sequences WHERE prefix='$prefix' AND year=$year")->fetchColumn();
    $nextNo = $prefix . '-' . $year . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

// ── Customers for autocomplete ─────────────────
$customers = $pdo->query("SELECT id, customer_name, tin, reg_no, email, phone, address_line_0, address_line_1, city, postal_code, state_code, country_code FROM customers ORDER BY customer_name")->fetchAll();

// ── Company MSIC default ───────────────────────
$company = $pdo->query("SELECT msic_code FROM company_profiles WHERE id=1")->fetch();

$title = $editMode ? 'Edit Invoice' : 'New Invoice';
$sub   = $editMode ? 'Update invoice details below.' : 'Fill in the details below to create an invoice.';

$statusOptions = [
    'draft'   => 'Draft',
    'sent'    => 'Sent',
    'paid'    => 'Paid',
    'overdue' => 'Overdue',
];
$currencyOptions = [
    'MYR' => 'MYR',
    'USD' => 'USD',
    'SGD' => 'SGD',
    'EUR' => 'EUR',
    'GBP' => 'GBP',
];
$paymentMethodOptions = [
    '01' => 'Cash',
    '02' => 'Cheque',
    '03' => 'Bank Transfer',
    '04' => 'Credit Card',
    '05' => 'Online Banking',
];
$lhdnInvoiceTypeOptions = [
    '01' => '01 — Invoice',
    '02' => '02 — Credit Note',
    '03' => '03 — Debit Note',
    '04' => '04 — Refund Note',
];

layoutOpen($title, $sub);
?>

<!-- Breadcrumb + actions -->
<script>
document.getElementById('pageActions').innerHTML = `
    <a href="invoice.php" class="<?= t('btn_base') ?> <?= t('btn_ghost') ?> h-9">Cancel</a>
    <button onclick="document.getElementById('invoiceForm').requestSubmit()"
            class="<?= t('btn_base') ?> <?= t('btn_primary') ?> h-9">
        <?= $editMode ? 'Update Invoice' : 'Save Invoice' ?>
    </button>`;
</script>

<form id="invoiceForm" method="POST" action="save_invoice.php">
    <?php if ($editMode): ?>
    <input type="hidden" name="edit_id" value="<?= $inv['id'] ?>">
    <?php endif; ?>

    <div class="flex gap-5 items-start">

        <!-- ── Left: main form ──────────────────── -->
        <div class="flex-1 min-w-0 space-y-4">

            <!-- Invoice details -->
            <div class="<?= t('card') ?>">
                <h2 class="<?= t('card_title') ?>">Invoice Details</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="<?= t('label') ?>">Invoice Number <span class="text-red-400">*</span></label>
                        <input type="text" name="invoice_no"
                               value="<?= e($editMode ? $inv['invoice_no'] : $nextNo) ?>"
                               required class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Status</label>
                        <?php
                        renderDropdown(
                            name: 'status',
                            options: $statusOptions,
                            selected: $editMode ? (string)($inv['status'] ?? 'draft') : 'draft',
                            placeholder: 'Select status',
                            extraClasses: 'w-full'
                        );
                        ?>
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Invoice Date <span class="text-red-400">*</span></label>
                        <input type="date" name="invoice_date"
                               value="<?= e($editMode ? $inv['invoice_date'] : date('Y-m-d')) ?>"
                               required class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Due Date</label>
                        <input type="date" name="due_date"
                               value="<?= e($editMode ? ($inv['due_date'] ?? '') : date('Y-m-d', strtotime('+30 days'))) ?>"
                               class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Currency</label>
                        <?php
                        renderDropdown(
                            name: 'currency',
                            options: $currencyOptions,
                            selected: $editMode ? (string)($inv['currency'] ?? 'MYR') : 'MYR',
                            placeholder: 'Select currency',
                            extraClasses: 'w-full'
                        );
                        ?>
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Payment Method</label>
                        <?php
                        renderDropdown(
                            name: 'payment_method',
                            options: $paymentMethodOptions,
                            selected: $editMode ? (string)($inv['payment_method'] ?? '01') : '01',
                            placeholder: 'Select payment method',
                            extraClasses: 'w-full'
                        );
                        ?>
                    </div>
                </div>
            </div>

            <!-- Bill To -->
            <div class="<?= t('card') ?>" x-data="customerSearch()">
                <h2 class="<?= t('card_title') ?>">Bill To</h2>

                <!-- Customer search -->
                <div class="mb-4">
                    <label class="<?= t('label') ?>">Search existing customer</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                        <input type="text" x-model="query" @input="search()" @keydown.escape="clear()"
                               placeholder="Type to search customers..."
                               autocomplete="off"
                               class="<?= t('input') ?> pl-8">
                        <ul x-show="results.length > 0"
                            class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-52 overflow-y-auto">
                            <template x-for="c in results" :key="c.id">
                                <li @click="select(c)"
                                    class="px-4 py-2.5 hover:bg-slate-50 cursor-pointer flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-slate-800" x-text="c.customer_name"></div>
                                        <div class="text-xs text-slate-400" x-text="c.tin ? 'TIN: ' + c.tin : ''"></div>
                                    </div>
                                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="<?= t('label') ?>">Customer Name <span class="text-red-400">*</span></label>
                        <input type="text" name="customer_name" id="f_customer_name"
                               value="<?= e($editMode ? $inv['customer_name'] : '') ?>"
                               required placeholder="e.g. Acme Sdn Bhd"
                               class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">TIN <span class="text-red-400">*</span></label>
                        <input type="text" name="customer_tin" id="f_customer_tin"
                               value="<?= e($editMode ? $inv['customer_tin'] : '') ?>"
                               placeholder="C12345678900" class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Registration No (SSM / IC)</label>
                        <input type="text" name="customer_reg_no" id="f_customer_reg_no"
                               value="<?= e($editMode ? $inv['customer_reg_no'] : '') ?>"
                               placeholder="202001012345" class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Email</label>
                        <input type="email" name="customer_email" id="f_customer_email"
                               value="<?= e($editMode ? $inv['customer_email'] : '') ?>"
                               placeholder="customer@email.com" class="<?= t('input') ?>">
                    </div>
                    <div>
                        <label class="<?= t('label') ?>">Phone</label>
                        <input type="text" name="customer_phone" id="f_customer_phone"
                               value="<?= e($editMode ? $inv['customer_phone'] : '') ?>"
                               placeholder="+60 12-345 6789" class="<?= t('input') ?>">
                    </div>
                    <div class="col-span-2">
                        <label class="<?= t('label') ?>">Address</label>
                        <textarea name="customer_address" id="f_customer_address" rows="2"
                                  placeholder="Street, City, Postcode, State"
                                  class="<?= t('input') ?> h-auto py-2 resize-none"><?= e($editMode ? $inv['customer_address'] : '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="<?= t('card') ?>">
                <h2 class="<?= t('card_title') ?>">Line Items</h2>

                <!-- Column headers -->
                <div class="grid gap-2 mb-2 pr-9" style="grid-template-columns: 3fr 80px 110px 80px 110px">
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Description</span>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide text-right">Qty</span>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide text-right">Unit Price</span>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide text-right">Tax</span>
                    <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wide text-right">Amount</span>
                </div>

                <div id="itemsContainer" class="space-y-2">
                    <?php
                    $initItems = $editMode && !empty($items) ? $items : [
                        ['description' => '', 'quantity' => 1, 'unit_price' => '', 'tax_type' => 'none', 'line_total' => 0]
                    ];
                    foreach ($initItems as $i => $item):
                    ?>
                    <div class="item-row grid gap-2 items-center" style="grid-template-columns: 3fr 80px 110px 80px 110px 36px">
                        <input type="text" name="items[<?= $i ?>][description]"
                               value="<?= e($item['description']) ?>"
                               placeholder="Item description" required
                               class="<?= t('input') ?> text-sm">
                        <input type="number" name="items[<?= $i ?>][quantity]"
                               value="<?= e($item['quantity']) ?>"
                               min="0.01" step="0.01"
                               class="<?= t('input') ?> text-right item-qty text-sm">
                        <input type="number" name="items[<?= $i ?>][unit_price]"
                               value="<?= e($item['unit_price']) ?>"
                               min="0" step="0.01" placeholder="0.00"
                               class="<?= t('input') ?> text-right item-price text-sm">
                        <select name="items[<?= $i ?>][tax_type]"
                                class="<?= t('select') ?> w-full item-tax text-xs">
                            <option value="none"     <?= $item['tax_type']==='none'     ? 'selected':'' ?>>—</option>
                            <option value="sst6"     <?= $item['tax_type']==='sst6'     ? 'selected':'' ?>>SST 6%</option>
                            <option value="sst10"    <?= $item['tax_type']==='sst10'    ? 'selected':'' ?>>SST 10%</option>
                            <option value="service6" <?= $item['tax_type']==='service6' ? 'selected':'' ?>>Svc 6%</option>
                        </select>
                        <input type="text" name="items[<?= $i ?>][line_total]"
                               value="<?= number_format((float)$item['quantity'] * (float)$item['unit_price'], 2) ?>"
                               readonly
                               class="<?= t('input') ?> text-right item-total bg-slate-50 text-slate-600 font-medium text-sm cursor-default">
                        <button type="button" onclick="removeRow(this)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" onclick="addRow()"
                        class="mt-3 flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Add Item
                </button>
            </div>

            <!-- LHDN Fields -->
            <div class="<?= t('lhdn_section') ?>">
                <h2 class="<?= t('lhdn_title') ?>">
                    <svg class="w-3.5 h-3.5 inline mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    LHDN e-Invoice Fields
                </h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="<?= t('label') ?> text-violet-700">Invoice Type <span class="text-red-400">*</span></label>
                        <?php
                        renderDropdown(
                            name: 'lhdn_invoice_type',
                            options: $lhdnInvoiceTypeOptions,
                            selected: $editMode ? (string)($inv['lhdn_invoice_type'] ?? '01') : '01',
                            placeholder: 'Select invoice type',
                            required: true,
                            extraClasses: 'w-full'
                        );
                        ?>
                    </div>
                    <div>
                        <label class="<?= t('label') ?> text-violet-700">MSIC Code <span class="text-red-400">*</span></label>
                        <input type="text" name="msic_code"
                               value="<?= e($editMode ? $inv['msic_code'] : ($company['msic_code'] ?? '')) ?>"
                               placeholder="e.g. 62010" class="<?= t('input') ?>">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="<?= t('card') ?>">
                <h2 class="<?= t('card_title') ?>">Notes</h2>
                <textarea name="notes" rows="3"
                          placeholder="Payment terms, bank details, or additional notes..."
                          class="<?= t('input') ?> h-auto py-2 resize-none w-full"><?= e($editMode ? ($inv['notes'] ?? '') : '') ?></textarea>
            </div>

        </div>

        <!-- ── Right: summary panel ──────────────── -->
        <div class="w-64 shrink-0 sticky top-0 space-y-4">
            <div class="<?= t('card') ?>">
                <h2 class="<?= t('card_title') ?>">Summary</h2>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal</span>
                        <span id="summarySubtotal" class="font-medium text-slate-800">RM 0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600 gap-2">
                        <span class="whitespace-nowrap">Discount (RM)</span>
                        <input type="number" name="discount_amount" id="discountInput"
                               value="<?= e($editMode ? $inv['discount_amount'] : '0') ?>"
                               min="0" step="0.01"
                               oninput="updateTotals()"
                               class="w-24 h-8 border border-slate-200 rounded-lg px-2 text-right text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Tax</span>
                        <span id="summaryTax" class="font-medium text-slate-800">RM 0.00</span>
                    </div>
                    <div class="border-t border-slate-100 pt-2 flex justify-between font-bold text-slate-900">
                        <span>Total</span>
                        <span id="summaryTotal">RM 0.00</span>
                    </div>
                </div>

                <!-- Hidden computed values -->
                <input type="hidden" name="subtotal"    id="hiddenSubtotal">
                <input type="hidden" name="tax_amount"  id="hiddenTaxAmount">
                <input type="hidden" name="total_amount" id="hiddenTotal">

                <div class="mt-4 space-y-2">
                    <button type="submit"
                            class="<?= t('btn_base') ?> <?= t('btn_primary') ?> w-full justify-center h-9">
                        <?= $editMode ? 'Update Invoice' : 'Save Invoice' ?>
                    </button>
                    <a href="invoice.php"
                       class="<?= t('btn_base') ?> <?= t('btn_ghost') ?> w-full justify-center h-9">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

    </div>
</form>

<!-- Customer JSON for autocomplete -->
<script>
const CUSTOMERS = <?= json_encode(array_values($customers)) ?>;
</script>

<script>
// ── Row counter ──
let rowIndex = <?= count($initItems) ?>;

// ── Tax rates ──
const TAX_RATES = { none: 0, sst6: 0.06, sst10: 0.10, service6: 0.06 };

function calcRow(row) {
    const qty   = parseFloat(row.querySelector('.item-qty').value)   || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    const taxType = row.querySelector('.item-tax').value;
    const taxRate = TAX_RATES[taxType] || 0;
    const subtotal = qty * price;
    const tax   = subtotal * taxRate;
    const total = subtotal + tax;
    row.querySelector('.item-total').value = total.toFixed(2);
    updateTotals();
}

function updateTotals() {
    let subtotal = 0, totalTax = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const qty      = parseFloat(row.querySelector('.item-qty').value)   || 0;
        const price    = parseFloat(row.querySelector('.item-price').value) || 0;
        const taxType  = row.querySelector('.item-tax').value;
        const taxRate  = TAX_RATES[taxType] || 0;
        const rowSub   = qty * price;
        const rowTax   = rowSub * taxRate;
        subtotal += rowSub;
        totalTax += rowTax;
    });

    const discount = parseFloat(document.getElementById('discountInput').value) || 0;
    const taxable  = Math.max(subtotal - discount, 0);
    const total    = taxable + totalTax;

    document.getElementById('summarySubtotal').textContent = 'RM ' + subtotal.toFixed(2);
    document.getElementById('summaryTax').textContent      = 'RM ' + totalTax.toFixed(2);
    document.getElementById('summaryTotal').textContent    = 'RM ' + total.toFixed(2);

    document.getElementById('hiddenSubtotal').value  = subtotal.toFixed(2);
    document.getElementById('hiddenTaxAmount').value = totalTax.toFixed(2);
    document.getElementById('hiddenTotal').value     = total.toFixed(2);
}

function addRow() {
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.className = 'item-row grid gap-2 items-center';
    div.style.gridTemplateColumns = '3fr 80px 110px 80px 110px 36px';
    div.innerHTML = `
        <input type="text" name="items[${rowIndex}][description]" placeholder="Item description" required
               class="<?= t('input') ?> text-sm">
        <input type="number" name="items[${rowIndex}][quantity]" value="1" min="0.01" step="0.01"
               class="<?= t('input') ?> text-right item-qty text-sm" oninput="calcRow(this.closest('.item-row'))">
        <input type="number" name="items[${rowIndex}][unit_price]" value="" min="0" step="0.01" placeholder="0.00"
               class="<?= t('input') ?> text-right item-price text-sm" oninput="calcRow(this.closest('.item-row'))">
        <select name="items[${rowIndex}][tax_type]"
                class="<?= t('select') ?> w-full item-tax text-xs" onchange="calcRow(this.closest('.item-row'))">
            <option value="none">—</option>
            <option value="sst6">SST 6%</option>
            <option value="sst10">SST 10%</option>
            <option value="service6">Svc 6%</option>
        </select>
        <input type="text" name="items[${rowIndex}][line_total]" value="0.00" readonly
               class="<?= t('input') ?> text-right item-total bg-slate-50 text-slate-600 font-medium text-sm cursor-default">
        <button type="button" onclick="removeRow(this)"
                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>`;
    container.appendChild(div);

    // Attach event listeners to new row
    div.querySelectorAll('.item-qty, .item-price').forEach(el => el.addEventListener('input', () => calcRow(div)));
    div.querySelector('.item-tax').addEventListener('change', () => calcRow(div));
    rowIndex++;
}

function removeRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length <= 1) return;
    btn.closest('.item-row').remove();
    updateTotals();
}

// Attach listeners to initial rows
document.querySelectorAll('.item-row').forEach(row => {
    row.querySelectorAll('.item-qty, .item-price').forEach(el => el.addEventListener('input', () => calcRow(row)));
    row.querySelector('.item-tax').addEventListener('change', () => calcRow(row));
});

// Init totals on load
updateTotals();

// ── Customer autocomplete ──
function customerSearch() {
    return {
        query: '',
        results: [],
        search() {
            const q = this.query.toLowerCase().trim();
            this.results = q.length < 1 ? [] :
                CUSTOMERS.filter(c =>
                    c.customer_name.toLowerCase().includes(q) ||
                    (c.tin && c.tin.toLowerCase().includes(q))
                ).slice(0, 8);
        },
        select(c) {
            document.getElementById('f_customer_name').value    = c.customer_name;
            document.getElementById('f_customer_tin').value     = c.tin       || '';
            document.getElementById('f_customer_reg_no').value  = c.reg_no    || '';
            document.getElementById('f_customer_email').value   = c.email     || '';
            document.getElementById('f_customer_phone').value   = c.phone     || '';
            const addr = [c.address_line_0, c.address_line_1, c.city, c.postal_code].filter(Boolean).join(', ');
            document.getElementById('f_customer_address').value = addr;
            this.query   = c.customer_name;
            this.results = [];
        },
        clear() { this.results = []; }
    };
}
</script>

<?php layoutClose(); ?>
