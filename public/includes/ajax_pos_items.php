<?php
session_start();
require_once 'connect_db.php';
require_once 'DashboardItemsQuery.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// For POS: ignore external search/filter params.
// Always fetch a full, alphabetically sorted, in-stock list for this user.
$input = [
    'sort' => 'name_asc',
    'hide_out_of_stock' => true,
];

try {
    // Fetch a large chunk of items for the POS grid for this user
    // (client-side will handle any searching/filtering).
    $pageData = inv_fetch_dashboard_items($pdo, $userId, $input, 500);
    
    echo json_encode([
        'success' => true,
        'items' => $pageData['items']
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>