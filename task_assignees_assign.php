<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php'; // ensure include db.php
require_once __DIR__ . '/push_helper.php'; // for push notification

// Log file setup
$logFile = __DIR__ . '/task_assignees_assign.log';

function logError($message, $data = null)
{
    global $logFile;
    $logContent = date('Y-m-d H:i:s') . " - ERROR: $message";
    if ($data) {
        $logContent .= "\nData: " . print_r($data, true);
    }
    $logContent .= "\n\n";
    file_put_contents($logFile, $logContent, FILE_APPEND);
}

function logInfo($message, $data = null)
{
    global $logFile;
    $logContent = date('Y-m-d H:i:s') . " - INFO: $message";
    if ($data) {
        $logContent .= "\nData: " . print_r($data, true);
    }
    $logContent .= "\n\n";
    file_put_contents($logFile, $logContent, FILE_APPEND);
}

try {
    // Dapatkan koneksi database dari db.php
    $conn = get_db_connection();
    logInfo("Database connection established");

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    logInfo("Received task assignment request", $input);
    
    $task_id = intval($input['task_id'] ?? 0);
    $freelancer_id = intval($input['freelancer_id'] ?? 0);
    $role = $input['role'] ?? 'other';

    if ($task_id <= 0 || $freelancer_id <= 0) {
        logError("Missing required fields", ['task_id' => $task_id, 'freelancer_id' => $freelancer_id]);
        json_error(400, 'Missing fields');
    }
    
    logInfo("Processing task assignment", [
        'task_id' => $task_id,
        'freelancer_id' => $freelancer_id,
        'role' => $role
    ]);

    $conn->set_charset('utf8mb4');

    // ensure exist
    $chk = $conn->prepare("SELECT id FROM tasks WHERE id=?");
    $chk->bind_param("i", $task_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        logError("Task not found", ['task_id' => $task_id]);
        json_error(404, 'Task not found');
    }

    $chk2 = $conn->prepare("SELECT id FROM freelancers WHERE id=?");
    $chk2->bind_param("i", $freelancer_id);
    $chk2->execute();
    if ($chk2->get_result()->num_rows === 0) {
        logError("Freelancer not found", ['freelancer_id' => $freelancer_id]);
        json_error(404, 'Freelancer not found');
    }
    
    logInfo("Task and freelancer validation passed", [
        'task_id' => $task_id,
        'freelancer_id' => $freelancer_id
    ]);

    // insert or update role on duplicate
    $sql = "INSERT INTO task_assignees (task_id, freelancer_id, role)
          VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE role = VALUES(role)";
    $st = $conn->prepare($sql);
    $st->bind_param("iis", $task_id, $freelancer_id, $role);
    if (!$st->execute()) {
        logError("Failed to assign freelancer to task", [
            'task_id' => $task_id,
            'freelancer_id' => $freelancer_id,
            'role' => $role,
            'error' => $st->error
        ]);
        json_error(500, 'Failed to assign freelancer to task');
    }
    
    logInfo("Freelancer assigned to task successfully", [
        'task_id' => $task_id,
        'freelancer_id' => $freelancer_id,
        'role' => $role
    ]);

    // Get task and freelancer details for notifications
    $taskDetails = null;
    $freelancerDetails = null;
    
    try {
        // Get task details
        $taskStmt = $conn->prepare("
            SELECT t.id, t.title, t.description, p.title as project_title, p.id as project_id
            FROM tasks t
            LEFT JOIN projects p ON t.project_id = p.id
            WHERE t.id = ?
        ");
        $taskStmt->bind_param("i", $task_id);
        $taskStmt->execute();
        $taskResult = $taskStmt->get_result();
        $taskDetails = $taskResult->fetch_assoc();
        $taskStmt->close();
        
        // Get freelancer details
        $freelancerStmt = $conn->prepare("
            SELECT f.id, f.skillset, u.name, u.email, u.id as user_id
            FROM freelancers f
            LEFT JOIN users u ON f.user_id = u.id
            WHERE f.id = ?
        ");
        $freelancerStmt->bind_param("i", $freelancer_id);
        $freelancerStmt->execute();
        $freelancerResult = $freelancerStmt->get_result();
        $freelancerDetails = $freelancerResult->fetch_assoc();
        $freelancerStmt->close();
        
        logInfo("Retrieved task and freelancer details", [
            'task_details' => $taskDetails,
            'freelancer_details' => $freelancerDetails
        ]);
        
    } catch (Exception $e) {
        logError("Failed to get task/freelancer details", [
            'error' => $e->getMessage(),
            'task_id' => $task_id,
            'freelancer_id' => $freelancer_id
        ]);
    }

    // Push notification ke admin
    try {
        logInfo("Preparing push notification to admins");
        
        // Get admin IDs
        $adminIds = [];
        $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $adminIds[] = (int)$row['id'];
        }
        $stmt->close();
        
        if (!empty($adminIds)) {
            $taskTitle = $taskDetails['title'] ?? "Task #$task_id";
            $projectTitle = $taskDetails['project_title'] ?? "Project";
            $freelancerName = $freelancerDetails['name'] ?? "Freelancer #$freelancer_id";
            
            $notifTitle = "Freelancer Assigned to Task";
            $notifBody = "$freelancerName has been assigned to task: $taskTitle";
            $notifData = [
                'type' => 'task_assignment',
                'task_id' => $task_id,
                'freelancer_id' => $freelancer_id,
                'project_id' => $taskDetails['project_id'] ?? null,
                'task_title' => $taskTitle,
                'project_title' => $projectTitle,
                'freelancer_name' => $freelancerName,
                'role' => $role
            ];
            
            $notifResult = notify_users($conn, $adminIds, $notifTitle, $notifBody, $notifData, 'task_assignment');
            
            // Check notification result
            if ($notifResult && isset($notifResult['success']) && $notifResult['success']) {
                $sentCount = $notifResult['sent'] ?? 0;
                $failedCount = $notifResult['failed'] ?? 0;
                
                if ($sentCount > 0 && $failedCount == 0) {
                    logInfo("✅ Push notification SUCCESS - All admins notified", [
                        'task_id' => $task_id,
                        'freelancer_id' => $freelancer_id,
                        'admin_count' => count($adminIds),
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                } else if ($sentCount > 0 && $failedCount > 0) {
                    logInfo("⚠️ Push notification PARTIAL SUCCESS - Some admins notified", [
                        'task_id' => $task_id,
                        'freelancer_id' => $freelancer_id,
                        'admin_count' => count($adminIds),
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                } else {
                    logError("❌ Push notification FAILED - No admins notified", [
                        'task_id' => $task_id,
                        'freelancer_id' => $freelancer_id,
                        'admin_count' => count($adminIds),
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                }
            } else {
                logError("❌ Push notification FAILED - notify_users returned error", [
                    'task_id' => $task_id,
                    'freelancer_id' => $freelancer_id,
                    'admin_count' => count($adminIds),
                    'notification_result' => $notifResult
                ]);
            }
        } else {
            logInfo("No admin users found for notification", ['task_id' => $task_id, 'freelancer_id' => $freelancer_id]);
        }
        
    } catch (Exception $notifError) {
        logError("Push notification error", [
            'task_id' => $task_id,
            'freelancer_id' => $freelancer_id,
            'error' => $notifError->getMessage()
        ]);
    }

    // Push notification ke freelancer
    if ($freelancerDetails && isset($freelancerDetails['user_id'])) {
        try {
            logInfo("Preparing push notification to freelancer");
            
            $taskTitle = $taskDetails['title'] ?? "Task #$task_id";
            $projectTitle = $taskDetails['project_title'] ?? "Project";
            
            $notifTitle = "New Task Assignment";
            $notifBody = "You have been assigned to task: $taskTitle";
            $notifData = [
                'type' => 'task_assigned_to_me',
                'task_id' => $task_id,
                'project_id' => $taskDetails['project_id'] ?? null,
                'task_title' => $taskTitle,
                'project_title' => $projectTitle,
                'role' => $role
            ];
            
            $freelancerUserId = (int)$freelancerDetails['user_id'];
            $notifResult = notify_users($conn, [$freelancerUserId], $notifTitle, $notifBody, $notifData, 'task_assigned_to_me');
            
            // Check notification result
            if ($notifResult && isset($notifResult['success']) && $notifResult['success']) {
                $sentCount = $notifResult['sent'] ?? 0;
                $failedCount = $notifResult['failed'] ?? 0;
                
                if ($sentCount > 0) {
                    logInfo("✅ Push notification SUCCESS - Freelancer notified", [
                        'task_id' => $task_id,
                        'freelancer_id' => $freelancer_id,
                        'freelancer_user_id' => $freelancerUserId,
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                } else {
                    logError("❌ Push notification FAILED - Freelancer not notified", [
                        'task_id' => $task_id,
                        'freelancer_id' => $freelancer_id,
                        'freelancer_user_id' => $freelancerUserId,
                        'sent_count' => $sentCount,
                        'failed_count' => $failedCount,
                        'notification_result' => $notifResult
                    ]);
                }
            } else {
                logError("❌ Push notification FAILED - notify_users returned error for freelancer", [
                    'task_id' => $task_id,
                    'freelancer_id' => $freelancer_id,
                    'freelancer_user_id' => $freelancerUserId,
                    'notification_result' => $notifResult
                ]);
            }
            
        } catch (Exception $notifError) {
            logError("Push notification error for freelancer", [
                'task_id' => $task_id,
                'freelancer_id' => $freelancer_id,
                'error' => $notifError->getMessage()
            ]);
        }
    } else {
        logInfo("Freelancer user ID not found, skipping freelancer notification", [
            'task_id' => $task_id,
            'freelancer_id' => $freelancer_id,
            'freelancer_details' => $freelancerDetails
        ]);
    }

    logInfo("Task assignment process completed successfully", [
        'task_id' => $task_id,
        'freelancer_id' => $freelancer_id,
        'role' => $role,
        'task_title' => $taskDetails['title'] ?? null,
        'freelancer_name' => $freelancerDetails['name'] ?? null
    ]);

    json_ok([
        'success' => true,
        'task_id' => $task_id,
        'freelancer_id' => $freelancer_id,
        'role' => $role,
        'task_title' => $taskDetails['title'] ?? null,
        'freelancer_name' => $freelancerDetails['name'] ?? null
    ]);
} catch (Throwable $e) {
    logError("Task assignment process failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'input_data' => $input ?? null
    ]);
    json_error(500, 'Server error: ' . $e->getMessage());
}

// publish