<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// --- Dapatkan token & project_id ---
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$project_id = $_GET['project_id'] ?? null;

if (!$token || !$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or project_id']);
    exit;
}

// --- Connect ke DB ---
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// --- Cari user berdasarkan token ---
$stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

// --- Sahkan user adalah owner projek ini ---
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND client_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - not your project']);
    exit;
}

// --- Fetch tasks (anggaran table `tasks`) ---
$stmt = $conn->prepare("
    SELECT id, title, description, status, due_date
    FROM tasks
    WHERE project_id = ?
    ORDER BY due_date ASC
");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = $row;
}

echo json_encode($tasks);
$conn->close();