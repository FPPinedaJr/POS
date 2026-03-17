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
    // 1. Fetch Unpaid Transactions with Aging Calculation
    $stmt = $pdo->prepare("
        SELECT 
            th.transaction_uuid,
            th.transaction_number,
            th.customer,
            th.total_amount,
            th.created_at,
            -- FIX: Use the filter's end date instead of CURRENT_DATE()
            DATEDIFF(:end_date_diff, DATE(th.created_at)) as days_outstanding
        FROM transaction_header th
        WHERE th.user_id = :uid 
          AND th.is_unpaid = 1 
          AND DATE(th.created_at) >= :sd 
          AND DATE(th.created_at) <= :ed
        ORDER BY th.created_at DESC
    ");

    // Add 'end_date_diff' to your execute array
    $stmt->execute([
        'uid' => $userId,
        'sd' => $startDate,
        'ed' => $endDate,
        'end_date_diff' => $endDate // Passes the filter's end date to DATEDIFF
    ]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalReceivables = 0;
    $totalInvoices = count($transactions);
    $data = [];

    if ($totalInvoices > 0) {
        // 2. Fetch all items for these specific unpaid transactions
        $stmtItems = $pdo->prepare("
            SELECT 
                ti.transaction_uuid,
                i.item_name,
                ti.quantity,
                ti.unit_price_at_sale,
                (ti.quantity * ti.unit_price_at_sale) as subtotal
            FROM transaction_item ti
            JOIN item i ON ti.item_id = i.item_id
            JOIN transaction_header th ON ti.transaction_uuid = th.transaction_uuid
            WHERE th.user_id = :uid 
              AND th.is_unpaid = 1 
              AND DATE(th.created_at) >= :sd 
              AND DATE(th.created_at) <= :ed
        ");
        $stmtItems->execute(['uid' => $userId, 'sd' => $startDate, 'ed' => $endDate]);
        $allItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        // Group items by transaction_uuid
        $itemsGrouped = [];
        foreach ($allItems as $item) {
            $itemsGrouped[$item['transaction_uuid']][] = [
                'name' => $item['item_name'],
                'qty' => $item['quantity'],
                'price' => $item['unit_price_at_sale'],
                'subtotal' => $item['subtotal']
            ];
        }

        // Assemble the final array
        foreach ($transactions as $txn) {
            $totalReceivables += (float) $txn['total_amount'];
            $data[] = [
                'uuid' => $txn['transaction_uuid'],
                'number' => $txn['transaction_number'],
                'customer' => empty($txn['customer']) ? 'Unknown' : $txn['customer'],
                'total' => (float) $txn['total_amount'],
                'created_at' => date('M d, Y', strtotime($txn['created_at'])),
                'days_outstanding' => (int) $txn['days_outstanding'],
                'items' => $itemsGrouped[$txn['transaction_uuid']] ?? []
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'metrics' => [
            'total_amount' => $totalReceivables,
            'total_invoices' => $totalInvoices
        ],
        'data' => $data
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>