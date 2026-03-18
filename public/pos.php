<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/connect_db.php';
require_once __DIR__ . '/includes/DashboardItemsQuery.php';

$userId = (int) $_SESSION['user_id'];

// Preload POS items (server-side, like inventory.php)
$posItems = [];
try {
    $posInput = [
        'sort' => 'name_asc',
        'hide_out_of_stock' => true,
    ];
    $posPageData = inv_fetch_dashboard_items($pdo, $userId, $posInput, 500);
    $posItems = $posPageData['items'] ?? [];
} catch (Throwable $e) {
    $posItems = [];
}

// Preload today's transactions (created today OR settled today) + all receivables
$todaysTransactions = [];
$allReceivables = [];
try {
    $stmtToday = $pdo->prepare("
        SELECT 
            th.transaction_uuid,
            th.transaction_number,
            th.customer,
            th.total_amount,
            th.is_unpaid,
            th.void_date,
            th.settle_date,
            th.created_at,
            GROUP_CONCAT(CONCAT(ti.quantity, 'x ', i.item_name) SEPARATOR ', ') AS items_summary
        FROM transaction_header th
        LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        LEFT JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :user_id
          AND (
                DATE(th.created_at) = CURDATE()
                OR th.settle_date = CURDATE()
              )
        GROUP BY th.transaction_uuid, th.transaction_number, th.customer, th.total_amount, th.is_unpaid, th.void_date, th.settle_date, th.created_at
        ORDER BY th.created_at DESC
    ");
    $stmtToday->execute(['user_id' => $userId]);
    $todaysTransactions = $stmtToday->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmtRecv = $pdo->prepare("
        SELECT 
            th.transaction_uuid,
            th.transaction_number,
            th.customer,
            th.total_amount,
            th.is_unpaid,
            th.void_date,
            th.settle_date,
            th.created_at,
            GROUP_CONCAT(CONCAT(ti.quantity, 'x ', i.item_name) SEPARATOR ', ') AS items_summary
        FROM transaction_header th
        LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        LEFT JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :user_id
          AND th.is_unpaid = 1
          AND th.void_date IS NULL
        GROUP BY th.transaction_uuid, th.transaction_number, th.customer, th.total_amount, th.is_unpaid, th.void_date, th.settle_date, th.created_at
        ORDER BY th.created_at DESC
    ");
    $stmtRecv->execute(['user_id' => $userId]);
    $allReceivables = $stmtRecv->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $todaysTransactions = [];
    $allReceivables = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Terminal - Venda Track</title>
    <link rel="stylesheet" href="assets/css/output.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>

<body class="h-screen bg-slate-100 flex flex-col font-sans text-slate-800 overflow-hidden">
    <div class="fixed inset-0 z-[-2] bg-gradient-to-br from-teal-500/10 via-emerald-500/5 to-cyan-500/10 pointer-events-none"></div>
    <div class="fixed inset-0 z-[-1] bg-[linear-gradient(to_right,#cbd5e1_1px,transparent_1px),linear-gradient(to_bottom,#cbd5e1_1px,transparent_1px)] bg-[size:32px_32px] opacity-30 pointer-events-none [mask-image:linear-gradient(to_bottom,black_40%,transparent_100%)]"></div>

    <?php include 'includes/partial/header.php'; ?>

    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 flex-1 flex flex-col overflow-hidden">
        <?php if ($currentPage === 'pos.php'): ?>
            <div class="pt-4 pb-3">
                <div class="max-w-2xl relative">
                <i
                    class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 transition-colors pointer-events-none"
                    id="pos-search-icon"></i>
                <input type="search" id="pos-search"
                    class="w-full bg-white/70 border border-white/60 rounded-2xl pl-10 pr-4 py-2.5 focus:bg-white focus:ring-4 focus:ring-teal-500/15 focus:border-teal-200 transition-all font-medium text-slate-800 placeholder-slate-400/80 shadow-sm shadow-slate-200"
                    placeholder="Search inventory..." autocomplete="off">
                </div>
            </div>
        <?php endif; ?>

        <div class="flex-1 flex overflow-hidden">

            <main class="flex-1 flex flex-col h-full relative z-10">

                <div class="flex-1 overflow-y-auto pb-6">
                    <div class="bg-white/60 backdrop-blur-xl border border-white rounded-3xl shadow-xl shadow-slate-200/40 p-4 sm:p-5">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div>
                                <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">Products</h2>
                                <p class="text-xs sm:text-sm text-slate-500 font-medium mt-0.5">Tap an item to add it to the order.</p>
                            </div>
                        </div>

                        <div id="pos-item-grid"
                            class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-4">
                        </div>
                <div id="pos-loading"
                    class="hidden text-center py-10 text-slate-400 font-bold uppercase tracking-widest text-sm">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading Items...
                </div>
                <div id="pos-no-results" class="hidden text-center py-12">
                    <i class="fa-solid fa-box-open text-4xl text-slate-300 mb-4"></i>
                    <h3 class="text-lg font-black text-slate-700">No items found</h3>
                    <p class="text-sm text-slate-500 font-medium">Try a different search term.</p>
                </div>
                    </div>
            </div>
        </main>

        <!-- Current Order / Cart container temporarily removed for later modification -->
    </div>
    </div>

    <!-- Item quantity & price selection modal -->
    <div id="item-select-modal"
        class="fixed inset-0 z-[55] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="bg-white/95 backdrop-blur-xl w-full max-w-sm rounded-3xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden border border-white">
            <div class="p-5 border-b border-teal-100/60 flex justify-between items-center shrink-0">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.2em] text-teal-500">Add to order</p>
                    <h2 id="item-select-name" class="text-lg font-black text-slate-900 tracking-tight mt-1 truncate">
                        Item name
                    </h2>
                    <p id="item-select-stock"
                        class="text-[11px] font-semibold text-slate-500 mt-1 uppercase tracking-[0.2em]">
                        In stock
                    </p>
                </div>
                <button id="item-select-close"
                    class="h-8 w-8 rounded-full bg-slate-100 hover:bg-teal-50 text-slate-500 hover:text-teal-700 flex items-center justify-center transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5 space-y-4 flex-1 overflow-y-auto">
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-[0.2em]">Quantity</label>
                    <div class="flex items-center gap-3">
                        <button type="button" id="item-select-qty-minus"
                            class="h-8 w-8 flex items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-50 cursor-pointer">
                            <i class="fa-solid fa-minus"></i>
                        </button>
                        <input type="number" id="item-select-qty"
                            class="w-20 text-center text-base font-bold px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-teal-500/40"
                            min="1" value="1">
                        <button type="button" id="item-select-qty-plus"
                            class="h-8 w-8 flex items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-50 cursor-pointer">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400 font-medium" id="item-select-qty-hint"></p>
                </div>

                <div class="space-y-2">
                    <p class="block text-xs font-bold text-slate-500 uppercase tracking-[0.2em]">Price type</p>
                    <div class="grid grid-cols-2 gap-3" id="item-select-price-options">
                        <label
                            class="price-option-retail flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 cursor-pointer hover:border-teal-400 hover:bg-teal-50/40">
                            <input type="radio" name="item-select-price" value="retail" class="hidden" checked>
                            <div
                                class="h-4 w-4 rounded-full border border-slate-300 flex items-center justify-center">
                                <span class="dot h-2 w-2 rounded-full bg-teal-500 hidden"></span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-800">Retail</span>
                                <span id="item-select-retail"
                                    class="text-[11px] font-semibold text-slate-500">₱0.00</span>
                            </div>
                        </label>

                        <label
                            class="price-option-wholesale flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-200 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/40">
                            <input type="radio" name="item-select-price" value="wholesale" class="hidden">
                            <div
                                class="h-4 w-4 rounded-full border border-slate-300 flex items-center justify-center">
                                <span class="dot h-2 w-2 rounded-full bg-emerald-500 hidden"></span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-800">Wholesale</span>
                                <span id="item-select-wholesale"
                                    class="text-[11px] font-semibold text-slate-500">₱0.00</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div
                    class="mt-2 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 flex justify-between items-center">
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-[0.2em]">Total</div>
                    <div id="item-select-total" class="text-2xl font-black text-slate-900">₱0.00</div>
                </div>
            </div>

            <div class="p-4 border-t border-teal-100/60 bg-teal-50/40 flex justify-end gap-3 shrink-0">
                <button id="item-select-cancel"
                    class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-white hover:border-slate-300 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button id="item-select-add"
                    class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-sm font-black tracking-[0.2em] uppercase shadow-md shadow-teal-200 cursor-pointer">
                    Add to Order
                </button>
            </div>
        </div>
    </div>

    <!-- Floating checkout button (appears when cart has items) -->
    <button id="floating-checkout"
        class="hidden fixed bottom-4 right-4 z-40 bg-teal-600 hover:bg-teal-700 text-white rounded-full shadow-lg shadow-teal-200/80 px-5 py-3 flex items-center gap-3 text-sm font-black tracking-widest uppercase cursor-pointer">
        <span class="inline-flex items-center justify-center h-7 w-7 rounded-full bg-white/10 border border-white/30 text-[11px]"
            id="floating-checkout-count">0</span>
        <div class="flex flex-col items-start leading-tight">
            <span>Checkout</span>
            <span class="text-[11px] font-semibold text-teal-100" id="floating-checkout-total">₱0.00</span>
        </div>
    </button>

    <div id="sales-history-modal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="bg-white/95 backdrop-blur-xl w-full max-w-2xl rounded-[2rem] shadow-2xl flex flex-col max-h-[85vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center shrink-0">
                <h2 class="text-xl font-black text-slate-900 tracking-tight">Today's Transactions</h2>
                <button
                    class="close-sales-modal h-8 w-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-6 space-y-3" id="ui-transaction-list">
            </div>
        </div>
    </div>

    <!-- Step-by-step checkout wizard -->
    <div id="checkout-wizard-modal"
        class="fixed inset-0 z-[65] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="bg-white w-full max-w-lg rounded-[2rem] shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex justify-between items-center shrink-0">
                <div class="flex items-center gap-3">
                    <button id="co-back-inline"
                        class="hidden h-8 w-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-teal-500">Step <span
                                id="co-step-number">1</span> of 2</p>
                        <h2 id="co-step-title" class="text-xl font-black text-slate-900 tracking-tight mt-1">Review
                            Order
                        </h2>
                    </div>
                </div>
                <button id="co-close"
                    class="h-8 w-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto">
                <!-- Step 1: Review order items -->
                <div id="co-step-1" class="p-5 space-y-4">
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-4 max-h-64 overflow-y-auto"
                        id="co-items-container">
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-bold text-slate-500">Order Total</span>
                        <span class="text-2xl font-black text-slate-900" id="co-total-1">₱0.00</span>
                    </div>
                    <p class="text-xs text-slate-400 font-medium">
                        You can still go back to the main screen to adjust quantities before checking out.
                    </p>
                </div>

                <!-- Step 2: Payment type + optional credit customer -->
                <div id="co-step-2" class="hidden p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3" id="co-payment-options">
                        <label
                            class="co-pay-option co-pay-cash flex items-center gap-3 px-3 py-3 rounded-2xl border border-slate-200 cursor-pointer hover:border-emerald-400 hover:bg-emerald-50/40">
                            <input type="radio" name="co-payment" value="cash" class="hidden" checked>
                            <div
                                class="h-4 w-4 rounded-full border border-slate-300 flex items-center justify-center">
                                <span class="dot h-2 w-2 rounded-full bg-emerald-500 hidden"></span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-800">Cash (Paid)</span>
                                <span class="text-[11px] font-semibold text-slate-500">Mark as fully paid</span>
                            </div>
                        </label>

                        <label
                            class="co-pay-option co-pay-credit flex items-center gap-3 px-3 py-3 rounded-2xl border border-slate-200 cursor-pointer hover:border-amber-400 hover:bg-amber-50/40">
                            <input type="radio" name="co-payment" value="credit" class="hidden">
                            <div
                                class="h-4 w-4 rounded-full border border-slate-300 flex items-center justify-center">
                                <span class="dot h-2 w-2 rounded-full bg-amber-500 hidden"></span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-slate-800">On Credit (Receivable)</span>
                                <span class="text-[11px] font-semibold text-slate-500">Customer will pay later</span>
                            </div>
                        </label>
                    </div>

                    <div class="mt-4 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 flex justify-between items-center">
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-[0.2em]">Total Amount</div>
                        <div class="text-2xl font-black text-slate-900" id="co-total-2">₱0.00</div>
                    </div>
                    <div id="co-credit-name-wrapper" class="hidden space-y-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-[0.2em]">
                            Customer Name
                        </label>
                        <input type="text" id="co-credit-name"
                            class="w-full text-sm px-3 py-2 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                            placeholder="e.g. Juan Dela Cruz">
                    </div>
                </div>
            </div>

            <div
                class="p-4 border-t border-slate-100 bg-slate-50/80 flex justify-end items-center gap-3 shrink-0">
                <button id="co-next"
                    class="px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-sm font-black tracking-[0.2em] uppercase shadow-md shadow-teal-200 cursor-pointer">
                    Next
                </button>
                <button id="co-confirm"
                    class="hidden px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-black tracking-[0.2em] uppercase shadow-md shadow-emerald-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    Confirm &amp; Process
                </button>
            </div>
        </div>
    </div>

    <!-- Confirm action modal (for Void / Pay) -->
    <div id="confirm-action-modal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 flex flex-col gap-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p id="confirm-action-title" class="text-lg font-black text-slate-900">Confirm Action</p>
                    <p id="confirm-action-message" class="mt-1 text-sm text-slate-600"></p>
                </div>
                <button id="confirm-action-close"
                    class="h-8 w-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="flex justify-end gap-2 mt-2">
                <button id="confirm-action-cancel"
                    class="px-4 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-600 hover:bg-white hover:border-slate-300 transition-colors cursor-pointer">
                    Cancel
                </button>
                <button id="confirm-action-confirm"
                    class="px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-black tracking-[0.2em] uppercase shadow-md shadow-red-200 cursor-pointer">
                    Confirm
                </button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/toast-helper.js"></script>
    <script>
        // Server-preloaded POS data (no AJAX reads required)
        window.__POS_ITEMS__ = <?php echo json_encode($posItems, JSON_UNESCAPED_SLASHES); ?>;
        window.__TODAYS_TRANSACTIONS__ = <?php echo json_encode($todaysTransactions, JSON_UNESCAPED_SLASHES); ?>;
        window.__ALL_RECEIVABLES__ = <?php echo json_encode($allReceivables, JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script>
        $(document).ready(function () {

            // ------------------------------------------
            // A. GLOBAL STATE & HEADER LOGIC
            // ------------------------------------------
            let cart = [];
            let searchDebounceTimer;
            let allItems = Array.isArray(window.__POS_ITEMS__) ? window.__POS_ITEMS__ : [];
            let checkoutStep = 1;
            let selectedItemForCart = null;
            let checkoutTotal = 0;
            let pendingAction = null;
            let todaysTransactions = { success: true, transactions: Array.isArray(window.__TODAYS_TRANSACTIONS__) ? window.__TODAYS_TRANSACTIONS__ : [] };
            let allReceivables = { success: true, transactions: Array.isArray(window.__ALL_RECEIVABLES__) ? window.__ALL_RECEIVABLES__ : [] };

            const $grid = $('#pos-item-grid');
            const $loader = $('#pos-loading');
            const $noResults = $('#pos-no-results');
            const $salesModal = $('#sales-history-modal');
            const $itemSelectModal = $('#item-select-modal');
            const $checkoutModal = $('#checkout-wizard-modal');
            const $confirmModal = $('#confirm-action-modal');
            const $profileBtn = $('#profileTrigger');
            const $profileMenu = $('#googleMenu');
            const $posSearch = $('#pos-search');
            const $posSearchIcon = $('#pos-search-icon');

            // POS Search theming (teal focus)
            if ($posSearch.length && $posSearchIcon.length) {
                $posSearch.on('focus', () => $posSearchIcon.removeClass('text-slate-400').addClass('text-teal-600'));
                $posSearch.on('blur', () => $posSearchIcon.removeClass('text-teal-600').addClass('text-slate-400'));
            }

            // Header Profile Dropdown
            $profileBtn.on('click', function (e) {
                e.stopPropagation();
                $profileMenu.toggleClass('hidden');
            });

            $(document).on('click', function (e) {
                // If the click is inside any modal/backdrop, don't auto-close the account menu.
                // This prevents "close modal" clicks from also closing the google menu.
                if ($(e.target).closest('#sales-history-modal, #item-select-modal, #checkout-wizard-modal, #confirm-action-modal').length > 0) {
                    return;
                }

                if (
                    !$profileMenu.is(e.target) &&
                    $profileMenu.has(e.target).length === 0 &&
                    !$profileBtn.is(e.target)
                ) {
                    $profileMenu.addClass('hidden');
                }
            });


            // ------------------------------------------
            // B. ITEM GRID & LIVE SEARCH
            // ------------------------------------------
            function renderItems(items) {
                $grid.empty();

                // Filter out items with zero or negative stock
                const visibleItems = (items || []).filter(item => parseInt(item.item_count, 10) > 0);

                if (visibleItems.length === 0) {
                    $noResults.removeClass('hidden');
                    return;
                }

                $noResults.addClass('hidden');

                visibleItems.forEach(item => {
                    const imgSrc = item.image_thumb_path ? item.image_thumb_path : 'assets/images/placeholder.png';
                    const initial = item.item_name.charAt(0).toUpperCase();

                    const imageHtml = item.image_thumb_path
                        ? `<img src="${imgSrc}" class="w-full h-full object-cover" loading="lazy">`
                        : `<div class="w-full h-full flex items-center justify-center bg-slate-200 text-slate-400 font-black text-2xl">${initial}</div>`;

                    const card = `
                        <div class="pos-item-card bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden cursor-pointer hover:shadow-md hover:border-teal-200 transition-all active:scale-95 group flex flex-col"
                             data-id="${item.item_id}" 
                             data-name="${item.item_name}" 
                             data-retail="${item.retail_price}" 
                             data-wholesale="${item.wholesale_price}" 
                             data-image="${item.image_thumb_path || ''}"
                             data-stock="${item.item_count}">
                            <div class="aspect-square w-full bg-slate-50 relative shrink-0">
                                ${imageHtml}
                                ${parseInt(item.item_count, 10) > 0
                                    ? `<div class="absolute top-2 right-2 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-lg text[10px] font-black text-slate-700 shadow-sm">
                                            Qty: ${item.item_count}
                                       </div>`
                                    : ''
                                }
                            </div>
                            <div class="p-3 flex-1 flex flex-col justify-center">
                                <h3 class="text-sm font-bold text-slate-800 truncate group-hover:text-teal-700 transition-colors">${item.item_name}</h3>
                                <p class="text-xs font-black text-teal-600 mt-1">₱${parseFloat(item.retail_price).toFixed(2)}</p>
                            </div>
                        </div>
                    `;
                    $grid.append(card);
                });
            }

            function applySearchFilter() {
                const query = ($('#pos-search').val() || '').toString().toLowerCase().trim();

                if (!query) {
                    renderItems(allItems);
                    return;
                }

                const filtered = allItems.filter(item =>
                    item.item_name && item.item_name.toLowerCase().includes(query)
                );

                renderItems(filtered);
            }

            function loadPosItems() {
                // Items are preloaded server-side; just render them.
                $loader.addClass('hidden');
                applySearchFilter();
            }

            // Stock sync: refresh item_count every 5 minutes when online
            function syncStockIfOnline() {
                if (typeof navigator !== 'undefined' && navigator.onLine === false) return;

                $.get('includes/ajax_pos_items.php', function (res) {
                    if (!res || !res.success || !Array.isArray(res.items)) return;

                    const stockById = {};
                    res.items.forEach(it => {
                        stockById[String(it.item_id)] = parseInt(it.item_count, 10) || 0;
                    });

                    allItems = (allItems || []).map(it => {
                        const id = String(it.item_id);
                        if (!(id in stockById)) return it;
                        const next = { ...it };
                        next.item_count = stockById[id];
                        return next;
                    });

                    applySearchFilter();
                }, 'json');
            }

            $(document).on('input', '#pos-search', function () {
                clearTimeout(searchDebounceTimer);

                searchDebounceTimer = setTimeout(() => {
                    applySearchFilter();
                }, 200);
            });

            loadPosItems();
            // Initial sync after load (in case stocks changed since render)
            setTimeout(syncStockIfOnline, 3000);
            // Repeat every 5 minutes
            setInterval(syncStockIfOnline, 5 * 60 * 1000);


            // ------------------------------------------
            // C. CART MANAGEMENT
            // ------------------------------------------
            function renderCart() {
                const $container = $('#cart-items-container');
                const $emptyMsg = $('#empty-cart-msg');
                let total = 0;

                $container.find('.cart-item').remove();

                if (cart.length === 0) {
                    $emptyMsg.removeClass('hidden');
                    $('#checkout-btn').prop('disabled', true);

                    // If all items are removed while checkout wizard is open, close it
                    if ($checkoutModal.hasClass('flex')) {
                        $checkoutModal.addClass('hidden').removeClass('flex');
                    }
                } else {
                    $emptyMsg.addClass('hidden');
                    $('#checkout-btn').prop('disabled', false);

                    cart.forEach(item => {
                        const subtotal = item.price * item.qty;
                        total += subtotal;

                        $container.append(`
                            <div class="cart-item bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-2">
                                <div class="flex justify-between items-start">
                                    <span class="text-sm font-bold text-slate-800 line-clamp-2">${item.name}</span>
                                    <span class="text-sm font-black text-teal-700">₱${subtotal.toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <p class="text-[10px] font-bold text-slate-400">₱${item.price.toFixed(2)} each</p>
                                    <div class="flex items-center gap-2 bg-slate-50 rounded-lg p-1 border border-slate-200">
                                        <button class="cart-action w-6 h-6 flex items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-red-500 hover:shadow-sm transition-all" data-id="${item.id}" data-action="decrease"><i class="fa-solid fa-minus text-[10px]"></i></button>
                                        <span class="text-xs font-black w-4 text-center">${item.qty}</span>
                                        <button class="cart-action w-6 h-6 flex items-center justify-center rounded-md text-slate-500 hover:bg-white hover:text-teal-700 hover:shadow-sm transition-all" data-id="${item.id}" data-action="increase"><i class="fa-solid fa-plus text-[10px]"></i></button>
                                    </div>
                                </div>
                            </div>
                        `);
                    });
                }

                $('#cart-subtotal, #cart-total').text('₱' + total.toFixed(2));

                // Update floating checkout button
                if (cart.length === 0) {
                    $('#floating-checkout').addClass('hidden');
                } else {
                    $('#floating-checkout').removeClass('hidden');
                    $('#floating-checkout-count').text(cart.length);
                    $('#floating-checkout-total').text('₱' + total.toFixed(2));
                }

                // If checkout wizard is open, re-render step 1 list & totals to stay in sync
                if ($checkoutModal.hasClass('flex')) {
                    const $items = $('#co-items-container');
                    $items.empty();
                    let wizardTotal = 0;

                    cart.forEach(item => {
                        const subtotal = item.price * item.qty;
                        wizardTotal += subtotal;

                        const canDecreaseToDelete = item.qty === 1;
                        const hasImage = !!item.image;
                        const imageInitial = item.name ? item.name.charAt(0).toUpperCase() : '?';

                        $items.append(`
                            <div class="flex items-center justify-between text-sm py-2 border-b border-slate-100 last:border-b-0">
                                <div class="flex items-center gap-3 flex-1 pr-3">
                                    <div class="h-12 w-12 rounded-xl overflow-hidden bg-slate-100 flex-shrink-0 flex items-center justify-center">
                                        ${hasImage
                                            ? '<img src="' + item.image + '" class="h-full w-full object-cover" loading="lazy">'
                                            : '<span class="text-sm font-black text-slate-400">' + imageInitial + '</span>'
                                        }
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-slate-800 font-semibold truncate">${item.name}</p>
                                        <p class="text-[11px] text-slate-400 font-medium truncate">₱${item.price.toFixed(2)} each</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center gap-2 bg-slate-50 rounded-full px-2 py-1 border border-slate-200">
                                        <button 
                                            class="co-cart-action h-7 w-7 flex items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-red-500 hover:shadow-sm transition-all"
                                            data-id="${item.id}" 
                                            data-action="${canDecreaseToDelete ? 'remove' : 'decrease'}">
                                            ${canDecreaseToDelete
                                                ? '<i class="fa-solid fa-trash-can text-[11px]"></i>'
                                                : '<i class="fa-solid fa-minus text-[11px]"></i>'}
                                        </button>
                                        <span class="text-xs font-black w-4 text-center">${item.qty}</span>
                                        <button 
                                            class="co-cart-action h-7 w-7 flex items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-teal-700 hover:shadow-sm transition-all"
                                            data-id="${item.id}" 
                                            data-action="increase">
                                            <i class="fa-solid fa-plus text-[11px]"></i>
                                        </button>
                                    </div>
                                    <span class="text-sm font-black text-slate-900 min-w-[80px] text-right">₱${subtotal.toFixed(2)}</span>
                                </div>
                            </div>
                        `);
                    });

                    checkoutTotal = wizardTotal;
                    $('#co-total-1, #co-total-2').text('₱' + wizardTotal.toFixed(2));
                }
            }

            // Item click -> open quantity & price modal
            function updateItemSelectTotal() {
                if (!selectedItemForCart) return;

                const qty = parseInt($('#item-select-qty').val(), 10) || 1;
                const priceType = $('input[name="item-select-price"]:checked').val() === 'wholesale'
                    ? 'wholesale'
                    : 'retail';
                const unitPrice = priceType === 'wholesale'
                    ? selectedItemForCart.wholesalePrice
                    : selectedItemForCart.retailPrice;

                const total = qty * unitPrice;
                $('#item-select-total').text('₱' + total.toFixed(2));

                // Reset base styles
                $('.price-option-retail')
                    .removeClass('bg-teal-50 border-teal-400')
                    .addClass('bg-white border-slate-200');
                $('.price-option-wholesale')
                    .removeClass('bg-emerald-50 border-emerald-400')
                    .addClass('bg-white border-slate-200');
                $('#item-select-total')
                    .removeClass('text-teal-500 text-emerald-500')
                    .addClass('text-slate-900');

                // Highlight selected price option + total color
                $('#item-select-price-options label .dot').addClass('hidden');
                if (priceType === 'wholesale') {
                    $('.price-option-wholesale .dot').removeClass('hidden');
                    $('.price-option-wholesale')
                        .removeClass('bg-white border-slate-200')
                        .addClass('bg-emerald-50 border-emerald-400');
                    $('#item-select-total')
                        .removeClass('text-slate-900')
                        .addClass('text-emerald-500');
                } else {
                    $('.price-option-retail .dot').removeClass('hidden');
                    $('.price-option-retail')
                        .removeClass('bg-white border-slate-200')
                        .addClass('bg-teal-50 border-teal-400');
                    $('#item-select-total')
                        .removeClass('text-slate-900')
                        .addClass('text-teal-500');
                }
            }

            $(document).on('click', '.pos-item-card', function () {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const retailPrice = parseFloat($(this).data('retail'));
                const wholesalePriceRaw = $(this).data('wholesale');
                const wholesalePrice = wholesalePriceRaw !== undefined && wholesalePriceRaw !== null
                    ? parseFloat(wholesalePriceRaw)
                    : 0;
                const image = $(this).data('image') || '';
                const maxStock = parseInt($(this).data('stock'), 10);

                if (!maxStock || maxStock <= 0) {
                    if (typeof showToast === 'function') {
                        showToast('error', 'This item is out of stock.');
                    }
                    return;
                }

                selectedItemForCart = {
                    id,
                    name,
                    retailPrice: retailPrice || 0,
                    wholesalePrice: wholesalePrice || 0,
                    image: image || '',
                    maxStock
                };

                // Populate modal UI
                $('#item-select-name').text(name);
                $('#item-select-stock').text('In stock: ' + maxStock);
                $('#item-select-qty').val(1).attr({ min: 1, max: maxStock });
                $('#item-select-qty-hint').text('Maximum available: ' + maxStock);
                $('#item-select-retail').text('₱' + (retailPrice || 0).toFixed(2));
                $('#item-select-wholesale').text('₱' + (wholesalePrice || 0).toFixed(2));

                // Default price selection: retail (or wholesale if retail is 0 and wholesale > 0)
                let defaultType = 'retail';
                if ((!retailPrice || retailPrice <= 0) && wholesalePrice > 0) {
                    defaultType = 'wholesale';
                }
                $('input[name="item-select-price"][value="' + defaultType + '"]').prop('checked', true);

                updateItemSelectTotal();
                $itemSelectModal.removeClass('hidden').addClass('flex');
            });

            $('#item-select-qty-minus').on('click', function () {
                if (!selectedItemForCart) return;
                const $input = $('#item-select-qty');
                let val = parseInt($input.val(), 10) || 1;
                val = Math.max(1, val - 1);
                $input.val(val);
                updateItemSelectTotal();
            });

            $('#item-select-qty-plus').on('click', function () {
                if (!selectedItemForCart) return;
                const $input = $('#item-select-qty');
                let val = parseInt($input.val(), 10) || 1;
                val = Math.min(selectedItemForCart.maxStock, val + 1);
                $input.val(val);
                updateItemSelectTotal();
            });

            $('#item-select-qty').on('input', function () {
                if (!selectedItemForCart) return;
                let val = parseInt($(this).val(), 10) || 1;
                if (val < 1) val = 1;
                if (val > selectedItemForCart.maxStock) val = selectedItemForCart.maxStock;
                $(this).val(val);
                updateItemSelectTotal();
            });

            $('#item-select-price-options').on('click', 'label', function () {
                const radio = $(this).find('input[type="radio"]');
                radio.prop('checked', true);
                updateItemSelectTotal();
            });

            function closeItemSelectModal() {
                $itemSelectModal.addClass('hidden').removeClass('flex');
                selectedItemForCart = null;
            }

            $('#item-select-close, #item-select-cancel').on('click', function () {
                closeItemSelectModal();
            });

            $('#item-select-add').on('click', function () {
                if (!selectedItemForCart) return;

                const qty = parseInt($('#item-select-qty').val(), 10) || 1;
                const priceType = $('input[name="item-select-price"]:checked').val() === 'wholesale'
                    ? 'wholesale'
                    : 'retail';
                const unitPrice = priceType === 'wholesale'
                    ? selectedItemForCart.wholesalePrice
                    : selectedItemForCart.retailPrice;

                if (qty < 1 || qty > selectedItemForCart.maxStock) {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Invalid quantity.');
                    }
                    return;
                }

                const existingItem = cart.find(item => item.id === selectedItemForCart.id);

                if (existingItem) {
                    if (existingItem.qty + qty > selectedItemForCart.maxStock) {
                        if (typeof showToast === 'function') {
                            showToast('error', 'Cannot add more. Out of stock!');
                        }
                        return;
                    }
                    existingItem.qty += qty;
                    existingItem.price = unitPrice; // last chosen price wins
                } else {
                    cart.push({
                        id: selectedItemForCart.id,
                        name: selectedItemForCart.name,
                        image: selectedItemForCart.image || '',
                        price: unitPrice,
                        qty,
                        maxStock: selectedItemForCart.maxStock
                    });
                }

                renderCart();
                closeItemSelectModal();
            });

            function handleCartAction(id, action) {
                const itemIndex = cart.findIndex(item => item.id === id);

                if (itemIndex > -1) {
                    if (action === 'increase' && cart[itemIndex].qty < cart[itemIndex].maxStock) {
                        cart[itemIndex].qty++;
                    } else if (action === 'decrease' && cart[itemIndex].qty > 1) {
                        cart[itemIndex].qty--;
                    } else if (action === 'remove' || (action === 'decrease' && cart[itemIndex].qty === 1)) {
                        cart.splice(itemIndex, 1);
                    }
                    renderCart();
                }
            }

            $(document).on('click', '.cart-action', function () {
                const id = $(this).data('id');
                const action = $(this).data('action');
                handleCartAction(id, action);
            });

            // Step 1 (checkout wizard) quantity controls
            $(document).on('click', '.co-cart-action', function () {
                const id = $(this).data('id');
                const action = $(this).data('action');
                handleCartAction(id, action);
            });

            $('#clear-cart').on('click', function () {
                cart = [];
                renderCart();
            });


            // ------------------------------------------
            // D. CHECKOUT (STEP-BY-STEP WIZARD)
            // ------------------------------------------
            function updateConfirmButtonState() {
                const paymentType = $('input[name="co-payment"]:checked').val();
                if (paymentType === 'credit') {
                    const name = ($('#co-credit-name').val() || '').trim();
                    $('#co-confirm').prop('disabled', name.length === 0);
                } else {
                    $('#co-confirm').prop('disabled', false);
                }
            }

            function setCheckoutStep(step) {
                checkoutStep = step;
                $('#co-step-number').text(step);

                if (step === 1) {
                    $('#co-step-title').text('Review Order');
                    $('#co-back-inline').addClass('hidden');
                    $('#co-step-1').removeClass('hidden');
                    $('#co-step-2').addClass('hidden');
                    $('#co-next').removeClass('hidden');
                    $('#co-confirm').addClass('hidden');
                } else if (step === 2) {
                    $('#co-step-title').text('Payment Method');
                    $('#co-back-inline').removeClass('hidden');
                    $('#co-step-1').addClass('hidden');
                    $('#co-step-2').removeClass('hidden');
                    $('#co-next').addClass('hidden');
                    $('#co-confirm').removeClass('hidden');

                    const paymentType = $('input[name="co-payment"]:checked').val();
                    if (paymentType === 'credit') {
                        $('#co-credit-name-wrapper').removeClass('hidden');
                    } else {
                        $('#co-credit-name-wrapper').addClass('hidden');
                    }
                    updateConfirmButtonState();
                }
            }

            function openCheckoutWizard() {
                if (cart.length === 0) {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Cart is empty.');
                    }
                    return;
                }

                // Populate step 1: order items & total
                const $items = $('#co-items-container');
                $items.empty();
                let total = 0;

                cart.forEach(item => {
                    const subtotal = item.price * item.qty;
                    total += subtotal;

                    const canDecreaseToDelete = item.qty === 1;
                    const hasImage = !!item.image;
                    const imageInitial = item.name ? item.name.charAt(0).toUpperCase() : '?';

                    $items.append(`
                        <div class="flex items-center justify-between text-sm py-2 border-b border-slate-100 last:border-b-0">
                            <div class="flex items-center gap-3 flex-1 pr-3">
                                <div class="h-12 w-12 rounded-xl overflow-hidden bg-slate-100 flex-shrink-0 flex items-center justify-center">
                                    ${hasImage
                                        ? `<img src="${item.image}" class="h-full w-full object-cover" loading="lazy">`
                                        : `<span class="text-sm font-black text-slate-400">${imageInitial}</span>`
                                    }
                                </div>
                                <div class="min-w-0">
                                    <p class="text-slate-800 font-semibold truncate">${item.name}</p>
                                    <p class="text-[11px] text-slate-400 font-medium truncate">₱${item.price.toFixed(2)} each</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2 bg-slate-50 rounded-full px-2 py-1 border border-slate-200">
                                    <button 
                                        class="co-cart-action h-7 w-7 flex items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-red-500 hover:shadow-sm transition-all"
                                        data-id="${item.id}" 
                                        data-action="${canDecreaseToDelete ? 'remove' : 'decrease'}">
                                        ${canDecreaseToDelete
                                            ? '<i class="fa-solid fa-trash-can text-[11px]"></i>'
                                            : '<i class="fa-solid fa-minus text-[11px]"></i>'}
                                    </button>
                                    <span class="text-xs font-black w-4 text-center">${item.qty}</span>
                                    <button 
                                        class="co-cart-action h-7 w-7 flex items-center justify-center rounded-full text-slate-500 hover:bg-white hover:text-teal-700 hover:shadow-sm transition-all"
                                        data-id="${item.id}" 
                                        data-action="increase">
                                        <i class="fa-solid fa-plus text-[11px]"></i>
                                    </button>
                                </div>
                                <span class="text-sm font-black text-slate-900 min-w-[80px] text-right">₱${subtotal.toFixed(2)}</span>
                            </div>
                        </div>
                    `);
                });

                checkoutTotal = total;
                $('#co-total-1, #co-total-2').text('₱' + total.toFixed(2));

                // Reset step 2 fields (payment type)
                $('input[name="co-payment"][value="cash"]').prop('checked', true);
                $('#co-payment-options .dot').addClass('hidden');
                $('.co-pay-cash .dot').removeClass('hidden');

                // Reset credit name
                $('#co-credit-name').val('');

                setCheckoutStep(1);
                $checkoutModal.removeClass('hidden').addClass('flex');
            }

            function closeCheckoutWizard() {
                $checkoutModal.addClass('hidden').removeClass('flex');
            }

            function performCheckout($triggerBtn) {
                if (cart.length === 0) return;

                const $btn = $triggerBtn;
                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin mr-2"></i>Processing...');

                const paymentType = $('input[name="co-payment"]:checked').val();
                const isCredit = paymentType === 'credit';
                const customerName = isCredit ? ($('#co-credit-name').val() || '').trim() : '';

                const payload = {
                    customer: customerName,
                    is_unpaid: isCredit,
                    cart: cart
                };

                // Optimistic UI: apply local changes immediately, rollback on failure.
                const snapshot = {
                    cart: JSON.parse(JSON.stringify(cart || [])),
                    allItems: JSON.parse(JSON.stringify(allItems || [])),
                    todays: JSON.parse(JSON.stringify(todaysTransactions || {})),
                    receivables: JSON.parse(JSON.stringify(allReceivables || {})),
                };

                // Clear cart right away
                cart = [];
                renderCart();

                // Update local stock cache and re-render items
                try {
                    const soldMap = {};
                    (payload.cart || []).forEach(ci => {
                        const id = String(ci.id);
                        soldMap[id] = (soldMap[id] || 0) + (parseInt(ci.qty, 10) || 0);
                    });
                    allItems = (allItems || []).map(it => {
                        const id = String(it.item_id);
                        if (!soldMap[id]) return it;
                        const next = { ...it };
                        const prevCount = parseInt(next.item_count, 10) || 0;
                        next.item_count = Math.max(0, prevCount - soldMap[id]);
                        return next;
                    });
                    applySearchFilter();
                } catch (e) { }

                // Add transaction to today's + receivables cache immediately (temp until server confirms)
                const nowIso = new Date().toISOString();
                const itemsSummary = (payload.cart || [])
                    .map(ci => `${parseInt(ci.qty, 10) || 0}x ${ci.name}`)
                    .join(', ');

                const optimisticUuid = 'local-' + Math.random().toString(16).slice(2);
                const optimisticTxn = {
                    transaction_uuid: optimisticUuid,
                    transaction_number: 'PENDING',
                    customer: payload.customer || 'Walk-in',
                    total_amount: String((payload.cart || []).reduce((s, ci) => s + ((parseFloat(ci.price) || 0) * (parseInt(ci.qty, 10) || 0)), 0)),
                    is_unpaid: payload.is_unpaid ? 1 : 0,
                    void_date: null,
                    settle_date: null,
                    created_at: nowIso,
                    items_summary: itemsSummary
                };
                if (todaysTransactions && Array.isArray(todaysTransactions.transactions)) {
                    todaysTransactions.transactions.unshift(optimisticTxn);
                }
                if (payload.is_unpaid && allReceivables && Array.isArray(allReceivables.transactions)) {
                    allReceivables.transactions.unshift(optimisticTxn);
                }
                rerenderTransactionsIfOpen();

                // Show success toast immediately (offline-first)
                if (typeof showToast === 'function') {
                    showToast('success', 'Transaction Saved.');
                }

                $.ajax({
                    url: 'includes/save_transaction.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: function (res) {
                        if (res.success) {
                            // Replace optimistic txn with server-confirmed identifiers
                            const realUuid = res.transaction_uuid || optimisticUuid;
                            const realNumber = res.transaction_number || '';
                            const patchList = (list) => {
                                if (!Array.isArray(list)) return;
                                const idx = list.findIndex(t => String(t.transaction_uuid) === String(optimisticUuid));
                                if (idx >= 0) {
                                    list[idx].transaction_uuid = realUuid;
                                    list[idx].transaction_number = realNumber;
                                }
                            };
                            patchList(todaysTransactions && todaysTransactions.transactions);
                            patchList(allReceivables && allReceivables.transactions);
                            rerenderTransactionsIfOpen();

                            closeCheckoutWizard();
                        } else {
                            // Rollback optimistic changes
                            cart = snapshot.cart;
                            allItems = snapshot.allItems;
                            todaysTransactions = snapshot.todays;
                            allReceivables = snapshot.receivables;
                            renderCart();
                            applySearchFilter();
                            rerenderTransactionsIfOpen();
                            if (typeof showToast === 'function') {
                                showToast('error', res.message || 'Checkout failed.');
                            }
                        }
                    },
                    error: function () {
                        // Rollback optimistic changes
                        cart = snapshot.cart;
                        allItems = snapshot.allItems;
                        todaysTransactions = snapshot.todays;
                        allReceivables = snapshot.receivables;
                        renderCart();
                        applySearchFilter();
                        rerenderTransactionsIfOpen();
                        if (typeof showToast === 'function') {
                            showToast('error', 'Server error during checkout.');
                        }
                    },
                    complete: function () {
                        $btn.prop('disabled', false).text('Confirm & Process');
                    }
                });
            }

            // Open wizard from floating button (and any existing checkout button if present)
            $('#floating-checkout').on('click', function () {
                openCheckoutWizard();
            });

            $('#checkout-btn').on('click', function () {
                openCheckoutWizard();
            });

            // Wizard controls
            $('#co-next').on('click', function () {
                if (checkoutStep === 1) {
                    setCheckoutStep(2);
                }
            });

            $('#co-back-inline').on('click', function () {
                setCheckoutStep(1);
            });

            $('#co-close').on('click', function () {
                closeCheckoutWizard();
            });

            $('#co-confirm').on('click', function () {
                performCheckout($(this));
            });

            // Highlight selected payment type in step 2 and toggle credit input
            $('#co-payment-options').on('click', '.co-pay-option', function () {
                const radio = $(this).find('input[type="radio"]');
                radio.prop('checked', true);
                $('#co-payment-options .dot').addClass('hidden');
                $(this).find('.dot').removeClass('hidden');

                const paymentType = radio.val();
                if (paymentType === 'credit') {
                    $('#co-credit-name-wrapper').removeClass('hidden');
                } else {
                    $('#co-credit-name-wrapper').addClass('hidden');
                }
                updateConfirmButtonState();
            });

            // Enable/disable confirm based on credit customer name
            $('#co-credit-name').on('input', function () {
                updateConfirmButtonState();
            });


            // ------------------------------------------
            // E. SALES HISTORY LIST
            // ------------------------------------------
            function renderTransactionsList(transactions, emptyMessage) {
                let listHtml = '';
                if (!transactions || transactions.length === 0) {
                    listHtml = `<div class="text-center py-6 text-slate-400 text-sm font-medium">${emptyMessage}</div>`;
                } else {
                    transactions.forEach(t => {
                        const isUnpaid = parseInt(t.is_unpaid) === 1;
                        const isVoided = !!t.void_date;
                        const isSettledReceivable = !!t.settle_date;
                        const isPending = (t && t._sync === 'pending') || String(t.transaction_number || '').toUpperCase() === 'PENDING';
                        const customer = t.customer || 'Walk-in';
                        const primaryLabel = isUnpaid
                            ? (customer || 'Walk-in')
                            : (t.transaction_number || 'Receipt');
                        const itemsSummary = t.items_summary || '';

                        const btnLabel = isUnpaid ? 'Pay' : 'Void Sale';
                        const btnColorClasses = isUnpaid
                            ? 'text-emerald-700 bg-emerald-50 hover:bg-emerald-100'
                            : 'text-red-600 bg-red-50 hover:bg-red-100';
                        const statusBadge = isVoided
                            ? `<span class="bg-slate-200 text-slate-700 text-[10px] px-2 py-0.5 rounded font-black uppercase tracking-widest">Voided</span>`
                            : (isSettledReceivable
                                ? `<span class="bg-emerald-100 text-emerald-700 text-[10px] px-2 py-0.5 rounded font-black uppercase tracking-widest">From Receivable</span>`
                                : '');

                        const syncBadge = isPending
                            ? `<span class="inline-flex items-center justify-center bg-teal-50 text-teal-700 text-[10px] px-2 py-0.5 rounded font-black uppercase tracking-widest" title="Pending sync">
                                   <i class="fa-solid fa-spinner fa-spin"></i>
                               </span>`
                            : `<span class="inline-flex items-center justify-center bg-slate-100 text-slate-700 text-[10px] px-2 py-0.5 rounded font-black uppercase tracking-widest" title="Saved">
                                   <i class="fa-solid fa-check"></i>
                               </span>`;

                        listHtml += `
                            <div class="flex justify-between items-start p-4 bg-white border border-slate-200 rounded-xl hover:border-teal-300 hover:shadow-md transition-all">
                                <div class="pr-4">
                                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                                        <p class="text-sm font-bold text-slate-800">${primaryLabel}</p>
                                        ${syncBadge}
                                        ${statusBadge}
                                    </div>
                                    ${itemsSummary ? `<p class="text-xs text-slate-500 leading-snug">${itemsSummary}</p>` : ''}
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <p class="text-lg font-black text-slate-900">₱${parseFloat(t.total_amount).toFixed(2)}</p>
                                    ${isVoided
                                        ? ''
                                        : `<button 
                                            class="inline-void-transaction text-[11px] font-black uppercase tracking-widest px-3 py-1.5 rounded-full cursor-pointer shadow-sm ${btnColorClasses}"
                                            data-uuid="${t.transaction_uuid}"
                                            data-is-unpaid="${isUnpaid ? '1' : '0'}">
                                            ${btnLabel}
                                        </button>`
                                    }
                                </div>
                            </div>
                        `;
                    });
                }
                $('#ui-transaction-list').html(listHtml);
            }

            function rerenderTransactionsIfOpen() {
                if (!$salesModal.hasClass('flex')) return;
                const title = ($('#sales-history-modal h2').text() || '').toLowerCase();

                if (title.indexOf('receivables') !== -1) {
                    const useData = allReceivables;
                    renderTransactionsList((useData && useData.transactions) ? useData.transactions : [], 'No receivables found.');
                } else {
                    const useData = todaysTransactions;
                    const onlyPaid = (useData && useData.transactions)
                        ? useData.transactions.filter(t => parseInt(t.is_unpaid) !== 1)
                        : [];
                    renderTransactionsList(onlyPaid, 'No transactions yet today.');
                }
            }

            $('#open-sales-history').on('click', function () {
                $('#sales-history-modal h2').text("Today's Transactions");
                $('#ui-transaction-list').html('<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-slate-400"></i></div>');
                $salesModal.removeClass('hidden').addClass('flex');

                const useData = todaysTransactions;
                if (useData && useData.success) {
                    const onlyPaid = (useData.transactions || []).filter(t => parseInt(t.is_unpaid) !== 1);
                    renderTransactionsList(onlyPaid, 'No transactions yet today.');
                } else {
                    $.get('includes/ajax_transactions.php', function (res) {
                        if (res.success) {
                            todaysTransactions = res;
                            const onlyPaid = (res.transactions || []).filter(t => parseInt(t.is_unpaid) !== 1);
                            renderTransactionsList(onlyPaid, 'No transactions yet today.');
                        }
                    });
                }
            });

            $('#open-receivables').on('click', function () {
                $('#sales-history-modal h2').text("Receivables");
                $('#ui-transaction-list').html('<div class="text-center py-4"><i class="fa-solid fa-spinner fa-spin text-slate-400"></i></div>');
                $salesModal.removeClass('hidden').addClass('flex');

                const useData = allReceivables;
                if (useData && useData.success) {
                    renderTransactionsList(useData.transactions || [], 'No receivables found.');
                } else {
                    renderTransactionsList([], 'No receivables found.');
                }
            });

            $(document).on('click', '.inline-void-transaction', function () {
                const isUnpaid = $(this).data('is-unpaid') === 1 || $(this).data('is-unpaid') === '1';
                const uuid = $(this).data('uuid');

                pendingAction = { isUnpaid, uuid };

                if (isUnpaid) {
                    $('#confirm-action-title').text('Pay Receivable');
                    $('#confirm-action-message').text('Mark this receivable as fully paid?');
                    $('#confirm-action-confirm')
                        .removeClass('bg-red-600 hover:bg-red-700 shadow-red-200')
                        .addClass('bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200')
                        .text('Pay');
                } else {
                    $('#confirm-action-title').text('Void Sale');
                    $('#confirm-action-message').text('WARNING: Are you sure you want to void this transaction? Stock will be restored.');
                    $('#confirm-action-confirm')
                        .removeClass('bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200')
                        .addClass('bg-red-600 hover:bg-red-700 shadow-red-200')
                        .text('Void');
                }

                $confirmModal.removeClass('hidden').addClass('flex');
            });

            function closeConfirmModal() {
                $confirmModal.addClass('hidden').removeClass('flex');
                pendingAction = null;
                $('#confirm-action-confirm').prop('disabled', false);
                $('#confirm-action-cancel').prop('disabled', false);
                $('#confirm-action-close').prop('disabled', false);
                const $confirmBtn = $('#confirm-action-confirm');
                const originalText = $confirmBtn.data('original-text');
                if (originalText) {
                    $confirmBtn.text(originalText);
                    $confirmBtn.removeData('original-text');
                }
            }

            $('#confirm-action-close, #confirm-action-cancel').on('click', function () {
                closeConfirmModal();
            });

            $('#confirm-action-confirm').on('click', function () {
                if (!pendingAction) {
                    closeConfirmModal();
                    return;
                }

                const $confirmBtn = $('#confirm-action-confirm');
                const $cancelBtn = $('#confirm-action-cancel');
                const $closeBtn = $('#confirm-action-close');

                // Offline-first: disable immediately, close modal (no "Processing..." UI)
                $confirmBtn.prop('disabled', true);
                $cancelBtn.prop('disabled', true);
                $closeBtn.prop('disabled', true);

                const { isUnpaid, uuid } = pendingAction;
                closeConfirmModal();

                if (isUnpaid) {
                    // Optimistic UI: remove from receivables, add to today's as paid-from-receivable
                    const snapshot = {
                        todays: JSON.parse(JSON.stringify(todaysTransactions || {})),
                        receivables: JSON.parse(JSON.stringify(allReceivables || {})),
                    };

                    const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
                    let paidTxn = null;
                    if (allReceivables && Array.isArray(allReceivables.transactions)) {
                        const idx = allReceivables.transactions.findIndex(t => String(t.transaction_uuid) === String(uuid));
                        if (idx >= 0) {
                            paidTxn = { ...allReceivables.transactions[idx] };
                            allReceivables.transactions.splice(idx, 1);
                        }
                    }
                    if (paidTxn) {
                        paidTxn.is_unpaid = 0;
                        paidTxn.settle_date = today;
                        paidTxn._sync = 'pending';
                        if (todaysTransactions && Array.isArray(todaysTransactions.transactions)) {
                            todaysTransactions.transactions = todaysTransactions.transactions.filter(t => String(t.transaction_uuid) !== String(uuid));
                            todaysTransactions.transactions.unshift(paidTxn);
                        }
                    }
                    rerenderTransactionsIfOpen();
                    if (typeof showToast === 'function') showToast('success', 'Receivable Paid!');

                    $.ajax({
                        url: 'includes/pay_receivable.php',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ uuid }),
                        success: function (res) {
                            if (res.success) {
                                // Mark as synced (remove pending flag)
                                if (todaysTransactions && Array.isArray(todaysTransactions.transactions)) {
                                    const tIdx = todaysTransactions.transactions.findIndex(t => String(t.transaction_uuid) === String(uuid));
                                    if (tIdx >= 0) {
                                        delete todaysTransactions.transactions[tIdx]._sync;
                                    }
                                }
                                rerenderTransactionsIfOpen();
                            } else {
                                // Rollback optimistic change
                                todaysTransactions = snapshot.todays;
                                allReceivables = snapshot.receivables;
                                rerenderTransactionsIfOpen();
                                if (typeof showToast === 'function') {
                                    showToast('error', res.message || 'Unable to pay receivable.');
                                }
                            }
                        },
                        error: function () {
                            // Rollback optimistic change
                            todaysTransactions = snapshot.todays;
                            allReceivables = snapshot.receivables;
                            rerenderTransactionsIfOpen();
                            if (typeof showToast === 'function') {
                                showToast('error', 'Server error while paying receivable.');
                            }
                        },
                        complete: function () { }
                    });
                } else {
                    // Optimistic UI: mark voided / remove from receivables list
                    const snapshot = {
                        todays: JSON.parse(JSON.stringify(todaysTransactions || {})),
                        receivables: JSON.parse(JSON.stringify(allReceivables || {})),
                    };
                    const today = new Date().toISOString().slice(0, 10); // YYYY-MM-DD
                    if (todaysTransactions && Array.isArray(todaysTransactions.transactions)) {
                        const tIdx = todaysTransactions.transactions.findIndex(t => String(t.transaction_uuid) === String(uuid));
                        if (tIdx >= 0) {
                            todaysTransactions.transactions[tIdx].void_date = today;
                            todaysTransactions.transactions[tIdx]._sync = 'pending';
                        }
                    }
                    if (allReceivables && Array.isArray(allReceivables.transactions)) {
                        allReceivables.transactions = allReceivables.transactions.filter(t => String(t.transaction_uuid) !== String(uuid));
                    }
                    rerenderTransactionsIfOpen();
                    if (typeof showToast === 'function') showToast('success', 'Transaction Voided.');

                    $.ajax({
                        url: 'includes/void_transaction.php',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ uuid }),
                        success: function (res) {
                            if (res.success) {
                                // Mark as synced (remove pending flag)
                                if (todaysTransactions && Array.isArray(todaysTransactions.transactions)) {
                                    const tIdx = todaysTransactions.transactions.findIndex(t => String(t.transaction_uuid) === String(uuid));
                                    if (tIdx >= 0) {
                                        delete todaysTransactions.transactions[tIdx]._sync;
                                    }
                                }
                                rerenderTransactionsIfOpen();
                                loadPosItems();
                            } else {
                                // Rollback optimistic change
                                todaysTransactions = snapshot.todays;
                                allReceivables = snapshot.receivables;
                                rerenderTransactionsIfOpen();
                                if (typeof showToast === 'function') {
                                    showToast('error', res.message || 'Unable to void transaction.');
                                }
                            }
                        },
                        error: function () {
                            // Rollback optimistic change
                            todaysTransactions = snapshot.todays;
                            allReceivables = snapshot.receivables;
                            rerenderTransactionsIfOpen();
                            if (typeof showToast === 'function') {
                                showToast('error', 'Server error while voiding transaction.');
                            }
                        },
                        complete: function () { }
                    });
                }
            });

            $('.close-sales-modal').on('click', () => $salesModal.addClass('hidden').removeClass('flex'));

            // Global Escape key modal closer
            $(document).on('keydown', function (e) {
                if (e.key === "Escape") {
                    $salesModal.addClass('hidden').removeClass('flex');
                    $itemSelectModal.addClass('hidden').removeClass('flex');
                    $profileMenu.addClass('hidden');
                }
            });
        });
    </script>
</body>

</html>