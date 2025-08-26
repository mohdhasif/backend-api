<?php
require_once __DIR__ . '/db.php';

try {
    $user = require_auth($conn);
    
    // Get freelancer_id from request
    $freelancer_id = isset($_GET['freelancer_id']) ? (int)$_GET['freelancer_id'] : 0;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : null;
    
    if ($freelancer_id <= 0) {
        throw new Exception("Invalid freelancer_id parameter");
    }
    
    // Validate status filter if provided
    $valid_statuses = ['pending', 'in_progress', 'completed'];
    if ($status_filter && !in_array($status_filter, $valid_statuses)) {
        throw new Exception("Invalid status parameter. Must be one of: " . implode(', ', $valid_statuses));
    }
    
    // Query to get tasks for the specific freelancer
    $sql = "
        SELECT 
            t.id as task_id,
            t.title as task_title,
            t.description as task_description,
            t.status as task_status,
            t.due_date as task_due_date,
            t.start_at as task_start_at,
            t.end_at as task_end_at,
            t.created_at as task_created_at,
            t.updated_at as task_updated_at,
            t.completed_at as task_completed_at,
            
            p.id as project_id,
            p.title as project_title,
            p.description as project_description,
            p.status as project_status,
            p.priority as project_priority,
            p.due_date as project_due_date,
            
            ta.role as assigned_role,
            ta.assigned_at as task_assigned_at,
            
            f.id as freelancer_id,
            f.skillset as freelancer_skillset,
            f.status as freelancer_status,
            f.avatar_url as freelancer_avatar,
            
            u.name as freelancer_name,
            u.email as freelancer_email,
            
            c.id as client_id,
            c.client_type,
            c.company_name,
            cu.name as client_name,
            cu.email as client_email
            
        FROM task_assignees ta
        INNER JOIN tasks t ON ta.task_id = t.id
        INNER JOIN projects p ON t.project_id = p.id
        INNER JOIN freelancers f ON ta.freelancer_id = f.id
        INNER JOIN users u ON f.user_id = u.id
        LEFT JOIN clients c ON p.client_id = c.id
        LEFT JOIN users cu ON c.user_id = cu.id
        WHERE ta.freelancer_id = ?
    ";
    
    $params = [$freelancer_id];
    $types = "i";
    
    // Add status filter if provided
    if ($status_filter) {
        $sql .= " AND t.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    $sql .= " ORDER BY t.due_date ASC, t.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database get_result failed: " . $stmt->error);
    }
    
    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate task progress based on status
        $progress = 0;
        switch ($row['task_status']) {
            case 'completed':
                $progress = 100;
                break;
            case 'in_progress':
                $progress = 50;
                break;
            case 'pending':
                $progress = 0;
                break;
        }
        
        $tasks[] = [
            'task' => [
                'id' => (int)$row['task_id'],
                'title' => $row['task_title'],
                'description' => $row['task_description'],
                'status' => $row['task_status'],
                'progress' => $progress,
                'due_date' => $row['task_due_date'],
                'start_at' => $row['task_start_at'],
                'end_at' => $row['task_end_at'],
                'created_at' => $row['task_created_at'],
                'updated_at' => $row['task_updated_at'],
                'completed_at' => $row['task_completed_at']
            ],
            'project' => [
                'id' => (int)$row['project_id'],
                'title' => $row['project_title'],
                'description' => $row['project_description'],
                'status' => $row['project_status'],
                'priority' => $row['project_priority'],
                'due_date' => $row['project_due_date']
            ],
            'assignment' => [
                'role' => $row['assigned_role'],
                'assigned_at' => $row['task_assigned_at']
            ],
            'freelancer' => [
                'id' => (int)$row['freelancer_id'],
                'name' => $row['freelancer_name'],
                'email' => $row['freelancer_email'],
                'skillset' => $row['freelancer_skillset'],
                'status' => $row['freelancer_status'],
                'avatar_url' => $row['freelancer_avatar']
            ],
            'client' => [
                'id' => (int)$row['client_id'],
                'type' => $row['client_type'],
                'name' => $row['client_name'],
                'email' => $row['client_email'],
                'company_name' => $row['company_name']
            ]
        ];
    }
    
    // Get summary statistics
    $total_tasks = count($tasks);
    $completed_tasks = count(array_filter($tasks, fn($t) => $t['task']['status'] === 'completed'));
    $pending_tasks = count(array_filter($tasks, fn($t) => $t['task']['status'] === 'pending'));
    $in_progress_tasks = count(array_filter($tasks, fn($t) => $t['task']['status'] === 'in_progress'));
    
    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'freelancer_id' => $freelancer_id,
        'summary' => [
            'total_tasks' => $total_tasks,
            'completed_tasks' => $completed_tasks,
            'pending_tasks' => $pending_tasks,
            'in_progress_tasks' => $in_progress_tasks,
            'completion_rate' => $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100, 1) : 0
        ],
        'tasks' => $tasks
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("get_freelancer_tasks.php error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch freelancer tasks',
        'details' => $e->getMessage()
    ]);
}
