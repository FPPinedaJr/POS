<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php'; // Needed for the Google Client

// Initialize the Google Client
$client = new Google\Client();

// If the user has an active Google token, revoke it
if (isset($_SESSION['user_token'])) {
    $client->revokeToken($_SESSION['user_token']);
}

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the PHP session
session_destroy();

// Redirect back to the login page (one level up from /includes)
header('Location: ../index.php');
exit;
?>