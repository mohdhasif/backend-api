<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

$input = json_decode(file_get_contents("php://input"), true);

$task_id = $input['task_id'] ?? null;
$title = $input['title'] ?? null;
$description = $input['description'] ?? null;
$status = $input['status'] ?? null;
// $due_date = $input['due_date'] ?? null;
$start_at = $input['start_at'] ?? null;
$end_at = $input['end_at'] ?? null;

if (!$task_id || !$title || !$status || !$start_at || !$end_at) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$user = auth_user($conn);              // <-- pusat
$user_id = (int)$user['id'];

// Sahkan task milik client melalui projek
$query = "
SELECT t.id
FROM tasks t
JOIN projects p ON t.project_id = p.id
WHERE t.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'You are not allowed to update this task']);
    exit;
}

// Update task
$stmt = $conn->prepare("
    UPDATE tasks
    SET title = ?, description = ?, status = ?, start_at = ?, end_at = ?
    WHERE id = ?
");
$stmt->bind_param("sssssi", $title, $description, $status, $start_at, $end_at, $task_id);
$success = $stmt->execute();

if ($success) {
    echo json_encode(['message' => 'Task updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update task']);
}
