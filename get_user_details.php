<?php
require_once __DIR__ . '/db.php';

try {
    // Try to get authenticated user
    $user = null;
    try {
        $user = require_auth($conn);
    } catch (Exception $auth_error) {
        // If authentication fails, return proper error
        http_response_code(401);
        echo json_encode([
            'error' => true,
            'message' => 'Authentication failed',
            'details' => $auth_error->getMessage()
        ]);
        exit;
    }
    
    // Validate that user data is properly returned
    if (!$user || !isset($user['id'])) {
        http_response_code(401);
        echo json_encode([
            'error' => true,
            'message' => 'Invalid user data returned from authentication'
        ]);
        exit;
    }
    
    // Get user_id from request (optional - if not provided, return current user's details)
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)$user['id'];
    
    // If no specific user_id provided, use the authenticated user's ID
    if ($user_id <= 0) {
        $user_id = (int)$user['id'];
    }
    
    // Query to get user details
    $sql = "
        SELECT 
            id,
            name,
            email,
            phone,
            role,
            avatar_url
        FROM users 
        WHERE id = ?
    ";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database get_result failed: " . $stmt->error);
    }
    
    $user_data = $result->fetch_assoc();
    
    if (!$user_data) {
        throw new Exception("User not found");
    }
    
    // Validate user_data structure
    if (!isset($user_data['id']) || !isset($user_data['role'])) {
        throw new Exception("Invalid user data structure retrieved from database");
    }
    
    // Get additional role-specific information based on user role
    $additional_info = [];
    
    switch ($user_data['role']) {
        case 'client':
            // Get client information
            $client_sql = "
                SELECT 
                    c.id as client_id,
                    c.company_name,
                    c.phone as client_phone,
                    c.status as client_status,
                    c.client_type
                FROM clients c
                WHERE c.user_id = ?
            ";
            $client_stmt = $conn->prepare($client_sql);
            $client_stmt->bind_param("i", $user_id);
            $client_stmt->execute();
            $client_result = $client_stmt->get_result();
            $client_data = $client_result->fetch_assoc();
            
            if ($client_data) {
                $additional_info['client'] = [
                    'id' => (int)$client_data['client_id'],
                    'company_name' => $client_data['company_name'],
                    'phone' => $client_data['client_phone'],
                    'status' => $client_data['client_status'],
                    'type' => $client_data['client_type']
                ];
            }
            $client_stmt->close();
            break;
            
        case 'freelancer':
            // Get freelancer information
            $freelancer_sql = "
                SELECT 
                    f.id as freelancer_id,
                    f.skillset,
                    f.availability,
                    f.status as freelancer_status,
                    f.approved_at
                FROM freelancers f
                WHERE f.user_id = ?
            ";
            $freelancer_stmt = $conn->prepare($freelancer_sql);
            $freelancer_stmt->bind_param("i", $user_id);
            $freelancer_stmt->execute();
            $freelancer_result = $freelancer_stmt->get_result();
            $freelancer_data = $freelancer_result->fetch_assoc();
            
            if ($freelancer_data) {
                $additional_info['freelancer'] = [
                    'id' => (int)$freelancer_data['freelancer_id'],
                    'skillset' => $freelancer_data['skillset'],
                    'availability' => (bool)$freelancer_data['availability'],
                    'status' => $freelancer_data['freelancer_status'],
                    'approved_at' => $freelancer_data['approved_at']
                ];
            }
            $freelancer_stmt->close();
            break;
            
        case 'admin':
            // Admin doesn't have additional tables, just basic info
            $additional_info['admin'] = [
                'is_admin' => true,
                'permissions' => 'full_access'
            ];
            break;
    }
    
    // Determine status based on role
    $client_status = 'active'; // default for admin
    $profile_id = null;
    
    if ($user_data['role'] === 'client' && isset($additional_info['client'])) {
        $client_status = $additional_info['client']['status'];
        $profile_id = $additional_info['client']['id'];
    } elseif ($user_data['role'] === 'freelancer' && isset($additional_info['freelancer'])) {
        $client_status = $additional_info['freelancer']['status'];
        $profile_id = $additional_info['freelancer']['id'];
    }
    
    // Prepare response data (exclude sensitive information)
    $response_data = [
        'id' => (int)$user_data['id'],
        'name' => $user_data['name'] ?? '',
        'email' => $user_data['email'] ?? '',
        'phone' => $user_data['phone'] ?? '',
        'role' => $user_data['role'] ?? '',
        'avatar_url' => $user_data['avatar_url'] ?? '',
        'client_status' => $client_status,
        'profile_id' => $profile_id,
        'additional_info' => $additional_info
    ];
    
    // Return JSON response
    echo json_encode([
        'status' => 'success',
        'user' => $response_data
    ], JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("get_user_details.php error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'details' => $e->getMessage()
    ]);
}

// publish