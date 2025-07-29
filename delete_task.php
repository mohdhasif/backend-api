<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$input = json_decode(file_get_contents("php://input"), true);
$task_id = $input['task_id'] ?? null;

if (!$token || !$task_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or task_id']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

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

// Pastikan task milik client
$query = "
SELECT t.id
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
    echo json_encode(['error' => 'Not allowed to delete this task']);
    exit;
}

// Delete task
$stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
$stmt->bind_param("i", $task_id);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['message' => 'Task deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to delete task']);
}
