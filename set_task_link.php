<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$task_id = isset($input['task_id']) ? (int)$input['task_id'] : 0;
$url     = isset($input['url']) ? trim($input['url']) : '';

if ($task_id <= 0 || $url === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing task_id or url']);
  exit;
}

try {
  // Ensure UNIQUE(task_id) on task_links for UPSERT
  $sql = "INSERT INTO task_links (task_id, url, updated_at)
          VALUES (?, ?, NOW())
          ON DUPLICATE KEY UPDATE url = VALUES(url), updated_at = NOW()";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("is", $task_id, $url);
  $stmt->execute();

  echo json_encode(['success' => true, 'task_id' => $task_id, 'url' => $url]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to set link']);
}

// publish