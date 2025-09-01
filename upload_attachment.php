<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
if ($task_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing or invalid task_id']);
  exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'File is required']);
  exit;
}

try {
  $uploadDir = __DIR__ . '/uploads/attachments';
  if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
  }

  $origName  = $_FILES['file']['name'];
  $tmpPath   = $_FILES['file']['tmp_name'];
  $mimeType  = $_FILES['file']['type'] ?? null;
  $sizeBytes = (int)($_FILES['file']['size'] ?? 0);

  $ext = pathinfo($origName, PATHINFO_EXTENSION);
  $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
  $filename = sprintf('%s_t%s_%s.%s', 'att', time(), bin2hex(random_bytes(4)), $ext ?: 'dat');

  $destPath = $uploadDir . '/' . $filename;
  if (!move_uploaded_file($tmpPath, $destPath)) {
    throw new Exception('Failed to move uploaded file');
  }

  // Store URL as relative path; frontend BASE_URL will join.
  $fileUrl = 'uploads/attachments/' . $filename;

  $sql = "INSERT INTO task_attachments (task_id, file_name, file_url, mime_type, size_bytes, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isssi", $task_id, $origName, $fileUrl, $mimeType, $sizeBytes);
  $stmt->execute();

  $id = $stmt->insert_id;

  $attachment = [
    'id' => $id,
    'task_id' => $task_id,
    'file_name' => $origName,
    'file_url' => $fileUrl,
    'mime_type' => $mimeType,
    'size_bytes' => $sizeBytes,
    'created_at' => date('Y-m-d H:i:s'),
  ];

  echo json_encode(['success' => true, 'attachment' => $attachment]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to upload attachment']);
}

// publish