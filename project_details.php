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
SELECT *
FROM projects
WHERE id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $project_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Project not found or not yours']);
    exit;
}

$project = $result->fetch_assoc();
echo json_encode($project);

// publish