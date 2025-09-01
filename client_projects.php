<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

try {
    $user = auth_user($conn);              // <-- pusat
    $auth_user_id = (int)$user['id'];

    // --- Ambil projek berdasarkan client_id ---
    $stmt = $conn->prepare("
        SELECT projects.id, projects.title, projects.description, projects.status, projects.progress, projects.created_at
        FROM projects
        JOIN clients ON projects.client_id = clients.id
        WHERE clients.user_id = ?
        ORDER BY created_at DESC
    ");

    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $auth_user_id);

    if (!$stmt->execute()) {
        throw new Exception("Database execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Database get_result failed: " . $stmt->error);
    }

    $projects = $result->fetch_all(MYSQLI_ASSOC);

    // --- Return JSON ---
    echo json_encode($projects);
} catch (Exception $e) {
    // Log error for debugging
    error_log("client_projects.php error: " . $e->getMessage());

    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to fetch projects',
        'details' => $e->getMessage()
    ]);
}

// publish