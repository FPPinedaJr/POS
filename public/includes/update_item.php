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
    $iditem = (int) ($_POST['iditem'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    $idCategory = (int) ($_POST['idcategory'] ?? 0);
    $value = (float) ($_POST['value'] ?? 0.0);
    $newCount = (int) ($_POST['item_count'] ?? 0);

    if ($iditem <= 0 || $itemName === '') {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: Item Name is required.']);
        exit;
    }

    if ($idCategory <= 0) {
        $idCategory = null; 
    }

    try {
        $stmt = $pdo->prepare("
            SELECT i.*, 
                   (SELECT item_count 
                    FROM item_history 
                    WHERE iditem = i.iditem 
                    ORDER BY iditem_history DESC LIMIT 1) AS current_count
            FROM item i 
            WHERE i.iditem = :iditem AND i.iduser = :iduser
        ");
        $stmt->execute([
            'iditem' => $iditem,
            'iduser' => $userId
        ]);
        
        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentItem) {
            throw new Exception("Item not found or you do not have permission to edit it.");
        }

        $currentCount = (int) ($currentItem['current_count'] ?? 0);

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
                idcategory = :idcategory, 
                value = :value, 
                image_basename = :image_basename, 
                image_thumb_path = :image_thumb_path, 
                image_preview_path = :image_preview_path
            WHERE iditem = :iditem AND iduser = :iduser
        ");
        
        $updateStmt->execute([
            'item_name' => $itemName,
            'idcategory' => $idCategory,
            'value' => $value,
            'image_basename' => $imageBasename,
            'image_thumb_path' => $imageThumbPath,
            'image_preview_path' => $imagePreviewPath,
            'iditem' => $iditem,
            'iduser' => $userId
        ]);

        if ($newCount !== $currentCount) {
            $histStmt = $pdo->prepare("
                INSERT INTO item_history (iditem, item_count, created_at) 
                VALUES (:iditem, :item_count, NOW())
            ");
            $histStmt->execute([
                'iditem' => $iditem,
                'item_count' => $newCount
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