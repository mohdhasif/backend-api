<?php
require 'db.php';
$input = json_decode(file_get_contents('php://input'), true);

$task_id = (int)($input['task_id'] ?? 0);
$url = trim($input['url'] ?? '');

if ($task_id <= 0 || $url === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'task_id/url required']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO task_links (task_id, url) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE url=VALUES(url), updated_at=CURRENT_TIMESTAMP");
$stmt->bind_param("is", $task_id, $url);
$stmt->execute();

echo json_encode(['success' => true, 'task_id' => $task_id, 'url' => $url]);
