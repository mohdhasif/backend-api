<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$headers = apache_request_headers();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

$project_id = $_GET['project_id'] ?? null;

if (!$token || !$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or project_id']);
    exit;
}

$conn = new mysqli("localhost", "root", "", "finiteapp");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];

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
