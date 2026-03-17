<?php
session_start();
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Default to the last 30 days if no dates are provided
$startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_POST['end_date'] ?? date('Y-m-d');

try {
    // We join the transaction_header, transaction_item, and item tables
    // We filter by DATE(created_at) because the Sales report is based on when it left the store!
    $sql = "
        SELECT 
            th.transaction_uuid, 
            th.transaction_number, 
            th.customer, 
            th.total_amount, 
            th.is_unpaid, 
            th.settle_date, 
            th.created_at,
            ti.quantity, 
            ti.unit_price_at_sale,
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

    // Group the flat SQL results into structured transactions with an 'items' array
    $transactions = [];
    foreach ($results as $row) {
        $uuid = $row['transaction_uuid'];
        
        if (!isset($transactions[$uuid])) {
            $transactions[$uuid] = [
                'uuid' => $uuid,
                'number' => $row['transaction_number'],
                'customer' => !empty($row['customer']) ? $row['customer'] : 'Walk-in Customer',
                'total' => (float)$row['total_amount'],
                'is_unpaid' => (int)$row['is_unpaid'] === 1,
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'settle_date' => $row['settle_date'] ? date('M d, Y', strtotime($row['settle_date'])) : null,
                'items' => []
            ];
        }

        // Add the item to the receipt list if it exists
        if (!empty($row['item_name'])) {
            $subtotal = (int)$row['quantity'] * (float)$row['unit_price_at_sale'];
            $transactions[$uuid]['items'][] = [
                'name' => $row['item_name'],
                'qty' => (int)$row['quantity'],
                'price' => (float)$row['unit_price_at_sale'],
                'subtotal' => $subtotal
            ];
        }
    }

    // Convert associative array to indexed array for easier JS mapping
    echo json_encode([
        'success' => true, 
        'data' => array_values($transactions)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>