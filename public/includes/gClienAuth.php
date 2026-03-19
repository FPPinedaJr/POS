<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/connect_db.php';

$env = parse_ini_file(__DIR__ . '/../.env');

// Determine environment
$isDev = isset($env['IS_DEV']) && (bool) $env['IS_DEV'];

// Fetch the appropriate credentials based on the flag
$clientId = $isDev ? $env['DEV_GOOGLE_CLIENT_ID'] : $env['PROD_GOOGLE_CLIENT_ID'];
$clientSecret = $isDev ? $env['DEV_GOOGLE_CLIENT_SECRET'] : $env['PROD_GOOGLE_CLIENT_SECRET'];
$redirectUri = $isDev ? $env['DEV_GOOGLE_REDIRECT_URI'] : $env['PROD_GOOGLE_REDIRECT_URI'];

$client = new Google\Client();
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

// Check if Google sent back an authorization code
if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);

        $google_oauth = new Google\Service\Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();

        $google_id = $google_account_info->id;
        $email = $google_account_info->email;
        $name = $google_account_info->name;
        $picture = $google_account_info->picture;

        // --- Database Logic ---

        // 1. Check if user exists by EMAIL instead of google_id
        $stmt = $pdo->prepare("SELECT id, google_id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // User already exists (either via standard signup or previous Google login)
            $userId = $user['id'];

            // 2. If they exist but don't have a google_id linked yet, link them now!
            if (empty($user['google_id'])) {
                $updateStmt = $pdo->prepare("UPDATE users SET google_id = :google_id, picture = :picture WHERE id = :id");
                $updateStmt->execute([
                    'google_id' => $google_id,
                    'picture' => $picture, // Optional: update their profile picture to the Google one
                    'id' => $userId
                ]);
            }
        } else {
            // 3. User does not exist at all. Proceed with fresh account creation.
            try {
                $pdo->beginTransaction();

                // Insert New User
                $stmt = $pdo->prepare("INSERT INTO users (google_id, name, email, picture) VALUES (:google_id, :name, :email, :picture)");
                $stmt->execute([
                    'google_id' => $google_id,
                    'name' => $name,
                    'email' => $email,
                    'picture' => $picture
                ]);
                $userId = $pdo->lastInsertId();

                // Insert Default Categories
                $defaultCategories = ['Supplies', 'Equipment', 'Furniture'];
                $catStmt = $pdo->prepare("INSERT INTO category (user_id, category_name, is_deleted) VALUES (:user_id, :cat_name, 0)");

                foreach ($defaultCategories as $catName) {
                    $catStmt->execute([
                        'user_id' => $userId,
                        'cat_name' => $catName
                    ]);
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                // Handle error
                exit("Failed to create account: " . $e->getMessage());
            }
        }

        // --- Setup Session ---
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_token'] = $token['access_token'];
        $_SESSION['user_name'] = $name;
        $_SESSION['user_picture'] = $picture;
        $_SESSION['google_id'] = $google_id;
        $_SESSION['user_email'] = $email;

        // Regenerate session ID for security
        session_regenerate_id(true);

        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Successfully logged in with Google!'];

        header('Location: portal.php');
        exit;
    }
}

// Generate the login URL to be used on index.php
$loginUrl = $client->createAuthUrl();
?>