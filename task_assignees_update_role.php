<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    require_once __DIR__ . '/db.php';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $auth);

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $task_id = intval($input['task_id'] ?? 0);
    $freelancer_id = intval($input['freelancer_id'] ?? 0);
    $role = $input['role'] ?? 'other';

    if (!$token || $task_id <= 0 || $freelancer_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit;
    }

    $conn->set_charset('utf8mb4');

    $u = $conn->prepare("SELECT id FROM users WHERE token=?");
    $u->bind_param("s", $token);
    $u->execute();
    if (!$u->get_result()->fetch_assoc()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $st = $conn->prepare("UPDATE task_assignees SET role=? WHERE task_id=? AND freelancer_id=?");
    $st->bind_param("sii", $role, $task_id, $freelancer_id);
    $st->execute();

    echo json_encode(['success' => true, 'affected' => $conn->affected_rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
