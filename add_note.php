<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/push_helper.php';

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

  // Get task and project information for notifications
  $taskInfo = null;
  $projectInfo = null;
  $clientUserId = null;
  
  // Get task details
  $taskStmt = $conn->prepare("
    SELECT t.title as task_title, t.project_id, p.title as project_title, p.client_id
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE t.id = ?
  ");
  $taskStmt->bind_param("i", $task_id);
  $taskStmt->execute();
  $taskResult = $taskStmt->get_result();
  if ($taskRow = $taskResult->fetch_assoc()) {
    $taskInfo = $taskRow;
    
    // Get client user_id
    if ($taskRow['client_id']) {
      $clientStmt = $conn->prepare("SELECT user_id FROM clients WHERE id = ?");
      $clientStmt->bind_param("i", $taskRow['client_id']);
      $clientStmt->execute();
      $clientResult = $clientStmt->get_result();
      if ($clientRow = $clientResult->fetch_assoc()) {
        $clientUserId = (int)$clientRow['user_id'];
      }
      $clientStmt->close();
    }
  }
  $taskStmt->close();

  // Get admin IDs
  $adminIds = [];
  $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
  $adminStmt->execute();
  $adminResult = $adminStmt->get_result();
  while ($adminRow = $adminResult->fetch_assoc()) {
    $adminIds[] = (int)$adminRow['id'];
  }
  $adminStmt->close();

  // Prepare notification payload
  $taskTitle = $taskInfo['task_title'] ?? "Task #$task_id";
  $projectTitle = $taskInfo['project_title'] ?? "Project";
  $senderName = $user['name'] ?? "User";
  
  $notifTitle = "New Note Added";
  $notifBody = "$senderName added a note to task \"$taskTitle\" in project \"$projectTitle\"";
  $notifData = [
    'type' => 'note_added',
    'task_id' => $task_id,
    'project_id' => $taskInfo['project_id'] ?? null,
    'note_id' => $id,
    'sender_type' => $senderType,
    'sender_id' => $senderId
  ];

  // Send notifications to admins AND the client
  $allUserIds = $adminIds;
  if ($clientUserId && !in_array($clientUserId, $allUserIds)) {
    $allUserIds[] = $clientUserId;
  }

  // Send notifications
  if (!empty($allUserIds)) {
    $notifResult = notify_users($conn, $allUserIds, $notifTitle, $notifBody, $notifData, 'note_added');
  }

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
