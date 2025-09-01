<?php
require_once __DIR__ . '/db.php';

try {
    $user = require_auth($conn);
    
    // Get project_id from request
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    
    if ($project_id <= 0) {
        throw new Exception("Invalid project_id parameter");
    }
    
    // Query to get unique freelancers for the project
    // This joins tasks -> task_assignees -> freelancers to get all freelancers involved
    $stmt = $conn->prepare("
        SELECT DISTINCT 
            f.id as freelancer_id,
            f.user_id,
            f.avatar_url,
            f.skillset,
            f.status as freelancer_status,
            u.name as freelancer_name,
            u.email as freelancer_email,
            u.avatar_url as freelancer_avatar_url
        FROM tasks t
        INNER JOIN task_assignees ta ON t.id = ta.task_id
        INNER JOIN freelancers f ON ta.freelancer_id = f.id
        INNER JOIN users u ON f.user_id = u.id
        WHERE t.project_id = ?
        AND f.status = 'approved'
        ORDER BY u.name ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $project_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database get_result failed: " . $stmt->error);
    }
    
    $freelancers = $result->fetch_all(MYSQLI_ASSOC);
    
    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'project_id' => $project_id,
        'freelancers' => $freelancers,
        'count' => count($freelancers)
    ]);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("get_project_freelancers.php error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch project freelancers',
        'details' => $e->getMessage()
    ]);
}

// publish