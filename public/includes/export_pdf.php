<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/connect_db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Security check
if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access.');
}

$userId = (int) $_SESSION['user_id'];
$reportType = $_GET['report'] ?? 'sales';

// Date handling for the filters
$startDate = !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$displayStartDate = date('F j, Y', strtotime($startDate));
$displayEndDate = date('F j, Y', strtotime($endDate));

// 1. Initialize HTML and CSS styling for the PDF
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
    .grand-total { font-size: 16px; color: #0f172a; padding-top: 15px; border-top: 2px solid #cbd5e1; text-align: right; }
</style>
';

// 2. ROUTING LOGIC: Build the content based on which tab was clicked

if ($reportType === 'sales') {
    // --- A. SALES REPORT ---
    $html .= '<div class="header">
                <h1 class="title">Sales Report</h1>
                <p class="subtitle">Period: ' . $displayStartDate . ' to ' . $displayEndDate . '</p>
              </div>';

    $stmt = $pdo->prepare("SELECT * FROM transaction_header WHERE user_id = :uid AND DATE(created_at) >= :sd AND DATE(created_at) <= :ed ORDER BY created_at DESC");
    $stmt->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $html .= '<table>
                <thead>
                    <tr>
                        <th>Transaction #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th class="text-right">Total Amount</th>
                    </tr>
                </thead>
                <tbody>';

    $grandTotal = 0;
    foreach ($transactions as $txn) {
        $grandTotal += (float) $txn['total_amount'];
        $status = $txn['is_unpaid'] ? 'Unpaid' : 'Paid';

        $html .= '<tr>
                    <td class="font-bold">' . htmlspecialchars($txn['transaction_number']) . '</td>
                    <td>' . date('M d, Y', strtotime($txn['created_at'])) . '</td>
                    <td>' . htmlspecialchars($txn['customer'] ?: 'Cash Sale') . '</td>
                    <td>' . $status . '</td>
                    <td class="text-right font-bold">P ' . number_format($txn['total_amount'], 2) . '</td>
                  </tr>';
    }

    $html .= '  </tbody></table>';
    $html .= '<div class="grand-total"><strong>Grand Total Sales: </strong> P ' . number_format($grandTotal, 2) . '</div>';

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
    // --- C. RECEIVABLES REPORT (The missing piece!) ---
    $html .= '<div class="header">
                <h1 class="title">Receivables Report</h1>
                <p class="subtitle">Unpaid Transactions from ' . $displayStartDate . ' to ' . $displayEndDate . '</p>
              </div>';

    // Query for unpaid transactions, calculating days outstanding against the filter's end date
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
        'ed_diff' => $endDate // Calculate age relative to the selected end date
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

        // Make the overdue text red if it's past 30 days
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
}

// 3. GENERATE THE PDF USING DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Set paper size (A4, portrait)
$dompdf->setPaper('A4', 'portrait');

// Render the HTML into PDF format
$dompdf->render();

// Output the generated PDF to the Browser
// Change "Attachment" => 1 if you want it to automatically download instead of opening in a new tab
$dompdf->stream("Report_" . ucfirst($reportType) . "_" . date('Ymd') . ".pdf", ["Attachment" => 0]);
?>