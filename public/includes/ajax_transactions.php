<?php
session_start();
require_once 'connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    if (isset($_GET['uuid'])) {
        // --- 1. FETCH SPECIFIC RECEIPT DETAILS ---
        $stmt = $pdo->prepare("
            SELECT 
                th.transaction_number, th.customer, th.total_amount, th.is_unpaid, th.created_at,
                ti.quantity, ti.unit_price_at_sale, i.item_name
            FROM transaction_header th
            LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
            LEFT JOIN item i ON ti.item_id = i.item_id
            WHERE th.transaction_uuid = :uuid
              AND th.user_id = :user_id
        ");
        $stmt->execute([
            'uuid' => $_GET['uuid'],
            'user_id' => $userId
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$results) {
            echo json_encode(['success' => false, 'message' => 'Transaction not found']);
            exit;
        }

        // Structure the response so the header data is at the top, and items are in an array
        $receipt = [
            'transaction_number' => $results[0]['transaction_number'],
            'customer' => $results[0]['customer'] ?: 'Walk-in Customer',
            'total_amount' => $results[0]['total_amount'],
            'is_unpaid' => (bool)$results[0]['is_unpaid'],
            'date' => date('M j, Y g:i A', strtotime($results[0]['created_at'])),
            'items' => []
        ];

        foreach ($results as $row) {
            if ($row['item_name']) {
                $receipt['items'][] = [
                    'name' => $row['item_name'],
                    'qty' => $row['quantity'],
                    'price' => $row['unit_price_at_sale'],
                    'subtotal' => $row['quantity'] * $row['unit_price_at_sale']
                ];
            }
        }
        echo json_encode(['success' => true, 'data' => $receipt]);

    } else {
        // Modes:
        // - default: today's transactions (created today OR settled today), with item summaries
        // - ?mode=receivables_all : all unpaid receivables (any date), with item summaries
        $mode = $_GET['mode'] ?? '';

        if ($mode === 'receivables_all') {
            $stmt = $pdo->prepare("
                SELECT 
                    th.transaction_uuid,
                    th.transaction_number,
                    th.customer,
                    th.total_amount,
                    th.is_unpaid,
                    th.void_date,
                    th.settle_date,
                    th.created_at,
                    GROUP_CONCAT(CONCAT(ti.quantity, 'x ', i.item_name) SEPARATOR ', ') AS items_summary
                FROM transaction_header th
                LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
                LEFT JOIN item i ON ti.item_id = i.item_id
                WHERE th.user_id = :user_id
                  AND th.is_unpaid = 1
                  AND th.void_date IS NULL
                GROUP BY th.transaction_uuid, th.transaction_number, th.customer, th.total_amount, th.is_unpaid, th.void_date, th.settle_date, th.created_at
                ORDER BY th.created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    th.transaction_uuid,
                    th.transaction_number,
                    th.customer,
                    th.total_amount,
                    th.is_unpaid,
                    th.void_date,
                    th.settle_date,
                    th.created_at,
                    GROUP_CONCAT(CONCAT(ti.quantity, 'x ', i.item_name) SEPARATOR ', ') AS items_summary
                FROM transaction_header th
                LEFT JOIN transaction_item ti ON th.transaction_uuid = ti.transaction_uuid
                LEFT JOIN item i ON ti.item_id = i.item_id
                WHERE th.user_id = :user_id
                  AND (
                        DATE(th.created_at) = CURDATE()
                        OR th.settle_date = CURDATE()
                      )
                GROUP BY th.transaction_uuid, th.transaction_number, th.customer, th.total_amount, th.is_unpaid, th.void_date, th.settle_date, th.created_at
                ORDER BY th.created_at DESC
            ");
            $stmt->execute(['user_id' => $userId]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions
            ]);
        }
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>