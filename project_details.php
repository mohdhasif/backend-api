<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

$user = auth_user($conn);              // <-- pusat
$auth_user_id = (int)$user['id'];

$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing project_id']);
    exit;
}

$user_id = $auth_user_id;

// Sah kan projek memang milik client ini
$query = "
SELECT id, title, description, status, progress, created_at, updated_at
FROM projects
WHERE id = ? AND client_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $project_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found or not yours']);
    exit;
}

$project = $result->fetch_assoc();
echo json_encode($project);
