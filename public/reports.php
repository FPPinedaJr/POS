<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
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

<body class="min-h-screen flex flex-col relative font-sans text-slate-800 bg-slate-50">

    <div
        class="fixed inset-0 z-[-2] bg-linear-to-br from-indigo-500/5 via-fuchsia-500/5 to-teal-500/5 pointer-events-none">
    </div>

    <div
        class="fixed inset-0 z-[-1] bg-[linear-gradient(to_right,#cbd5e1_1px,transparent_1px),linear-gradient(to_bottom,#cbd5e1_1px,transparent_1px)] bg-[size:32px_32px] opacity-40 pointer-events-none [mask-image:linear-gradient(to_bottom,black_40%,transparent_100%)]">
    </div>

    <div class="fixed top-0 left-0 right-0 h-1 bg-linear-to-r from-indigo-500 via-fuchsia-500 to-teal-400 z-100"></div>

    <?php include_once("includes/partial/header.php"); ?>

    <main class="flex-1 relative z-10 w-full max-w-7xl mx-auto mb-10 px-4 sm:px-6 py-8">

        <div class="mb-8">
            <h2 class="text-3xl font-black text-slate-800 tracking-tight drop-shadow-sm">Reports Overview</h2>
            <p class="text-sm text-slate-500 font-medium mt-1">Track your business performance and inventory health.</p>
        </div>

        <div id="dashboard-overview-container"></div>

        <div class="flex items-center gap-1 border-b border-slate-200 mb-6 overflow-x-auto hide-scrollbar">
            <button data-target="#tab-sales"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-fuchsia-600 text-fuchsia-600 whitespace-nowrap transition-all">
                <i class="fa-solid fa-chart-line mr-1.5"></i> Sales Report
            </button>
            <button data-target="#tab-income"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-money-bill-wave mr-1.5"></i> Income Report
            </button>
            <button data-target="#tab-receivables"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-file-invoice-dollar mr-1.5"></i> Receivables
            </button>
            <button data-target="#tab-inventory"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-clipboard-list mr-1.5"></i> Inventory
            </button>
            <button data-target="#tab-purchases"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-cart-shopping mr-1.5"></i> Item Purchases
            </button>
            <button data-target="#tab-payables"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-file-invoice mr-1.5"></i> Payables
            </button>
        </div>

        <div
            class="bg-white/90 backdrop-blur-xl border border-white rounded-4xl shadow-xl shadow-slate-200/50 p-4 sm:p-6 min-h-100">

            <div
                class="flex flex-row items-center justify-between w-full mb-6 pb-6 border-b border-slate-100 gap-2 sm:gap-4">

                <div id="date-filter-section"
                    class="flex flex-row items-center gap-1 sm:gap-3 flex-1 sm:flex-none min-w-0">

                    <div class="flex flex-row items-center gap-1 sm:gap-2 flex-1 sm:flex-none min-w-0">
                        <input type="date" id="filter_start"
                            class="w-full sm:w-35 min-w-0 rounded-lg sm:rounded-xl border border-slate-200 px-1 sm:px-4 py-2 text-[10px] sm:text-sm font-medium text-slate-700 focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 outline-none text-center sm:text-left tracking-tighter sm:tracking-normal">

                        <span class="text-slate-400 font-bold text-[10px] sm:text-sm shrink-0">to</span>

                        <input type="date" id="filter_end"
                            class="w-full sm:w-35 min-w-0 rounded-lg sm:rounded-xl border border-slate-200 px-1 sm:px-4 py-2 text-[10px] sm:text-sm font-medium text-slate-700 focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 outline-none text-center sm:text-left tracking-tighter sm:tracking-normal">
                    </div>

                    <button type="button" id="btn_apply_filters"
                        class="shrink-0 bg-slate-100 hover:bg-slate-200 text-slate-700 w-9 h-9 sm:w-auto sm:h-auto sm:px-4 sm:py-2 rounded-lg sm:rounded-xl text-sm font-bold transition-colors cursor-pointer flex items-center justify-center">
                        <i class="fa-solid fa-filter"></i>
                        <span class="hidden sm:inline ml-2">Filter</span>
                    </button>

                </div>

                <button type="button" id="export-btn"
                    class="shrink-0 bg-fuchsia-50 text-fuchsia-600 hover:bg-fuchsia-100 w-9 h-9 sm:w-auto sm:h-auto sm:px-4 sm:py-2 rounded-lg sm:rounded-xl text-sm font-bold transition-colors flex items-center justify-center cursor-pointer sm:ml-auto">
                    <i class="fa-solid fa-download"></i>
                    <span class="hidden sm:inline ml-2">Export Data</span>
                </button>

            </div>

            <div id="tab-sales" class="tab-content block">
                <div id="sales-report-container" class="space-y-4"></div>
            </div>

            <div id="tab-income" class="tab-content hidden">
                <div id="income-report-container"></div>
            </div>

            <div id="tab-receivables" class="tab-content hidden">
                <div id="receivables-report-container"></div>
            </div>

            <div id="tab-inventory" class="tab-content hidden">
                <div id="inventory-report-container"></div>
            </div>

            <div id="tab-purchases" class="tab-content hidden">
                <div id="purchases-report-container"></div>
            </div>

            <div id="tab-payables" class="tab-content hidden">
                <div id="payables-report-container"></div>
            </div>
        </div>
    </main>

    <?php include_once("includes/partial/footer.php"); ?>
</body>

<script src="./assets/js/jquery-4.0.0.min.js"></script>
<script>
    $(document).ready(function () {
        const $profileBtn = $('#profileTrigger');
        const $profileMenu = $('#googleMenu');

        // Header Profile Dropdown
        $profileBtn.on('click', function (e) {
            e.stopPropagation();
            $profileMenu.toggleClass('hidden');
        });

        $(document).on('click', function (e) {
            if (!$profileMenu.is(e.target) && $profileMenu.has(e.target).length === 0 && !$profileBtn.is(e.target)) {
                $profileMenu.addClass('hidden');
            }
        });

        // 1. GLOBAL HELPERS & DEFAULTS
        const formatMoney = (amount) => {
            return '₱' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        $('#filter_start').val(thirtyDaysAgo.toISOString().split('T')[0]);
        $('#filter_end').val(today.toISOString().split('T')[0]);


        // 2. FETCH FUNCTIONS
        function loadSalesReport() {
            const startDate = $('#filter_start').val();
            const endDate = $('#filter_end').val();
            const $container = $('#sales-report-container');

            $container.html('<div class="p-8 text-center text-sm font-bold text-fuchsia-400 animate-pulse"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Loading data...</div>');

            $.post('includes/fetch_sales.php', { start_date: startDate, end_date: endDate }, function (res) {
                if (res.success) {
                    $container.empty();

                    if (res.data.length === 0) {
                        $container.html('<div class="p-8 text-center text-sm font-bold text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">No transactions found for this date range.</div>');
                        return;
                    }

                    // Calculate Subtotals
                    let grandTotal = 0;
                    let totalCash = 0;
                    let totalGCash = 0;
                    let totalBank = 0;
                    let totalCredit = 0;

                    res.data.forEach(txn => {
                        let amount = parseFloat(txn.total);
                        grandTotal += amount;

                        if (txn.is_unpaid) {
                            totalCredit += amount;
                        } else if (txn.is_bank) {
                            totalBank += amount;
                        } else if (txn.is_gcash) {
                            totalGCash += amount;
                        } else {
                            totalCash += amount;
                        }
                    });

                    let totalTransactions = res.data.length;

                    // Updated Summary HTML
                    let summaryHtml = `
                    <div class="bg-white border border-slate-200 rounded-[1.25rem] p-5 sm:p-6 mb-6 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                        
                        <div class="flex items-center gap-4">
                            <div class="h-11 w-11 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500 shadow-sm shrink-0">
                                <i class="fa-solid fa-chart-line text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Total Gross Sales</h3>
                                <div class="flex items-baseline gap-2">
                                    <p class="text-2xl sm:text-3xl font-black text-slate-800 leading-none">${formatMoney(grandTotal)}</p>
                                    <span class="text-[10px] font-bold text-slate-400"> from ${totalTransactions} transactions</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-row items-center justify-between sm:justify-start gap-4 sm:gap-6 text-xs font-bold border-t lg:border-t-0 border-slate-100 pt-4 lg:pt-0 overflow-x-auto hide-scrollbar">
                            
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-money-bill-wave text-emerald-400"></i> Cash
                                </span>
                                <span class="text-sm text-slate-700">${formatMoney(totalCash)}</span>
                            </div>
                            
                            <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                            
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-mobile-screen text-blue-400"></i> GCash
                                </span>
                                <span class="text-sm text-slate-700">${formatMoney(totalGCash)}</span>
                            </div>

                            <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>

                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-building-columns text-indigo-400"></i> Bank
                                </span>
                                <span class="text-sm text-slate-700">${formatMoney(totalBank)}</span>
                            </div>
                            
                            <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                            
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-clock text-rose-400"></i> Credit
                                </span>
                                <span class="text-sm text-slate-700">${formatMoney(totalCredit)}</span>
                            </div>

                        </div>
                    </div>`;
                    $container.append(summaryHtml);

                    $container.append(`
                    <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <div class="w-1/4">Transaction</div>
                        <div class="w-1/4">Customer</div>
                        <div class="w-1/4 pl-4">Status</div>
                        <div class="w-1/4 text-right pr-12">Total Amount</div>
                    </div>`);

                    res.data.forEach(txn => {
                        let borderLeft = '';
                        let customerHtml = '';
                        let statusHtml = '';

                        // Handle Badge Logic (Added Bank)
                        let methodBadge = '';
                        if (txn.is_bank) {
                            methodBadge = `<div class="bg-violet-50 text-violet-700 px-2 py-1 text-[10px] font-bold border-l border-emerald-100">Bank</div>`;
                        } else if (txn.is_gcash) {
                            methodBadge = `<div class="bg-blue-50 text-blue-600 px-2 py-1 text-[10px] font-bold border-l border-emerald-100">GCash</div>`;
                        } else {
                            methodBadge = `<div class="bg-emerald-50/40 text-emerald-600 px-2 py-1 text-[10px] font-bold border-l border-emerald-100">Cash</div>`;
                        }

                        if (txn.is_unpaid) {
                            borderLeft = 'border-l-rose-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                                <i class="fa-solid fa-user text-slate-400"></i>
                                <span class="truncate">${txn.customer}</span>
                            </div>`;
                            statusHtml = `
                            <div class="inline-flex items-center border border-rose-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-rose-50 text-rose-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-clock"></i> Unpaid</div>
                                <div class="bg-white text-rose-400 px-2 py-1 text-[9px] font-bold border-l border-rose-100 uppercase tracking-wider">Pending</div>
                            </div>`;
                        } else if (!txn.is_unpaid && txn.settle_date) {
                            borderLeft = 'border-l-emerald-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                                <i class="fa-solid fa-user text-slate-400"></i>
                                <span class="truncate">${txn.customer}</span>
                            </div>`;
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-check"></i>Settled</div>
                                ${methodBadge}
                                <div class="bg-slate-50 text-slate-500 px-2 py-1 text-[10px] font-semibold border-l border-emerald-100">${txn.settle_date}</div>
                            </div>`;
                        } else {
                            borderLeft = 'border-l-emerald-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-600">
                                <i class="fa-solid fa-money-bill-wave text-emerald-400"></i> Cash Sale
                            </div>`;
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-check-double"></i> Paid</div>
                                ${methodBadge}
                            </div>`;
                        }

                        let itemsHtml = '';
                        txn.items.forEach(item => {
                            const wholesaleBadge = item.is_wholesale
                                ? `<span class="ml-2 px-1.5 py-0.5 bg-amber-100 text-amber-700 text-[8px] font-medium rounded-full uppercase tracking-widest shrink-0 shadow-sm border border-amber-200/50">Wholesale</span>`
                                : '';

                            itemsHtml += `
                            <li class="flex items-center justify-between py-1.5 border-b border-slate-100/50 last:border-0">
                                <div class="flex items-center gap-2 pr-2">
                                    <span class="text-fuchsia-600 font-bold shrink-0">${item.qty}x</span>
                                    <div class="flex items-center">
                                        <span class="text-slate-700 font-medium truncate">${item.name}</span>
                                        ${wholesaleBadge}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 text-right text-[10px]">
                                    <span class="text-slate-400 font-medium">@ ${formatMoney(item.price)}</span>
                                    <span class="text-slate-200 font-light mx-0.5">|</span>
                                    <span class="font-bold text-slate-700 min-w-[3.5rem]">${formatMoney(item.subtotal)}</span>
                                </div>
                            </li>`;
                        });

                        let tileHtml = `
                        <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 overflow-hidden transition-all duration-200 border-l-4 ${borderLeft}">
                            <div class="sales-row-trigger flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-3.5 cursor-pointer hover:bg-slate-50 transition-colors group gap-3 sm:gap-0">
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:block">
                                    <h4 class="text-sm font-black text-slate-800 tracking-wide">${txn.number}</h4>
                                    <span class="text-[10px] font-bold text-slate-400">${txn.created_at}</span>
                                </div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:block">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer</span>
                                    ${customerHtml}
                                </div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-start sm:pl-4">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</span>
                                    ${statusHtml}
                                </div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-end gap-4 pt-3 sm:pt-0 border-t border-slate-100 sm:border-t-0 mt-1 sm:mt-0">
                                    <span class="sm:hidden text-xs font-black text-slate-500 uppercase tracking-widest">Total</span>
                                    <span class="text-base font-black text-slate-900">${formatMoney(txn.total)}</span>
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-fuchsia-50 group-hover:text-fuchsia-600 transition-colors hidden sm:flex shrink-0">
                                        <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="sales-row-details hidden bg-slate-50/50 px-5 py-4 border-t border-slate-100">
                                <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5"><i class="fa-solid fa-list-ul"></i> Transaction Details</h5>
                                <ul class="text-[11px] flex flex-col w-full sm:w-2/3">${itemsHtml}</ul>
                            </div>
                        </div>`;

                        $container.append(tileHtml);
                    });
                } else {
                    $container.html(`<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Error: ${res.message}</div>`);
                }
            }, 'json').fail(function () {
                $container.html('<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Network or Server Error fetching Sales.</div>');
            });
        }

        function loadIncomeReport() {
            const startDate = $('#filter_start').val();
            const endDate = $('#filter_end').val();
            const $container = $('#income-report-container');

            $container.html('<div class="p-8 text-center text-sm font-bold text-fuchsia-400 animate-pulse"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Generating statement...</div>');

            $.post('includes/fetch_income.php', { start_date: startDate, end_date: endDate }, function (res) {
                if (res.success) {
                    const m = res.metrics;
                    const b = res.breakdowns;
                    const profitColor = m.net_profit >= 0 ? 'text-emerald-600' : 'text-rose-600';

                    const buildItemRows = (items) => {
                        if (!items || items.length === 0) return '<li class="text-center text-slate-400 py-4 text-[11px] font-medium border-t border-slate-100 mt-2">No items in this category.</li>';
                        return items.map((item, index) => `
                        <li class="flex justify-between items-center py-3 ${index === 0 ? 'border-t border-slate-100 mt-2 pt-4' : 'border-t border-slate-50'}">
                            <div class="flex items-center gap-3">
                                <span class="text-slate-400 font-bold text-[10px] w-5 text-right">${item.total_qty}x</span>
                                <span class="text-xs font-bold text-slate-700">${item.item_name}</span>
                            </div>
                            <span class="text-xs font-black text-slate-800">${formatMoney(item.total_revenue)}</span>
                        </li>`).join('');
                    };

                    const buildTxnRows = (txns) => {
                        if (!txns || txns.length === 0) return '<li class="text-center text-slate-400 py-4 text-[11px] font-medium border-t border-slate-100 mt-2">No settled payments in this period.</li>';

                        return txns.map((txn, index) => {
                            // Determine the Payment Method Badge
                            let methodBadge = '';
                            if (parseInt(txn.is_bank) === 1) {
                                methodBadge = `<span class="bg-indigo-50 text-indigo-600 px-1.5 py-0.5 text-[8px] font-black rounded border border-indigo-100 uppercase tracking-widest shadow-sm">Bank</span>`;
                            } else if (parseInt(txn.is_gcash) === 1) {
                                methodBadge = `<span class="bg-blue-50 text-blue-600 px-1.5 py-0.5 text-[8px] font-black rounded border border-blue-100 uppercase tracking-widest shadow-sm">GCash</span>`;
                            } else {
                                methodBadge = `<span class="bg-emerald-50 text-emerald-600 px-1.5 py-0.5 text-[8px] font-black rounded border border-emerald-100 uppercase tracking-widest shadow-sm">Cash</span>`;
                            }

                            return `
                            <li class="flex justify-between items-center py-3 ${index === 0 ? 'border-t border-slate-100 mt-2 pt-4' : 'border-t border-slate-50'}">
                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-black text-slate-800">${txn.transaction_number}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] text-slate-500 font-medium bg-slate-100 px-2 py-0.5 rounded-md">${txn.customer || 'Walk-in'}</span>
                                        ${methodBadge}
                                    </div>
                                </div>
                                <span class="text-xs font-black text-emerald-600">+ ${formatMoney(txn.total_amount)}</span>
                            </li>`;
                        }).join('');
                    };

                    let statementHtml = `
                    <div class="max-w-2xl mx-auto">
                        <div class="bg-white rounded-[2rem] p-6 sm:p-10 border border-slate-200 shadow-xl shadow-slate-200/50 relative overflow-hidden z-10">
                            <div class="absolute top-0 left-0 right-0 h-2 bg-fuchsia-500"></div>
                            <div class="text-center mb-10 mt-2">
                                <h3 class="text-xl sm:text-2xl font-black text-slate-800 uppercase tracking-widest">Income Statement</h3>
                                <p class="text-[11px] font-bold text-slate-400 tracking-widest mt-1">${startDate} &nbsp;—&nbsp; ${endDate}</p>
                            </div>
                            
                            <div class="space-y-4 text-xs sm:text-sm font-semibold text-slate-600">
                                <div class="flex justify-between items-end pb-1">
                                    <span>Total Gross Sales</span>
                                    <span class="text-slate-900 font-bold">${formatMoney(m.gross_sales)}</span>
                                </div>
                                <div class="flex justify-between items-end pb-1 text-rose-500">
                                    <span>Less: Unpaid Credit Sales</span>
                                    <span>- ${formatMoney(m.unpaid_sales)}</span>
                                </div>
                                <div class="flex justify-between items-end pb-1 text-emerald-500">
                                    <span>Plus: Past Debts Settled</span>
                                    <span>+ ${formatMoney(m.settled_past)}</span>
                                </div>
                                <div class="border-t border-slate-200 pt-4 pb-2 flex justify-between items-center text-sm sm:text-base text-slate-800">
                                    <span class="font-bold">Total Cash Collected</span>
                                    <span class="font-black">${formatMoney(m.total_cash)}</span>
                                </div>
                                <div class="flex justify-between items-end pb-1 text-slate-500 pt-2">
                                    <span>Less: Cost of Goods Sold (COGS)</span>
                                    <span>- ${formatMoney(m.cogs)}</span>
                                </div>
                                <div class="border-t-2 border-slate-800 mt-6 pt-6 flex justify-between items-center">
                                    <span class="text-sm sm:text-lg font-black text-slate-800 uppercase tracking-wide">Net Cash Profit</span>
                                    <span class="text-xl sm:text-2xl font-black ${profitColor}">${formatMoney(m.net_profit)}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center mt-12 mb-8">
                            <div class="flex-grow border-t border-slate-200"></div>
                            <span class="px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-chart-pie text-slate-300"></i> Detailed Breakdowns
                            </span>
                            <div class="flex-grow border-t border-slate-200"></div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-[1.5rem] shadow-sm overflow-hidden">
                            
                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-5 sm:px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500 transition-colors"><i class="fa-solid fa-money-bill-wave"></i></div>
                                        Items Paid via Cash
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-5 sm:px-6 pb-4 bg-white"><ul class="flex flex-col">${buildItemRows(b.cash_items)}</ul></div>
                            </div>

                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-5 sm:px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-500 transition-colors"><i class="fa-solid fa-mobile-screen"></i></div>
                                        Items Paid via GCash
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-5 sm:px-6 pb-4 bg-white"><ul class="flex flex-col">${buildItemRows(b.gcash_items)}</ul></div>
                            </div>

                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-5 sm:px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-500 transition-colors"><i class="fa-solid fa-building-columns"></i></div>
                                        Items Paid via Bank
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-5 sm:px-6 pb-4 bg-white"><ul class="flex flex-col">${buildItemRows(b.bank_items)}</ul></div>
                            </div>

                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-5 sm:px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 transition-colors"><i class="fa-solid fa-clock"></i></div>
                                        Unpaid Credit Items
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-5 sm:px-6 pb-4 bg-white"><ul class="flex flex-col">${buildItemRows(b.unpaid_items)}</ul></div>
                            </div>

                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-5 sm:px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 transition-colors"><i class="fa-solid fa-handshake"></i></div>
                                        Settled Payments (Txn)
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-5 sm:px-6 pb-4 bg-white"><ul class="flex flex-col">${buildTxnRows(b.settled_txns)}</ul></div>
                            </div>

                        </div>
                    </div>`;

                    $container.html(statementHtml);
                } else {
                    $container.html(`<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Error: ${res.message}</div>`);
                }
            }, 'json').fail(function () {
                $container.html('<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Network or Server Error fetching Income.</div>');
            });
        }

        function loadReceivablesReport() {
            const startDate = $('#filter_start').val();
            const endDate = $('#filter_end').val();
            const $container = $('#receivables-report-container');

            $container.html('<div class="p-8 text-center text-sm font-bold text-fuchsia-400 animate-pulse"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Fetching receivables...</div>');

            $.post('includes/fetch_receivables.php', { start_date: startDate, end_date: endDate }, function (res) {
                if (res.success) {
                    $container.empty();
                    const m = res.metrics;

                    let summaryHtml = `
                    <div class="bg-rose-50/50 border border-rose-100 rounded-[1.5rem] p-6 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="h-12 w-12 rounded-full bg-white border border-rose-100 flex items-center justify-center text-rose-500 shadow-sm shrink-0">
                                <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Unpaid within period</h3>
                                <p class="text-sm font-bold text-slate-700">${m.total_invoices} Outstanding Invoice${m.total_invoices !== 1 ? 's' : ''}</p>
                            </div>
                        </div>
                        <div class="text-left sm:text-right border-t border-rose-100 sm:border-0 pt-4 sm:pt-0 mt-2 sm:mt-0">
                            <h3 class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-0.5">Total Amount Owed</h3>
                            <p class="text-3xl font-black text-rose-600">${formatMoney(m.total_amount)}</p>
                        </div>
                    </div>`;
                    $container.append(summaryHtml);

                    if (res.data.length === 0) {
                        $container.append('<div class="p-8 text-center text-sm font-bold text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">No unpaid receivables found for this date range.</div>');
                        return;
                    }

                    $container.append(`
                    <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <div class="w-1/4">Transaction</div>
                        <div class="w-1/4 text-center">Customer</div>
                        <div class="w-1/4 text-center">Overdue Status</div>
                        <div class="w-1/4 text-right pr-12">Total Owed</div>
                    </div>`);

                    res.data.forEach(txn => {
                        let agingBadge = '';
                        const days = txn.days_outstanding;

                        if (days === 0) {
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">Today</span>`;
                        } else if (days >= 1 && days <= 30) {
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200">1–30 days past due</span>`;
                        } else if (days >= 31 && days <= 60) {
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-700 border border-orange-200">31–60 days past due</span>`;
                        } else if (days >= 61 && days <= 90) {
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-rose-400 text-white shadow-sm ring-2 ring-rose-200/50">61–90 days past due</span>`;
                        } else {
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-red-500 text-white shadow-sm ring-2 ring-red-300/50">91+ days past due</span>`;
                        }

                        let itemsHtml = '';
                        txn.items.forEach(item => {
                            itemsHtml += `
                            <li class="flex items-center justify-between py-1.5 border-b border-slate-100/50 last:border-0">
                                <div class="flex items-center gap-2 overflow-hidden pr-2">
                                    <span class="text-fuchsia-600 font-bold shrink-0">${item.qty}x</span>
                                    <span class="text-slate-700 font-medium truncate">${item.name}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 text-right text-[10px]">
                                    <span class="text-slate-400 font-medium">@ ${formatMoney(item.price)}</span>
                                    <span class="text-slate-200 font-light mx-0.5">|</span>
                                    <span class="font-bold text-slate-700 min-w-[3.5rem]">${formatMoney(item.subtotal)}</span>
                                </div>
                            </li>`;
                        });

                        let tileHtml = `
                        <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 overflow-hidden transition-all duration-200 border-l-4 border-l-rose-400">
                            <div class="receivables-row-trigger flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-3.5 cursor-pointer hover:bg-slate-50 transition-colors group gap-3 sm:gap-0">
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:block">
                                    <h4 class="text-sm font-black text-slate-800 tracking-wide">${txn.number}</h4>
                                    <span class="text-[10px] font-bold text-slate-400">${txn.created_at}</span>
                                </div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer</span>
                                    <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                                        <i class="fa-solid fa-user text-slate-400"></i> ${txn.customer}
                                    </div>
                                </div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</span>
                                    ${agingBadge}
                                </div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-end gap-4 pt-3 sm:pt-0 border-t border-slate-100 sm:border-t-0 mt-1 sm:mt-0">
                                    <span class="sm:hidden text-xs font-black text-slate-500 uppercase tracking-widest">Total Owed</span>
                                    <span class="text-base font-black text-rose-600">${formatMoney(txn.total)}</span>
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-rose-50 group-hover:text-rose-600 transition-colors hidden sm:flex shrink-0">
                                        <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="receivables-row-details hidden bg-slate-50/50 px-5 py-4 border-t border-slate-100">
                                <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5"><i class="fa-solid fa-list-ul"></i> Unpaid Items</h5>
                                <ul class="text-[11px] flex flex-col w-full sm:w-2/3">${itemsHtml}</ul>
                            </div>
                        </div>`;
                        $container.append(tileHtml);
                    });

                } else {
                    $container.html(`<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Error: ${res.message}</div>`);
                }
            }, 'json').fail(function () {
                $container.html('<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Network or Server Error fetching Receivables.</div>');
            });
        }

        function loadInventoryReport() {
            const $container = $('#inventory-report-container');
            $container.html('<div class="p-8 text-center animate-pulse text-fuchsia-400 font-bold"><i class="fa-solid fa-boxes-stacked mr-2"></i> Analyzing stock levels...</div>');

            $.getJSON('includes/fetch_inventory.php', function (res) {
                if (!res.success) {
                    $container.html(`<div class="p-4 bg-red-50 text-red-600 rounded-xl">${res.message}</div>`);
                    return;
                }

                $container.empty();
                const m = res.metrics;

                let summaryHtml = `
                <div class="bg-fuchsia-50 border border-fuchsia-100 rounded-[1.5rem] p-6 mb-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-full bg-white border border-fuchsia-100 flex items-center justify-center text-fuchsia-500 shadow-sm shrink-0">
                            <i class="fa-solid fa-warehouse text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xs font-black text-fuchsia-500 uppercase tracking-widest">Stock Overview</h3>
                            <p class="text-base font-bold text-slate-700">${m.total_units.toLocaleString()} Total Units</p>
                        </div>
                    </div>
                    <div class="text-left sm:text-right border-t border-fuchsia-100 sm:border-0 pt-4 sm:pt-0 mt-2 sm:mt-0 w-full sm:w-auto">
                        <h3 class="text-xs font-black text-fuchsia-500 uppercase tracking-widest mb-0.5">Inventory Valuation</h3>
                        <p class="text-4xl font-black text-fuchsia-600">${formatMoney(m.overall_value)}</p>
                    </div>
                </div>`;
                $container.append(summaryHtml);

                for (const [category, items] of Object.entries(res.data)) {
                    let categoryValue = 0;
                    let itemsHtml = '';

                    items.forEach(item => {
                        categoryValue += parseFloat(item.total_value);
                        const isLow = parseFloat(item.current_stock) <= parseFloat(item.stock_threshold);
                        const restockBadge = isLow
                            ? `<span class="ml-2 px-1.5 py-0.5 bg-rose-400 text-white text-[.5em] font-medium rounded-sm uppercase tracking-tighter shadow-sm">Low Stock</span>`
                            : '';

                        const stockColorClass = isLow ? 'text-rose-600' : 'text-slate-700';

                        itemsHtml += `
                        <div class="flex flex-col sm:flex-row sm:items-center py-4 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors px-5 sm:px-6 gap-3 sm:gap-0">
                            <div class="w-full sm:w-2/5 flex items-center justify-between sm:justify-start pr-0 sm:pr-4">
                                <div class="flex items-center">
                                    <p class="text-sm font-bold text-slate-800 truncate">${item.item_name}</p>
                                    ${restockBadge}
                                </div>
                            </div>
                            <div class="w-full sm:w-3/5 flex items-center justify-between sm:justify-end gap-2 sm:gap-0 bg-slate-50 sm:bg-transparent p-2 sm:p-0 rounded-lg">
                                <div class="w-1/3 text-left sm:text-center">
                                    <div class="sm:hidden text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Stock</div>
                                    <span class="text-sm font-black ${stockColorClass}">${item.current_stock}</span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase ml-0.5">${item.unit}</span>
                                </div>
                                <div class="w-1/3 text-center sm:flex sm:justify-center">
                                    <div class="sm:hidden text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Cost</div>
                                    <span class="bg-white sm:bg-slate-100 text-slate-600 text-xs font-bold px-2 py-0.5 sm:px-3 sm:py-1 rounded border border-slate-200 inline-block">${formatMoney(item.unit_cost)}</span>
                                </div>
                                <div class="w-1/3 text-right">
                                    <div class="sm:hidden text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Value</div>
                                    <span class="font-black text-sm text-slate-900 block">${formatMoney(item.total_value)}</span>
                                </div>
                            </div>
                        </div>`;
                    });

                    let catHtml = `
                    <div class="max-w-5xl mx-auto mb-10 overflow-hidden bg-white border border-slate-200 rounded-[1.25rem] shadow-sm">
                        <div class="bg-slate-100/80 px-5 sm:px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                            <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-folder-open text-fuchsia-500"></i> <span class="truncate max-w-[120px] sm:max-w-xs">${category}</span>
                            </h4>
                            <span class="text-[10px] sm:text-xs font-bold text-slate-500 bg-white px-2 sm:px-3 py-1 rounded-full border border-slate-200 shadow-sm">
                                Subtotal: <span class="text-fuchsia-600 font-black ml-1">${formatMoney(categoryValue)}</span>
                            </span>
                        </div>
                        <div class="hidden sm:flex px-6 py-2.5 bg-slate-50/30 border-b border-slate-100 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <div class="w-2/5">Product Name</div>
                            <div class="w-1/5 text-center">Available Stock</div>
                            <div class="w-1/5 text-center">Unit Cost</div>
                            <div class="w-1/5 text-right">Total Value</div>
                        </div>
                        <div class="divide-y divide-slate-50">
                            ${itemsHtml}
                        </div>
                    </div>`;
                    $container.append(catHtml);
                }
            });
        }

        function loadDashboardOverview() {
            const $container = $('#dashboard-overview-container');

            $container.html('<div class="p-8 text-center text-sm font-bold text-violet-400 animate-pulse"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Loading overview...</div>');

            $.getJSON('includes/fetch_dashboard_overview.php', function (res) {
                if (!res.success) {
                    $container.html(`<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Error: ${res.message}</div>`);
                    return;
                }

                const data = res.data;

                let bestSellersHtml = '';
                if (data.best_sellers && data.best_sellers.length > 0) {
                    data.best_sellers.forEach((item, index) => {
                        bestSellersHtml += `
                        <li class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                            <div class="w-6 h-6 rounded bg-emerald-100 text-emerald-600 flex items-center justify-center text-[10px] font-black shrink-0">${index + 1}</div>
                            <span class="font-bold text-slate-700 truncate flex-1 text-sm">${item.item_name}</span>
                            <span class="font-black text-emerald-600 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded text-[10px] uppercase tracking-wider shrink-0">${item.total_sold} sold</span>
                        </li>`;
                    });
                } else {
                    bestSellersHtml = '<li class="text-sm text-slate-400 font-medium italic p-2">No sales recorded yet.</li>';
                }

                let topDebtorsHtml = '';
                if (data.top_debtors && data.top_debtors.length > 0) {
                    data.top_debtors.forEach((debtor, index) => {
                        topDebtorsHtml += `
                        <li class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 transition-colors">
                            <div class="w-7 h-7 rounded-full bg-rose-50 text-rose-400 flex items-center justify-center text-xs shrink-0 border border-rose-100">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <span class="font-bold text-slate-700 truncate flex-1 text-sm">${debtor.customer || 'Unknown'}</span>
                            <span class="font-black text-rose-600 shrink-0 text-sm">${formatMoney(debtor.total_owed)}</span>
                        </li>`;
                    });
                } else {
                    topDebtorsHtml = '<li class="text-sm text-slate-400 font-medium italic p-2">No unpaid accounts!</li>';
                }

                let dashboardHtml = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white/95 backdrop-blur-xl border border-slate-100 p-6 rounded-[1.5rem] shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-5">
                            <div>
                                <h3 class="text-[10px] font-black text-slate-700 uppercase tracking-widest mb-0.5">Best Sellers</h3>
                                <p class="text-xs font-bold text-emerald-500/70">Last 30 Days</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 border border-emerald-100 shadow-sm shrink-0">
                                <i class="fa-solid fa-ranking-star"></i>
                            </div>
                        </div>
                        <ul class="space-y-1">${bestSellersHtml}</ul>
                    </div>

                    <div class="bg-white/95 backdrop-blur-xl border border-slate-100 p-6 rounded-[1.5rem] shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-[10px] font-black text-slate-700 uppercase tracking-widest mb-0.5">Today's Inflow</h3>
                                <p class="text-xs font-bold text-violet-500/70">Daily Revenue</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-violet-50 flex items-center justify-center text-violet-500 border border-violet-100 shadow-sm shrink-0">
                                <i class="fa-solid fa-bolt"></i>
                            </div>
                        </div>
                        <p class="text-4xl font-black text-violet-600 mb-5 tracking-tight">${formatMoney(data.inflow.total)}</p>
                        <div class="space-y-2">
                            <div class="bg-slate-50/80 rounded-lg p-2.5 border border-slate-100 flex justify-between items-center">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider"><i class="fa-solid fa-money-bill-wave text-slate-400 mr-1.5"></i> Cash Sales</span>
                                <span class="font-black text-slate-700 text-sm">${formatMoney(data.inflow.cash_sales)}</span>
                            </div>
                            <div class="bg-slate-50/80 rounded-lg p-2.5 border border-slate-100 flex justify-between items-center">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider"><i class="fa-solid fa-handshake text-slate-400 mr-1.5"></i> Debts Collected</span>
                                <span class="font-black text-slate-700 text-sm">${formatMoney(data.inflow.debts_collected)}</span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/95 backdrop-blur-xl border border-slate-100 p-6 rounded-[1.5rem] shadow-sm hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-5">
                            <div>
                                <h3 class="text-[10px] font-black text-slate-700 uppercase tracking-widest mb-0.5">Top Debtors</h3>
                                <p class="text-xs font-bold text-rose-500/70">Unpaid Accounts</p>
                            </div>
                            <div class="h-10 w-10 rounded-xl bg-rose-50 flex items-center justify-center text-rose-500 border border-rose-100 shadow-sm shrink-0">
                                <i class="fa-solid fa-user-clock text-lg"></i>
                            </div>
                        </div>
                        <ul class="space-y-1">${topDebtorsHtml}</ul>
                    </div>
                </div>`;

                $container.html(dashboardHtml);
            }).fail(function () {
                $container.html('<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Network or Server Error fetching Overview.</div>');
            });
        }

        function loadPurchasesReport() {
            const startDate = $('#filter_start').val();
            const endDate = $('#filter_end').val();
            const $container = $('#purchases-report-container');

            $container.html('<div class="p-8 text-center text-sm font-bold text-fuchsia-400 animate-pulse"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Loading purchases...</div>');

            $.post('includes/fetch_purchases.php', { start_date: startDate, end_date: endDate }, function (res) {
                if (res.success) {
                    $container.empty();

                    if (res.data.length === 0) {
                        $container.html('<div class="p-8 text-center text-sm font-bold text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">No purchases found for this date range.</div>');
                        return;
                    }

                    // Calculate Subtotals
                    let grandTotal = 0;
                    let totalCash = 0;
                    let totalGCash = 0;
                    let totalBank = 0;
                    let totalPayable = 0;

                    res.data.forEach(item => {
                        let amount = parseFloat(item.total_amount);
                        grandTotal += amount;

                        if (parseInt(item.is_unpaid) === 1) {
                            totalPayable += amount;
                        } else if (parseInt(item.is_bank) === 1) {
                            totalBank += amount;
                        } else if (parseInt(item.is_gcash) === 1) {
                            totalGCash += amount;
                        } else {
                            totalCash += amount;
                        }
                    });

                    // 1. Sleek Minimalist Summary Card
                    let summaryHtml = `
                    <div class="bg-white border border-slate-200 rounded-[1.25rem] p-5 sm:p-6 mb-6 shadow-sm flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                        <div class="flex items-center gap-4">
                            <div class="h-11 w-11 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 shadow-sm shrink-0">
                                <i class="fa-solid fa-cart-shopping text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Total Purchases</h3>
                                <div class="flex items-baseline gap-2">
                                    <p class="text-2xl sm:text-3xl font-black text-slate-800 leading-none">${formatMoney(grandTotal)}</p>
                                    <span class="text-[10px] font-bold text-slate-400">/ ${res.data.length} items</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-row items-center justify-between sm:justify-start gap-4 sm:gap-6 text-xs font-bold border-t lg:border-t-0 border-slate-100 pt-4 lg:pt-0 overflow-x-auto hide-scrollbar pb-2 lg:pb-0">
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><i class="fa-solid fa-money-bill-wave text-emerald-400"></i> Cash</span>
                                <span class="text-sm text-slate-700">${formatMoney(totalCash)}</span>
                            </div>
                            <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><i class="fa-solid fa-mobile-screen text-blue-400"></i> GCash</span>
                                <span class="text-sm text-slate-700">${formatMoney(totalGCash)}</span>
                            </div>
                            <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><i class="fa-solid fa-building-columns text-teal-400"></i> Bank</span>
                                <span class="text-sm text-slate-700">${formatMoney(totalBank)}</span>
                            </div>
                            <div class="w-px h-6 bg-slate-200 hidden sm:block"></div>
                            <div class="flex flex-col gap-1 shrink-0">
                                <span class="text-[9px] uppercase tracking-widest text-slate-400 flex items-center gap-1.5"><i class="fa-solid fa-file-invoice text-rose-400"></i> Payable</span>
                                <span class="text-sm text-rose-600">${formatMoney(totalPayable)}</span>
                            </div>
                        </div>
                    </div>`;
                    $container.append(summaryHtml);

                    // 2. Table Headers
                    $container.append(`
                    <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <div class="w-1/3">Item Details</div>
                        <div class="w-1/6 text-center">Qty / Unit</div>
                        <div class="w-1/6 text-center">Unit Cost</div>
                        <div class="w-1/6 text-center">Status</div>
                        <div class="w-1/6 text-right pr-4">Total Amount</div>
                    </div>`);

                    // 3. Flat Rows
                    // 3. Flat Rows with Advanced Segmented Badges
                    res.data.forEach(item => {
                        let statusHtml = '';
                        let borderLeft = 'border-l-slate-200';

                        // A. Determine Method Badge (Inner Right Side)
                        let methodBadge = '';
                        if (parseInt(item.is_bank) === 1) {
                            methodBadge = `<div class="bg-violet-50 text-violet-700 px-2 py-1 text-[10px] font-bold border-l border-emerald-100">Bank</div>`;
                        } else if (parseInt(item.is_gcash) === 1) {
                            methodBadge = `<div class="bg-blue-50 text-blue-600 px-2 py-1 text-[10px] font-bold border-l border-emerald-100">GCash</div>`;
                        } else {
                            methodBadge = `<div class="bg-emerald-50/40 text-emerald-600 px-2 py-1 text-[10px] font-bold border-l border-emerald-100">Cash</div>`;
                        }

                        // B. Build the Segmented Pill based on Status
                        if (parseInt(item.is_unpaid) === 1) {
                            borderLeft = 'border-l-rose-400';
                            let dueText = item.due_date_formatted ? `Due: ${item.due_date_formatted}` : 'PENDING';
                            statusHtml = `
                            <div class="inline-flex items-center border border-rose-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-rose-50 text-rose-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-clock"></i> Unpaid</div>
                                <div class="bg-white text-rose-400 px-2 py-1 text-[9px] font-bold border-l border-rose-100 uppercase tracking-wider">${dueText}</div>
                            </div>`;
                        } else if (item.settle_date_formatted) {
                            borderLeft = 'border-l-emerald-400';
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-check"></i> Settled</div>
                                ${methodBadge}
                                <div class="bg-slate-50 text-slate-500 px-2 py-1 text-[10px] font-semibold border-l border-emerald-100">${item.settle_date_formatted}</div>
                            </div>`;
                        } else {
                            borderLeft = 'border-l-emerald-400';
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-check-double"></i> Paid</div>
                                ${methodBadge}
                            </div>`;
                        }

                        let supplierHtml = item.supplier ? `<span class="ml-2 text-slate-400 font-medium text-[10px] truncate max-w-[100px] inline-block align-bottom">- ${item.supplier}</span>` : '';

                        let rowHtml = `
                        <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 transition-all duration-200 border-l-4 ${borderLeft}">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-3.5 gap-3 sm:gap-0">
                                
                                <div class="w-full sm:w-1/3 flex items-center justify-between sm:block">
                                    <div>
                                        <h4 class="text-sm font-black text-slate-800 tracking-wide">${item.item_name} ${supplierHtml}</h4>
                                        <span class="text-[10px] font-bold text-slate-400">${item.created_at_formatted}</span>
                                    </div>
                                </div>
                                
                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Qty</span>
                                    <span class="text-sm font-black text-slate-700">${item.qty} <span class="text-[10px] text-slate-400 uppercase">${item.unit}</span></span>
                                </div>

                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Unit Cost</span>
                                    <span class="text-xs font-bold text-slate-500">${formatMoney(item.unit_cost)}</span>
                                </div>

                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-start sm:pl-4">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</span>
                                    ${statusHtml}
                                </div>

                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-end pr-0 sm:pr-4 pt-3 sm:pt-0 border-t border-slate-100 sm:border-t-0 mt-1 sm:mt-0">
                                    <span class="sm:hidden text-xs font-black text-slate-500 uppercase tracking-widest">Total</span>
                                    <span class="text-base font-black text-slate-900">${formatMoney(item.total_amount)}</span>
                                </div>
                                
                            </div>
                        </div>`;

                        $container.append(rowHtml);
                    });
                } else {
                    $container.html(`<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Error: ${res.message}</div>`);
                }
            }, 'json').fail(function () {
                $container.html('<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Network or Server Error fetching Purchases.</div>');
            });
        }

        function loadPayablesReport() {
            const startDate = $('#filter_start').val();
            const endDate = $('#filter_end').val();
            const $container = $('#payables-report-container');

            $container.html('<div class="p-8 text-center text-sm font-bold text-fuchsia-400 animate-pulse"><i class="fa-solid fa-circle-notch fa-spin mr-2"></i> Fetching payables...</div>');

            $.post('includes/fetch_payables_report.php', { start_date: startDate, end_date: endDate }, function (res) {
                if (res.success) {
                    $container.empty();
                    const m = res.metrics;

                    // 1. Payables Summary Card
                    let summaryHtml = `
                    <div class="bg-rose-50/30 border border-rose-200/50 rounded-[1.25rem] p-5 sm:p-6 mb-6 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                        <div class="flex items-center gap-4">
                            <div class="h-11 w-11 rounded-full bg-white border border-rose-100 flex items-center justify-center text-rose-500 shadow-sm shrink-0">
                                <i class="fa-solid fa-file-invoice text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-0.5">Total Unpaid Purchases</h3>
                                <div class="flex items-baseline gap-2">
                                    <span class="text-sm font-bold text-slate-700">${m.total_items} Pending Item${m.total_items !== 1 ? 's' : ''}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-left sm:text-right border-t border-rose-100/50 sm:border-0 pt-4 sm:pt-0 mt-2 sm:mt-0">
                            <h3 class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-0.5">Total Amount Payable</h3>
                            <p class="text-3xl font-black text-rose-600 leading-none">${formatMoney(m.total_amount)}</p>
                        </div>
                    </div>`;
                    $container.append(summaryHtml);

                    if (res.data.length === 0) {
                        $container.append('<div class="p-8 text-center text-sm font-bold text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">No unpaid purchases found for this date range.</div>');
                        return;
                    }

                    // 2. Table Headers (Expanded the Status column width slightly to fit the dates)
                    $container.append(`
                    <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <div class="w-1/3">Item Details</div>
                        <div class="w-1/6 text-center">Qty / Unit</div>
                        <div class="w-1/6 text-center">Unit Cost</div>
                        <div class="w-1/4 text-center">Status & Due Date</div>
                        <div class="w-1/6 text-right pr-4">Total Payable</div>
                    </div>`);

                    // 3. Flat Rows
                    res.data.forEach(item => {
                        let supplierHtml = item.supplier ? `<span class="ml-2 text-slate-400 font-medium text-[10px] truncate max-w-[100px] inline-block align-bottom">- ${item.supplier}</span>` : '';

                        // Format the right side of the segmented pill
                        let rightSideText = item.due_date_formatted ? `Due: ${item.due_date_formatted}` : 'No Due Date';

                        // Sleek Segmented Pill matching your reference image
                        let statusHtml = `
                        <div class="inline-flex items-center border border-rose-100 rounded-md overflow-hidden shadow-sm">
                            <div class="bg-rose-50 text-rose-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5 shrink-0">
                                <i class="fa-solid fa-clock"></i> Unpaid
                            </div>
                            <div class="bg-white text-rose-400 px-2 py-1 text-[9px] font-bold border-l border-rose-100 uppercase tracking-wider whitespace-nowrap">
                                ${rightSideText}
                            </div>
                        </div>`;

                        let rowHtml = `
                        <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 transition-all duration-200 border-l-4 border-l-rose-400 hover:bg-slate-50">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-3.5 gap-3 sm:gap-0">
                                
                                <div class="w-full sm:w-1/3 flex items-center justify-between sm:block">
                                    <div>
                                        <h4 class="text-sm font-black text-slate-800 tracking-wide">${item.item_name} ${supplierHtml}</h4>
                                        <span class="text-[10px] font-bold text-slate-400">Purchased: ${item.created_at_formatted}</span>
                                    </div>
                                </div>
                                
                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Qty</span>
                                    <span class="text-sm font-black text-slate-700">${item.qty} <span class="text-[10px] text-slate-400 uppercase">${item.unit}</span></span>
                                </div>

                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Unit Cost</span>
                                    <span class="text-xs font-bold text-slate-500">${formatMoney(item.unit_cost)}</span>
                                </div>

                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-center">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-widest">Status</span>
                                    ${statusHtml}
                                </div>

                                <div class="w-full sm:w-1/6 flex items-center justify-between sm:justify-end pr-0 sm:pr-4 pt-3 sm:pt-0 border-t border-slate-100 sm:border-t-0 mt-1 sm:mt-0">
                                    <span class="sm:hidden text-xs font-black text-slate-500 uppercase tracking-widest">Total Payable</span>
                                    <span class="text-base font-black text-rose-600">${formatMoney(item.total_amount)}</span>
                                </div>
                                
                            </div>
                        </div>`;
                        $container.append(rowHtml);
                    });
                } else {
                    $container.html(`<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Error: ${res.message}</div>`);
                }
            }, 'json').fail(function () {
                $container.html('<div class="p-8 text-center text-sm font-bold text-red-500 bg-red-50 rounded-2xl border border-red-100">Network or Server Error fetching Payables.</div>');
            });
        }

        // 3. EVENT LISTENERS

        loadDashboardOverview();
        loadSalesReport();
        loadIncomeReport();
        loadReceivablesReport();
        loadInventoryReport();
        loadPurchasesReport();
        loadPayablesReport()

        $('#btn_apply_filters').on('click', function () {
            loadSalesReport();
            loadIncomeReport();
            loadReceivablesReport();
            loadInventoryReport();
            loadPurchasesReport();
            loadPayablesReport()
        });

        $('#export-btn').on('click', function () {
            const activeTabTarget = $('.tab-btn.border-fuchsia-600').data('target');
            let reportType = 'sales';
            if (activeTabTarget === '#tab-receivables') reportType = 'receivables';
            if (activeTabTarget === '#tab-inventory') reportType = 'inventory';
            if (activeTabTarget === '#tab-income') reportType = 'income';
            if (activeTabTarget === '#tab-purchases') reportType = 'purchases';
            if (activeTabTarget === '#tab-payables') reportType = 'payables';

            const startDate = $('#filter_start').val();
            const endDate = $('#filter_end').val();
            let exportUrl = `includes/export_pdf.php?report=${reportType}`;

            if (reportType !== 'inventory') {
                exportUrl += `&start_date=${startDate}&end_date=${endDate}`;
            }

            window.open(exportUrl, '_blank');
        });

        $('.tab-btn').on('click', function () {
            const target = $(this).data('target');

            const $dateFilter = $('#date-filter-section, .hide-on-inventory');

            $('.tab-btn').removeClass('border-fuchsia-600 text-fuchsia-600').addClass('border-transparent text-slate-500');
            $(this).addClass('border-fuchsia-600 text-fuchsia-600').removeClass('border-transparent text-slate-500');

            if (target === '#tab-inventory') {
                $dateFilter.fadeOut(200);
                loadInventoryReport();
            } else {
                $dateFilter.fadeIn(200);
                if (target === '#tab-sales') loadSalesReport();
                if (target === '#tab-receivables') loadReceivablesReport();
                if (target === '#tab-income') loadIncomeReport();
                if (target === '#tab-purchases') loadPurchasesReport();
                if (target === '#tab-payables') loadPayablesReport();
            }

            $('.tab-content').addClass('hidden');
            $(target).removeClass('hidden');
        });

        $('#sales-report-container').on('click', '.sales-row-trigger', function () {
            $(this).next('.sales-row-details').slideToggle(250);
            $(this).find('.toggle-icon').toggleClass('rotate-180');
        });

        $('#income-report-container').on('click', '.breakdown-trigger', function () {
            $(this).next('.breakdown-content').slideToggle(250);
            $(this).find('.toggle-icon').toggleClass('rotate-180');
        });

        $('#receivables-report-container').on('click', '.receivables-row-trigger', function () {
            $(this).next('.receivables-row-details').slideToggle(250);
            $(this).find('.toggle-icon').toggleClass('rotate-180');
        });

    });
</script>

</html>