<?php
/**
 * Database connection bootstrap.
 */

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("Environment file not found at: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0)
                continue;

            // Handle lines that might not have an '='
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', trim($line), 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
}

// Load the .env file
loadEnv(__DIR__ . '/../.env');

date_default_timezone_set("Asia/Manila");

// 1. Determine Environment from $_ENV
$isDev = isset($_ENV['IS_DEV']) && $_ENV['IS_DEV'] == "1";

// 2. Map the correct keys from $_ENV based on the environment
if ($isDev) {
    $host = $_ENV['DEV_DB_HOST'] ?? 'localhost';
    $db = $_ENV['DEV_DB_NAME'] ?? '';
    $user = $_ENV['DEV_DB_USER'] ?? '';
    $pass = $_ENV['DEV_DB_PASS'] ?? '';
    $port = $_ENV['DEV_DB_PORT'] ?? '3306';
} else {
    $host = $_ENV['PROD_DB_HOST'] ?? '';
    $db = $_ENV['PROD_DB_NAME'] ?? '';
    $user = $_ENV['PROD_DB_USER'] ?? '';
    $pass = $_ENV['PROD_DB_PASS'] ?? '';
    $port = $_ENV['PROD_DB_PORT'] ?? '3306';
}

// 3. Construct the DSN (Fixed the curly braces and variable names)
$dsn = "mysql:host=$host;dbname=$db;port=$port;charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// 4. Create the Global PDO instance
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Log error and stop execution
        error_log("Connection failed: " . $e->getMessage());
        exit("Database connection error. Please check your configuration.");
    }
}