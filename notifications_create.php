<?php
require_once 'db.php';

try {
    $admin = require_auth($conn); // up to you: enforce role=admin

    $payload = json_decode(file_get_contents('php://input'), true) ?? [];
    $user_id = (int)($payload['user_id'] ?? 0);
    $title = trim($payload['title'] ?? '');
    $body = trim($payload['body'] ?? '');
    $type = isset($payload['type']) ? substr($payload['type'], 0, 50) : null;
    $data = $payload['data'] ?? null;
    $push = !empty($payload['push']); // if true, we’ll try to push too

    if ($user_id <= 0 || $title === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'user_id & title diperlukan']);
        exit;
    }

    $data_json = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, data_json) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issss", $user_id, $title, $body, $type, $data_json);
    $stmt->execute();
    $id = $conn->insert_id;

    $result = ['success' => true, 'id' => $id];

    // optional push
    if ($push) {
        // Delegate to OneSignal/FCM helper endpoint (below) or call directly here.
        $result['push'] = 'queued';
    }

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Gagal cipta notifikasi']);
}

// publish