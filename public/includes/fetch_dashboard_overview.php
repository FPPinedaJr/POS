<?php
session_start();
require_once __DIR__ . '/connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$data = [];

try {
    // 1. BEST SELLERS (Last 30 Days)
    $stmtSellers = $pdo->prepare("
        SELECT 
            i.item_name, 
            SUM(ti.quantity) as total_sold
        FROM transaction_item ti
        JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid
        JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :uid 
          AND DATE(th.created_at) >= DATE_SUB(CURRENT_DATE(), INTERVAL 30 DAY)
        GROUP BY i.item_id, i.item_name
        ORDER BY total_sold DESC
        LIMIT 3
    ");
    $stmtSellers->execute(['uid' => $userId]);
    $data['best_sellers'] = $stmtSellers->fetchAll(PDO::FETCH_ASSOC);

    // 2. TODAY'S INFLOW
    $stmtInflow = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN is_unpaid = 0 AND settle_date IS NULL AND DATE(created_at) = CURRENT_DATE() THEN total_amount ELSE 0 END) as cash_sales,
            SUM(CASE WHEN is_unpaid = 0 AND settle_date IS NOT NULL AND DATE(settle_date) = CURRENT_DATE() THEN total_amount ELSE 0 END) as debts_collected
        FROM transaction_header
        WHERE user_id = :uid
    ");
    $stmtInflow->execute(['uid' => $userId]);
    $inflow = $stmtInflow->fetch(PDO::FETCH_ASSOC);

    $data['inflow'] = [
        'cash_sales' => (float) ($inflow['cash_sales'] ?? 0),
        'debts_collected' => (float) ($inflow['debts_collected'] ?? 0),
        'total' => (float) ($inflow['cash_sales'] ?? 0) + (float) ($inflow['debts_collected'] ?? 0)
    ];

    // 3. TOP UNPAID ACCOUNTS
    $stmtDebtors = $pdo->prepare("
        SELECT 
            customer, 
            SUM(total_amount) as total_owed
        FROM transaction_header
        WHERE user_id = :uid AND is_unpaid = 1
        GROUP BY customer
        ORDER BY total_owed DESC
        LIMIT 3
    ");
    $stmtDebtors->execute(['uid' => $userId]);
    $data['top_debtors'] = $stmtDebtors->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>