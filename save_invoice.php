<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE && !empty(file_get_contents('php://input'))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

if (!is_array($input)) {
    $input = $_POST;
}

$requiredFields = ['vendor', 'invoice_date', 'total_amount', 'category'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || trim((string) $input[$field]) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: {$field}"]);
        exit;
    }
}

$vendor = trim((string) $input['vendor']);
$invoiceDate = trim((string) $input['invoice_date']);
$totalAmount = trim((string) $input['total_amount']);
$category = trim((string) $input['category']);

if (mb_strlen($vendor) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Vendor name is too long.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invoice date must be in YYYY-MM-DD format.']);
    exit;
}

if (!is_numeric($totalAmount) || (float) $totalAmount < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Total amount must be a non-negative number.']);
    exit;
}

$allowedCategories = ['Office Supplies', 'Software', 'Marketing', 'Travel', 'Utilities', 'Other'];
if (!in_array($category, $allowedCategories, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid category selected.']);
    exit;
}

$host = 'localhost';
$db = 'invoices_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $pdoOptions);

    $sql = 'INSERT INTO invoices (vendor, invoice_date, total_amount, category) VALUES (:vendor, :invoice_date, :total_amount, :category)';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':vendor' => $vendor,
        ':invoice_date' => $invoiceDate,
        ':total_amount' => number_format((float) $totalAmount, 2, '.', ''),
        ':category' => $category,
    ]);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Invoice saved successfully.',
        'data' => [
            'vendor' => $vendor,
            'invoice_date' => $invoiceDate,
            'total_amount' => number_format((float) $totalAmount, 2, '.', ''),
            'category' => $category,
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
    exit;
}
