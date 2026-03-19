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
            ip.purchase_id,
            ip.qty,
            ip.value as unit_cost,
            ip.total_amount,
            ip.is_unpaid,
            ip.is_gcash,
            ip.is_bank,
            ip.created_at,
            ip.supplier,
            i.item_name,
            i.unit
        FROM item_purchase ip
        LEFT JOIN item i ON ip.item_id = i.item_id
        WHERE ip.user_id = :user_id 
          AND DATE(ip.created_at) >= :start_date 
          AND DATE(ip.created_at) <= :end_date
        ORDER BY ip.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $userId,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format data for JSON
    $purchases = array_map(function($row) {
        $row['total_amount'] = (float)$row['total_amount'];
        $row['unit_cost'] = (float)$row['unit_cost'];
        $row['created_at_formatted'] = date('M d, Y', strtotime($row['created_at']));
        return $row;
    }, $results);

    echo json_encode(['success' => true, 'data' => $purchases]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>