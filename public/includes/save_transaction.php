<?php
session_start();
require_once 'connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Helper to generate standard UUIDv4 for our database tables
function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

// Read raw JSON post data from the JavaScript fetch/ajax call
$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['cart']) || !is_array($data['cart'])) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit;
}

$customer = trim($data['customer']) ?: 'Walk-in';
$isUnpaid = !empty($data['is_unpaid']) ? 1 : 0;
$totalAmount = 0;
$transactionUuid = generate_uuid();
$transactionNumber = 'TRX-' . strtoupper(substr(uniqid(), -6)); // E.g., TRX-9A2F4B

try {
    $pdo->beginTransaction();

    // Track locked stock per item so history can store absolute new stock.
    $lockedStock = [];

    // 1. Calculate true total and verify stock first
    foreach ($data['cart'] as $item) {
        $itemId = (int)$item['id'];
        $qty = (int)$item['qty'];
        
        // Lock the row for update to prevent race conditions (double-selling)
        $stmt = $pdo->prepare("SELECT item_name, retail_price, current_stock FROM item WHERE item_id = :id FOR UPDATE");
        $stmt->execute(['id' => $itemId]);
        $dbItem = $stmt->fetch();

        if (!$dbItem) {
            throw new Exception("Item ID {$itemId} not found.");
        }
        if ($dbItem['current_stock'] < $qty) {
            throw new Exception("Not enough stock for {$dbItem['item_name']}. Only {$dbItem['current_stock']} left.");
        }

        $lockedStock[$itemId] = (int) $dbItem['current_stock'];
        $totalAmount += ($dbItem['retail_price'] * $qty);
    }

    // 2. Insert into transaction_header
    $stmtHeader = $pdo->prepare("
        INSERT INTO transaction_header (transaction_uuid, transaction_number, customer, total_amount, is_unpaid, user_id)
        VALUES (:uuid, :num, :customer, :total, :unpaid, :user_id)
    ");
    $stmtHeader->execute([
        'uuid' => $transactionUuid,
        'num' => $transactionNumber,
        'customer' => $customer,
        'total' => $totalAmount,
        'unpaid' => $isUnpaid,
        'user_id' => $userId
    ]);

    // Prepare statements for the loop
    $stmtItemInsert = $pdo->prepare("
        INSERT INTO transaction_item (item_uuid, transaction_uuid, item_id, quantity, unit_price_at_sale)
        VALUES (:iuuid, :tuuid, :item_id, :qty, :price)
    ");
    $stmtStockUpdate = $pdo->prepare("
        UPDATE item SET current_stock = current_stock - :qty WHERE item_id = :item_id
    ");
    $stmtHistoryInsert = $pdo->prepare("
        INSERT INTO item_history (history_uuid, transaction_uuid, item_id, item_count, description)
        VALUES (:huuid, :tuuid, :item_id, :qty, :desc)
    ");

    // 3. Process each item (Deduct stock, insert history, save transaction details)
    foreach ($data['cart'] as $item) {
        $itemId = (int)$item['id'];
        $qty = (int)$item['qty'];
        $price = (float)$item['price'];

        // Save transaction line item
        $stmtItemInsert->execute([
            'iuuid' => generate_uuid(),
            'tuuid' => $transactionUuid,
            'item_id' => $itemId,
            'qty' => $qty,
            'price' => $price
        ]);

        // Deduct inventory
        $stmtStockUpdate->execute([
            'qty' => $qty,
            'item_id' => $itemId
        ]);

        // Audit Trail
        $prevStock = $lockedStock[$itemId] ?? null;
        $newStock = ($prevStock !== null) ? max(0, $prevStock - $qty) : null;

        $stmtHistoryInsert->execute([
            'huuid' => generate_uuid(),
            'tuuid' => $transactionUuid,
            'item_id' => $itemId,
            // Store absolute stock after change (matches fetch_history.php expectations)
            'qty' => ($newStock !== null ? $newStock : 0),
            'desc' => "SOLD: {$qty} unit(s) via POS (Receipt: {$transactionNumber})"
        ]);

        // If the same item appears again (shouldn't), keep stock consistent.
        if ($prevStock !== null) {
            $lockedStock[$itemId] = max(0, $prevStock - $qty);
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'transaction_number' => $transactionNumber]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>