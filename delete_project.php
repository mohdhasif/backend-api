<?php
// delete_project.php
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
  $project_id = (int)($data['project_id'] ?? 0);

  if ($project_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Project ID is required']);
    exit;
  }

  // ✅ Check if project exists and user has permission
  $check_sql = "SELECT p.*, c.user_id as client_user_id, c.company_name as client_company
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

  // ✅ Permission check: Only admin or project owner can delete
  $can_delete = false;
  if ($user['role'] === 'admin') {
    $can_delete = true;
  } elseif ($user['role'] === 'client' && $existing_project['client_user_id'] == $user['id']) {
    $can_delete = true;
  }
  
  if (!$can_delete) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized to delete this project']);
    exit;
  }

  // ✅ Check if project has tasks (optional: prevent deletion if has active tasks)
  $tasks_sql = "SELECT COUNT(*) as task_count FROM tasks WHERE project_id = ?";
  $tasks_stmt = $conn->prepare($tasks_sql);
  $tasks_stmt->bind_param("i", $project_id);
  $tasks_stmt->execute();
  $tasks_result = $tasks_stmt->get_result();
  $task_count = $tasks_result->fetch_assoc()['task_count'];
  $tasks_stmt->close();

  if ($task_count > 0) {
    http_response_code(400);
    echo json_encode([
      'success' => false, 
      'error' => "Cannot delete project: Project has $task_count active task(s). Please delete all tasks first."
    ]);
    exit;
  }

  // ✅ Store project info for notifications before deletion
  $project_title = $existing_project['title'];
  $client_company = $existing_project['client_company'];

  // ✅ Delete project
  $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
  $stmt->bind_param("i", $project_id);
  $success = $stmt->execute();
  
  if (!$success || $stmt->affected_rows === 0) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete project']);
    exit;
  }

  $stmt->close();

  // ✅ Prepare notification data
  $notifTitle = "Project deleted";
  $notifBody = "Project \"$project_title\" has been deleted (ID #$project_id).";
  
  // ✅ Get admin IDs for notifications
  $adminIds = [];
  $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $adminIds[] = (int)$row['id'];
  }
  $stmt->close();

  // ✅ Get client ID (the project owner) for notification
  $clientUserId = $existing_project['client_user_id'];

  // ✅ Prepare notification payload
  $notification_data = [
    'type' => 'project_deleted',
    'project_id' => $project_id,
    'project_title' => $project_title,
    'client_company' => $client_company
  ];

  // ✅ Send notifications to admins AND the client
  $allUserIds = $adminIds;
  if ($clientUserId && !in_array($clientUserId, $allUserIds)) {
    $allUserIds[] = $clientUserId;
  }

  $notification_result = notify_users($conn, $allUserIds, $notifTitle, $notifBody, $notification_data, 'project_deleted');

  // ✅ Success response
  echo json_encode([
    'success' => true,
    'message' => 'Project deleted successfully',
    'deleted_project_id' => $project_id,
    'project_title' => $project_title,
    'client_company' => $client_company,
    'notification_sent' => $notification_result
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to delete project: ' . $e->getMessage()]);
}

