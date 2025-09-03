<?php
require_once __DIR__ . '/db.php';

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

// Get and validate task_id
$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
if ($task_id <= 0) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing or invalid task_id']);
  exit;
}

try {
  // Authenticate user
  $user = require_auth($conn);
  
  // Get task details from tasks table only
  $sql = "SELECT 
            id,
            title,
            description,
            status,
            project_id,
            start_at,
            end_at
          FROM tasks
          WHERE id = ?";
  
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("i", $task_id);
  $stmt->execute();
  $result = $stmt->get_result();
  
  if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Task not found']);
    exit;
  }
  
  $task = $result->fetch_assoc();
  $stmt->close();
  
  // Format dates
  $task['due_date'] = $task['due_date'] ? date('Y-m-d', strtotime($task['due_date'])) : null;
  $task['created_at'] = date('Y-m-d H:i:s', strtotime($task['created_at']));
  $task['updated_at'] = $task['updated_at'] ? date('Y-m-d H:i:s', strtotime($task['updated_at'])) : null;
  
  // Calculate time remaining if due date exists
  if ($task['due_date']) {
    $due_timestamp = strtotime($task['due_date']);
    $current_timestamp = time();
    $time_remaining = $due_timestamp - $current_timestamp;
    
    if ($time_remaining > 0) {
      $days_remaining = ceil($time_remaining / (24 * 60 * 60));
      $task['time_remaining'] = $days_remaining . ' day' . ($days_remaining > 1 ? 's' : '') . ' remaining';
      $task['is_overdue'] = false;
    } else {
      $days_overdue = abs(ceil($time_remaining / (24 * 60 * 60)));
      $task['time_remaining'] = $days_overdue . ' day' . ($days_overdue > 1 ? 's' : '') . ' overdue';
      $task['is_overdue'] = true;
    }
  } else {
    $task['time_remaining'] = 'No due date set';
    $task['is_overdue'] = false;
  }
  
  // Add formatted labels
  $task['progress_status'] = getProgressStatus($task['progress_percentage']);
  $task['priority_level'] = getPriorityLevel($task['priority']);
  $task['status_label'] = getStatusLabel($task['status']);
  
  // Success response
  echo json_encode([
    'success' => true,
    'task' => $task
  ]);
  
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to fetch task details: ' . $e->getMessage()]);
}

/**
 * Get progress status description
 */
function getProgressStatus($percentage) {
  if ($percentage === null || $percentage === '') {
    return 'Not started';
  } elseif ($percentage == 0) {
    return 'Not started';
  } elseif ($percentage < 25) {
    return 'Just started';
  } elseif ($percentage < 50) {
    return 'In progress';
  } elseif ($percentage < 75) {
    return 'More than halfway';
  } elseif ($percentage < 100) {
    return 'Almost complete';
  } else {
    return 'Completed';
  }
}

/**
 * Get priority level description
 */
function getPriorityLevel($priority) {
  switch ($priority) {
    case 'low':
      return 'Low Priority';
    case 'medium':
      return 'Medium Priority';
    case 'high':
      return 'High Priority';
    case 'urgent':
      return 'Urgent';
    default:
      return 'Not set';
  }
}

/**
 * Get status label
 */
function getStatusLabel($status) {
  switch ($status) {
    case 'pending':
      return 'Pending';
    case 'in_progress':
      return 'In Progress';
    case 'complete':
      return 'Complete';
    default:
      return 'Unknown';
  }
}
