// claim_install_subscriptions.php
<?php
require_once __DIR__ . '/db.php'; // ada $conn dan require_auth()

try {
    $user = require_auth($conn); // ['id' => ...]
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: [];
    $install_id = $data['install_id'] ?? '';

    if (!$install_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing install_id']);
        exit;
    }

    // Pindahkan semua rekod anonymous untuk install_id ini ke user_id sebenar
    $stmt = $conn->prepare("
    UPDATE user_push_subscriptions
       SET user_id = ?
     WHERE install_id = ? AND (user_id IS NULL OR user_id <> ?)
  ");
    $stmt->bind_param("isi", $user['id'], $install_id, $user['id']);
    $stmt->execute();

    echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
