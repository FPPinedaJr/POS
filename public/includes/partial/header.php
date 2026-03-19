<?php
// Get the name of the current file (e.g., 'portal.php' or 'inventory.php')
$currentPage = basename($_SERVER['PHP_SELF']);

// Theme accents per page (matches portal action colors)
$theme = 'indigo';
if ($currentPage === 'pos.php') {
    $theme = 'teal';
} elseif ($currentPage === 'inventory.php') {
    $theme = 'indigo';
} elseif ($currentPage === 'reports.php' || $currentPage === 'analytics.php') {
    $theme = 'fuchsia';
}

$ringClass = $theme === 'teal'
    ? 'hover:ring-teal-500/10'
    : ($theme === 'fuchsia' ? 'hover:ring-fuchsia-500/10' : 'hover:ring-indigo-500/10');
$hoverTextClass = $theme === 'teal'
    ? 'hover:text-teal-600'
    : ($theme === 'fuchsia' ? 'hover:text-fuchsia-600' : 'hover:text-indigo-600');
$iconClass = $theme === 'teal'
    ? 'text-teal-400'
    : ($theme === 'fuchsia' ? 'text-fuchsia-400' : 'text-indigo-400');
?>

<header class="bg-white/70 backdrop-blur-xl border-b border-white sticky top-0 z-40 shadow-sm shadow-slate-200/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between gap-4">

        <div class="flex items-center gap-3 shrink-0">
            <?php if ($currentPage === 'portal.php'): ?>
                <a href="#" onclick="window.location.reload(); return false;" class="flex items-center focus:outline-none" aria-label="Refresh portal">
            <?php else: ?>
                <a href="portal.php" class="flex items-center focus:outline-none" aria-label="Go to portal">
            <?php endif; ?>
                <img src="assets/images/header.png" alt="Venda Track"
                    class="h-10 w-auto max-w-[200px] sm:max-w-[240px] object-contain">
            </a>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 shrink-0">
            <div class="flex items-center gap-1.5 sm:gap-3">
                <?php
                $notifCount = isset($lowStockCount) ? (int) $lowStockCount : 0;
                ?>
                <?php if ($currentPage === 'inventory.php'): ?>
                    <div class="relative">
                        <button id="stock-notif-btn" type="button"
                            class="flex items-center justify-center p-1 rounded-full hover:cursor-pointer hover:ring-4 <?php echo $ringClass; ?> transition-all focus:outline-none shadow-sm bg-white/50">
                            <span class="h-9 w-9 flex items-center justify-center text-slate-900">
                                <i class="fa-solid fa-bell text-sm"></i>
                            </span>
                            <?php if ($notifCount > 0): ?>
                                <span
                                    class="absolute top-0 right-0 translate-x-1/3 -translate-y-1/3 h-4 w-4 rounded-full bg-red-500 text-[9px] font-bold text-white flex items-center justify-center shadow-md border border-white leading-none">
                                    <?php echo $notifCount; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <div id="stock-notif-panel"
                            class="hidden absolute right-0 mt-3 w-80 bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white z-50 overflow-hidden">
                            <div class="p-6 flex flex-col text-left relative">
                                <div class="absolute inset-0 linear-gradient-to-br from-sky-500/5 to-indigo-500/5"></div>

                                <div class="relative z-10 mb-3 flex items-center justify-between">
                                    <h3 class="text-base font-semibold text-slate-900">Low Stock Alert</h3>
                                </div>

                                <div class="relative z-10 max-h-80 overflow-y-auto -mx-2 px-1.5">
                                    <?php if (!empty($lowStockItems ?? []) && $notifCount > 0): ?>
                                        <?php foreach ($lowStockItems as $idx => $ls): ?>
                                            <?php
                                            $name = (string) ($ls['name'] ?? '');
                                            $current = (int) ($ls['current'] ?? 0);
                                            $image = isset($ls['image']) ? (string) $ls['image'] : null;
                                            $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : 'I', 0, 1));
                                            ?>
                                            <button type="button"
                                                class="stock-notif-item w-full text-left mb-2 last:mb-0 flex items-center gap-3 px-3 py-1.5">
                                                <div
                                                    class="h-10 w-10 rounded-full overflow-hidden flex-shrink-0 border border-slate-300 bg-slate-100 flex items-center justify-center">
                                                    <?php if ($image): ?>
                                                        <img src="<?php echo htmlspecialchars($image); ?>"
                                                            alt="<?php echo htmlspecialchars($name); ?>"
                                                            class="h-full w-full object-cover">
                                                    <?php else: ?>
                                                        <span class="text-xs font-bold text-sky-600">
                                                            <?php echo htmlspecialchars($initial); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-semibold text-slate-900 truncate">
                                                        <?php echo htmlspecialchars($name); ?>
                                                    </p>
                                                    <p class="text-xs text-slate-600 mt-0.5">
                                                        Remaining stock:
                                                        <span
                                                            class="font-semibold text-sky-600"><?php echo $current; ?></span>
                                                    </p>
                                                    <p class="text-[11px] text-red-500 mt-0.5 font-semibold">
                                                        Stock is at or below its alert level.
                                                    </p>
                                                </div>
                                            </button>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="px-4 py-6 text-center text-sm text-slate-500">
                                            <p class="font-semibold mb-1">No stock alerts</p>
                                            <p class="text-xs text-slate-400">You’re all caught up. Items are above their
                                                thresholds.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="ml-2 flex items-center gap-2">
                    <?php if ($currentPage === 'pos.php'): ?>
                        <button id="pos-open-qr-scanner" type="button"
                            class="sm:hidden group relative flex items-center justify-center p-1.5 rounded-full hover:cursor-pointer hover:ring-4 <?php echo $ringClass; ?> transition-all focus:outline-none shadow-sm bg-white/60 hover:bg-white"
                            title="Scan item QR" aria-label="Scan item QR">
                            <span class="relative h-10 w-10 flex items-center justify-center">
                                <img src="assets/images/scanner-icon.svg" alt=""
                                    class="h-6 w-6 opacity-90 group-hover:opacity-100 transition-all duration-200 group-hover:scale-[1.03]" />
                            </span>
                        </button>
                    <?php endif; ?>

                    <div class="relative">
                        <button id="profileTrigger"
                            class="flex items-center justify-center p-1 rounded-full hover:cursor-pointer hover:ring-4 <?php echo $ringClass; ?> transition-all focus:outline-none shadow-sm bg-white/50">
                            <img class="h-9 w-9 rounded-full object-cover border-2 border-white"
                                src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" alt="User">
                        </button>

                        <div id="googleMenu"
                            class="hidden absolute right-0 mt-3 w-80 bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white z-50 overflow-hidden">
                            <div class="p-6 flex flex-col items-center text-center relative">
                                <div class="absolute inset-0 linear-gradient-to-br from-indigo-500/5 to-purple-500/5"></div>
                                <div class="relative mb-3 z-10">
                                    <img class="h-24 w-24 rounded-full object-cover border-4 border-white shadow-lg"
                                        src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" alt="User">
                                </div>
                                <h2 class="text-xl text-slate-900 font-bold z-10">Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                                <p class="text-sm font-medium text-slate-500 mb-4 z-10"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>

                                <?php if (empty($_SESSION['google_id'])): ?>
                                    <button id="openPassModal"
                                        class="mt-4 px-6 py-2 border hover:cursor-pointer border-slate-200 bg-white/80 rounded-full text-sm font-semibold text-slate-700 hover:bg-white hover:shadow-md transition-all z-10 <?php echo $hoverTextClass; ?>">
                                        Change password
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="bg-slate-50/80 p-2 border-t border-slate-100">
                                <?php if ($currentPage === 'inventory.php'): ?>
                                    <button id="openHistorySidebar"
                                        class="w-full hover:cursor-pointer flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-white <?php echo $hoverTextClass; ?> hover:shadow-sm rounded-2xl transition-all">
                                        <i class="fa-solid fa-clock-rotate-left <?php echo $iconClass; ?>"></i>
                                        <span>Inventory History</span>
                                    </button>
                                <?php endif; ?>

                                <?php if ($currentPage === 'pos.php'): ?>
                                    <button id="open-sales-history"
                                        class="w-full hover:cursor-pointer flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-white <?php echo $hoverTextClass; ?> hover:shadow-sm rounded-2xl transition-all mb-1">
                                        <i class="fa-solid fa-receipt <?php echo $iconClass; ?>"></i>
                                        <span>Today's Transaction</span>
                                    </button>
                                    <button id="open-receivables"
                                        class="w-full hover:cursor-pointer flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-white <?php echo $hoverTextClass; ?> hover:shadow-sm rounded-2xl transition-all mb-1">
                                        <i class="fa-solid fa-hand-holding-dollar <?php echo $iconClass; ?>"></i>
                                        <span>Receivables</span>
                                    </button>
                                <?php endif; ?>

                                <a href="includes/logout.php"
                                    class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-600 hover:bg-red-50 hover:shadow-sm rounded-2xl transition-all mt-1">
                                    <i class="fa-solid fa-right-from-bracket text-red-400"></i>
                                    <span>Sign out</span>
                                </a>
                            </div>
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>