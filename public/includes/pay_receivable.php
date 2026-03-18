<?php
session_start();
require_once 'connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$uuid = $data['uuid'] ?? '';
$isGcash = !empty($data['is_gcash']) ? 1 : 0;

if (!$uuid) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID missing.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE transaction_header 
        SET is_unpaid = 0, is_gcash = :is_gcash, settle_date = CURDATE() 
        WHERE transaction_uuid = :uuid AND is_unpaid = 1
    ");
    $stmt->execute(['uuid' => $uuid, 'is_gcash' => $isGcash]);

    if ($stmt->rowCount() === 0) {
        throw new Exception("Transaction not found or already paid.");
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Receivable settled.']);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>