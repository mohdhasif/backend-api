<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if ($task_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing or invalid task_id']);
  exit;
}

try {
  $stmt = $conn->prepare("SELECT task_id, url, updated_at FROM task_links WHERE task_id = ? LIMIT 1");
  $stmt->bind_param("i", $task_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();

  // Frontend expects either an object or null
  echo json_encode($row ?: null);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to get link']);
}

// publish