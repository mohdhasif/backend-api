<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

$user = auth_user($conn);              // <-- pusat
$auth_user_id = (int)$user['id'];

$input = json_decode(file_get_contents("php://input"), true);
$task_id = $input['task_id'] ?? null;

if (!$task_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing task_id']);
    exit;
}

$user_id = $auth_user_id;

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
