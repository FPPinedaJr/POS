<?php
session_start();
require_once 'connect_db.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Please enter email and password.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        if (empty($user['password']) && !empty($user['google_id'])) {
            echo json_encode(['status' => 'error', 'message' => 'This email is linked to a Google account. Please log in with Google.']);
            exit;
        }

        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_picture'] = $user['picture'];
            $_SESSION['google_id'] = $user['google_id'];

            echo json_encode(['status' => 'success', 'message' => 'Welcome back! Redirecting to dashboard...']);
            exit;
        }
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
    exit;
}
?>