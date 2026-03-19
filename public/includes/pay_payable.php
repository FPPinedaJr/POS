<?php
session_start();
require_once 'connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$purchaseId = isset($data['purchase_id']) ? (int) $data['purchase_id'] : 0;
$isGcash = !empty($data['is_gcash']) ? 1 : 0;
$isBank = !empty($data['is_bank']) ? 1 : 0;

// Enforce mutual exclusivity
if ($isBank === 1) {
    $isGcash = 0;
} elseif ($isGcash === 1) {
    $isBank = 0;
}

if ($purchaseId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Purchase ID missing.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE item_purchase
        SET is_unpaid = 0,
            is_gcash = :is_gcash,
            is_bank = :is_bank,
            settle_date = CURDATE()
        WHERE purchase_id = :purchase_id
          AND user_id = :user_id
          AND is_unpaid = 1
    ");
    $stmt->execute([
        'purchase_id' => $purchaseId,
        'user_id' => $userId,
        'is_gcash' => $isGcash,
        'is_bank' => $isBank,
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Payable not found or already paid.');
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payable settled.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

