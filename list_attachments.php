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
  // Get all attachments for the task
  $sql = "SELECT id, task_id, file_name, file_url, mime_type, size_bytes, created_at
          FROM task_attachments
          WHERE task_id = ?
          ORDER BY created_at ASC";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $task_id);
  $stmt->execute();
  $res = $stmt->get_result();

  $attachments = [];
  while ($row = $res->fetch_assoc()) {
    // Add human-readable file size
    $row['size_formatted'] = formatFileSize($row['size_bytes']);
    // Add file type category
    $row['file_category'] = getFileCategory($row['mime_type']);
    $attachments[] = $row;
  }
  $stmt->close();

  echo json_encode($attachments);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to list attachments: ' . $e->getMessage()]);
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

/**
 * Categorize file types for better organization
 */
function getFileCategory($mimeType) {
  if (!$mimeType) return 'unknown';
  
  if (strpos($mimeType, 'image/') === 0) {
    return 'image';
  } elseif (strpos($mimeType, 'video/') === 0) {
    return 'video';
  } elseif (strpos($mimeType, 'audio/') === 0) {
    return 'audio';
  } elseif (strpos($mimeType, 'application/pdf') === 0) {
    return 'document';
  } elseif (strpos($mimeType, 'text/') === 0) {
    return 'text';
  } elseif (strpos($mimeType, 'application/msword') === 0 || 
            strpos($mimeType, 'application/vnd.openxmlformats-officedocument') === 0) {
    return 'document';
  } else {
    return 'other';
  }
}