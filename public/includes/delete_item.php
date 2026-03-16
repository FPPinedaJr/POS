<?php
session_start();
require_once __DIR__ . '/connect_db.php';

// Tell the browser we are sending back JSON
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $userId = (int) $_SESSION['user_id'];

    if ($item_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item ID.']);
        exit;
    }

    try {
        // 1. Fetch the image data BEFORE we delete the row
        $stmtFetch = $pdo->prepare("SELECT image_basename, image_thumb_path, image_preview_path FROM item WHERE item_id = :item_id AND user_id = :user_id");
        $stmtFetch->execute(['item_id' => $item_id, 'user_id' => $userId]);
        $itemData = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        // 2. Delete history first
        $stmtHist = $pdo->prepare("DELETE FROM item_history WHERE item_id = :item_id");
        $stmtHist->execute(['item_id' => $item_id]);

        // 3. Delete the item
        $stmtItem = $pdo->prepare("DELETE FROM item WHERE item_id = :item_id AND user_id = :user_id");
        $stmtItem->execute([
            'item_id' => $item_id,
            'user_id' => $userId
        ]);

        if ($stmtItem->rowCount() > 0) {
            $pdo->commit();

            // 4. CLEANUP: Delete physical files from the server
            if ($itemData) {
                // Match this path to your save_item.php base path
                $basePath = __DIR__ . '/../../storage/uploads';

                // Delete Thumbnail
                if (!empty($itemData['image_thumb_path'])) {
                    $thumbFile = $basePath . '/thumbs/' . basename($itemData['image_thumb_path']);
                    if (file_exists($thumbFile)) {
                        unlink($thumbFile);
                    }
                }

                // Delete Preview
                if (!empty($itemData['image_preview_path'])) {
                    $previewFile = $basePath . '/previews/' . basename($itemData['image_preview_path']);
                    if (file_exists($previewFile)) {
                        unlink($previewFile);
                    }
                }

                // Delete Original (Using glob because the extension might be .png, .jpg, or .webp)
                if (!empty($itemData['image_basename'])) {
                    $pattern = $basePath . '/originals/' . $itemData['image_basename'] . '_original.*';
                    foreach (glob($pattern) as $filename) {
                        if (file_exists($filename)) {
                            unlink($filename);
                        }
                    }
                }
            }

            echo json_encode(['success' => true]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Item not found or you do not have permission to delete it.']);
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database error: Unable to delete item.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}