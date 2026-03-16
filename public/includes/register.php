<?php
session_start();
require_once 'connect_db.php';

// Tell the browser to expect a JSON response
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format.']);
        exit;
    }

    if (strlen($password) < 8 || !preg_match("#[0-9]+#", $password) || !preg_match("#[a-z]+#", $password) || !preg_match("#[A-Z]+#", $password)) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be 8+ chars with 1 uppercase, 1 lowercase, and 1 number.']);
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, google_id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        if (!empty($existingUser['google_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'This email is linked to a Google account. Please log in with Google.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Email is already registered.']);
        }
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $default_picture = 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0D8ABC&color=fff';

    $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, picture) VALUES (:name, :email, :password, :picture)");

    try {
        $pdo->beginTransaction();

        // 1. Insert the User
        $insertStmt = $pdo->prepare("INSERT INTO users (name, email, password, picture) VALUES (:name, :email, :password, :picture)");
        $insertStmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $hashed_password,
            'picture' => $default_picture
        ]);
        $userId = $pdo->lastInsertId();

        // 2. Insert Default Categories
        $defaultCategories = ['Supplies', 'Equipment', 'Furniture'];
        $catStmt = $pdo->prepare("INSERT INTO category (iduser, category_name, is_deleted) VALUES (:iduser, :cat_name, 0)");

        foreach ($defaultCategories as $catName) {
            $catStmt->execute([
                'iduser' => $userId,
                'cat_name' => $catName
            ]);
        }

        $pdo->commit();

        echo json_encode(['status' => 'success', 'message' => 'Account created! Redirecting to login...']);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Database error. Please try again.']);
        exit;
    }
}
?>