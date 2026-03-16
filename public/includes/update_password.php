<?php
session_start();
require_once 'connect_db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']))
    exit(json_encode(['status' => 'error', 'message' => 'Not authorized']));

$current = $_POST['current_password'];
$new = $_POST['new_password'];

// 1. PHP Validation (Mirroring your requirements)
if (strlen($new) < 8 || !preg_match("#[0-9]+#", $new) || !preg_match("#[a-z]+#", $new) || !preg_match("#[A-Z]+#", $new)) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be 8+ chars with 1 uppercase, 1 lowercase, and 1 number.']);
    exit;
}

// 2. Verify Current Password
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($user && password_verify($current, $user['password'])) {
    $hashed = password_hash($new, PASSWORD_DEFAULT);
    $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update->execute([$hashed, $_SESSION['user_id']]);

    session_unset();
    session_destroy();

    echo json_encode(['status' => 'success', 'message' => 'Password updated! Please login again.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Current password does not match our records.']);
}