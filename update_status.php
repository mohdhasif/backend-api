<?php
// api/tasks/update_status.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php'; // $conn = new mysqli(...)

// --- Read headers & auth ---
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? '';
$token = trim(str_replace('Bearer', '', $authHeader));
$token = preg_replace('/^\\s+|\\s+$/', '', $token);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token']);
        exit;
    }
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }

    // Parse JSON body
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    $status = strtolower(trim($payload['status'] ?? ''));

    if (!in_array($status, ['pending', 'in_progress', 'completed'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        exit;
    }

    // Auth by token
    $stmt = $conn->prepare("SELECT id FROM users WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $userRes = $stmt->get_result();
    if ($userRes->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $user = $userRes->fetch_assoc();
    $userId = intval($user['id']);

    // Update task status
    if ($status === 'completed') {
        $stmt = $conn->prepare("UPDATE tasks SET status='completed', completed_at=NOW(), updated_at=NOW() WHERE id=?");
        $stmt->bind_param("i", $id);
    } else {
        // if you want to allow reverting
        $stmt = $conn->prepare("UPDATE tasks SET status=?, updated_at=NOW(), completed_at=NULL WHERE id=?");
        $stmt->bind_param("si", $status, $id);
    }

    if (!$stmt->execute()) {
        throw new Exception('DB update failed');
    }

    // Return updated row
    $stmt = $conn->prepare("SELECT id, title, status FROM tasks WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    echo json_encode(['success' => true, 'data' => $row]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
