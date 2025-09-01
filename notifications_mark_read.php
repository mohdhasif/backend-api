<?php
require_once 'db.php';
try {
    $user = require_auth($conn);
    $uid = (int)$user['id'];
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing id']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL");
    $stmt->bind_param("ii", $id, $uid);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal update notifikasi']);
}

// publish