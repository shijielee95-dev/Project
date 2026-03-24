<?php
require_once 'config/bootstrap.php';
requireAuth();
include 'includes/layout.php';

$pdo = db();

// ── Stats ──────────────────────────────────────
$stats = $pdo->query("
    SELECT
        COUNT(*)                                                           AS total,
        COALESCE(SUM(CASE WHEN status='paid'    THEN 1 END), 0)           AS paid,
        COALESCE(SUM(CASE WHEN status='sent'    THEN 1 END), 0)           AS sent,
        COALESCE(SUM(CASE WHEN status='overdue' THEN 1 END), 0)           AS overdue,
        COALESCE(SUM(CASE WHEN status='draft'   THEN 1 END), 0)           AS draft,
        COALESCE(SUM(CASE WHEN status='paid' THEN total_amount END), 0)   AS revenue
    FROM invoices
")->fetch();

// ── Filters ────────────────────────────────────
$search  = trim($_GET['search']  ?? '');
$statusF = trim($_GET['status']  ?? '');
$lhdnF   = trim($_GET['lhdn']    ?? '');

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(i.invoice_no LIKE ? OR i.customer_name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusF !== '') {
    $where[]  = 'i.status = ?';
    $params[] = $statusF;
}
if ($lhdnF !== '') {
    if ($lhdnF === 'none') {
        $where[] = 'ls.id IS NULL';
    } else {
        $where[]  = 'ls.status = ?';
        $params[] = $lhdnF;
    }
}

$sql = "
    SELECT i.id, i.invoice_no, i.customer_name, i.invoice_date, i.due_date,
           i.total_amount, i.status,
           ls.status AS lhdn_status
    FROM invoices i
    LEFT JOIN lhdn_submissions ls ON ls.invoice_id = i.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY i.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

layoutOpen('Invoices', number_format($stats['total']) . ' total invoices');
?>

<!-- Page action -->
<script>
document.getElementById('pageActions').innerHTML = `
    <a href="create_invoice.php" class="<?= t('btn_base') ?> <?= t('btn_primary') ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        New Invoice
    </a>`;
</script>

<!-- ── Stat cards ──────────────────────────────── -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
    <?php
    $cards = [
        ['label' => 'All',     'value' => $stats['total'],   'sub' => '',                          'active' => $statusF === '',        'filter' => ''],
        ['label' => 'Paid',    'value' => $stats['paid'],    'sub' => rm($stats['revenue']),       'active' => $statusF === 'paid',    'filter' => 'paid'],
        ['label' => 'Sent',    'value' => $stats['sent'],    'sub' => 'Awaiting payment',          'active' => $statusF === 'sent',    'filter' => 'sent'],
        ['label' => 'Draft',   'value' => $stats['draft'],   'sub' => 'Not yet sent',              'active' => $statusF === 'draft',   'filter' => 'draft'],
        ['label' => 'Overdue', 'value' => $stats['overdue'], 'sub' => 'Past due date',             'active' => $statusF === 'overdue', 'filter' => 'overdue'],
    ];
    foreach ($cards as $c):
        $active = $c['active'];
        $url    = '?' . http_build_query(array_merge($_GET, ['status' => $c['filter'], 'search' => $search]));
    ?>
    <a href="<?= $url ?>"
       class="<?= t('card') ?> block transition-all <?= $active ? 'ring-2 ring-indigo-500 ring-offset-1' : 'hover:border-slate-300' ?>">
        <div class="text-[10px] font-semibold uppercase tracking-wide <?= $active ? 'text-indigo-600' : 'text-slate-400' ?> mb-1">
            <?= $c['label'] ?>
        </div>
        <div class="text-xl font-bold text-slate-800"><?= $c['value'] ?></div>
        <?php if ($c['sub']): ?>
        <div class="text-[10px] text-slate-400 mt-0.5 truncate"><?= $c['sub'] ?></div>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- ── Filter bar ─────────────────────────────── -->
<form method="GET" class="<?= t('card') ?> flex flex-wrap items-end gap-3 mb-5">
    <!-- Search -->
    <div class="flex-1 min-w-48">
        <label class="<?= t('label') ?>">Search</label>
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" name="search" value="<?= e($search) ?>"
                   placeholder="Invoice # or customer..."
                   class="<?= t('input') ?> pl-8">
        </div>
    </div>

    <!-- Status -->
    <div>
        <label class="<?= t('label') ?>">Status</label>
        <select name="status" class="<?= t('select') ?>">
            <option value="">All Status</option>
            <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
            <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- LHDN -->
    <div>
        <label class="<?= t('label') ?>">LHDN Status</label>
        <select name="lhdn" class="<?= t('select') ?>">
            <option value="">All</option>
            <option value="none"    <?= $lhdnF === 'none'    ? 'selected' : '' ?>>Not Submitted</option>
            <option value="pending" <?= $lhdnF === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="valid"   <?= $lhdnF === 'valid'   ? 'selected' : '' ?>>Validated</option>
            <option value="invalid" <?= $lhdnF === 'invalid' ? 'selected' : '' ?>>Invalid</option>
        </select>
    </div>

    <div class="flex gap-2">
        <button type="submit" class="<?= t('btn_base') ?> <?= t('btn_primary') ?> h-9">Filter</button>
        <?php if ($search || $statusF || $lhdnF): ?>
        <a href="invoice.php" class="<?= t('btn_base') ?> <?= t('btn_ghost') ?> h-9">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- ── Invoice table ──────────────────────────── -->
<div class="<?= t('table_wrap') ?>">
    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="<?= t('th') ?>">Invoice #</th>
                <th class="<?= t('th') ?>">Customer</th>
                <th class="<?= t('th') ?>">Invoice Date</th>
                <th class="<?= t('th') ?>">Due Date</th>
                <th class="<?= t('th') ?> text-right">Amount</th>
                <th class="<?= t('th') ?> text-center">Status</th>
                <th class="<?= t('th') ?> text-center">LHDN</th>
                <th class="<?= t('th') ?> text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($invoices)): ?>
            <tr>
                <td colspan="8" class="px-4 py-12 text-center">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <p class="text-sm text-slate-400 mb-1">No invoices found.</p>
                    <a href="create_invoice.php" class="text-sm text-indigo-600 hover:underline">Create your first invoice →</a>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($invoices as $inv): ?>
            <?php
                $overdue = $inv['status'] === 'sent'
                    && $inv['due_date']
                    && strtotime($inv['due_date']) < time();
            ?>
            <tr class="hover:bg-slate-50/60 transition-colors <?= $overdue ? 'bg-red-50/30' : '' ?>">
                <td class="<?= t('td') ?>">
                    <a href="view_invoice.php?id=<?= $inv['id'] ?>"
                       class="font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                        <?= e($inv['invoice_no']) ?>
                    </a>
                </td>
                <td class="<?= t('td') ?> max-w-[200px] truncate"><?= e($inv['customer_name']) ?></td>
                <td class="<?= t('td') ?> text-slate-500 whitespace-nowrap"><?= fmtDate($inv['invoice_date']) ?></td>
                <td class="<?= t('td') ?> whitespace-nowrap <?= $overdue ? 'text-red-600 font-medium' : 'text-slate-500' ?>">
                    <?= $inv['due_date'] ? fmtDate($inv['due_date']) : '—' ?>
                    <?php if ($overdue): ?>
                    <span class="text-[10px] text-red-500 block">Overdue</span>
                    <?php endif; ?>
                </td>
                <td class="<?= t('td') ?> text-right font-semibold text-slate-800 whitespace-nowrap">
                    <?= rm($inv['total_amount']) ?>
                </td>
                <td class="<?= t('td') ?> text-center">
                    <span class="<?= badge($inv['status']) ?>"><?= ucfirst($inv['status']) ?></span>
                </td>
                <td class="<?= t('td') ?> text-center">
                    <?php if ($inv['lhdn_status']): ?>
                        <span class="<?= badge($inv['lhdn_status']) ?>"><?= ucfirst($inv['lhdn_status']) ?></span>
                    <?php else: ?>
                        <span class="text-xs text-slate-300">—</span>
                    <?php endif; ?>
                </td>
                <td class="<?= t('td') ?> text-center">
                    <div class="flex items-center justify-center gap-1">
                        <!-- View -->
                        <a href="view_invoice.php?id=<?= $inv['id'] ?>"
                           title="View"
                           class="w-7 h-7 flex items-center justify-center rounded-md text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </a>
                        <!-- Edit -->
                        <a href="create_invoice.php?edit=<?= $inv['id'] ?>"
                           title="Edit"
                           class="w-7 h-7 flex items-center justify-center rounded-md text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </a>
                        <!-- Delete -->
                        <button onclick="confirmDelete(<?= $inv['id'] ?>, '<?= e($inv['invoice_no']) ?>')"
                                title="Delete"
                                class="w-7 h-7 flex items-center justify-center rounded-md text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ── Delete modal ───────────────────────────── -->
<div id="deleteModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="closeDelete()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 p-6">
        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center mx-auto mb-4">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
        </div>
        <h3 class="text-base font-semibold text-slate-800 text-center mb-1">Delete Invoice</h3>
        <p class="text-sm text-slate-500 text-center mb-5">
            Delete <strong id="deleteInvNo" class="text-slate-700"></strong>? This cannot be undone.
        </p>
        <div class="flex gap-3">
            <button onclick="closeDelete()" class="<?= t('btn_base') ?> <?= t('btn_ghost') ?> flex-1 justify-center">Cancel</button>
            <button onclick="doDelete()"    class="<?= t('btn_base') ?> <?= t('btn_danger') ?> flex-1 justify-center">Delete</button>
        </div>
    </div>
</div>

<script>
let _deleteId = null;

function confirmDelete(id, no) {
    _deleteId = id;
    document.getElementById('deleteInvNo').textContent = no;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}
function closeDelete() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
    _deleteId = null;
}
function doDelete() {
    if (!_deleteId) return;
    fetch('delete_invoice.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + _deleteId
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            showToast('Invoice deleted.', 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(d.message || 'Failed to delete.', 'error');
        }
    })
    .catch(() => showToast('Server error.', 'error'));
}
</script>

<?php layoutClose(); ?>
