<?php
require_once __DIR__ . '/db.php'; // pastikan include db.php

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $task_id = intval($input['task_id'] ?? 0);
    $freelancer_id = intval($input['freelancer_id'] ?? 0);
    $role = $input['role'] ?? 'other';

    if ($task_id <= 0 || $freelancer_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing fields']);
        exit;
    }

    $conn->set_charset('utf8mb4');

    // ensure exist
    $chk = $conn->prepare("SELECT id FROM tasks WHERE id=?");
    $chk->bind_param("i", $task_id);
    $chk->execute();
    if ($chk->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Task not found']);
        exit;
    }

    $chk2 = $conn->prepare("SELECT id FROM freelancers WHERE id=?");
    $chk2->bind_param("i", $freelancer_id);
    $chk2->execute();
    if ($chk2->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Freelancer not found']);
        exit;
    }

    // insert or update role on duplicate
    $sql = "INSERT INTO task_assignees (task_id, freelancer_id, role)
          VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE role = VALUES(role)";
    $st = $conn->prepare($sql);
    $st->bind_param("iis", $task_id, $freelancer_id, $role);
    $st->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}

// publish