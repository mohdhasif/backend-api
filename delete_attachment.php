<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing or invalid id']);
  exit;
}

try {
  // Get current to delete file
  $stmt = $conn->prepare("SELECT file_url FROM task_attachments WHERE id = ? LIMIT 1");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if ($row) {
    $abs = __DIR__ . '/' . ltrim($row['file_url'], '/');
    if (is_file($abs)) @unlink($abs);
  }

  $stmt = $conn->prepare("DELETE FROM task_attachments WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to delete attachment']);
}
