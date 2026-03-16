<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/connect_db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

// 1. Fetch Data
$sql = "SELECT 
    i.item_name, 
    i.value AS price, 
    COALESCE(c.category_name, 'Uncategorized') AS category_name, 
    COALESCE(i.current_stock, 0) AS latest_stock
FROM item i
LEFT JOIN category c ON i.category_id = c.category_id
WHERE i.user_id = :user_id AND (c.is_deleted = 0 OR c.is_deleted IS NULL)
ORDER BY c.category_name ASC, i.item_name ASC;";

$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$items = $stmt->fetchAll();

// Calculate Grand Totals for Header
$overallTotalValue = 0;
$totalItemsCount = 0;
foreach ($items as $row) {
    $overallTotalValue += ($row['price'] * $row['latest_stock']);
    $totalItemsCount += $row['latest_stock'];
}

$date = date('F d, Y');
$peso = "&#8369;";
$userName = htmlspecialchars($_SESSION['user_name']);

// 2. Build HTML Content
$html = "
<html>
<head>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #333; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #4f46e5; padding-bottom: 10px; }
        .header h1 { color: #4f46e5; margin: 0; text-transform: uppercase; letter-spacing: 2px; font-size: 20px; }
        
        /* Summary Box */
        .summary-container { margin-bottom: 30px; padding: 15px; background: #f5f7ff; border-radius: 10px; border: 1px solid #e0e7ff; }
        .summary-table { width: 100%; border: none; }
        .summary-label { color: #6b7280; text-transform: uppercase; font-size: 9px; font-weight: bold; }
        .summary-value { font-size: 16px; font-weight: bold; color: #1e1b4b; }

        .category-header { background: #4f46e5; color: white; padding: 8px 12px; border-radius: 5px; font-size: 12px; font-weight: bold; margin-top: 25px; margin-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th { color: #4b5563; text-align: left; padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; text-transform: uppercase; }
        td { padding: 8px; border-bottom: 1px solid #f3f4f6; }
        
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .cat-footer { background-color: #f9fafb; border-bottom: 2px solid #e5e7eb; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 9px; color: #9ca3af; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>Inventory Report</h1>
        <p>For: {$userName}</p>
    </div>
    <div class='summary-container'>
        <table class='summary-table'>
            <tr>
                <td width='33%'>
                    <div class='summary-label'>Generated On</div>
                    <div class='summary-value'>$date</div>
                </td>
                <td width='33%' class='text-right'>
                    <div class='summary-label'>Total Stock Count</div>
                    <div class='summary-value'>" . number_format($totalItemsCount) . "</div>
                </td>
                <td width='33%' class='text-right'>
                    <div class='summary-label'>Overall Asset Value</div>
                    <div class='summary-value' style='color: #4f46e5;'>$peso " . number_format($overallTotalValue, 2) . "</div>
                </td>
            </tr>
        </table>
    </div>";

if (empty($items)) {
    $html .= "<p style='text-align:center; color:#999; margin-top:50px;'>No inventory data available.</p>";
} else {
    $currentCategory = null;
    $categoryTotal = 0;
    $firstCategory = true;

    foreach ($items as $index => $item) {
        if ($item['category_name'] !== $currentCategory) {
            if (!$firstCategory) {
                $html .= "
                    <tr class='cat-footer'>
                        <td colspan='3' class='text-right font-bold'>Total for $currentCategory:</td>
                        <td class='text-right font-bold' style='color: #4f46e5;'>$peso " . number_format($categoryTotal, 2) . "</td>
                    </tr>
                </tbody></table>";
                $categoryTotal = 0;
            }

            $html .= "<div class='category-header'>CATEGORY: " . strtoupper($item['category_name']) . "</div>";
            $html .= "<table>
                        <thead>
                            <tr>
                                <th width='50%'>Item Name</th>
                                <th class='text-right'>Unit Price</th>
                                <th class='text-right'>Qty</th>
                                <th class='text-right'>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>";

            $currentCategory = $item['category_name'];
            $firstCategory = false;
        }

        $subtotal = $item['price'] * $item['latest_stock'];
        $categoryTotal += $subtotal;

        $html .= "
            <tr>
                <td class='font-bold'>{$item['item_name']}</td>
                <td class='text-right'>$peso " . number_format($item['price'], 2) . "</td>
                <td class='text-right'>" . number_format($item['latest_stock']) . "</td>
                <td class='text-right'>$peso " . number_format($subtotal, 2) . "</td>
            </tr>";

        if ($index === count($items) - 1) {
            $html .= "
                <tr class='cat-footer'>
                    <td colspan='3' class='text-right font-bold'>Total for $currentCategory:</td>
                    <td class='text-right font-bold' style='color: #4f46e5;'>$peso " . number_format($categoryTotal, 2) . "</td>
                </tr>
            </tbody></table>";
        }
    }
}

$html .= "
    <div class='footer'>This is a computer-generated document. • Puerto Princesa City, Palawan</div>
</body>
</html>";

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("Inventory_Report_" . date('Y-m-d') . ".pdf", ["Attachment" => false]);