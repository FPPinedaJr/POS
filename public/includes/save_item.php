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
$categoryId = isset($_POST['idcategory']) ? (int) $_POST['idcategory'] : null;
$value = isset($_POST['value']) ? (float) $_POST['value'] : 0;
$itemCount = isset($_POST['item_count']) ? (int) $_POST['item_count'] : 0;

if ($itemName === '' || !$categoryId) {
    echo json_encode(['success' => false, 'message' => 'Item name and category are required.']);
    exit;
}

if ($itemCount < 0) {
    echo json_encode(['success' => false, 'message' => 'Item count cannot be negative.']);
    exit;
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

    $sql = "INSERT INTO item (idcategory, iduser, item_name, value, image_basename, image_thumb_path, image_preview_path)
            VALUES (:idcategory, :iduser, :item_name, :value, :image_basename, :thumb, :preview)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'idcategory' => $categoryId,
        'iduser' => (int) $_SESSION['user_id'],
        'item_name' => $itemName,
        'value' => $value,
        'image_basename' => $imageMeta['basename'] ?? null,
        'thumb' => $imageMeta['thumb']['url'] ?? null,
        'preview' => $imageMeta['preview']['url'] ?? null,
    ]);

    $itemId = (int) $pdo->lastInsertId();

    if ($itemId > 0 && $itemCount !== 0) {
        $histStmt = $pdo->prepare(
            "INSERT INTO item_history (iditem, item_count, created_at)
             VALUES (:iditem, :item_count, NOW())" 
        );

        $histStmt->execute([
            'iditem' => $itemId,
            'item_count' => $itemCount,
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