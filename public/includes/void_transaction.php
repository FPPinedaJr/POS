<?php
session_start();
require_once 'connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function generate_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000, mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}

$data = json_decode(file_get_contents('php://input'), true);
$uuid = $data['uuid'] ?? '';

if (!$uuid) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID missing.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Mark header as voided (ensure it's not already voided)
    $stmtVoid = $pdo->prepare("
        UPDATE transaction_header 
        SET void_date = CURDATE() 
        WHERE transaction_uuid = :uuid AND void_date IS NULL
    ");
    $stmtVoid->execute(['uuid' => $uuid]);

    if ($stmtVoid->rowCount() === 0) {
        throw new Exception("Transaction already voided or not found.");
    }

    // Get the transaction number for the history description
    $stmtGetNum = $pdo->prepare("SELECT transaction_number FROM transaction_header WHERE transaction_uuid = :uuid");
    $stmtGetNum->execute(['uuid' => $uuid]);
    $trxNumber = $stmtGetNum->fetchColumn() ?: 'Unknown';

    // 2. Fetch all items from this transaction
    $stmtGetItems = $pdo->prepare("SELECT item_id, quantity FROM transaction_item WHERE transaction_uuid = :uuid");
    $stmtGetItems->execute(['uuid' => $uuid]);
    $items = $stmtGetItems->fetchAll();

    // Prepare statements for the loop
    $stmtRestoreStock = $pdo->prepare("UPDATE item SET current_stock = current_stock + :qty WHERE item_id = :id");
    $stmtHistoryLog = $pdo->prepare("
        INSERT INTO item_history (history_uuid, transaction_uuid, item_id, item_count, description)
        VALUES (:huuid, :tuuid, :item_id, :qty, :desc)
    ");

    // 3. Loop items to restore stock and log history
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        
        // Add voided qty back to current_stock
        $stmtRestoreStock->execute(['qty' => $qty, 'id' => $item['item_id']]);

        // Insert positive count in history
        $stmtHistoryLog->execute([
            'huuid' => generate_uuid(),
            'tuuid' => $uuid,
            'item_id' => $item['item_id'],
            'qty' => $qty, // Positive to show it came back
            'desc' => "Voided sale (Receipt: {$trxNumber}). Restored {$qty} unit(s)."
        ]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Transaction successfully voided.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>