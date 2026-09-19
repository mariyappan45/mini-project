<?php
// db.php - Database connection configuration

$host = '127.0.0.1';
$db   = 'expense_tracker';
$user = 'root';        // Adjust for your MySQL username
$pass = '';            // Adjust for your MySQL password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return associative arrays by default
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Enforce native prepared statements
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Return a JSON error if the connection fails
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database connection failure: ' . $e->getMessage()
    ]);
    exit;
}