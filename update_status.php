<?php
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php'; // $conn = new mysqli(...)

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
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

    $user = auth_user($conn);              // <-- pusat
    $auth_user_id = (int)$user['id'];

    $userId = $auth_user_id;

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

// publish