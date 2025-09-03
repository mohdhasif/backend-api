<?php
// update_project.php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/push_helper.php';

try {
  // ✅ Auth
  $user = require_auth($conn);

  // ✅ Read JSON body
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);

  if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
  }

  // ✅ Extract & validate
  $project_id  = (int)($data['project_id'] ?? 0);
  $title       = trim($data['title'] ?? '');
  $client_id   = (int)($data['client_id'] ?? 0);
  $description = isset($data['description']) && $data['description'] !== '' ? $data['description'] : null;
  $priority    = $data['priority'] ?? null; // 'low' | 'medium' | 'high' | null
  $start_at    = $data['start_at'] ?? null; // 'YYYY-MM-DD HH:MM:SS' | null
  $end_at      = $data['end_at'] ?? null;   // 'YYYY-MM-DD HH:MM:SS' | null
  $status      = $data['status'] ?? 'pending';
  $progress    = (int)($data['progress'] ?? 0);
  $due_date    = $data['due_date'] ?? null; // 'YYYY-MM-DD' | null

  // ✅ Validation
  if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Project ID is required']);
    exit;
  }

  if ($title === '' || $client_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Validation failed: title & client_id required']);
    exit;
  }

  // Optional: basic datetime sanity (server-side)
  if ($start_at && $end_at && strtotime($end_at) < strtotime($start_at)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'End date/time must be after Start date/time']);
    exit;
  }

  // ✅ Check if project exists and user has permission
  $check_sql = "SELECT p.*, c.user_id as client_user_id 
                FROM projects p 
                JOIN clients c ON p.client_id = c.id 
                WHERE p.id = ?";
  
  $check_stmt = $conn->prepare($check_sql);
  $check_stmt->bind_param("i", $project_id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  
  if ($check_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
  }
  
  $existing_project = $check_result->fetch_assoc();
  $check_stmt->close();

  // ✅ Permission check: Only admin or project owner can update
  $can_update = false;
  if ($user['role'] === 'admin') {
    $can_update = true;
  } elseif ($user['role'] === 'client' && $existing_project['client_user_id'] == $user['id']) {
    $can_update = true;
  }
  
  if (!$can_update) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized to update this project']);
    exit;
  }

  // ✅ Get old values for comparison
  $old_title = $existing_project['title'];
  $old_status = $existing_project['status'];
  $old_progress = $existing_project['progress'];

  // ✅ Update project
  $stmt = $conn->prepare("
    UPDATE projects 
    SET 
      client_id = ?,
      title = ?,
      description = ?,
      priority = ?,
      start_at = ?,
      end_at = ?,
      status = ?,
      progress = ?,
      due_date = ?,
      updated_at = NOW()
    WHERE id = ?
  ");

  // Convert empty string to null safely
  $start_at_val = $start_at ? $start_at : null;
  $end_at_val   = $end_at ? $end_at : null;
  $due_date_val = $due_date ? $due_date : null;

  $stmt->bind_param(
    'issssssisi',
    $client_id,
    $title,
    $description,
    $priority,
    $start_at_val,
    $end_at_val,
    $status,
    $progress,
    $due_date_val,
    $project_id
  );

  $stmt->execute();
  
  if ($stmt->affected_rows === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No changes made to project']);
    exit;
  }

  $stmt->close();

  // ✅ Prepare notification data
  $notifTitle = "Project updated";
  $notifBody = "Project \"$title\" has been updated (ID #$project_id).";
  
  // Check what changed for more specific notification
  $changes = [];
  if ($old_title !== $title) $changes[] = "title";
  if ($old_status !== $status) $changes[] = "status";
  if ($old_progress !== $progress) $changes[] = "progress";
  
  if (!empty($changes)) {
    $notifBody = "Project \"$title\" updated: " . implode(', ', $changes) . " changed (ID #$project_id).";
  }

  // ✅ Get admin IDs for notifications
  $adminIds = [];
  $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $adminIds[] = (int)$row['id'];
  }
  $stmt->close();

  // ✅ Get client ID (the project owner)
  $clientUserId = null;
  $stmt = $conn->prepare("SELECT user_id FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    $clientUserId = (int)$row['user_id'];
  }
  $stmt->close();

  // ✅ Prepare notification payload
  $notification_data = [
    'type' => 'project_updated',
    'project_id' => $project_id,
    'changes' => $changes
  ];

  // ✅ Send notifications to admins AND the client
  $allUserIds = $adminIds;
  if ($clientUserId && !in_array($clientUserId, $allUserIds)) {
    $allUserIds[] = $clientUserId;
  }

  $notification_result = notify_users($conn, $allUserIds, $notifTitle, $notifBody, $notification_data, 'project_updated');

  // ✅ Success response
  echo json_encode([
    'success' => true,
    'message' => 'Project updated successfully',
    'project_id' => $project_id,
    'changes' => $changes,
    'notification_sent' => $notification_result
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to update project: ' . $e->getMessage()]);
}
