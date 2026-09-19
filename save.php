<?php
// save.php - Receives, validates, and stores invoice records

header('Content-Type: application/json; charset=utf-8');

// Ensure request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

// Decode input (supports raw JSON from fetch() or standard $_POST)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? [];
} else {
    $data = $_POST;
}

// 1. Sanitize & trim inputs
$vendor       = trim($data['vendor'] ?? '');
$invoice_date = trim($data['invoice_date'] ?? '');
$total_amount = trim($data['total_amount'] ?? '');
$category     = trim($data['category'] ?? 'Uncategorized');

// 2. Validate input fields
$errors = [];

if (empty($vendor)) {
    $errors[] = 'Vendor name is required.';
}

if (empty($invoice_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoice_date)) {
    $errors[] = 'A valid invoice date (YYYY-MM-DD) is required.';
}

if (!is_numeric($total_amount) || floatval($total_amount) < 0) {
    $errors[] = 'Total amount must be a valid positive number.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'status' => 'error',
        'errors' => $errors
    ]);
    exit;
}

// 3. Execute parameterized insertion using PDO
try {
    $sql = "INSERT INTO invoices (vendor, invoice_date, total_amount, category) 
            VALUES (:vendor, :invoice_date, :total_amount, :category)";
    
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute([
        ':vendor'       => $vendor,
        ':invoice_date' => $invoice_date,
        ':total_amount' => number_format((float)$total_amount, 2, '.', ''),
        ':category'     => $category
    ]);

    $insertedId = $pdo->lastInsertId();

    http_response_code(201);
    echo json_encode([
        'status'  => 'success',
        'message' => 'Invoice saved successfully.',
        'id'      => $insertedId
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}