<?php

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/connect_db.php';
require_once __DIR__ . '/ImageService.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$basePath = __DIR__ . '/../storage/uploads';
// Use a relative URL so it works under /inventory/public/... on XAMPP
$baseUrl = 'storage/uploads';

$imageService = new ImageService($basePath, $baseUrl);

// If the uploaded file exceeds PHP limits, PHP will NOT populate normal POST fields reliably.
// Handle upload errors first so the user gets the correct message.
if (isset($_FILES['item_image']) && is_array($_FILES['item_image'])) {
    $uploadErr = (int) ($_FILES['item_image']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadErr === UPLOAD_ERR_INI_SIZE || $uploadErr === UPLOAD_ERR_FORM_SIZE) {
        echo json_encode(['success' => false, 'message' => 'Image upload failed: File too large (max 5MB).']);
        exit;
    }
    if ($uploadErr !== UPLOAD_ERR_OK && $uploadErr !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => 'Image upload failed (error code: ' . $uploadErr . ').']);
        exit;
    }
}

$itemName = trim($_POST['item_name'] ?? '');
$categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : null;
$unit = trim($_POST['unit'] ?? '');
$value = isset($_POST['value']) ? (float) $_POST['value'] : 0;
$retailPrice = isset($_POST['retail_price']) ? (float) $_POST['retail_price'] : 0;
$wholesalePrice = isset($_POST['wholesale_price']) ? (float) $_POST['wholesale_price'] : 0;
$stockThreshold = isset($_POST['stock_threshold']) ? (int) $_POST['stock_threshold'] : 0;
$itemCount = isset($_POST['item_count']) ? (int) $_POST['item_count'] : 0;

// Purchase step
$purchasePayment = trim($_POST['purchase_payment'] ?? 'cash'); // cash | gcash | bank | unpaid
$purchaseSupplier = trim($_POST['purchase_supplier'] ?? '');
$purchaseDueDate = trim($_POST['purchase_due_date'] ?? '');

if ($itemName === '' || !$categoryId) {
    echo json_encode(['success' => false, 'message' => 'Item name and category are required.']);
    exit;
}

if ($itemCount < 0) {
    echo json_encode(['success' => false, 'message' => 'Item count cannot be negative.']);
    exit;
}

if (!in_array($purchasePayment, ['cash', 'gcash', 'bank', 'unpaid'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid purchase payment method.']);
    exit;
}

if ($purchasePayment === 'unpaid') {
    if ($purchaseSupplier === '' || $purchaseDueDate === '') {
        echo json_encode(['success' => false, 'message' => 'Supplier and due date are required for unpaid purchases.']);
        exit;
    }
}

$imageMeta = null;

if (!empty($_FILES['item_image']['name'] ?? '')) {
    try {
        $imageMeta = $imageService->handleUpload($_FILES['item_image']);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Image upload failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES)]);
        exit;
    }
}

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO item (
                category_id,
                user_id,
                item_name,
                unit,
                value,
                retail_price,
                wholesale_price,
                stock_threshold,
                current_stock,
                image_basename,
                image_thumb_path,
                image_preview_path
            )
            VALUES (
                :category_id,
                :user_id,
                :item_name,
                :unit,
                :value,
                :retail_price,
                :wholesale_price,
                :stock_threshold,
                :current_stock,
                :image_basename,
                :thumb,
                :preview
            )";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'category_id' => $categoryId,
        'user_id' => (int) $_SESSION['user_id'],
        'item_name' => $itemName,
        'unit' => $unit,
        'value' => $value,
        'retail_price' => $retailPrice,
        'wholesale_price' => $wholesalePrice,
        'stock_threshold' => $stockThreshold,
        'current_stock' => max(0, (int) $itemCount),
        'image_basename' => $imageMeta['basename'] ?? null,
        'thumb' => $imageMeta['thumb']['url'] ?? null,
        'preview' => $imageMeta['preview']['url'] ?? null,
    ]);

    $itemId = (int) $pdo->lastInsertId();

    // Store a purchase record for the new item (qty & unit cost are derived from item fields).
    if ($itemId > 0) {
        $qty = max(0, (int) $itemCount);
        $unitCost = (float) $value;
        $totalAmount = round($qty * $unitCost, 2);

        $isUnpaid = ($purchasePayment === 'unpaid') ? 1 : 0;
        $isGcash = ($purchasePayment === 'gcash') ? 1 : 0;
        $isBank = ($purchasePayment === 'bank') ? 1 : 0;

        $dueDateSql = ($isUnpaid && $purchaseDueDate !== '') ? $purchaseDueDate : null;
        $supplierSql = ($isUnpaid && $purchaseSupplier !== '') ? $purchaseSupplier : '';
        $settleDateSql = null;

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
                :settle_date,
                NOW()
            )"
        );

        $purchaseStmt->execute([
            'user_id' => (int) $_SESSION['user_id'],
            'item_id' => $itemId,
            'qty' => $qty,
            'value' => $unitCost,
            'total_amount' => $totalAmount,
            'is_unpaid' => $isUnpaid,
            'is_gcash' => $isGcash,
            'is_bank' => $isBank,
            'supplier' => $supplierSql,
            'due_date' => $dueDateSql,
            'settle_date' => $settleDateSql,
        ]);
    }

    if ($itemId > 0 && $itemCount !== 0) {
        $historyUuid = $_POST['history_uuid'] ?? null;
        if (!$historyUuid) {
            // Fallback: generate a random UUID-like string server-side
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

        $histStmt = $pdo->prepare(
            "INSERT INTO item_history (history_uuid, item_id, item_count, description, created_at)
             VALUES (:history_uuid, :item_id, :item_count, :description, NOW())"
        );

        $histStmt->execute([
            'history_uuid' => $historyUuid,
            'item_id' => $itemId,
            'item_count' => $itemCount,
            'description' => 'ADD: ' . $itemName,
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}