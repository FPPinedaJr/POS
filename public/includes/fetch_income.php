<?php
session_start();
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_POST['end_date'] ?? date('Y-m-d');

try {
    // 1. Get Gross Sales & Unpaid Sales (Within the period)
    $stmtA = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as gross_sales,
            COALESCE(SUM(
                CASE 
                    -- It's unpaid if it has no settle date, OR if it was settled AFTER our filter end date
                    WHEN is_unpaid = 1 OR (is_unpaid = 0 AND DATE(settle_date) > :ed_check) 
                    THEN total_amount 
                    ELSE 0 
                END
            ), 0) as unpaid_sales
        FROM transaction_header
        WHERE user_id = :uid AND DATE(created_at) >= :sd AND DATE(created_at) <= :ed
    ");
    $stmtA->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'ed_check' => $endDate]);
    $salesData = $stmtA->fetch(PDO::FETCH_ASSOC);

    // 2. Get Settled Past Debts (Created before start date, but paid WITHIN the period)
    $stmtB = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as settled_past
        FROM transaction_header
        WHERE user_id = :uid 
          AND is_unpaid = 0 
          AND DATE(settle_date) >= :sd 
          AND DATE(settle_date) <= :ed
          AND DATE(created_at) < :sd2
    ");
    $stmtB->execute([
        'uid' => $userId,
        'sd' => $startDate,
        'ed' => $endDate,
        'sd2' => $startDate
    ]);
    $settledData = $stmtB->fetch(PDO::FETCH_ASSOC);

    // 3. Get Cost of Goods Sold (COGS) 
    $stmtC = $pdo->prepare("
        SELECT COALESCE(SUM(ti.quantity * i.value), 0) as cogs
        FROM transaction_header th
        JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed
    ");
    $stmtC->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
    $cogsData = $stmtC->fetch(PDO::FETCH_ASSOC);

    // 4. Breakdowns (Filtering out items that were settled outside the date range)

    // 4A. Cash Items (Paid instantly in cash)
    $stmtD1 = $pdo->prepare("
        SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue
        FROM transaction_header th
        JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed 
          AND th.is_unpaid = 0 AND th.is_gcash = 0 AND th.is_bank = 0
          AND DATE(th.settle_date) <= :ed_check -- Must be settled within the period
        GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC
    ");
    $stmtD1->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'ed_check' => $endDate]);
    $cashItems = $stmtD1->fetchAll(PDO::FETCH_ASSOC);

    // 4B. GCash Items (Paid instantly in GCash)
    $stmtD2 = $pdo->prepare("
        SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue
        FROM transaction_header th
        JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed 
          AND th.is_unpaid = 0 AND th.is_gcash = 1
          AND DATE(th.settle_date) <= :ed_check -- Must be settled within the period
        GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC
    ");
    $stmtD2->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'ed_check' => $endDate]);
    $gcashItems = $stmtD2->fetchAll(PDO::FETCH_ASSOC);

    // 4C. Bank Items (Paid instantly via Bank)
    $stmtD3 = $pdo->prepare("
        SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue
        FROM transaction_header th
        JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed 
          AND th.is_unpaid = 0 AND th.is_bank = 1
          AND DATE(th.settle_date) <= :ed_check -- Must be settled within the period
        GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC
    ");
    $stmtD3->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'ed_check' => $endDate]);
    $bankItems = $stmtD3->fetchAll(PDO::FETCH_ASSOC);

    // 5. Breakdown: Unpaid Credit Items (Truly unpaid, or paid AFTER the filter period)
    $stmtD4 = $pdo->prepare("
        SELECT i.item_name, SUM(ti.quantity) as total_qty, SUM(ti.quantity * ti.unit_price_at_sale) as total_revenue
        FROM transaction_header th
        JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND DATE(th.created_at) >= :sd AND DATE(th.created_at) <= :ed 
          AND (th.is_unpaid = 1 OR (th.is_unpaid = 0 AND DATE(th.settle_date) > :ed_check))
        GROUP BY i.item_id, i.item_name ORDER BY total_revenue DESC
    ");
    $stmtD4->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'ed_check' => $endDate]);
    $unpaidItems = $stmtD4->fetchAll(PDO::FETCH_ASSOC);

    // 6. Breakdown: Settled Transactions (Created before, Paid during)
    $stmtD5 = $pdo->prepare("
        SELECT 
            transaction_number, 
            customer, 
            total_amount, 
            settle_date,
            is_gcash, 
            is_bank    
        FROM transaction_header
        WHERE user_id = :uid 
          AND is_unpaid = 0 
          AND DATE(settle_date) >= :sd AND DATE(settle_date) <= :ed
          AND DATE(created_at) < :sd2
        ORDER BY settle_date DESC
    ");
    $stmtD5->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate, 'sd2' => $startDate]);
    $settledTxns = $stmtD5->fetchAll(PDO::FETCH_ASSOC);

    $grossSales = (float) $salesData['gross_sales'];
    $unpaidSales = (float) $salesData['unpaid_sales'];
    $settledPast = (float) $settledData['settled_past'];
    $cogs = (float) $cogsData['cogs'];

    $totalCashCollected = $grossSales - $unpaidSales + $settledPast;
    $netCashProfit = $totalCashCollected - $cogs;

    echo json_encode([
        'success' => true,
        'metrics' => [
            'gross_sales' => $grossSales,
            'unpaid_sales' => $unpaidSales,
            'settled_past' => $settledPast,
            'total_cash' => $totalCashCollected,
            'cogs' => $cogs,
            'net_profit' => $netCashProfit
        ],
        'breakdowns' => [
            'cash_items' => $cashItems,
            'gcash_items' => $gcashItems,
            'bank_items' => $bankItems,
            'unpaid_items' => $unpaidItems,
            'settled_txns' => $settledTxns
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>