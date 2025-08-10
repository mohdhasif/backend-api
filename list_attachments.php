<?php
require 'db.php';

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($task_id <= 0) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, file_name, file_url, mime_type, size_bytes, created_at FROM task_attachments WHERE task_id=? ORDER BY id DESC");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
