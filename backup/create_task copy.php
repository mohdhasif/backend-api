<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// --- Dapatkan token & parse JSON body ---
$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$input = json_decode(file_get_contents("php://input"), true);

$project_id = $input['project_id'] ?? null;
$title = $input['title'] ?? null;
$description = $input['description'] ?? null;
$status = $input['status'] ?? 'pending';
$due_date = $input['due_date'] ?? null;

if (!$token || !$project_id || !$title || !$due_date) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// --- Connect ke DB ---
$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// --- Sahkan token client ---
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

// --- Sahkan projek milik client ---
$stmt = $conn->prepare("SELECT id FROM projects WHERE id = ? AND client_id = ?");
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this project']);
    exit;
}

// --- Insert task baru ---
$stmt = $conn->prepare("
    INSERT INTO tasks (project_id, title, description, status, due_date)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("issss", $project_id, $title, $description, $status, $due_date);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['message' => 'Task created successfully', 'task_id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create task']);
}
