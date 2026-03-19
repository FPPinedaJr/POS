<?php

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/ImageService.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $value = (float) ($_POST['value'] ?? 0.0);
    $retailPrice = isset($_POST['retail_price']) ? (float) $_POST['retail_price'] : 0.0;
    $wholesalePrice = isset($_POST['wholesale_price']) ? (float) $_POST['wholesale_price'] : 0.0;
    $stockThreshold = isset($_POST['stock_threshold']) ? (int) $_POST['stock_threshold'] : 0;
    $newCount = (int) ($_POST['item_count'] ?? 0);

    // Optional purchase details (from edit modal)
    $editPurchasePayment = trim($_POST['edit_purchase_payment'] ?? 'cash'); // cash | gcash | bank | unpaid
    $editPurchaseSupplier = trim($_POST['edit_purchase_supplier'] ?? '');
    $editPurchaseDueDate = trim($_POST['edit_purchase_due_date'] ?? ''); // optional

    if ($item_id <= 0 || $itemName === '') {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: Item Name is required.']);
        exit;
    }

    if ($category_id <= 0) {
        $category_id = null;
    }

    if (!in_array($editPurchasePayment, ['cash', 'gcash', 'bank', 'unpaid'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid purchase payment method.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   (SELECT item_count 
                    FROM item_history 
                    WHERE item_id = i.item_id 
                    ORDER BY created_at DESC LIMIT 1) AS current_count
            FROM item i 
            WHERE i.item_id = :item_id AND i.user_id = :user_id
        ");
        $stmt->execute([
            'item_id' => $item_id,
            'user_id' => $userId
        ]);

        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentItem) {
            throw new Exception("Item not found or you do not have permission to edit it.");
        }

        $currentCount = (int) ($currentItem['current_count'] ?? 0);
        $delta = $newCount - $currentCount;

        $imageBasename = $currentItem['image_basename'];
        $imageThumbPath = $currentItem['image_thumb_path'];
        $imagePreviewPath = $currentItem['image_preview_path'];

        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {

            // Match paths to save_item.php configuration (public/storage/...)
            $basePath = __DIR__ . '/../storage/uploads';
            // Use a relative URL so it works under /inventory/public/... on XAMPP
            $baseUrl = 'storage/uploads';

            $imageService = new ImageService($basePath, $baseUrl);

            $imgData = $imageService->handleUpload($_FILES['item_image']);

            $imageBasename = $imgData['basename'];
            $imageThumbPath = $imgData['thumb']['url'];
            $imagePreviewPath = $imgData['preview']['url'];
        } elseif (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("File upload error code: " . $_FILES['item_image']['error']);
        }

        $pdo->beginTransaction();

        $updateStmt = $pdo->prepare("
            UPDATE item 
            SET item_name = :item_name, 
                category_id = :category_id, 
                unit = :unit,
                value = :value, 
                retail_price = :retail_price,
                wholesale_price = :wholesale_price,
                stock_threshold = :stock_threshold,
                current_stock = :current_stock,
                image_basename = :image_basename, 
                image_thumb_path = :image_thumb_path, 
                image_preview_path = :image_preview_path
            WHERE item_id = :item_id AND user_id = :user_id
        ");

        $updateStmt->execute([
            'item_name' => $itemName,
            'category_id' => $category_id,
            'unit' => $unit,
            'value' => $value,
            'retail_price' => $retailPrice,
            'wholesale_price' => $wholesalePrice,
            'stock_threshold' => $stockThreshold,
            'current_stock' => max(0, (int) $newCount),
            'image_basename' => $imageBasename,
            'image_thumb_path' => $imageThumbPath,
            'image_preview_path' => $imagePreviewPath,
            'item_id' => $item_id,
            'user_id' => $userId
        ]);

        if ($newCount !== $currentCount) {
            $historyUuid = $_POST['history_uuid'] ?? null;
            if (!$historyUuid) {
                $bytes = bin2hex(random_bytes(16));
                $historyUuid = sprintf(
                    '%s-%s-%s-%s-%s',
                    substr($bytes, 0, 8),
                    substr($bytes, 8, 4),
                    substr($bytes, 12, 4),
                    substr($bytes, 16, 4),
                    substr($bytes, 20)
                );
            }

            $histStmt = $pdo->prepare("
                INSERT INTO item_history (history_uuid, item_id, item_count, description, created_at) 
                VALUES (:history_uuid, :item_id, :item_count, :description, NOW())
            ");
            $histStmt->execute([
                'history_uuid' => $historyUuid,
                'item_id' => $item_id,
                'item_count' => $newCount,
                'description' => 'UPDATE: ' . $itemName,
            ]);
        }

        // If stock increased, create a purchase record (qty = delta, value = current unit cost).
        if ($delta > 0) {
            if ($editPurchasePayment === 'unpaid' && $editPurchaseSupplier === '') {
                throw new Exception('Supplier is required for unpaid purchases.');
            }

            $qty = (int) $delta;
            $unitCost = (float) $value;
            $totalAmount = round($qty * $unitCost, 2);

            $isUnpaid = ($editPurchasePayment === 'unpaid') ? 1 : 0;
            $isGcash = ($editPurchasePayment === 'gcash') ? 1 : 0;
            $isBank = ($editPurchasePayment === 'bank') ? 1 : 0;

            $dueDateSql = ($isUnpaid && $editPurchaseDueDate !== '') ? $editPurchaseDueDate : null;
            $supplierSql = ($isUnpaid && $editPurchaseSupplier !== '') ? $editPurchaseSupplier : '';

            $purchaseStmt = $pdo->prepare(
                "INSERT INTO item_purchase (
                    user_id,
                    item_id,
                    qty,
                    value,
                    total_amount,
                    is_unpaid,
                    is_gcash,
                    is_bank,
                    supplier,
                    due_date,
                    settle_date,
                    created_at
                ) VALUES (
                    :user_id,
                    :item_id,
                    :qty,
                    :value,
                    :total_amount,
                    :is_unpaid,
                    :is_gcash,
                    :is_bank,
                    :supplier,
                    :due_date,
                    NULL,
                    NOW()
                )"
            );

            $purchaseStmt->execute([
                'user_id' => $userId,
                'item_id' => $item_id,
                'qty' => $qty,
                'value' => $unitCost,
                'total_amount' => $totalAmount,
                'is_unpaid' => $isUnpaid,
                'is_gcash' => $isGcash,
                'is_bank' => $isBank,
                'supplier' => $supplierSql,
                'due_date' => $dueDateSql,
            ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true]);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}