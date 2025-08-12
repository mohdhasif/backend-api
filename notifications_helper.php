<?php
// notifications_helper.php
// Reusable notification service for finiteApp

require_once __DIR__ . '/db.php';

// === CONFIG (fill these) ===
const ONESIGNAL_APP_ID  = 'eff1e397-c7ae-468d-9cd5-c673ba80821d';
const ONESIGNAL_REST_KEY = 'os_v2_app_57y6hf6hvzdi3hgvyzz3vaecdxzolklx5kxecfmr5z72zfpqbydphnpluho5tlcp26fvni3o5obqfhdy2gahxel46bo364mftmjqsfi';

// Cara pilih admin recipients
const USE_ROLE_BASED_ADMINS   = true;   // jika ada kolum users.role = 'admin'
const FALLBACK_ADMIN_USER_IDS = [1];    // fallback ID admin jika role tiada

// Had selamat untuk include_player_ids per request
const ONESIGNAL_MAX_IDS_PER_REQUEST = 900;

// === DEBUG SWITCH ===
const NOTIFY_DEBUG = true; // set false di production
// ============================================


// ----------------- Logging ------------------
/** Dapat path log: <project>/storage/logs/notify_debug.log  */
function notify_log_path(): string {
  $dir = __DIR__ . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
  if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
  return $dir . DIRECTORY_SEPARATOR . 'notify_debug.log';
}

/** Log helper (fallback ke error_log bila gagal tulis file) */
function dbg($label, $data = null): void {
  if (!NOTIFY_DEBUG) return;
  $ts = date('Y-m-d H:i:s');
  $line = "[$ts] $label";
  if ($data !== null) {
    if (is_array($data) || is_object($data)) $line .= ' ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    else $line .= ' ' . (string)$data;
  }
  $path = notify_log_path();
  if (@file_put_contents($path, $line . PHP_EOL, FILE_APPEND) === false) {
    error_log($line);
  }
}

function db_err(mysqli $conn): ?string {
  return $conn->error ? ('SQLERR: ' . $conn->error) : null;
}
// --------------------------------------------


// --------------- Core helpers ---------------
/** Ambil senarai user_id untuk admin */
function get_admin_user_ids(mysqli $conn): array {
  if (USE_ROLE_BASED_ADMINS) {
    try {
      // Ubah query ikut struktur sebenar jika role berbeza
      $q = $conn->query("SELECT id FROM users WHERE role = 'admin'");
      $ids = [];
      while ($row = $q->fetch_assoc()) $ids[] = (int)$row['id'];
      dbg('get_admin_user_ids (role-based)', ['ids'=>$ids, 'sql_err'=>db_err($conn)]);
      if (!empty($ids)) return $ids;
    } catch (Throwable $e) {
      dbg('get_admin_user_ids exception', $e->getMessage());
    }
  }
  dbg('get_admin_user_ids (fallback)', FALLBACK_ADMIN_USER_IDS);
  return array_map('intval', FALLBACK_ADMIN_USER_IDS);
}

/** Insert satu notifikasi ke DB */
function insert_notification(mysqli $conn, int $user_id, string $title, string $body = '', ?string $type = null, $data = null): int {
  $data_json = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;
  $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, body, type, data_json) VALUES (?,?,?,?,?)");
  if (!$stmt) {
    dbg('insert_notification prepare failed', db_err($conn));
    throw new Exception('prepare failed');
  }
  $stmt->bind_param("issss", $user_id, $title, $body, $type, $data_json);
  $stmt->execute();
  $id = (int)$conn->insert_id;
  dbg('insert_notification ok', ['user_id'=>$user_id, 'id'=>$id, 'title'=>$title, 'type'=>$type, 'data'=>$data]);
  return $id;
}

/** Ambil semua subscription_id (player_id) OneSignal untuk user */
function get_user_subscription_ids(mysqli $conn, int $user_id): array {
  $stmt = $conn->prepare("
    SELECT subscription_id
    FROM user_push_subscriptions
    WHERE user_id = ?
  ");
  if (!$stmt) {
    dbg('get_user_subscription_ids prepare failed', db_err($conn));
    return [];
  }
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $ids = [];
  while ($row = $res->fetch_assoc()) {
    $sid = trim((string)$row['subscription_id']);
    if ($sid !== '') $ids[] = $sid;
  }
  dbg('get_user_subscription_ids', ['user_id'=>$user_id, 'count'=>count($ids), 'ids'=>$ids]);
  return $ids;
}

/** Hantar push OneSignal kepada sekumpulan player IDs */
function onesignal_push_to_player_ids(array $playerIds, string $title, string $body = '', $data = null): array {
  if (empty($playerIds)) {
    dbg('onesignal_push_to_player_ids', 'empty playerIds');
    return ['success'=>false, 'http'=>0, 'error'=>'empty playerIds'];
  }

  $payload = [
    'app_id' => ONESIGNAL_APP_ID,
    'include_player_ids' => array_values($playerIds),
    'headings' => ['en' => $title],
    'contents' => ['en' => $body],
    'data' => $data ?: new stdClass(),
    'ios_badgeType' => 'Increase',
    'ios_badgeCount' => 1,
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => 'https://onesignal.com/api/v1/notifications',
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json; charset=utf-8',
      'Authorization: Basic ' . ONESIGNAL_REST_KEY,
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 8,
  ]);

  // Cross-platform verbose logging
  if (NOTIFY_DEBUG) {
    $fp = @fopen(notify_log_path(), 'ab'); // create if not exists
    if ($fp) {
      curl_setopt($ch, CURLOPT_VERBOSE, true);
      curl_setopt($ch, CURLOPT_STDERR, $fp);
    } else {
      dbg('WARN: cannot open log file for curl verbose');
    }
  }

  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $cerr = curl_error($ch);
  $erno = curl_errno($ch);
  curl_close($ch);
  if (isset($fp) && $fp) @fclose($fp);

  dbg('onesignal request', ['http'=>$http, 'errno'=>$erno, 'error'=>$cerr, 'payload'=>$payload]);
  if ($resp !== false) {
    $snippet = mb_substr($resp, 0, 500);
    dbg('onesignal response', $snippet);
  }

  $ok = ($resp !== false && $http >= 200 && $http < 300);
  return ['success'=>$ok, 'http'=>$http, 'errno'=>$erno, 'error'=>$cerr, 'response'=>$resp];
}

/** Notify seorang user (DB + optional push) */
function notify_user(mysqli $conn, int $user_id, string $title, string $body = '', ?string $type = null, $data = null, bool $push = true): int {
  $id = insert_notification($conn, $user_id, $title, $body, $type, $data);

  if ($push) {
    try {
      $subs = get_user_subscription_ids($conn, $user_id);
      if (!empty($subs)) {
        $chunks = array_chunk($subs, ONESIGNAL_MAX_IDS_PER_REQUEST);
        foreach ($chunks as $chunk) {
          onesignal_push_to_player_ids($chunk, $title, $body, $data);
        }
      } else {
        dbg('notify_user no subscriptions', ['user_id'=>$user_id]);
      }
    } catch (Throwable $e) {
      dbg('notify_user push exception', $e->getMessage());
    }
  }

  return $id;
}

/** Notify ramai user serentak */
function notify_many(mysqli $conn, array $user_ids, string $title, string $body = '', ?string $type = null, $data = null, bool $push = true): array {
  $ids = [];
  foreach ($user_ids as $uid) {
    $ids[(int)$uid] = notify_user($conn, (int)$uid, $title, $body, $type, $data, $push);
  }
  return $ids;
}

/** Notify ADMIN(s) — hantar kepada semua admin */
function notify_admins(mysqli $conn, string $title, string $body = '', ?string $type = null, $data = null, bool $push = true): array {
  $adminIds = get_admin_user_ids($conn);
  if (empty($adminIds)) {
    dbg('notify_admins no admins found');
    return [];
  }
  dbg('notify_admins recipients', $adminIds);
  return notify_many($conn, $adminIds, $title, $body, $type, $data, $push);
}
