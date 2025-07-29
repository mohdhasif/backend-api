<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// --- Dapatkan token & task_id ---
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$task_id = $_GET['task_id'] ?? null;

if (!$token || !$task_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or task_id']);
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

// --- Pastikan task itu milik client ---
$query = "
SELECT t.id, t.title, t.description, t.status, t.due_date, t.project_id
FROM tasks t
JOIN projects p ON t.project_id = p.id
WHERE t.id = ? AND p.client_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $task_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Task not found or not your project']);
    exit;
}

$task = $result->fetch_assoc();
echo json_encode($task);
