<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/connect_db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access.');
}

$userId = (int) $_SESSION['user_id'];
$reportType = $_GET['report'] ?? 'sales';

$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$displayStartDate = date('F j, Y', strtotime($startDate));
$displayEndDate = date('F j, Y', strtotime($endDate));

$html = '
<style>
    body { font-family: "Helvetica", "Arial", sans-serif; color: #333; font-size: 12px; }
    .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #f43f5e; padding-bottom: 10px; }
    .title { font-size: 24px; font-weight: bold; margin: 0 0 5px 0; color: #1e293b; text-transform: uppercase; }
    .subtitle { font-size: 14px; color: #64748b; margin: 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th { background-color: #f8fafc; text-align: left; padding: 10px; border-bottom: 2px solid #e2e8f0; color: #475569; text-transform: uppercase; font-size: 10px; }
    td { padding: 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .font-bold { font-weight: bold; }
    .text-red { color: #dc2626; }
    .text-emerald { color: #10b981; }
    .grand-total { font-size: 16px; color: #0f172a; padding-top: 15px; border-top: 2px solid #cbd5e1; text-align: right; }
    
    /* Specific Income Statement Styles */
    .statement-table { width: 100%; font-size: 14px; margin-bottom: 30px; }
    .statement-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; }
    .statement-table .indent { padding-left: 30px; color: #64748b; font-size: 13px; }
    .statement-table .subtotal-row td { border-top: 2px solid #cbd5e1; font-weight: bold; background-color: #f8fafc; font-size: 15px; }
    .statement-table .net-row td { border-top: 3px double #1e293b; font-weight: bold; font-size: 18px; color: #0f172a; }
    .section-title { font-size: 16px; font-weight: bold; color: #334155; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; }
</style>
';


if ($reportType === 'sales') {
    // --- A. SALES REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Sales Report</h1>
                <p class="subtitle">Period: ' . $displayStartDate . ' to ' . $displayEndDate . '</p>
              </div>';

    // 1. Fetch Transactions WITH Items (Added is_bank)
    $stmt = $pdo->prepare("
        SELECT 
            th.transaction_uuid, 
            th.transaction_number, 
            th.customer, 
            th.total_amount, 
            th.is_unpaid, 
            th.is_gcash,
            th.is_bank, /* <-- ADDED is_bank HERE */
            th.settle_date, 
            th.created_at,
            ti.quantity, 
            ti.unit_price_at_sale,
            ti.is_wholesale, 
            i.item_name
        FROM transaction_header th
        LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        LEFT JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND DATE(th.created_at) >= :sd 
          AND DATE(th.created_at) <= :ed
        ORDER BY th.created_at DESC
    ");
    $stmt->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Group Data & Calculate Subtotals
    $transactions = [];
    $grandTotal = 0;
    $totalCash = 0;
    $totalGCash = 0;
    $totalBank = 0; // <-- Added Bank Tracker
    $totalCredit = 0;

    foreach ($results as $row) {
        $uuid = $row['transaction_uuid'];

        if (!isset($transactions[$uuid])) {
            $amt = (float) $row['total_amount'];
            $isUnpaid = (int) $row['is_unpaid'] === 1;
            $isGcash = (int) $row['is_gcash'] === 1;
            $isBank = (int) $row['is_bank'] === 1; // <-- Captured Bank Flag

            // Calculate Subtotals
            $grandTotal += $amt;
            if ($isUnpaid) {
                $totalCredit += $amt;
            } elseif ($isBank) {
                $totalBank += $amt;
            } elseif ($isGcash) {
                $totalGCash += $amt;
            } else {
                $totalCash += $amt;
            }

            $transactions[$uuid] = [
                'number' => $row['transaction_number'],
                'customer' => !empty($row['customer']) ? $row['customer'] : 'Walk-in Customer',
                'total' => $amt,
                'is_unpaid' => $isUnpaid,
                'is_gcash' => $isGcash,
                'is_bank' => $isBank, // <-- Saved to transaction
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'items' => []
            ];
        }

        // Map Items
        if (!empty($row['item_name'])) {
            $subtotal = (int) $row['quantity'] * (float) $row['unit_price_at_sale'];
            $transactions[$uuid]['items'][] = [
                'name' => $row['item_name'],
                'qty' => (int) $row['quantity'],
                'price' => (float) $row['unit_price_at_sale'],
                'subtotal' => $subtotal,
                'is_wholesale' => (bool) $row['is_wholesale']
            ];
        }
    }

    // 3. Render the Summary Box (Distinct & Formal Card)
    $html .= '<table style="width: 55%; margin: 0 auto 40px auto; background-color: #f8fafc; border: 1px solid #cbd5e1; border-collapse: collapse;">
                <tr>
                    <td colspan="2" style="text-align: center; padding: 15px 20px 5px 20px;">
                        <div style="font-size: 10px; font-weight: bold; letter-spacing: 1.5px; color: #64748b; text-transform: uppercase;">
                            Period Summary
                        </div>
                        <div style="border-bottom: 1px solid #e2e8f0; margin-top: 8px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">Cash on Hand</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #334155;">P ' . number_format($totalCash, 2) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">GCash</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #334155;">P ' . number_format($totalGCash, 2) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">Bank Transfer</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #334155;">P ' . number_format($totalBank, 2) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">Credit Sales (Unpaid)</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #334155;">P ' . number_format($totalCredit, 2) . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0 20px;">
                        <div style="border-bottom: 2px solid #94a3b8; margin-top: 4px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 13px; color: #0f172a;">TOTAL GROSS SALES</td>
                    <td class="text-right" style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 14px; color: #0f172a;">P ' . number_format($grandTotal, 2) . '</td>
                </tr>
              </table>';

    // 4. Render the Transactions & Items Table (Clean Corporate Style)
    $html .= '<table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f8fafc; border-top: 2px solid #1e293b; border-bottom: 2px solid #1e293b;">
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Transaction Details</th>
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Customer</th>
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Status</th>
                        <th class="text-right" style="padding: 10px; font-size: 11px; color: #1e293b;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($transactions as $txn) {
        // Formal Text Status (Added Bank)
        if ($txn['is_unpaid']) {
            $status = '<strong>Unpaid</strong>';
        } elseif ($txn['is_bank']) {
            $status = 'Paid (Bank)';
        } elseif ($txn['is_gcash']) {
            $status = 'Paid (GCash)';
        } else {
            $status = 'Paid (Cash)';
        }

        // Main Transaction Row
        $html .= '<tr>
                    <td style="border-bottom: none; padding: 12px 10px 4px 10px;">
                        <strong>' . htmlspecialchars($txn['number']) . '</strong><br>
                        <span style="font-size: 10px; color: #64748b;">' . $txn['created_at'] . '</span>
                    </td>
                    <td style="border-bottom: none; padding: 12px 10px 4px 10px;">' . htmlspecialchars($txn['customer']) . '</td>
                    <td style="border-bottom: none; padding: 12px 10px 4px 10px;">' . $status . '</td>
                    <td class="text-right font-bold" style="border-bottom: none; padding: 12px 10px 4px 10px;">P ' . number_format($txn['total'], 2) . '</td>
                  </tr>';

        // Nested Items Row (Clean inner table)
        $html .= '<tr>
                    <td colspan="4" style="padding: 4px 10px 12px 20px; border-bottom: 1px solid #e2e8f0;">
                        <table style="width: 80%; margin: 0; font-size: 10px; color: #475569; border-collapse: collapse;">';

        foreach ($txn['items'] as $item) {
            // Formal Wholesale Text Indicator
            $wholesaleText = $item['is_wholesale'] ? ' <span style="font-style: italic; color: #94a3b8;">(Wholesale)</span>' : '';

            $html .= '<tr>
                        <td style="padding: 2px 0; width: 30px;">' . $item['qty'] . 'x</td>
                        <td style="padding: 2px 0;">' . htmlspecialchars($item['name']) . $wholesaleText . '</td>
                        <td style="padding: 2px 0; text-align: right; color: #94a3b8;">@ P ' . number_format($item['price'], 2) . '</td>
                        <td style="padding: 2px 0; text-align: right; font-weight: bold;">P ' . number_format($item['subtotal'], 2) . '</td>
                      </tr>';
        }

        $html .= '      </table>
                    </td>
                  </tr>';
    }

    $html .= '  </tbody></table>';
} elseif ($reportType === 'inventory') {
    // --- B. INVENTORY REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Inventory Report</h1>
                <p class="subtitle">As of ' . date('F j, Y, g:i A') . '</p>
              </div>';

    $stmt = $pdo->prepare("
        SELECT i.*, c.category_name 
        FROM item i 
        LEFT JOIN category c ON i.category_id = c.category_id 
        WHERE i.user_id = :uid 
        ORDER BY c.category_name ASC, i.item_name ASC
    ");
    $stmt->execute(['uid' => $userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html .= '<table>
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Item Name</th>
                        <th class="text-center">In Stock</th>
                        <th class="text-right">Unit Cost</th>
                        <th class="text-right">Total Value</th>
                    </tr>
                </thead>
                <tbody>';

    $overallValue = 0;
    foreach ($items as $item) {
        $totalVal = (float) $item['current_stock'] * (float) $item['value'];
        $overallValue += $totalVal;

        $html .= '<tr>
                    <td>' . htmlspecialchars($item['category_name'] ?: 'Uncategorized') . '</td>
                    <td class="font-bold">' . htmlspecialchars($item['item_name']) . '</td>
                    <td class="text-center">' . htmlspecialchars($item['current_stock']) . ' ' . htmlspecialchars($item['unit']) . '</td>
                    <td class="text-right">P ' . number_format($item['value'], 2) . '</td>
                    <td class="text-right font-bold">P ' . number_format($totalVal, 2) . '</td>
                  </tr>';
    }

    $html .= '  </tbody></table>';
    $html .= '<div class="grand-total"><strong>Estimated Inventory Value: </strong> P ' . number_format($overallValue, 2) . '</div>';

} elseif ($reportType === 'receivables') {
    // --- C. RECEIVABLES REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Receivables Report</h1>
                <p class="subtitle">Unpaid Transactions from ' . $displayStartDate . ' to ' . $displayEndDate . '</p>
              </div>';

    $stmt = $pdo->prepare("
        SELECT 
            transaction_number,
            customer,
            total_amount,
            created_at,
            DATEDIFF(:ed_diff, DATE(created_at)) as days_outstanding
        FROM transaction_header
        WHERE user_id = :uid 
          AND is_unpaid = 1 
          AND DATE(created_at) >= :sd 
          AND DATE(created_at) <= :ed
        ORDER BY created_at ASC
    ");
    $stmt->execute([
        'uid' => $userId,
        'sd' => $startDate,
        'ed' => $endDate,
        'ed_diff' => $endDate
    ]);
    $receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html .= '<table>
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Date Issued</th>
                        <th>Customer</th>
                        <th class="text-center">Overdue Status</th>
                        <th class="text-right">Amount Owed</th>
                    </tr>
                </thead>
                <tbody>';

    $totalReceivables = 0;
    foreach ($receivables as $txn) {
        $totalReceivables += (float) $txn['total_amount'];
        $days = (int) $txn['days_outstanding'];

        $daysText = $days === 0 ? 'Today' : $days . ' Days';
        $daysClass = $days > 30 ? 'text-red font-bold' : '';

        $html .= '<tr>
                    <td class="font-bold">' . htmlspecialchars($txn['transaction_number']) . '</td>
                    <td>' . date('M d, Y', strtotime($txn['created_at'])) . '</td>
                    <td class="font-bold">' . htmlspecialchars($txn['customer'] ?: 'Unknown') . '</td>
                    <td class="text-center ' . $daysClass . '">' . $daysText . '</td>
                    <td class="text-right font-bold text-red">P ' . number_format($txn['total_amount'], 2) . '</td>
                  </tr>';
    }

    $html .= '  </tbody></table>';
    $html .= '<div class="grand-total"><strong>Total Outstanding Receivables: </strong> <span class="text-red">P ' . number_format($totalReceivables, 2) . '</span></div>';

} elseif ($reportType === 'income') {
    // --- D. INCOME REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Income Statement</h1>
                <p class="subtitle">For the period: ' . $displayStartDate . ' to ' . $displayEndDate . '</p>
              </div>';

    // 1. Calculate Gross Sales
    $stmtGross = $pdo->prepare("SELECT SUM(total_amount) FROM transaction_header WHERE user_id = :uid AND DATE(created_at) >= :sd AND DATE(created_at) <= :ed");
    $stmtGross->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $grossSales = (float) $stmtGross->fetchColumn();

    // 2. Calculate Unpaid Sales
    $stmtUnpaid = $pdo->prepare("SELECT SUM(total_amount) FROM transaction_header WHERE user_id = :uid AND is_unpaid = 1 AND DATE(created_at) >= :sd AND DATE(created_at) <= :ed");
    $stmtUnpaid->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $unpaidSales = (float) $stmtUnpaid->fetchColumn();

    // 3. Calculate Past Debts Settled 
    $stmtSettled = $pdo->prepare("SELECT SUM(total_amount) FROM transaction_header WHERE user_id = :uid AND is_unpaid = 0 AND DATE(settle_date) >= :sd1 AND DATE(settle_date) <= :ed AND DATE(created_at) < :sd2");
    $stmtSettled->execute([
        'uid' => $userId,
        'sd1' => $startDate,
        'ed' => $endDate,
        'sd2' => $startDate
    ]);
    $settledPast = (float) $stmtSettled->fetchColumn();

    // 4. Calculate COGS
    $stmtCogs = $pdo->prepare("
        SELECT SUM(ti.quantity * i.value) 
        FROM transaction_item ti 
        JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid 
        JOIN item i ON ti.item_id = i.item_id 
        WHERE th.user_id = :uid AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed
    ");
    $stmtCogs->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $cogs = (float) $stmtCogs->fetchColumn();

    $totalCash = $grossSales - $unpaidSales + $settledPast;
    $netProfit = $totalCash - $cogs;

    $profitColor = $netProfit >= 0 ? 'text-emerald' : 'text-red';

    $html .= '<table class="statement-table">
                <tr>
                    <td>Total Gross Sales</td>
                    <td class="text-right">P ' . number_format($grossSales, 2) . '</td>
                </tr>
                <tr>
                    <td class="indent text-red">Less: Unpaid Credit Sales</td>
                    <td class="text-right text-red">- P ' . number_format($unpaidSales, 2) . '</td>
                </tr>
                <tr>
                    <td class="indent text-emerald">Plus: Past Debts Settled</td>
                    <td class="text-right text-emerald">+ P ' . number_format($settledPast, 2) . '</td>
                </tr>
                <tr class="subtotal-row">
                    <td>Total Cash Collected</td>
                    <td class="text-right">P ' . number_format($totalCash, 2) . '</td>
                </tr>
                <tr>
                    <td class="indent">Less: Cost of Goods Sold (COGS)</td>
                    <td class="text-right">- P ' . number_format($cogs, 2) . '</td>
                </tr>
                <tr class="net-row">
                    <td>NET CASH PROFIT</td>
                    <td class="text-right ' . $profitColor . '">P ' . number_format($netProfit, 2) . '</td>
                </tr>
              </table>';

    // Breakdown 1: Cash Sales Items
    $stmtCashItems = $pdo->prepare("
        SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue
        FROM transaction_item ti
        JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND th.is_unpaid = 0 
          AND th.settle_date IS NULL
          AND DATE(th.created_at) >= :sd 
          AND DATE(th.created_at) <= :ed
        GROUP BY i.item_id, i.item_name
        ORDER BY total_revenue DESC
    ");
    $stmtCashItems->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $cashItems = $stmtCashItems->fetchAll(PDO::FETCH_ASSOC);

    if (count($cashItems) > 0) {
        $html .= '<div class="section-title" style="margin-top: 30px;">Breakdown: Cash Sales Items</div>
                  <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th class="text-center">Quantity Sold</th>
                            <th class="text-right">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($cashItems as $item) {
            $html .= '<tr>
                        <td class="font-bold">' . htmlspecialchars($item['item_name']) . '</td>
                        <td class="text-center">' . (int) $item['total_qty'] . '</td>
                        <td class="text-right font-bold">P ' . number_format($item['total_revenue'], 2) . '</td>
                      </tr>';
        }
        $html .= '  </tbody></table>';
    }

    // Breakdown 2: Unpaid Credit Items
    $stmtUnpaidItems = $pdo->prepare("
        SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue
        FROM transaction_item ti
        JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND th.is_unpaid = 1
          AND DATE(th.created_at) >= :sd 
          AND DATE(th.created_at) <= :ed
        GROUP BY i.item_id, i.item_name
        ORDER BY total_revenue DESC
    ");
    $stmtUnpaidItems->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $unpaidItems = $stmtUnpaidItems->fetchAll(PDO::FETCH_ASSOC);

    if (count($unpaidItems) > 0) {
        $html .= '<div class="section-title" style="margin-top: 30px;">Breakdown: Unpaid Credit Items</div>
                  <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th class="text-center">Quantity Sold</th>
                            <th class="text-right">Pending Revenue</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($unpaidItems as $item) {
            $html .= '<tr>
                        <td class="font-bold">' . htmlspecialchars($item['item_name']) . '</td>
                        <td class="text-center">' . (int) $item['total_qty'] . '</td>
                        <td class="text-right font-bold text-red">P ' . number_format($item['total_revenue'], 2) . '</td>
                      </tr>';
        }
        $html .= '  </tbody></table>';
    }

    // Breakdown 3: Past Debts Settled (Transactions)
    $stmtBreakdown = $pdo->prepare("
        SELECT transaction_number, customer, total_amount, settle_date 
        FROM transaction_header 
        WHERE user_id = :uid AND is_unpaid = 0 AND DATE(settle_date) >= :sd1 AND DATE(settle_date) <= :ed AND DATE(created_at) < :sd2 
        ORDER BY settle_date ASC
    ");
    $stmtBreakdown->execute([
        'uid' => $userId,
        'sd1' => $startDate,
        'ed' => $endDate,
        'sd2' => $startDate
    ]);
    $settledTxns = $stmtBreakdown->fetchAll(PDO::FETCH_ASSOC);

    if (count($settledTxns) > 0) {
        $html .= '<div class="section-title" style="margin-top: 30px;">Breakdown: Past Debts Settled</div>
                  <table>
                    <thead>
                        <tr>
                            <th>Transaction #</th>
                            <th>Customer</th>
                            <th>Date Settled</th>
                            <th class="text-right">Amount Collected</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($settledTxns as $txn) {
            $html .= '<tr>
                        <td class="font-bold">' . htmlspecialchars($txn['transaction_number']) . '</td>
                        <td>' . htmlspecialchars($txn['customer'] ?: 'Unknown') . '</td>
                        <td>' . date('M d, Y', strtotime($txn['settle_date'])) . '</td>
                        <td class="text-right font-bold text-emerald">+ P ' . number_format($txn['total_amount'], 2) . '</td>
                      </tr>';
        }
        $html .= '  </tbody></table>';
    }
}

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

$dompdf->setPaper('A4', 'portrait');

$dompdf->render();

$dompdf->stream("Report_" . ucfirst($reportType) . "_" . date('Ymd') . ".pdf", ["Attachment" => 0]);
?>