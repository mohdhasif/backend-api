<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

$user = auth_user($conn);              // <-- pusat
$auth_user_id = (int)$user['id'];

try {
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

    if (!$project_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing project_id']);
        exit;
    }

    $user_id = $auth_user_id;

    // --- Sahkan user adalah owner projek ini ---
    $stmt = $conn->prepare("SELECT 
        projects.id 
    FROM 
        projects 
    JOIN clients ON projects.client_id = clients.id
    WHERE projects.id = ? AND clients.user_id = ?");
    $stmt->bind_param("ii", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Forbidden - not your project']);
        exit;
    }

    // --- Fetch tasks ---
    $stmt = $conn->prepare("
        SELECT id, title, description, status, due_date
        FROM tasks
        WHERE project_id = ?
        ORDER BY due_date ASC
    ");
    
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $tasks
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
