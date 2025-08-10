<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$task_id    = isset($input['task_id']) ? (int)$input['task_id'] : 0;
$senderType = isset($input['sender_type']) ? strtolower(trim($input['sender_type'])) : '';
$message    = isset($input['message']) ? trim($input['message']) : '';
$senderId   = isset($input['sender_id']) ? (int)$input['sender_id'] : null;

// Default sender_id to current user if none provided
if ($senderId === null && isset($user['id'])) {
  $senderId = (int)$user['id'];
}

if ($task_id <= 0 || !in_array($senderType, ['admin','client'], true) || $message === '') {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing/invalid task_id, sender_type or message']);
  exit;
}

try {
  $sql = "INSERT INTO task_notes (task_id, sender_type, sender_id, message, created_at)
          VALUES (?, ?, ?, ?, NOW())";
  $stmt = $conn->prepare($sql);
  if ($senderId === null) {
    // bind null for sender_id
    $null = null;
    $stmt->bind_param("isis", $task_id, $senderType, $null, $message);
  } else {
    $stmt->bind_param("isis", $task_id, $senderType, $senderId, $message);
  }
  $stmt->execute();
  $id = $stmt->insert_id;

  $note = [
    'id' => $id,
    'task_id' => $task_id,
    'sender_type' => $senderType,
    'sender_id' => $senderId,
    'message' => $message,
    'created_at' => date('Y-m-d H:i:s'),
  ];

  echo json_encode(['success' => true, 'note' => $note]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to add note']);
}
