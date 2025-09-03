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
if (!isset($_FILES['file'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'No file uploaded']);
  exit;
}

// Check for specific upload errors
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
  $errorMessage = '';
  switch ($_FILES['file']['error']) {
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
      $errorMessage = 'File size exceeds limit';
      break;
    case UPLOAD_ERR_PARTIAL:
      $errorMessage = 'File upload was incomplete';
      break;
    case UPLOAD_ERR_NO_FILE:
      $errorMessage = 'No file was uploaded';
      break;
    case UPLOAD_ERR_NO_TMP_DIR:
      $errorMessage = 'Missing temporary folder';
      break;
    case UPLOAD_ERR_CANT_WRITE:
      $errorMessage = 'Failed to write file to disk';
      break;
    case UPLOAD_ERR_EXTENSION:
      $errorMessage = 'File upload stopped by extension';
      break;
    default:
      $errorMessage = 'Unknown upload error';
  }
  
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => $errorMessage]);
  exit;
}

try {
  $uploadDir = __DIR__ . '/uploads/attachments';
  if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0775, true)) {
      throw new Exception('Failed to create upload directory');
    }
  }

  $origName = $_FILES['file']['name'];
  $tmpPath = $_FILES['file']['tmp_name'];
  $mimeType = $_FILES['file']['type'] ?? null;
  $sizeBytes = (int)($_FILES['file']['size'] ?? 0);

  // Validate file size (10MB limit)
  if ($sizeBytes <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'File is empty or invalid']);
    exit;
  }
  
  if ($sizeBytes > 10 * 1024 * 1024) {
    http_response_code(400);
    $sizeMB = number_format($sizeBytes / (1024 * 1024), 2);
    echo json_encode(['success' => false, 'error' => "File size too large: {$sizeMB}MB (max 10MB)"]);
    exit;
  }

  // Validate file type
  $allowedTypes = [
    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 
    'application/pdf', 'text/plain', 'text/csv',
    'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
  ];
  
  if ($mimeType && !in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => "File type not supported: {$mimeType}"]);
    exit;
  }

  // Generate unique filename
  $ext = pathinfo($origName, PATHINFO_EXTENSION);
  $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
  $filename = sprintf('%s_t%s_%s.%s', 'att', time(), bin2hex(random_bytes(4)), $ext ?: 'dat');

  $destPath = $uploadDir . '/' . $filename;
  
  // Try to move uploaded file
  if (!move_uploaded_file($tmpPath, $destPath)) {
    throw new Exception('Failed to move uploaded file to destination');
  }

  // Verify file was actually created
  if (!file_exists($destPath)) {
    throw new Exception('File was not created after upload');
  }

  // Store URL as relative path; frontend BASE_URL will join
  $fileUrl = 'uploads/attachments/' . $filename;

  // Insert into database
  $sql = "INSERT INTO task_attachments (task_id, file_name, file_url, mime_type, size_bytes, created_at)
          VALUES (?, ?, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("isssi", $task_id, $origName, $fileUrl, $mimeType, $sizeBytes);
  
  if (!$stmt->execute()) {
    // If database insert fails, delete the uploaded file
    @unlink($destPath);
    throw new Exception('Failed to save file information to database: ' . $stmt->error);
  }

  $id = $stmt->insert_id;
  $stmt->close();

  // Success response
  echo json_encode([
    'success' => true, 
    'message' => 'File uploaded successfully',
    'attachment' => [
      'id' => $id,
      'task_id' => $task_id,
      'file_name' => $origName,
      'file_url' => $fileUrl,
      'mime_type' => $mimeType,
      'size_bytes' => $sizeBytes,
      'size_formatted' => formatFileSize($sizeBytes),
      'created_at' => date('Y-m-d H:i:s')
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
}

/**
 * Format file size in human-readable format
 */
function formatFileSize($bytes) {
  if ($bytes >= 1073741824) {
    return number_format($bytes / 1073741824, 2) . ' GB';
  } elseif ($bytes >= 1048576) {
    return number_format($bytes / 1048576, 2) . ' MB';
  } elseif ($bytes >= 1024) {
    return number_format($bytes / 1024, 2) . ' KB';
  } else {
    return $bytes . ' bytes';
  }
}
