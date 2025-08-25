<?php
/**
 * cron_prayer_push_role_based.php
 * - Admin: Multiple devices = All devices get notifications
 * - Client/Freelancer: Multiple devices = Only latest device gets notification
 */

require_once __DIR__ . '/db.php'; // $conn (mysqli)

// ===== OneSignal Config =====
const ONESIGNAL_APP_ID = 'eff1e397-c7ae-468d-9cd5-c673ba80821d';
const ONESIGNAL_REST_API_KEY = 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi';

// ===== CA bundle =====
$CA_CERT = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem';

// ===== Enhanced duplicate prevention by subscription_id =====
function check_already_sent_today($conn, $subscriptionId, $prayerName): bool {
  $today = date('Y-m-d');
  $stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM prayer_notifications_sent 
    WHERE subscription_id = ? AND prayer = ? AND DATE(sent_at) = ?
  ");
  $stmt->bind_param("sss", $subscriptionId, $prayerName, $today);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  return $result['count'] > 0;
}

// ===== HTTP GET function =====
function http_get(string $url, int $timeout = 20): ?string {
  global $CA_CERT;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'User-Agent: prayer-cron/1.0',
    ],
  ]);
  if (is_file($CA_CERT)) {
    curl_setopt($ch, CURLOPT_CAINFO, $CA_CERT);
  }
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $errN = curl_errno($ch);
  $errS = curl_error($ch);
  curl_close($ch);

  if ($res === false || $code < 200 || $code >= 300) {
    error_log("http_get fail ($code/$errN): $errS $url");
    return null;
  }
  return $res;
}

// ===== OneSignal push =====
function onesignal_send(string $subscriptionId, string $title, string $body): bool {
  global $CA_CERT;
  $payload = [
    'app_id' => ONESIGNAL_APP_ID,
    'include_subscription_ids' => [$subscriptionId],
    'headings' => ['en' => $title],
    'contents' => ['en' => $body],
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
  if (is_file($CA_CERT)) {
    curl_setopt($ch, CURLOPT_CAINFO, $CA_CERT);
  }

  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  if ($code < 200 || $code >= 300) {
    $errN = curl_errno($ch);
    $errS = curl_error($ch);
    error_log("OneSignal error ($code/$errN): $errS; res=" . var_export($res, true));
    curl_close($ch);
    return false;
  }
  curl_close($ch);
  return true;
}

// ===== Cache helpers =====
function cache_dir(): string {
  $dir = __DIR__ . '/cache_prayer';
  if (!is_dir($dir)) mkdir($dir, 0775, true);
  return $dir;
}

function fetch_month_gps(float $lat, float $lng): ?array {
  $cache = sprintf('%s/gps_%s_%0.4f_%0.4f.json', cache_dir(), date('Y-m'), $lat, $lng);
  if (is_file($cache)) {
    $data = json_decode(file_get_contents($cache), true);
    if (is_array($data)) return $data;
  }
  $url = "https://api.waktusolat.app/v2/solat/{$lat}/{$lng}";
  $raw = http_get($url, 25);
  if (!$raw) return null;
  $data = json_decode($raw, true);
  if (!$data || empty($data['prayers'])) return null;
  file_put_contents($cache, json_encode($data));
  return $data;
}

function today_epochs_from_month(array $month): ?array {
  $day = (int)date('j');
  foreach ($month['prayers'] as $row) {
    if ((int)$row['day'] === $day) {
      return [
        'Fajr'    => (int)$row['fajr'],
        'Dhuhr'   => (int)$row['dhuhr'],
        'Asr'     => (int)$row['asr'],
        'Maghrib' => (int)$row['maghrib'],
        'Isha'    => (int)$row['isha'],
      ];
    }
  }
  return null;
}

function same_minute_epoch(int $a, int $b): bool {
  return intdiv($a, 60) === intdiv($b, 60);
}

// ===== Get user role =====
function get_user_role($conn, $userId): string {
  if (!$userId) return 'guest';
  
  $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
  $stmt->bind_param("i", $userId);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  
  return $result ? $result['role'] : 'guest';
}

// ===== Main logic: Role-based device handling =====
$sql = "
SELECT 
  ups.subscription_id,
  ups.user_id,
  ups.install_id,
  -- Priority: user settings > install settings > default
  COALESCE(
    (SELECT method FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
    (SELECT method FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1),
    'GPS'
  ) AS method,
  COALESCE(
    (SELECT latitude FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
    (SELECT latitude FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1)
  ) AS latitude,
  COALESCE(
    (SELECT longitude FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
    (SELECT longitude FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1)
  ) AS longitude,
  COALESCE(
    (SELECT jakim_zone FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
    (SELECT jakim_zone FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1)
  ) AS jakim_zone,
  COALESCE(
    (SELECT enabled FROM user_prayer_settings WHERE user_id = ups.user_id LIMIT 1),
    (SELECT enabled FROM user_prayer_settings WHERE install_id = ups.install_id LIMIT 1),
    1
  ) AS enabled,
  -- For non-admin users, only get the latest device
  CASE 
    WHEN ups.user_id IS NOT NULL THEN (
      SELECT MAX(ups2.id) 
      FROM user_push_subscriptions ups2 
      WHERE ups2.user_id = ups.user_id
    )
    ELSE ups.id
  END as latest_device_id
FROM user_push_subscriptions ups
WHERE ups.subscription_id IS NOT NULL
";

$res  = $conn->query($sql);
$subs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$now     = time();
$checked = 0;
$sent    = 0;

foreach ($subs as $r) {
  $checked++;

  if ((int)($r['enabled'] ?? 1) !== 1) continue;

  // For non-admin users, only process the latest device
  if ($r['user_id']) {
    $userRole = get_user_role($conn, $r['user_id']);
    
    // If not admin and not the latest device, skip
    if ($userRole !== 'admin' && $r['id'] != $r['latest_device_id']) {
      continue;
    }
  }

  $method = strtoupper((string)($r['method'] ?? 'GPS'));
  $times  = null;

  // === GPS method ===
  if ($method === 'GPS' && !empty($r['latitude']) && !empty($r['longitude'])) {
    $month = fetch_month_gps((float)$r['latitude'], (float)$r['longitude']);
    if ($month) $times = today_epochs_from_month($month);
  }

  if (!$times) continue;

  foreach ($times as $name => $ts) {
    if (!same_minute_epoch($now, $ts)) continue;

    // Enhanced duplicate check - check if already sent today for this subscription
    if (check_already_sent_today($conn, $r['subscription_id'], $name)) {
      continue; // Already sent today, skip
    }

    // Lock duplicate using subscription_id as primary key
    $whoUser = $r['user_id'] ? (int)$r['user_id'] : null;
    $whoInst = $r['user_id'] ? null : ($r['install_id'] ?? null);
    $tsStr   = (string)$ts;

    $lock = $conn->prepare("
      INSERT IGNORE INTO prayer_notifications_sent (user_id, install_id, prayer, sent_at, subscription_id)
      VALUES (?,?,?,FROM_UNIXTIME(?),?)
    ");
    $lock->bind_param("issss", $whoUser, $whoInst, $name, $tsStr, $r['subscription_id']);
    $lock->execute();

    if ($lock->affected_rows === 0) {
      // sudah wujud → skip
      continue;
    }

    // Hantar push
    $ok = onesignal_send($r['subscription_id'], "Waktu $name", "Sudah masuk waktu $name.");
    if (!$ok) {
      // rollback lock
      $del = $conn->prepare("
        DELETE FROM prayer_notifications_sent
        WHERE subscription_id = ? AND prayer = ? AND sent_at = FROM_UNIXTIME(?)
      ");
      $del->bind_param("sss", $r['subscription_id'], $name, $tsStr);
      $del->execute();
    } else {
      $sent++;
    }
  }
}

echo json_encode([
  'ok'      => true,
  'checked' => $checked,
  'sent'    => $sent,
  'time'    => date('c'),
]);
