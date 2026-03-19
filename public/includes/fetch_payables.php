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
    $stmt = $pdo->prepare("
        SELECT
            ip.purchase_id,
            ip.supplier,
            ip.item_id,
            i.item_name,
            ip.qty,
            ip.value,
            ip.total_amount,
            ip.is_unpaid,
            ip.is_gcash,
            ip.is_bank,
            ip.due_date,
            ip.settle_date,
            ip.created_at,
            CASE
                WHEN ip.due_date IS NULL THEN NULL
                ELSE DATEDIFF(:end_date_diff, ip.due_date)
            END AS days_from_due
        FROM item_purchase ip
        LEFT JOIN item i ON ip.item_id = i.item_id
        WHERE ip.user_id = :uid
          AND DATE(ip.created_at) >= :sd
          AND DATE(ip.created_at) <= :ed
        ORDER BY ip.created_at DESC
    ");

    $stmt->execute([
        'uid' => $userId,
        'sd' => $startDate,
        'ed' => $endDate,
        'end_date_diff' => $endDate
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $totalPayables = 0;
    $totalBills = count($rows);
    $data = [];

    foreach ($rows as $r) {
        if ((int) ($r['is_unpaid'] ?? 0) === 1) {
            $totalPayables += (float) ($r['total_amount'] ?? 0);
        }
        $data[] = [
            'purchase_id' => (int) $r['purchase_id'],
            'supplier' => empty($r['supplier']) ? 'Unknown' : $r['supplier'],
            'item_name' => empty($r['item_name']) ? 'Item #' . ((int) ($r['item_id'] ?? 0)) : $r['item_name'],
            'qty' => (int) ($r['qty'] ?? 0),
            'value' => (float) ($r['value'] ?? 0),
            'total' => (float) ($r['total_amount'] ?? 0),
            'is_unpaid' => (int) ($r['is_unpaid'] ?? 0),
            'is_gcash' => (int) ($r['is_gcash'] ?? 0),
            'is_bank' => (int) ($r['is_bank'] ?? 0),
            'due_date' => $r['due_date'],
            'settle_date' => $r['settle_date'],
            'created_at' => $r['created_at'] ? date('M d, Y', strtotime($r['created_at'])) : null,
            'days_from_due' => $r['days_from_due'] === null ? null : (int) $r['days_from_due'],
        ];
    }

    echo json_encode([
        'success' => true,
        'metrics' => [
            'total_amount' => $totalPayables,
            'total_bills' => $totalBills
        ],
        'data' => $data
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

