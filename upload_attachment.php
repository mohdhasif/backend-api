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

// Check if file is uploaded
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

  $origName = $_FILES['file']['name'];
  $tmpPath = $_FILES['file']['tmp_name'];
  $mimeType = $_FILES['file']['type'] ?? null;
  $sizeBytes = (int)($_FILES['file']['size'] ?? 0);

  // Validate file size (optional: 10MB limit)
  if ($sizeBytes > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File is too large (max 10MB)']);
    exit;
  }

  // Validate file type (optional: restrict to common types)
  $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
  if ($mimeType && !in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File type not supported']);
    exit;
  }

  $ext = pathinfo($origName, PATHINFO_EXTENSION);
  $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
  $filename = sprintf('%s_t%s_%s.%s', 'att', time(), bin2hex(random_bytes(4)), $ext ?: 'dat');

  $destPath = $uploadDir . '/' . $filename;
  if (!move_uploaded_file($tmpPath, $destPath)) {
    throw new Exception('Failed to move uploaded file');
  }

  // Store URL as relative path; frontend BASE_URL will join
  $fileUrl = 'uploads/attachments/' . $filename;

  // Insert into database
  $sql = "INSERT INTO task_attachments (task_id, file_name, file_url, mime_type, size_bytes, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isssi", $task_id, $origName, $fileUrl, $mimeType, $sizeBytes);
  
  if (!$stmt->execute()) {
    throw new Exception('Failed to save file to database');
  }

  $id = $stmt->insert_id;
  $stmt->close();


  echo json_encode(['success' => true]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to upload attachment: ' . $e->getMessage()]);
}
