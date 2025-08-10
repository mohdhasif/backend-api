<?php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

try {
    // --- Dapatkan token & project_id ---
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);

    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

    if (!$token || $project_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token or project_id']);
        exit;
    }

    // --- Connect ke DB ---
    $conn = new mysqli("localhost", "root", "", "finiteapp");
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    // --- Cari user berdasarkan token ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $user = $result->fetch_assoc();
    $user_id = (int)$user['id'];

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