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

    <div class="fixed top-0 left-0 right-0 h-1 bg-linear-to-r from-indigo-500 via-fuchsia-500 to-teal-400 z-100">
    </div>

    <?php include_once("includes/partial/header.php"); ?>


    <main class="flex-1 relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-6 py-8">

        <div class="mb-8">
            <h2 class="text-3xl font-black text-slate-800 tracking-tight drop-shadow-sm">Reports Overview</h2>
            <p class="text-sm text-slate-500 font-medium mt-1">Track your business performance and inventory health.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div
                class="bg-white/80 backdrop-blur-xl border border-white p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Total Sales (30 Days)</h3>
                    <div class="h-8 w-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500"><i
                            class="fa-solid fa-arrow-trend-up"></i></div>
                </div>
                <p class="text-3xl font-black text-slate-800">₱0.00</p>
            </div>

            <div
                class="bg-white/80 backdrop-blur-xl border border-white p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Unpaid Receivables</h3>
                    <div class="h-8 w-8 rounded-full bg-rose-50 flex items-center justify-center text-rose-500"><i
                            class="fa-solid fa-hand-holding-dollar"></i></div>
                </div>
                <p class="text-3xl font-black text-slate-800">₱0.00</p>
            </div>

            <div
                class="bg-white/80 backdrop-blur-xl border border-white p-5 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Est. Inventory Value</h3>
                    <div class="h-8 w-8 rounded-full bg-fuchsia-50 flex items-center justify-center text-fuchsia-500"><i
                            class="fa-solid fa-boxes-stacked"></i></div>
                </div>
                <p class="text-3xl font-black text-slate-800">₱0.00</p>
            </div>
        </div>

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
            <!-- <button data-target="#tab-audit"
                class="tab-btn px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Audit Trail
            </button> -->
        </div>

        <div
            class="bg-white/90 backdrop-blur-xl border border-white rounded-[2rem] shadow-xl shadow-slate-200/50 p-6 min-h-[400px]">

            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-6 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <input type="date" id="filter_start"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 outline-none">
                    <span class="text-slate-400 font-bold text-sm">to</span>
                    <input type="date" id="filter_end"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 outline-none">
                    <button type="button" id="btn_apply_filters"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold transition-colors cursor-pointer">Filter</button>
                </div>

                <button type="button"
                    class="bg-fuchsia-50 text-fuchsia-600 hover:bg-fuchsia-100 px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-download"></i> Export Data
                </button>
            </div>

            <div id="tab-sales" class="tab-content block">
                <div id="sales-report-container" class="space-y-4">
                </div>
            </div>

            <div id="tab-income" class="tab-content hidden">
                <div id="income-report-container">
                </div>
            </div>

            <div id="tab-receivables" class="tab-content hidden">
                <div id="receivables-report-container">
                </div>
            </div>

            <div id="tab-inventory" class="tab-content hidden">
                <div id="inventory-report-container">
                </div>
            </div>

            <!-- <div id="tab-trail" class="tab-content hidden">
                <div id="trail-report-container">
                </div>
            </div> -->

        </div>

        </div>
    </main>

    <?php include_once("includes/partial/footer.php"); ?>
</body>

<script src="./assets/js/jquery-4.0.0.min.js"></script>
<script>
    $(document).ready(function () {

        // 1. GLOBAL HELPERS & DEFAULTS
        // Define formatMoney so all reports can use it without crashing
        const formatMoney = (amount) => {
            return '₱' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        // Set default dates to the last 30 days BEFORE we load the reports
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

                    // Add the table header for context
                    $container.append(`
                    <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <div class="w-1/4">Transaction</div>
                        <div class="w-1/4">Customer</div>
                        <div class="w-1/4">Status</div>
                        <div class="w-1/4 text-right pr-12">Total</div>
                    </div>
                `);

                    res.data.forEach(txn => {
                        let borderLeft = '';
                        let customerHtml = '';
                        let statusHtml = '';

                        // UNPAID CREDIT SALE
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
                        }
                        // PAID CREDIT SALE
                        else if (!txn.is_unpaid && txn.settle_date) {
                            borderLeft = 'border-l-emerald-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                                <i class="fa-solid fa-user text-slate-400"></i>
                                <span class="truncate">${txn.customer}</span>
                            </div>`;
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-check"></i> Paid</div>
                                <div class="bg-slate-50 text-slate-500 px-2 py-1 text-[10px] font-semibold border-l border-emerald-100">${txn.settle_date}</div>
                            </div>`;
                        }
                        // CASH SALE
                        else {
                            borderLeft = 'border-l-emerald-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-600">
                                <i class="fa-solid fa-money-bill-wave text-emerald-400"></i> Cash Sale
                            </div>`;
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5"><i class="fa-solid fa-check-double"></i> Paid</div>
                                <div class="bg-emerald-50/40 text-emerald-500 px-2 py-1 text-[10px] font-semibold border-l border-emerald-100">Cash</div>
                            </div>`;
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
                        <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 overflow-hidden transition-all duration-200 border-l-4 ${borderLeft}">
                            <div class="sales-row-trigger flex flex-col sm:flex-row sm:items-center justify-between p-3.5 cursor-pointer hover:bg-slate-50 transition-colors group">
                                <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center justify-between sm:block">
                                    <h4 class="text-sm font-black text-slate-800 tracking-wide">${txn.number}</h4>
                                    <span class="text-[10px] font-bold text-slate-400">${txn.created_at}</span>
                                </div>
                                <div class="w-full sm:w-1/4 mb-2 sm:mb-0">${customerHtml}</div>
                                <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center justify-start sm:justify-start pl-0 sm:pl-4">${statusHtml}</div>
                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-end gap-4">
                                    <span class="text-sm sm:text-base font-black text-slate-900">${formatMoney(txn.total)}</span>
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-fuchsia-50 group-hover:text-fuchsia-600 transition-colors">
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
                    const profitColor = m.net_profit >= 0 ? 'text-fuchsia-600' : 'text-rose-600';

                    // Helper to build Item lists (Sleeker design)
                    const buildItemRows = (items) => {
                        if (!items || items.length === 0) return '<li class="text-center text-slate-400 py-4 text-[11px] font-medium border-t border-slate-100 mt-2">No items in this category.</li>';
                        return items.map((item, index) => `
                        <li class="flex justify-between items-center py-3 ${index === 0 ? 'border-t border-slate-100 mt-2 pt-4' : 'border-t border-slate-50'}">
                            <div class="flex items-center gap-3">
                                <span class="text-slate-400 font-bold text-[10px] w-5 text-right">${item.total_qty}x</span>
                                <span class="text-xs font-bold text-slate-700">${item.item_name}</span>
                            </div>
                            <span class="text-xs font-black text-slate-800">${formatMoney(item.total_revenue)}</span>
                        </li>
                    `).join('');
                    };

                    // Helper to build Transaction lists (Sleeker design)
                    const buildTxnRows = (txns) => {
                        if (!txns || txns.length === 0) return '<li class="text-center text-slate-400 py-4 text-[11px] font-medium border-t border-slate-100 mt-2">No settled payments in this period.</li>';
                        return txns.map((txn, index) => `
                        <li class="flex justify-between items-center py-3 ${index === 0 ? 'border-t border-slate-100 mt-2 pt-4' : 'border-t border-slate-50'}">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-black text-slate-800">${txn.transaction_number}</span>
                                <span class="text-[10px] text-slate-500 font-medium bg-slate-100 px-2 py-0.5 rounded-md">${txn.customer || 'Walk-in'}</span>
                            </div>
                            <span class="text-xs font-black text-emerald-600">+ ${formatMoney(txn.total_amount)}</span>
                        </li>
                    `).join('');
                    };

                    let statementHtml = `
                    <div class="max-w-2xl mx-auto">
                        
                        <div class="bg-white rounded-[2rem] p-8 sm:p-10 border border-slate-200 shadow-xl shadow-slate-200/50 relative overflow-hidden z-10">
                            <div class="absolute top-0 left-0 right-0 h-2 bg-fuchsia-500"></div>
                            <div class="text-center mb-10 mt-2">
                                <h3 class="text-2xl font-black text-slate-800 uppercase tracking-widest">Income Statement</h3>
                                <p class="text-[11px] font-bold text-slate-400 tracking-widest mt-1">${startDate} &nbsp;—&nbsp; ${endDate}</p>
                            </div>
                            
                            <div class="space-y-4 text-sm font-semibold text-slate-600">
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
                                <div class="border-t border-slate-200 pt-4 pb-2 flex justify-between items-center text-base text-slate-800">
                                    <span class="font-bold">Total Cash Collected</span>
                                    <span class="font-black">${formatMoney(m.total_cash)}</span>
                                </div>
                                <div class="flex justify-between items-end pb-1 text-slate-500 pt-2">
                                    <span>Less: Cost of Goods Sold (COGS)</span>
                                    <span>- ${formatMoney(m.cogs)}</span>
                                </div>
                                <div class="border-t-2 border-slate-800 mt-6 pt-6 flex justify-between items-center">
                                    <span class="text-lg font-black text-slate-800 uppercase tracking-wide">Net Cash Profit</span>
                                    <span class="text-2xl font-black ${profitColor}">${formatMoney(m.net_profit)}</span>
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
                                <button type="button" class="breakdown-trigger w-full px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-fuchsia-50 group-hover:text-fuchsia-600 transition-colors"><i class="fa-solid fa-box"></i></div>
                                        Cash Sales Items
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-6 pb-4 bg-white"><ul class="flex flex-col">${buildItemRows(b.cash_items)}</ul></div>
                            </div>

                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-rose-50 group-hover:text-rose-500 transition-colors"><i class="fa-solid fa-clock"></i></div>
                                        Unpaid Credit Items
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-6 pb-4 bg-white"><ul class="flex flex-col">${buildItemRows(b.unpaid_items)}</ul></div>
                            </div>

                            <div class="border-b border-slate-100 last:border-0">
                                <button type="button" class="breakdown-trigger w-full px-6 py-4 flex justify-between items-center hover:bg-slate-50 transition-colors cursor-pointer group">
                                    <span class="text-xs font-black text-slate-700 uppercase flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-500 transition-colors"><i class="fa-solid fa-handshake"></i></div>
                                        Settled Payments (Txn)
                                    </span>
                                    <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-slate-300 text-xs group-hover:text-slate-500"></i>
                                </button>
                                <div class="breakdown-content hidden px-6 pb-4 bg-white"><ul class="flex flex-col">${buildTxnRows(b.settled_txns)}</ul></div>
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

                    // 1. Build the Top Summary Card
                    let summaryHtml = `
            <div class="bg-rose-50/50 border border-rose-100 rounded-[1.5rem] p-6 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="h-12 w-12 rounded-full bg-white border border-rose-100 flex items-center justify-center text-rose-500 shadow-sm">
                        <i class="fa-solid fa-file-invoice-dollar text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-[10px] font-black text-rose-400 uppercase tracking-widest">Unpaid within period</h3>
                        <p class="text-sm font-bold text-slate-700">${m.total_invoices} Outstanding Invoice${m.total_invoices !== 1 ? 's' : ''}</p>
                    </div>
                </div>
                <div class="text-right">
                    <h3 class="text-[10px] font-black text-rose-400 uppercase tracking-widest mb-0.5">Total Amount Owed</h3>
                    <p class="text-3xl font-black text-rose-600">${formatMoney(m.total_amount)}</p>
                </div>
            </div>
            `;
                    $container.append(summaryHtml);

                    // Handle empty state
                    if (res.data.length === 0) {
                        $container.append('<div class="p-8 text-center text-sm font-bold text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">No unpaid receivables found for this date range.</div>');
                        return;
                    }

                    // Table Header - CHANGED to 4 columns (w-1/4)
                    $container.append(`
            <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                <div class="w-1/4">Transaction</div>
                <div class="w-1/4 text-center">Customer</div>
                <div class="w-1/4 text-center">Overdue Status</div>
                <div class="w-1/4 text-right pr-12">Total Owed</div>
            </div>
            `);

                    // 2. Loop through Receivables and build tiles
                    res.data.forEach(txn => {
                        // Logic for Standard Aging Buckets with escalating colors
                        let agingBadge = '';
                        const days = txn.days_outstanding;

                        if (days === 0) {
                            // Current / Today: Neutral
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">Today</span>`;
                        } else if (days >= 1 && days <= 30) {
                            // 1-30 Days: Mild warning (Amber)
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200">1–30 days past due</span>`;
                        } else if (days >= 31 && days <= 60) {
                            // 31-60 Days: Moderate warning (Orange)
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-700 border border-orange-200">31–60 days past due</span>`;
                        } else if (days >= 61 && days <= 90) {
                            // 61-90 Days: Severe warning (Solid Rose)
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-rose-400 text-white shadow-sm ring-2 ring-rose-200/50">61–90 days past due</span>`;
                        } else {
                            // 91+ Days: Critical (Dark Red)
                            agingBadge = `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black bg-red-500 text-white shadow-sm ring-2 ring-red-300/50">91+ days past due</span>`;
                        }

                        // Build the itemized list (same sleek layout as sales)
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

                        // Build the main Row Tile - CHANGED to split Customer and Status into two w-1/4 blocks
                        let tileHtml = `
                <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 overflow-hidden transition-all duration-200 border-l-4 border-l-rose-400">
                    <div class="receivables-row-trigger flex flex-col sm:flex-row sm:items-center justify-between p-3.5 cursor-pointer hover:bg-slate-50 transition-colors group">
                        
                        <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center justify-between sm:block">
                            <h4 class="text-sm font-black text-slate-800 tracking-wide">${txn.number}</h4>
                            <span class="text-[10px] font-bold text-slate-400">${txn.created_at}</span>
                        </div>

                        <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center sm:justify-center gap-1.5">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-700">
                                <i class="fa-solid fa-user text-slate-400"></i> ${txn.customer}
                            </div>
                        </div>

                        <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center sm:justify-center">
                            ${agingBadge}
                        </div>

                        <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-end gap-4">
                            <span class="text-sm sm:text-base font-black text-rose-600">${formatMoney(txn.total)}</span>
                            <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-rose-50 group-hover:text-rose-600 transition-colors shrink-0">
                                <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-xs"></i>
                            </div>
                        </div>
                    </div>

                    <div class="receivables-row-details hidden bg-slate-50/50 px-5 py-4 border-t border-slate-100">
                        <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-list-ul"></i> Unpaid Items
                        </h5>
                        <ul class="text-[11px] flex flex-col w-full sm:w-2/3">
                            ${itemsHtml}
                        </ul>
                    </div>
                </div>
                `;
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

                // 1. Summary Card
                let summaryHtml = `
        <div class="bg-fuchsia-50 border border-fuchsia-100 rounded-[1.5rem] p-6 mb-8 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-white border border-fuchsia-100 flex items-center justify-center text-fuchsia-500 shadow-sm">
                    <i class="fa-solid fa-warehouse text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xs font-black text-fuchsia-500 uppercase tracking-widest">Stock Overview</h3>
                    <p class="text-base font-bold text-slate-700">${m.total_units.toLocaleString()} Total Units</p>
                </div>
            </div>
            <div class="text-right">
                <h3 class="text-xs font-black text-fuchsia-500 uppercase tracking-widest mb-0.5">Inventory Valuation</h3>
                <p class="text-4xl font-black text-fuchsia-600">${formatMoney(m.overall_value)}</p>
            </div>
        </div>
        `;
                $container.append(summaryHtml);

                // 2. Loop through Categories
                for (const [category, items] of Object.entries(res.data)) {
                    let categoryValue = 0;
                    let itemsHtml = '';

                    items.forEach(item => {
                        categoryValue += parseFloat(item.total_value);

                        // Logic for Restock Warning
                        const isLow = parseFloat(item.current_stock) <= parseFloat(item.stock_threshold);
                        const restockBadge = isLow
                            ? `<span class="ml-2 px-1.5 py-0.5 bg-rose-400 text-white text-[.5em] font-medium rounded-sm uppercase tracking-tighter shadow-sm">Low Stock</span>`
                            : '';

                        // Color the number red if low
                        const stockColorClass = 'text-slate-700';

                        itemsHtml += `
                <div class="flex items-center py-4 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors px-6">
                    <div class="w-2/5 pr-4 flex items-center">
                        <p class="text-sm font-bold text-slate-800 truncate">${item.item_name}</p>
                        ${restockBadge}
                    </div>
                    
                    <div class="w-1/5 text-center">
                        <span class="text-sm font-black ${stockColorClass}">${item.current_stock}</span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase ml-0.5">${item.unit}</span>
                    </div>

                    <div class="w-1/5 flex justify-center">
                        <span class="bg-slate-100 text-slate-600 text-xs font-bold px-3 py-1 rounded border border-slate-200">
                           ${formatMoney(item.unit_cost)}
                        </span>
                    </div>

                    <div class="w-1/5 text-right font-black text-base text-slate-900">
                        ${formatMoney(item.total_value)}
                    </div>
                </div>`;
                    });

                    // Build Category Section
                    let catHtml = `
            <div class="max-w-5xl mx-auto mb-10 overflow-hidden bg-white border border-slate-200 rounded-[1.25rem] shadow-sm">
                <div class="bg-slate-100/80 px-6 py-3 border-b border-slate-200 flex justify-between items-center">
                    <h4 class="text-xs font-black text-slate-600 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-fuchsia-500"></i> ${category}
                    </h4>
                    <span class="text-xs font-bold text-slate-500 bg-white px-3 py-1 rounded-full border border-slate-200 shadow-sm">
                       Subtotal: <span class="text-fuchsia-600 font-black ml-1">${formatMoney(categoryValue)}</span>
                    </span>
                </div>

                <div class="flex px-6 py-2.5 bg-slate-50/30 border-b border-slate-100 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    <div class="w-2/5">Product Name</div>
                    <div class="w-1/5 text-center">Available Stock</div>
                    <div class="w-1/5 text-center">Unit Cost</div>
                    <div class="w-1/5 text-right">Total Value</div>
                </div>

                <div class="divide-y divide-slate-50">
                    ${itemsHtml}
                </div>
            </div>
            `;
                    $container.append(catHtml);
                }
            });
        }


        // 3. EVENT LISTENERS

        // Initial fetch on page load
        loadSalesReport();
        loadIncomeReport();
        loadReceivablesReport();
        loadInventoryReport();

        // Global Filter Click
        $('#btn_apply_filters').on('click', function () {
            loadSalesReport();
            loadIncomeReport();
            loadReceivablesReport();
            loadInventoryReport();

        });

        // Accordion toggles
        $('#sales-report-container').on('click', '.sales-row-trigger', function () {
            $(this).next('.sales-row-details').slideToggle(250);
            $(this).find('.toggle-icon').toggleClass('rotate-180');
        });

        $('#income-report-container').on('click', '.breakdown-trigger', function () {
            $(this).next('.breakdown-content').slideToggle(250);
            $(this).find('.toggle-icon').toggleClass('rotate-180');
        });

        // Tab Switching Logic
        $('.tab-btn').on('click', function () {
            $('.tab-btn').removeClass('border-fuchsia-600 text-fuchsia-600').addClass('border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300');
            $(this).removeClass('border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300').addClass('border-fuchsia-600 text-fuchsia-600');
            $('.tab-content').addClass('hidden').removeClass('block');
            $($(this).data('target')).removeClass('hidden').addClass('block');
        });

        $('#receivables-report-container').on('click', '.receivables-row-trigger', function () {
            $(this).next('.receivables-row-details').slideToggle(250);
            $(this).find('.toggle-icon').toggleClass('rotate-180');
        });

    });
</script>

</html>