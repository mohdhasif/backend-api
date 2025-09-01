<?php
// api/change_password.php
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

require_once __DIR__ . '/db.php'; // pastikan include db.php

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // --- Ambil input JSON ---
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $old = trim($data['old_password'] ?? '');
    $new = trim($data['new_password'] ?? '');
    $user = auth_user($conn);              // <-- pusat
    $auth_user_id = (int)$user['id'];
    
    if ($old === '' || $new === '') {
        throw new Exception('Missing old_password or new_password', 400);
    }

    if (strlen($new) < 8) {
        throw new Exception('New password must be at least 8 characters', 400);
    }

    if (!isset($conn) || !$conn) {
        throw new Exception('DB connection not available', 500);
    }
    $conn->set_charset('utf8mb4');

    // --- Verify old password (guna md5) ---
    if (md5($old) !== $user['password']) {
        throw new Exception('Old password is incorrect', 400);
    }

    if ($old === $new) {
        throw new Exception('New password must differ from old password', 400);
    }

    // --- Update password (guna md5) ---
    $newHash = md5($new);
    $stmt2 = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt2->bind_param("si", $newHash, $user['id']);
    if (!$stmt2->execute()) {
        throw new Exception('Failed to update password', 500);
    }

    echo json_encode(['success' => true, 'message' => 'Password updated']);
} catch (Exception $e) {
    $code = $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 400; // default bad request
    }
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// publish