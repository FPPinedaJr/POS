<?php
/**
 * Database connection bootstrap.
 * Loads environment variables from ../.env and exposes a global $pdo instance.
 */

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            throw new Exception("Environment file not found at: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Added trim() to prevent hidden whitespace/carriage return issues
            [$key, $value] = explode('=', trim($line), 2);

            // Use $_ENV instead of putenv() to bypass InfinityFree restrictions
            $_ENV[$key] = $value;
        }
    }
}

// .env is placed in the public directory (one level above this includes folder)
loadEnv(__DIR__ . '/../.env');

date_default_timezone_set("Asia/Manila");

// Retrieve using $_ENV instead of getenv()
$hostname = $_ENV['DB_HOST'] ?? '';
$port = $_ENV['DB_PORT'] ?? '3306';
$username = $_ENV['DB_USER'] ?? '';
$password = $_ENV['DB_PASS'] ?? '';
$defaultSchema = $_ENV['DB_NAME'] ?? '';
$charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

$dsn = "mysql:host={$hostname};dbname={$defaultSchema};charset={$charset};port={$port}";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Reuse existing connection in same request
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $pdo = new PDO($dsn, $username, $password, $options);
}