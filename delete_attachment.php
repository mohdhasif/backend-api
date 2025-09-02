<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$attachment_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($attachment_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing or invalid attachment ID']);
  exit;
}

try {
  // Get attachment details
  $sql = "SELECT ta.id, ta.task_id, ta.file_url, ta.file_name
          FROM task_attachments ta
          WHERE ta.id = ?";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $attachment_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Attachment not found']);
    exit;
  }
  
  $attachment = $result->fetch_assoc();
  $stmt->close();
  
  // Delete the physical file
  $filePath = __DIR__ . '/' . $attachment['file_url'];
  if (file_exists($filePath)) {
    if (!unlink($filePath)) {
      // Log warning but continue with database deletion
      error_log("Warning: Could not delete physical file: $filePath");
    }
  }
  
  // Delete from database
  $deleteSql = "DELETE FROM task_attachments WHERE id = ?";
  $deleteStmt = $conn->prepare($deleteSql);
  $deleteStmt->bind_param("i", $attachment_id);
  
  if (!$deleteStmt->execute()) {
    throw new Exception('Failed to delete attachment from database');
  }
  
  $deleteStmt->close();
  
  echo json_encode([
    'success' => true,
    'message' => 'Attachment deleted successfully'
  ]);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to delete attachment: ' . $e->getMessage()]);
}
