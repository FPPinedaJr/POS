<?php
/**
 * Category CRUD API for the Add Item flow.
 * Actions: add (create), delete (soft-delete). All scoped to the logged-in user.
 */

session_start();
require_once __DIR__ . '/connect_db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Not authorized']);
    exit;
}

$iduser = (int) $_SESSION['user_id'];
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($action === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing action']);
    exit;
}

if ($action === 'add') {
    $categoryName = trim($_POST['category_name'] ?? '');
    if ($categoryName === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Category name is required']);
        exit;
    }

    try {
        $check = $pdo->prepare(
            "SELECT 1 FROM category
             WHERE iduser = :iduser AND LOWER(TRIM(category_name)) = LOWER(:category_name)
             LIMIT 1"
        );
        $check->execute(['iduser' => $iduser, 'category_name' => $categoryName]);
        if ($check->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'A category with this name already exists.']);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO category (iduser, category_name, is_deleted)
             VALUES (:iduser, :category_name, 0)"
        );
        $stmt->execute([
            'iduser' => $iduser,
            'category_name' => $categoryName,
        ]);

        echo json_encode([
            'ok' => true,
            'idcategory' => (int) $pdo->lastInsertId(),
            'category_name' => $categoryName,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to add category']);
    }
    exit;
}

if ($action === 'update') {
    $idcategory = isset($_POST['idcategory']) ? (int) $_POST['idcategory'] : 0;
    $categoryName = trim($_POST['category_name'] ?? '');

    if ($idcategory <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid category']);
        exit;
    }

    if ($categoryName === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Category name is required']);
        exit;
    }

    try {
        // Ensure uniqueness (excluding the current category)
        $check = $pdo->prepare(
            "SELECT 1
             FROM category
             WHERE iduser = :iduser
               AND idcategory <> :idcategory
               AND LOWER(TRIM(category_name)) = LOWER(:category_name)
               AND is_deleted = 0
             LIMIT 1"
        );
        $check->execute([
            'iduser' => $iduser,
            'idcategory' => $idcategory,
            'category_name' => $categoryName,
        ]);

        if ($check->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'A category with this name already exists.']);
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE category
             SET category_name = :category_name
             WHERE idcategory = :idcategory
               AND iduser = :iduser
               AND is_deleted = 0"
        );
        $stmt->execute([
            'category_name' => $categoryName,
            'idcategory' => $idcategory,
            'iduser' => $iduser,
        ]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Category not found or access denied']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'idcategory' => $idcategory,
            'category_name' => $categoryName,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to update category']);
    }
    exit;
}

if ($action === 'delete') {
    $idcategory = isset($_POST['idcategory']) ? (int) $_POST['idcategory'] : 0;
    if ($idcategory <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Invalid category']);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE category
             SET is_deleted = 1
             WHERE idcategory = :idcategory AND iduser = :iduser"
        );
        $stmt->execute(['idcategory' => $idcategory, 'iduser' => $iduser]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Category not found or access denied']);
            exit;
        }

        echo json_encode(['ok' => true, 'idcategory' => $idcategory]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Failed to delete category']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Unknown action']);

