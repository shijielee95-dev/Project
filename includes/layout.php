<?php
/**
 * includes/layout.php
 */

function layoutOpen(string $pageTitle = '', string $pageSubtitle = ''): void {
    global $theme;
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= htmlspecialchars($theme['app_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-scroll { scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.3) transparent; }
        .sidebar-scroll::-webkit-scrollbar { width: 4px; }
        .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.3); border-radius: 99px; }
        .sidebar-scroll::-webkit-scrollbar-button { display: none; }
        main { scrollbar-width: thin; scrollbar-color: rgba(148,163,184,0.4) transparent; }
        main::-webkit-scrollbar { width: 5px; }
        main::-webkit-scrollbar-track { background: transparent; }
        main::-webkit-scrollbar-thumb { background: rgba(148,163,184,0.4); border-radius: 99px; }
        @keyframes toastIn { from { transform:translateY(-8px); opacity:0; } to { transform:translateY(0); opacity:1; } }
        #toastContainer > div { animation: toastIn 0.2s ease-out; }
        input:-webkit-autofill { -webkit-box-shadow: 0 0 0 100px white inset; }
        @media print { aside, header, #panelOverlay { display:none !important; } main { padding:0 !important; overflow:visible !important; } body { overflow:visible !important; height:auto !important; } }
    </style>
</head>
<body class="<?= $theme['body_bg'] ?> h-screen overflow-hidden">

<div class="flex h-screen">

    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Right column — header is sticky ONLY within this column, sidebar is untouched -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        <?php include __DIR__ . '/header.php'; ?>

        <?php if ($pageTitle): ?>
        <div class="h-14 px-6 flex items-center justify-between border-b border-slate-200 bg-white shrink-0">
            <div>
                <h1 class="text-base font-semibold text-slate-800"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if ($pageSubtitle): ?>
                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div id="pageActions" class="flex items-center gap-2"></div>
        </div>
        <?php endif; ?>

        <main class="flex-1 overflow-y-auto p-6">
    <?php

    $flash = getFlash();
    if ($flash) {
        $colors = [
            'success' => 'bg-green-50 border-green-200 text-green-800',
            'error'   => 'bg-red-50 border-red-200 text-red-800',
            'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
            'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
        ];
        $cls = $colors[$flash['type']] ?? $colors['info'];
        echo "<div class=\"mb-5 px-4 py-3 rounded-lg border text-sm {$cls}\">" . htmlspecialchars($flash['message']) . "</div>";
    }
}

function layoutClose(): void {
    ?>
        </main>
    </div>
</div>
</body>
</html>
    <?php
}
