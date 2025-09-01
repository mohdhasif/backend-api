<?php
require_once 'db.php';
try {
  $user = require_auth($conn);
  $uid = (int)$user['id'];

  $stmt = $conn->prepare("UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL");
  $stmt->bind_param("i", $uid);
  $stmt->execute();

  echo json_encode(['success' => true, 'updated' => $stmt->affected_rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal mark all read']);
}

// publish