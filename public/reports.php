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

    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="h-10 w-10 rounded-xl bg-fuchsia-600/90 flex items-center justify-center text-white shadow-md shadow-fuchsia-200">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-1">
                    <h1 class="text-xl font-black text-fuchsia-700 tracking-tight">Venda</h1>
                    <span class="text-lg font-medium text-slate-600 tracking-wide">Reports</span>
                </div>
            </div>

            <a href="dashboard.php" class="text-sm font-bold text-slate-500 hover:text-fuchsia-600 transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard
            </a>
        </div>
    </header>

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
            <button
                class="px-5 py-3 text-sm font-bold border-b-2 border-fuchsia-600 text-fuchsia-600 whitespace-nowrap">
                <i class="fa-solid fa-chart-line mr-1.5"></i> Sales Report
            </button>
            <button
                class="px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-money-bill-wave mr-1.5"></i> Income Report
            </button>
            <button
                class="px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-file-invoice-dollar mr-1.5"></i> Receivables
            </button>
            <button
                class="px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-clipboard-list mr-1.5"></i> Inventory
            </button>
            <button
                class="px-5 py-3 text-sm font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all whitespace-nowrap">
                <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Audit Trail
            </button>
        </div>

        <div
            class="bg-white/90 backdrop-blur-xl border border-white rounded-[2rem] shadow-xl shadow-slate-200/50 p-6 min-h-[400px]">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <input type="date" id="filter_start"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 outline-none">
                    <span class="text-slate-400 font-bold text-sm">to</span>
                    <input type="date" id="filter_end"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-fuchsia-500/20 focus:border-fuchsia-500 outline-none">
                    <button type="button" id="btn_filter_sales"
                        class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-xl text-sm font-bold transition-colors cursor-pointer">Filter</button>
                </div>

                <button type="button"
                    class="bg-fuchsia-50 text-fuchsia-600 hover:bg-fuchsia-100 px-4 py-2 rounded-xl text-sm font-bold transition-colors flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-download"></i> Export Data
                </button>
            </div>

            <div id="sales-report-container" class="space-y-4">
                <div
                    class="p-8 text-center text-sm font-bold text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">
                    Loading sales data...
                </div>
            </div>

        </div>
    </main>
</body>
<?php include_once("includes/partial/footer.php"); ?>

<script src="./assets/js/jquery-4.0.0.min.js"></script>
<script>
    $(document).ready(function () {

        // Set default dates (Last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);

        $('#filter_start').val(thirtyDaysAgo.toISOString().split('T')[0]);
        $('#filter_end').val(today.toISOString().split('T')[0]);

        // Format currency helper
        const formatMoney = (amount) => {
            return '₱' + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        // Main fetch function
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

                    // Optional: Add a subtle table header inside the container for context
                    $container.append(`
                    <div class="hidden sm:flex items-center px-4 pb-2 mb-2 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-100">
                        <div class="w-1/4">Transaction</div>
                        <div class="w-1/4">Customer</div>
                        <div class="w-1/4">Status</div>
                        <div class="w-1/4 text-right pr-12">Total</div>
                    </div>
                `);

                    // Loop through transactions and build tile-rows
                    res.data.forEach(txn => {
                        let borderLeft = '';
                        let customerHtml = '';
                        let statusHtml = '';

                        // LOGIC 1: UNPAID CREDIT SALE
                        if (txn.is_unpaid) {
                            borderLeft = 'border-l-rose-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                                <i class="fa-solid fa-user text-slate-400"></i>
                                <span class="truncate">${txn.customer}</span>
                            </div>`;

                            // Unified Split-Badge (Rose)
                            statusHtml = `
                            <div class="inline-flex items-center border border-rose-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-rose-50 text-rose-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-clock"></i> Unpaid
                                </div>
                                <div class="bg-white text-rose-400 px-2 py-1 text-[9px] font-bold border-l border-rose-100 uppercase tracking-wider">
                                    Pending
                                </div>
                            </div>`;
                        }
                        // LOGIC 2: PAID CREDIT SALE
                        else if (!txn.is_unpaid && txn.settle_date) {
                            borderLeft = 'border-l-emerald-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-slate-600">
                                <i class="fa-solid fa-user text-slate-400"></i>
                                <span class="truncate">${txn.customer}</span>
                            </div>`;

                            // Unified Split-Badge (Emerald + Slate Date)
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-check"></i> Settled
                                </div>
                                <div class="bg-slate-50 text-slate-500 px-2 py-1 text-[10px] font-semibold border-l border-emerald-100">
                                    ${txn.settle_date}
                                </div>
                            </div>`;
                        }
                        // LOGIC 3: CASH SALE
                        else {
                            borderLeft = 'border-l-emerald-400';
                            customerHtml = `
                            <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-600">
                                <i class="fa-solid fa-money-bill-wave text-emerald-400"></i>
                                Cash Sale
                            </div>`;

                            // Unified Split-Badge (Solid Emerald)
                            statusHtml = `
                            <div class="inline-flex items-center border border-emerald-100 rounded-md overflow-hidden shadow-sm">
                                <div class="bg-emerald-50 text-emerald-600 px-2 py-1 text-[10px] font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-check-double"></i> Paid
                                </div>
                                <div class="bg-emerald-50/40 text-emerald-500 px-2 py-1 text-[10px] font-semibold border-l border-emerald-100">
                                    Cash
                                </div>
                            </div>`;
                        }

                        // Build the single-line item rows
                        let itemsHtml = '';
                        txn.items.forEach(item => {
                            itemsHtml += `
                            <li class="flex items-center justify-between py-1.5 border-b border-slate-100 last:border-0">
                                <div class="flex items-center gap-2 overflow-hidden pr-2">
                                    <span class="text-fuchsia-600 font-bold shrink-0">${item.qty}x</span>
                                    <span class="text-slate-700 font-medium truncate">${item.name}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0 text-right text-[10px]">
                                    <span class="text-slate-400 font-medium">@ ${formatMoney(item.price)}</span>
                                    <span class="text-slate-200 font-light mx-0.5">|</span>
                                    <span class="font-bold text-slate-700 min-w-[3.5rem]">${formatMoney(item.subtotal)}</span>
                                </div>
                            </li>
                        `;
                        });

                        // Build the Tile-Row HTML
                        let tileHtml = `
                        <div class="bg-white border border-slate-200 rounded-[1rem] shadow-sm mb-3 overflow-hidden transition-all duration-200 border-l-4 ${borderLeft}">
                            
                            <div class="sales-row-trigger flex flex-col sm:flex-row sm:items-center justify-between p-3.5 cursor-pointer hover:bg-slate-50 transition-colors group">
                                
                                <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center justify-between sm:block">
                                    <h4 class="text-sm font-black text-slate-800 tracking-wide">${txn.number}</h4>
                                    <span class="text-[10px] font-bold text-slate-400">${txn.created_at}</span>
                                </div>

                                <div class="w-full sm:w-1/4 mb-2 sm:mb-0">
                                    ${customerHtml}
                                </div>

                                <div class="w-full sm:w-1/4 mb-2 sm:mb-0 flex items-center justify-start sm:justify-start pl-0 sm:pl-4">
                                    ${statusHtml}
                                </div>

                                <div class="w-full sm:w-1/4 flex items-center justify-between sm:justify-end gap-4">
                                    <span class="text-sm sm:text-base font-black text-slate-900">${formatMoney(txn.total)}</span>
                                    <div class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-fuchsia-50 group-hover:text-fuchsia-600 transition-colors">
                                        <i class="fa-solid fa-chevron-down transition-transform duration-200 toggle-icon text-xs"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="sales-row-details hidden bg-slate-50/50 px-5 py-4 border-t border-slate-100">
                                <h5 class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2.5 flex items-center gap-1.5">
                                    <i class="fa-solid fa-list-ul"></i> Transaction Details
                                </h5>
                                <ul class="text-[11px] flex flex-col w-full sm:w-1/4">
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
            }, 'json');
        }

        // --- EVENT LISTENERS ---

        // Initial fetch and filter click
        loadSalesReport();
        $('#btn_filter_sales').on('click', loadSalesReport);

        // Accordion Slide Toggle for the Tile-Rows
        $('#sales-report-container').on('click', '.sales-row-trigger', function () {
            const $details = $(this).next('.sales-row-details');
            const $icon = $(this).find('.toggle-icon');

            // Slide the details open/closed smoothly
            $details.slideToggle(250);
            // Flip the chevron icon upside down
            $icon.toggleClass('rotate-180');
        });
    });
</script>

</html>