<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

$task_id = $_GET['task_id'] ?? null;

if (!$task_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or task_id']);
    exit;
}

$user = auth_user($conn);              // <-- pusat

$user_id = (int)$user['id'];

// --- Ambil task details (tanpa tapis client) ---
$query = "
SELECT 
    t.id,
    t.title,
    t.description,
    t.status,
    t.due_date,
    t.project_id,
    p.title AS project_title
FROM tasks t
JOIN projects p ON t.project_id = p.id
WHERE t.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Task not found']);
    exit;
}

$task = $result->fetch_assoc();
echo json_encode($task, JSON_UNESCAPED_UNICODE);
