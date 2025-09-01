<?php
require_once 'db.php';
try {
  $user = require_auth($conn);
  $uid = (int)$user['id'];

  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND read_at IS NULL");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $unread = (int)$stmt->get_result()->fetch_assoc()['c'];

  echo json_encode(['success' => true, 'unread' => $unread]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal mendapatkan badge']);
}

// publish