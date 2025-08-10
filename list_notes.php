<?php
require 'db.php';

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($task_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT id, sender_type, sender_id, message, created_at FROM task_notes WHERE task_id=? ORDER BY id ASC");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows);
