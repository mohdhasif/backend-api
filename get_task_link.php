<?php
require 'db.php';

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($task_id <= 0) {
    echo json_encode(null);
    exit;
}

$stmt = $conn->prepare("SELECT task_id, url, updated_at FROM task_links WHERE task_id=?");
$stmt->bind_param("i", $task_id);
$stmt->execute();
$result = $stmt->get_result();
echo json_encode($result->fetch_assoc() ?: null);
