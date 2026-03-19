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
    $stmtSettled->execute(['uid' => $userId, 'sd1' => $startDate, 'ed' => $endDate, 'sd2' => $startDate]);
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

    // Distinct & Formal Summary Card
    $html .= '<table style="width: 60%; margin: 0 auto 40px auto; background-color: #f8fafc; border: 1px solid #cbd5e1; border-collapse: collapse;">
                <tr>
                    <td colspan="2" style="text-align: center; padding: 15px 20px 5px 20px;">
                        <div style="font-size: 10px; font-weight: bold; letter-spacing: 1.5px; color: #64748b; text-transform: uppercase;">
                            Income Summary
                        </div>
                        <div style="border-bottom: 1px solid #e2e8f0; margin-top: 8px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">Total Gross Sales</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #334155;">P ' . number_format($grossSales, 2) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #ef4444;">Less: Unpaid Credit Sales</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #ef4444;">- P ' . number_format($unpaidSales, 2) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #10b981;">Plus: Past Debts Settled</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #10b981;">+ P ' . number_format($settledPast, 2) . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0 20px;"><div style="border-bottom: 1px solid #cbd5e1; margin-top: 4px;"></div></td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #0f172a; font-weight: bold;">Total Cash Collected</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #0f172a;">P ' . number_format($totalCash, 2) . '</td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">Less: Cost of Goods Sold (COGS)</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #475569;">- P ' . number_format($cogs, 2) . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0 20px;"><div style="border-bottom: 2px solid #94a3b8; margin-top: 4px;"></div></td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 13px; color: #0f172a;">NET CASH PROFIT</td>
                    <td class="text-right" style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 14px; color: #0f172a;">P ' . number_format($netProfit, 2) . '</td>
                </tr>
              </table>';

    // ---------------------------------------------------------
    // HELPER FUNCTION FOR FORMAL ITEM TABLES
    // ---------------------------------------------------------
    $renderItemTable = function ($title, $items, $isPending = false) {
        if (count($items) === 0)
            return '';
        $revenueLabel = $isPending ? 'Pending Revenue' : 'Total Revenue';
        $revenueColor = $isPending ? 'color: #ef4444;' : 'color: #0f172a;';

        $html = '<div style="margin-top: 30px; font-size: 12px; font-weight: bold; color: #1e293b; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; margin-bottom: 10px;">' . $title . '</div>';
        $html .= '<table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background-color: #f8fafc; border-top: 2px solid #1e293b; border-bottom: 2px solid #1e293b;">
                            <th style="padding: 8px; text-align: left; color: #1e293b;">Item Name</th>
                            <th style="padding: 8px; text-align: center; color: #1e293b;">Quantity Sold</th>
                            <th style="padding: 8px; text-align: right; color: #1e293b;">' . $revenueLabel . '</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($items as $item) {
            $html .= '<tr>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($item['item_name']) . '</td>
                        <td style="padding: 6px 8px; text-align: center; border-bottom: 1px solid #e2e8f0;">' . (int) $item['total_qty'] . '</td>
                        <td style="padding: 6px 8px; text-align: right; font-weight: bold; border-bottom: 1px solid #e2e8f0; ' . $revenueColor . '">P ' . number_format($item['total_revenue'], 2) . '</td>
                      </tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    };

    // Breakdown 1A: Cash Items
    $stmt1A = $pdo->prepare("SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue FROM transaction_item ti JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid JOIN item i ON ti.item_id = i.item_id WHERE th.user_id = :uid AND th.is_unpaid = 0 AND th.is_gcash = 0 AND th.is_bank = 0 AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC");
    $stmt1A->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $html .= $renderItemTable('Breakdown: Items Paid via Cash', $stmt1A->fetchAll(PDO::FETCH_ASSOC));

    // Breakdown 1B: GCash Items
    $stmt1B = $pdo->prepare("SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue FROM transaction_item ti JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid JOIN item i ON ti.item_id = i.item_id WHERE th.user_id = :uid AND th.is_unpaid = 0 AND th.is_gcash = 1 AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC");
    $stmt1B->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $html .= $renderItemTable('Breakdown: Items Paid via GCash', $stmt1B->fetchAll(PDO::FETCH_ASSOC));

    // Breakdown 1C: Bank Items
    $stmt1C = $pdo->prepare("SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue FROM transaction_item ti JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid JOIN item i ON ti.item_id = i.item_id WHERE th.user_id = :uid AND th.is_unpaid = 0 AND th.is_bank = 1 AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC");
    $stmt1C->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $html .= $renderItemTable('Breakdown: Items Paid via Bank', $stmt1C->fetchAll(PDO::FETCH_ASSOC));

    // Breakdown 2: Unpaid Credit Items
    $stmtUnpaid = $pdo->prepare("SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue FROM transaction_item ti JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid JOIN item i ON ti.item_id = i.item_id WHERE th.user_id = :uid AND th.is_unpaid = 1 AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC");
    $stmtUnpaid->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $html .= $renderItemTable('Breakdown: Unpaid Credit Items', $stmtUnpaid->fetchAll(PDO::FETCH_ASSOC), true);

    // Breakdown 3: Past Debts Settled (Transactions with Payment Method)
    $stmtSettledTxns = $pdo->prepare("SELECT transaction_number, customer, total_amount, settle_date, is_gcash, is_bank FROM transaction_header WHERE user_id = :uid AND is_unpaid = 0 AND DATE(settle_date) >= :sd1 AND DATE(settle_date) <= :ed AND DATE(created_at) < :sd2 ORDER BY settle_date ASC");
    $stmtSettledTxns->execute(['uid' => $userId, 'sd1' => $startDate, 'ed' => $endDate, 'sd2' => $startDate]);
    $settledTxns = $stmtSettledTxns->fetchAll(PDO::FETCH_ASSOC);

    if (count($settledTxns) > 0) {
        $html .= '<div style="margin-top: 30px; font-size: 12px; font-weight: bold; color: #1e293b; border-bottom: 1px solid #cbd5e1; padding-bottom: 5px; margin-bottom: 10px;">Breakdown: Past Debts Settled</div>
                  <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                    <thead>
                        <tr style="background-color: #f8fafc; border-top: 2px solid #1e293b; border-bottom: 2px solid #1e293b;">
                            <th style="padding: 8px; text-align: left; color: #1e293b;">Transaction #</th>
                            <th style="padding: 8px; text-align: left; color: #1e293b;">Customer</th>
                            <th style="padding: 8px; text-align: left; color: #1e293b;">Method</th>
                            <th style="padding: 8px; text-align: left; color: #1e293b;">Date Settled</th>
                            <th style="padding: 8px; text-align: right; color: #1e293b;">Amount Collected</th>
                        </tr>
                    </thead>
                    <tbody>';
        foreach ($settledTxns as $txn) {
            $method = 'Cash';
            if ($txn['is_bank'])
                $method = 'Bank';
            elseif ($txn['is_gcash'])
                $method = 'GCash';

            $html .= '<tr>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-weight: bold;">' . htmlspecialchars($txn['transaction_number']) . '</td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . htmlspecialchars($txn['customer'] ?: 'Unknown') . '</td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; font-style: italic; color: #64748b;">' . $method . '</td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0;">' . date('M d, Y', strtotime($txn['settle_date'])) . '</td>
                        <td style="padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: bold; color: #0f172a;">+ P ' . number_format($txn['total_amount'], 2) . '</td>
                      </tr>';
        }
        $html .= '  </tbody></table>';
    }
} elseif ($reportType === 'purchases') {
    // --- E. ITEM PURCHASES REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Item Purchases Report</h1>
                <p class="subtitle">Period: ' . $displayStartDate . ' to ' . $displayEndDate . '</p>
              </div>';

    // ADDED due_date and settle_date to the query
    $stmt = $pdo->prepare("
        SELECT 
            ip.purchase_id, ip.qty, ip.value as unit_cost, ip.total_amount,
            ip.is_unpaid, ip.is_gcash, ip.is_bank, ip.created_at, ip.supplier,
            ip.due_date, ip.settle_date,
            i.item_name, i.unit
        FROM item_purchase ip
        LEFT JOIN item i ON ip.item_id = i.item_id
        WHERE ip.user_id = :uid 
          AND DATE(ip.created_at) >= :sd 
          AND DATE(ip.created_at) <= :ed
        ORDER BY ip.created_at DESC
    ");
    $stmt->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $grandTotal = 0;
    $totalCash = 0;
    $totalGCash = 0;
    $totalBank = 0;
    $totalPayable = 0;

    foreach ($results as $row) {
        $amt = (float) $row['total_amount'];
        $grandTotal += $amt;

        if ($row['is_unpaid'])
            $totalPayable += $amt;
        elseif ($row['is_bank'])
            $totalBank += $amt;
        elseif ($row['is_gcash'])
            $totalGCash += $amt;
        else
            $totalCash += $amt;
    }

    // Formal Summary Box
    $html .= '<table style="width: 55%; margin: 0 auto 40px auto; background-color: #f8fafc; border: 1px solid #cbd5e1; border-collapse: collapse;">
                <tr>
                    <td colspan="2" style="text-align: center; padding: 15px 20px 5px 20px;">
                        <div style="font-size: 10px; font-weight: bold; letter-spacing: 1.5px; color: #64748b; text-transform: uppercase;">
                            Purchases Summary
                        </div>
                        <div style="border-bottom: 1px solid #e2e8f0; margin-top: 8px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 20px; color: #475569;">Cash</td>
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
                    <td style="padding: 8px 20px; color: #475569;">Payables (Unpaid)</td>
                    <td class="text-right" style="padding: 8px 20px; font-weight: bold; color: #334155;">P ' . number_format($totalPayable, 2) . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0 20px;"><div style="border-bottom: 2px solid #94a3b8; margin-top: 4px;"></div></td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 13px; color: #0f172a;">TOTAL PURCHASES</td>
                    <td class="text-right" style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 14px; color: #0f172a;">P ' . number_format($grandTotal, 2) . '</td>
                </tr>
              </table>';

    // Formal Data Table
    $html .= '<table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f8fafc; border-top: 2px solid #1e293b; border-bottom: 2px solid #1e293b;">
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Item Details</th>
                        <th style="padding: 10px; text-align: center; font-size: 11px; color: #1e293b;">Qty / Unit</th>
                        <th style="padding: 10px; text-align: center; font-size: 11px; color: #1e293b;">Unit Cost</th>
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Status</th>
                        <th class="text-right" style="padding: 10px; font-size: 11px; color: #1e293b;">Total Amount</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($results as $item) {
        // Determine Payment Method Text
        $methodText = 'Cash';
        if ($item['is_bank'])
            $methodText = 'Bank';
        elseif ($item['is_gcash'])
            $methodText = 'GCash';

        // Advanced Status Logic (Matches the Web UI)
        if ($item['is_unpaid']) {
            $dueText = !empty($item['due_date']) ? '<span style="color: #ef4444;"> (Due: ' . date('M d', strtotime($item['due_date'])) . ')</span>' : '';
            $status = '<strong style="color: #ef4444;">Unpaid</strong>' . $dueText;
        } elseif (!empty($item['settle_date'])) {
            $status = '<strong style="color: #10b981;">Settled</strong> <span style="color: #64748b; font-style: italic;">(' . $methodText . ')</span><br><span style="font-size: 9px; color: #94a3b8;">' . date('M d, Y', strtotime($item['settle_date'])) . '</span>';
        } else {
            $status = '<strong>Paid</strong> <span style="color: #64748b; font-style: italic;">(' . $methodText . ')</span>';
        }

        // Subtle italic supplier name
        $supplierText = $item['supplier'] ? '<br><span style="font-size: 9px; color: #64748b; font-style: italic;">Supplier: ' . htmlspecialchars($item['supplier']) . '</span>' : '';

        $html .= '<tr>
                    <td style="padding: 12px 10px; border-bottom: 1px solid #e2e8f0;">
                        <strong>' . htmlspecialchars($item['item_name']) . '</strong>
                        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">' . date('M d, Y', strtotime($item['created_at'])) . '</div>
                        ' . $supplierText . '
                    </td>
                    <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #334155;">' . $item['qty'] . ' <span style="font-weight: normal; color: #64748b; font-size: 10px; text-transform: uppercase;">' . htmlspecialchars($item['unit']) . '</span></td>
                    <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; color: #475569;">P ' . number_format($item['unit_cost'], 2) . '</td>
                    <td style="padding: 12px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #0f172a;">' . $status . '</td>
                    <td class="text-right" style="padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">P ' . number_format($item['total_amount'], 2) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table>';

} elseif ($reportType === 'payables') {
    // --- F. PAYABLES REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Payables Report</h1>
                <p class="subtitle">As of: ' . $displayEndDate . '</p>
              </div>';

    // The due_date is already in the query
    $stmt = $pdo->prepare("
        SELECT 
            ip.purchase_id, ip.qty, ip.value as unit_cost, ip.total_amount,
            ip.created_at, ip.due_date, ip.supplier, i.item_name, i.unit, 
            DATEDIFF(:ed_diff, DATE(ip.created_at)) as days_outstanding
        FROM item_purchase ip
        LEFT JOIN item i ON ip.item_id = i.item_id
        WHERE ip.user_id = :uid 
          AND ip.is_unpaid = 1
          AND DATE(ip.created_at) >= :sd 
          AND DATE(ip.created_at) <= :ed
        ORDER BY ip.created_at ASC
    ");
    $stmt->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'ed_diff' => $endDate]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalPayable = array_sum(array_column($results, 'total_amount'));
    $totalItems = count($results);

    // Formal Summary Box
    $html .= '<table style="width: 55%; margin: 0 auto 40px auto; background-color: #f8fafc; border: 1px solid #cbd5e1; border-collapse: collapse;">
                <tr>
                    <td colspan="2" style="text-align: center; padding: 15px 20px 5px 20px;">
                        <div style="font-size: 10px; font-weight: bold; letter-spacing: 1.5px; color: #64748b; text-transform: uppercase;">
                            Payables Summary
                        </div>
                        <div style="border-bottom: 1px solid #e2e8f0; margin-top: 8px;"></div>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px 8px 20px; color: #475569;">Total Pending Items</td>
                    <td class="text-right" style="padding: 12px 20px 8px 20px; font-weight: bold; color: #334155;">' . $totalItems . '</td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 0 20px;"><div style="border-bottom: 2px solid #94a3b8; margin-top: 4px;"></div></td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 13px; color: #0f172a;">TOTAL AMOUNT PAYABLE</td>
                    <td class="text-right" style="padding: 12px 20px 20px 20px; font-weight: 900; font-size: 14px; color: #0f172a;">P ' . number_format($totalPayable, 2) . '</td>
                </tr>
              </table>';

    // Formal Data Table
    $html .= '<table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f8fafc; border-top: 2px solid #1e293b; border-bottom: 2px solid #1e293b;">
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Item Details</th>
                        <th style="padding: 10px; text-align: center; font-size: 11px; color: #1e293b;">Qty / Unit</th>
                        <th style="padding: 10px; text-align: center; font-size: 11px; color: #1e293b;">Unit Cost</th>
                        <th style="padding: 10px; text-align: left; font-size: 11px; color: #1e293b;">Status</th>
                        <th class="text-right" style="padding: 10px; font-size: 11px; color: #1e293b;">Total Payable</th>
                    </tr>
                </thead>
                <tbody>';

    foreach ($results as $item) {
        $days = (int) $item['days_outstanding'];
        
        // Format the Due Date string
        $dueText = !empty($item['due_date']) ? ' (Due: ' . date('M d, Y', strtotime($item['due_date'])) . ')' : '';

        // Apply Red Overdue warning if age is greater than 0
        if ($days > 0) {
            $status = '<strong style="color: #ef4444;">Unpaid</strong><br><span style="font-size: 9px; color: #ef4444; font-weight: bold;">' . $dueText . ' - Overdue</span>';
        } else {
            $status = '<strong style="color: #ef4444;">Unpaid</strong><br><span style="font-size: 9px; color: #64748b;">' . $dueText . '</span>';
        }

        $supplierText = $item['supplier'] ? '<br><span style="font-size: 9px; color: #64748b; font-style: italic;">Supplier: ' . htmlspecialchars($item['supplier']) . '</span>' : '';

        $html .= '<tr>
                    <td style="padding: 12px 10px; border-bottom: 1px solid #e2e8f0;">
                        <strong>' . htmlspecialchars($item['item_name']) . '</strong>
                        <div style="font-size: 10px; color: #64748b; margin-top: 2px;">' . date('M d, Y', strtotime($item['created_at'])) . '</div>
                        ' . $supplierText . '
                    </td>
                    <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #334155;">' . $item['qty'] . ' <span style="font-weight: normal; color: #64748b; font-size: 10px; text-transform: uppercase;">' . htmlspecialchars($item['unit']) . '</span></td>
                    <td style="padding: 12px 10px; text-align: center; border-bottom: 1px solid #e2e8f0; color: #475569;">P ' . number_format($item['unit_cost'], 2) . '</td>
                    <td style="padding: 12px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; color: #0f172a;">' . $status . '</td>
                    <td class="text-right" style="padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; color: #0f172a;">P ' . number_format($item['total_amount'], 2) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table>';
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