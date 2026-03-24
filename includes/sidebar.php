<?php
/**
 * includes/sidebar.php
 */
$currentPage = basename($_SERVER['PHP_SELF']);

// Fetch logo from company profile
$_logoPath = '';
try {
    $_logoRow = db()->query("SELECT logo_path FROM company_profiles WHERE id=1 LIMIT 1")->fetch();
    $_logoPath = $_logoRow['logo_path'] ?? '';
} catch (Exception $_e) {}

function sidebarLink(string $href, string $label, string $icon, string $current, array $match = []): void {
    global $theme;
    $pages  = array_merge([$href], $match);
    $active = in_array($current, $pages);
    $cls    = $active
        ? $theme['sidebar_active']
        : ($theme['sidebar_text'] . ' ' . $theme['sidebar_hover']);
    echo sprintf(
        '<a href="%s" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors %s">%s<span>%s</span></a>',
        $href, $cls, $icon, $label
    );
}

$icons = [
    'dashboard' => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    'invoice'   => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
    'quote'     => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
    'customer'  => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
    'supplier'  => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
    'company'   => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'lhdn'      => '<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    'chevron'   => '<svg class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>',
];

$user     = authUser();
$nameParts = explode(' ', $user['name']);
$initials  = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));

$openGroup = '';
if (in_array($currentPage, ['invoice.php','create_invoice.php','view_invoice.php'])) $openGroup = 'sales';
elseif (in_array($currentPage, ['customer.php'])) $openGroup = 'contact';
elseif (in_array($currentPage, ['company_details.php','invoice_settings.php'])) $openGroup = 'settings';
?>

<aside class="<?= t('sidebar_width') ?> <?= t('sidebar_bg') ?> flex flex-col h-screen sticky top-0 border-r border-white/5 shrink-0"
       x-data="{ openGroup: '<?= $openGroup ?>' }">

    <!-- Logo area -->
    <div class="flex items-center justify-center h-14 border-b border-white/5 shrink-0 px-4">
        <?php if ($_logoPath && file_exists($_logoPath)): ?>
            <!-- Company logo image -->
            <img src="<?= e($_logoPath) ?>" alt="Logo"
                 class="h-9 max-w-full object-contain">
        <?php else: ?>
            <!-- Fallback: icon + app name -->
            <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-lg <?= t('sidebar_logo_bg') ?> flex items-center justify-center shrink-0">
                    <span class="text-white text-xs font-bold">eI</span>
                </div>
                <div>
                    <div class="text-white text-sm font-semibold leading-none"><?= e($theme['app_name']) ?></div>
                    <div class="text-slate-500 text-[10px] mt-0.5"><?= e($theme['app_version']) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5 sidebar-scroll">

        <div class="mb-1">
            <?php sidebarLink('dashboard.php', 'Dashboard', $icons['dashboard'], $currentPage) ?>
        </div>

        <div class="<?= t('sidebar_label') ?> text-[10px] font-semibold uppercase tracking-widest px-3 pt-3 pb-1">Sales</div>

        <!-- Invoices accordion -->
        <div>
            <button @click="openGroup = openGroup === 'sales' ? '' : 'sales'"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors <?= t('sidebar_text') ?> <?= t('sidebar_hover') ?>">
                <div class="flex items-center gap-2.5">
                    <?= $icons['invoice'] ?>
                    <span>Invoices</span>
                </div>
                <span :class="openGroup === 'sales' ? 'rotate-180' : ''" class="transition-transform">
                    <?= $icons['chevron'] ?>
                </span>
            </button>
            <div x-show="openGroup === 'sales'"
                 x-transition:enter="transition duration-200 ease-out"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="ml-6 mt-0.5 space-y-0.5">
                <?php
                $subCls    = 'block px-3 py-1.5 rounded-lg text-xs transition-colors ';
                $activeSub = 'text-indigo-400 bg-white/5 font-medium';
                $normalSub = t('sidebar_text') . ' ' . t('sidebar_hover');
                ?>
                <a href="invoice.php"        class="<?= $subCls . ($currentPage === 'invoice.php'        ? $activeSub : $normalSub) ?>">All Invoices</a>
                <a href="create_invoice.php" class="<?= $subCls . ($currentPage === 'create_invoice.php' ? $activeSub : $normalSub) ?>">New Invoice</a>
            </div>
        </div>

        <?php sidebarLink('quotation.php', 'Quotations', $icons['quote'], $currentPage) ?>

        <div class="<?= t('sidebar_label') ?> text-[10px] font-semibold uppercase tracking-widest px-3 pt-3 pb-1">Contacts</div>

        <!-- Customers accordion -->
        <div>
            <button @click="openGroup = openGroup === 'contact' ? '' : 'contact'"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors <?= t('sidebar_text') ?> <?= t('sidebar_hover') ?>">
                <div class="flex items-center gap-2.5">
                    <?= $icons['customer'] ?>
                    <span>Customers</span>
                </div>
                <span :class="openGroup === 'contact' ? 'rotate-180' : ''" class="transition-transform">
                    <?= $icons['chevron'] ?>
                </span>
            </button>
            <div x-show="openGroup === 'contact'"
                 x-transition:enter="transition duration-200 ease-out"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="ml-6 mt-0.5 space-y-0.5">
                <a href="customer.php"             class="<?= $subCls . ($currentPage === 'customer.php' ? $activeSub : $normalSub) ?>">All Customers</a>
                <a href="customer.php?action=add"  class="<?= $subCls . $normalSub ?>">Add Customer</a>
            </div>
        </div>

        <?php sidebarLink('supplier.php', 'Suppliers', $icons['supplier'], $currentPage) ?>

        <div class="<?= t('sidebar_label') ?> text-[10px] font-semibold uppercase tracking-widest px-3 pt-3 pb-1">Settings</div>

        <!-- Company accordion -->
        <div>
            <button @click="openGroup = openGroup === 'settings' ? '' : 'settings'"
                    class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors <?= t('sidebar_text') ?> <?= t('sidebar_hover') ?>">
                <div class="flex items-center gap-2.5">
                    <?= $icons['company'] ?>
                    <span>Company</span>
                </div>
                <span :class="openGroup === 'settings' ? 'rotate-180' : ''" class="transition-transform">
                    <?= $icons['chevron'] ?>
                </span>
            </button>
            <div x-show="openGroup === 'settings'"
                 x-transition:enter="transition duration-200 ease-out"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="ml-6 mt-0.5 space-y-0.5">
                <a href="company_details.php"  class="<?= $subCls . ($currentPage === 'company_details.php'  ? $activeSub : $normalSub) ?>">Company Details</a>
                <a href="invoice_settings.php" class="<?= $subCls . ($currentPage === 'invoice_settings.php' ? $activeSub : $normalSub) ?>">Invoice Settings</a>
            </div>
        </div>

        <?php sidebarLink('lhdn_settings.php', 'LHDN e-Invoice', $icons['lhdn'], $currentPage) ?>

    </nav>

    <!-- User footer — IDs used by header.php JS for instant update -->
    <div class="border-t border-white/5 p-2 shrink-0">
        <div class="flex items-center gap-2.5 px-2 py-2 rounded-lg <?= t('sidebar_hover') ?> cursor-pointer" onclick="document.getElementById('userMenuBtn').click()">
            <div id="sidebarUserInitial" class="w-7 h-7 rounded-full bg-indigo-600 flex items-center justify-center text-white text-xs font-semibold shrink-0">
                <?= e($initials) ?>
            </div>
            <div class="flex-1 min-w-0">
                <div id="sidebarUserName"  class="text-slate-300 text-xs font-medium truncate"><?= e($user['name']) ?></div>
                <div id="sidebarUserEmail" class="text-slate-500 text-[10px] truncate"><?= e($user['email']) ?></div>
            </div>
        </div>
    </div>

</aside>

<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
