// save_push_subscription.php
<?php
require_once __DIR__ . '/db.php'; // ada $conn, dan require_auth() tapi DI SINI kita tak pakai auth

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true) ?: [];

  $subscription_id = $data['subscription_id'] ?? '';
  $install_id = $data['install_id'] ?? null;
  $platform = $data['platform'] ?? 'unknown';
  $timezone = $data['timezone'] ?? 'Asia/Kuala_Lumpur';

  if (!$subscription_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing subscription_id']);
    exit;
  }

  $stmt = $conn->prepare("
    INSERT INTO user_push_subscriptions (user_id, install_id, subscription_id, platform, timezone, last_seen_at)
    VALUES (NULL, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
      install_id = VALUES(install_id),
      platform = VALUES(platform),
      timezone = VALUES(timezone),
      last_seen_at = NOW()
  ");
  $stmt->bind_param("ssss", $install_id, $subscription_id, $platform, $timezone);
  $stmt->execute();

  echo json_encode(['success' => true]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Server error']);
}
