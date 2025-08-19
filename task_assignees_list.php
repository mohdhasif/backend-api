<?php

try {
    require_once __DIR__ . '/db.php';

    $task_id = intval($_GET['task_id'] ?? 0);
    if (!$token || $task_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token or task_id']);
        exit;
    }

    $conn->set_charset('utf8mb4');

    $sql = "SELECT 
                ta.freelancer_id AS id, 
                ta.role, ta.assigned_at,
                u.name, 
                u.email, 
                f.avatar_url, 
                f.status
          FROM task_assignees ta
          JOIN freelancers f ON f.id = ta.freelancer_id
          JOIN users u ON u.id = f.user_id
          WHERE ta.task_id = ?
          ORDER BY u.name ASC";
    $st = $conn->prepare($sql);
    $st->bind_param("i", $task_id);
    $st->execute();
    $res = $st->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
