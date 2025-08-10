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
  $sql = "SELECT id, task_id, sender_type, sender_id, message, created_at
          FROM task_notes
          WHERE task_id = ?
          ORDER BY created_at ASC, id ASC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $task_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;

  echo json_encode($rows);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to list notes']);
}
