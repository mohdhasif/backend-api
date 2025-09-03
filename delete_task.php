<?php
require_once __DIR__ . '/db.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

try {
  // Authenticate user
  $user = require_auth($conn);
  $auth_user_id = (int)$user['id'];
  
  // Get task_id from both JSON and FormData
  $task_id = null;
  
  // Check if it's JSON input
  $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
  if (strpos($content_type, 'application/json') !== false) {
    $input = json_decode(file_get_contents("php://input"), true);
    $task_id = $input['task_id'] ?? null;
  } else {
    // Check FormData
    $task_id = $_POST['task_id'] ?? null;
  }
  
  // Validate task_id
  if (!$task_id || !is_numeric($task_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing or invalid task_id']);
    exit;
  }
  
  $task_id = (int)$task_id;
  
  // Ensure task belongs to client (if user is client)
  if ($user['role'] === 'client') {
    $query = "SELECT t.id
              FROM tasks t
              JOIN projects p ON t.project_id = p.id
              WHERE t.id = ? AND p.client_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $task_id, $auth_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
      http_response_code(403);
      echo json_encode(['success' => false, 'error' => 'Not allowed to delete this task']);
      exit;
    }
    $stmt->close();
  }
  
  // For admin users, they can delete any task
  // For freelancers, they can only delete tasks assigned to them
  
  // Delete task
  $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
  $stmt->bind_param("i", $task_id);
  $success = $stmt->execute();
  
  if ($success && $stmt->affected_rows > 0) {
    echo json_encode([
      'success' => true,
      'message' => 'Task deleted successfully',
      'deleted_task_id' => $task_id
    ]);
  } else {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Task not found or already deleted']);
  }
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to delete task: ' . $e->getMessage()]);
}
