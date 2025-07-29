<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// --- Connect ke DB awal-awal dan set charset ---
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$conn->set_charset("utf8mb4");

// --- Ambil token dari header ---
$headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'No token provided']);
    exit;
}

// --- Dapatkan user ID berdasarkan token ---
$stmt = $conn->prepare("SELECT id FROM users WHERE token = ? LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $user['id'];

// --- Ambil projek berdasarkan client_id ---
$stmt = $conn->prepare("
    SELECT id, title, description, status, progress, created_at
    FROM projects
    WHERE client_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$projects = $result->fetch_all(MYSQLI_ASSOC);

// --- Return JSON ---
echo json_encode($projects);
