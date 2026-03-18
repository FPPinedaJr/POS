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
    $sql = "
        SELECT 
            th.transaction_uuid, 
            th.transaction_number, 
            th.customer, 
            th.total_amount, 
            th.is_unpaid, 
            th.is_gcash, /* <-- ADDED is_gcash HERE */
            th.settle_date, 
            th.created_at,
            ti.quantity, 
            ti.unit_price_at_sale,
            ti.is_wholesale, 
            i.item_name
        FROM transaction_header th
        LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
        LEFT JOIN item i ON ti.item_id = i.item_id
        WHERE th.user_id = :user_id 
          AND DATE(th.created_at) >= :start_date 
          AND DATE(th.created_at) <= :end_date
        ORDER BY th.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $transactions = [];
    foreach ($results as $row) {
        $uuid = $row['transaction_uuid'];

        if (!isset($transactions[$uuid])) {
            $transactions[$uuid] = [
                'uuid' => $uuid,
                'number' => $row['transaction_number'],
                'customer' => !empty($row['customer']) ? $row['customer'] : 'Walk-in Customer',
                'total' => (float) $row['total_amount'],
                'is_unpaid' => (int) $row['is_unpaid'] === 1,
                'is_gcash' => (int) $row['is_gcash'] === 1, /* <-- ADDED is_gcash HERE */
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'settle_date' => $row['settle_date'] ? date('M d, Y', strtotime($row['settle_date'])) : null,
                'items' => []
            ];
        }

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

    echo json_encode([
        'success' => true,
        'data' => array_values($transactions)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>