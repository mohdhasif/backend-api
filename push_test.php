<?php
// push_test.php
// Tujuan: hantar push OneSignal untuk test
// Bergantung pada: db.php (ada $conn dan require_auth($conn))

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php';

// ======== CONFIG ONE SIGNAL ========
// Ganti dengan App ID & REST API Key anda
const ONESIGNAL_APP_ID = 'eff1e397-c7ae-468d-9cd5-c673ba80821d';
const ONESIGNAL_REST_API_KEY = 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi'; // Settings > Keys & IDs -> REST API Key

// ======== UTIL ========
function json_input(): array
{
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function send_onesignal(array $subscriptionIds, string $title, string $body, array $data = []): array
{
  if (empty($subscriptionIds)) {
    return ['ok' => false, 'error' => 'No targets'];
  }

  $payload = [
    'app_id' => ONESIGNAL_APP_ID,
    'include_subscription_ids' => array_values(array_unique($subscriptionIds)),
    'headings' => ['en' => $title],
    'contents' => ['en' => $body],
    'data' => (object)$data,
    'ttl' => 300,
  ];

  $ch = curl_init('https://api.onesignal.com/notifications');
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json; charset=utf-8',
      'Authorization: Basic ' . ONESIGNAL_REST_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 25,
  ]);

  // === DEBUG tambahan ===
  // Sementara untuk diagnose; JANGAN tinggal di production
  // curl_setopt($ch, CURLOPT_VERBOSE, true);
  // cuba disable verify (sementara) untuk confirm isu SSL
  // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

  // Windows: tunjukkan lokasi cacert.pem yang baru dimuat turun
  $ca = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem'; // C:\backend-api\cacert.pem
  if (file_exists($ca)) {
    curl_setopt($ch, CURLOPT_CAINFO, $ca);
  }

  $res = curl_exec($ch);
  $errNo = curl_errno($ch);
  $err   = curl_error($ch);
  $code  = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);

  return [
    'http_code' => $code,
    'response' => $res,
    'curl_errno' => $errNo,
    'curl_error' => $err,
    'ok' => ($code >= 200 && $code < 300),
  ];
}

// ======== MAIN ========
try {
  $in = array_merge($_GET, json_input());

  // Input:
  //  - subscription_id: string (optional)
  //  - install_id: string (optional)
  //  - target: "me"  (optional, guna Authorization token)
  //  - title, body: optional (default disediakan)
  $subscription_id = isset($in['subscription_id']) ? trim((string)$in['subscription_id']) : null;
  $install_id      = isset($in['install_id']) ? trim((string)$in['install_id']) : null;
  $target          = isset($in['target']) ? trim((string)$in['target']) : null;

  $title = isset($in['title']) ? (string)$in['title'] : 'Test Push';
  $body  = isset($in['body'])  ? (string)$in['body']  : 'Hello from push_test.php';
  $extra = isset($in['data']) && is_array($in['data']) ? $in['data'] : [];

  $targets = [];

  if ($subscription_id) {
    // Cara 1: direct by subscription_id
    $targets[] = $subscription_id;
  } elseif ($install_id) {
    // Cara 2: dari install_id (anonymous)
    $stmt = $conn->prepare("SELECT subscription_id FROM user_push_subscriptions WHERE install_id = ?");
    $stmt->bind_param("s", $install_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
      if (!empty($row['subscription_id'])) $targets[] = $row['subscription_id'];
    }
  } elseif ($target === 'me') {
    // Cara 3: guna token -> hantar ke semua subscription user
    try {
      $user = require_auth($conn); // perlu Authorization: Bearer <token>
      $uid = (int)$user['id'];
      $stmt = $conn->prepare("SELECT subscription_id FROM user_push_subscriptions WHERE user_id = ?");
      $stmt->bind_param("i", $uid);
      $stmt->execute();
      $res = $stmt->get_result();
      while ($row = $res->fetch_assoc()) {
        if (!empty($row['subscription_id'])) $targets[] = $row['subscription_id'];
      }
    } catch (Throwable $e) {
      http_response_code(401);
      echo json_encode(['success' => false, 'error' => 'Unauthorized or invalid token for target=me']);
      exit;
    }
  } else {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error' => 'Provide one of: subscription_id, install_id, or target="me" (with Authorization).',
    ]);
    exit;
  }

  if (empty($targets)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'No subscription found for the given target']);
    exit;
  }

  $result = send_onesignal($targets, $title, $body, $extra);

  if (!$result['ok']) {
    http_response_code(502);
    echo json_encode([
      'success' => false,
      'error' => 'OneSignal error',
      'details' => $result,
      'targets' => $targets,
    ]);
    exit;
  }

  echo json_encode([
    'success' => true,
    'targets' => $targets,
    'onesignal' => $result,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Server error']);
}
