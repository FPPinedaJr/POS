<?php
session_start();
require_once __DIR__ . '/connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            i.item_id,
            i.item_name,
            c.category_name,
            i.current_stock,
            i.unit,
            i.value as unit_cost, 
            (i.current_stock * i.value) as total_value,
            i.stock_threshold
        FROM item i
        INNER JOIN category c ON c.category_id = i.category_id 
        WHERE i.user_id = :uid
        ORDER BY c.category_name ASC, i.item_name ASC
    ");
    $stmt->execute(['uid' => $userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $groupedData = [];
    $overallValue = 0;
    $totalItems = 0;

    foreach ($items as $item) {
        $cat = $item['category_name'] ?: 'Uncategorized';
        $groupedData[$cat][] = $item;

        $overallValue += (float) $item['total_value'];
        // FIXED: Changed quantity_on_hand to current_stock to match SQL
        $totalItems += (float) $item['current_stock'];
    }

    echo json_encode([
        'success' => true,
        'metrics' => [
            'overall_value' => $overallValue,
            'total_units' => $totalItems,
            'category_count' => count($groupedData)
        ],
        'data' => $groupedData
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}