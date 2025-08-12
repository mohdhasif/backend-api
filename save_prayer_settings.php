<?php
// save_prayer_settings.php
// Upsert user_prayer_settings untuk user (me=1 dengan require_auth) atau install_id (anonymous).
// Body JSON: { enabled: 0|1, latitude?: float, longitude?: float, [install_id?: string] }

require_once __DIR__ . '/db.php'; // $conn (mysqli)
date_default_timezone_set('Asia/Kuala_Lumpur');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function read_json(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function json_out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_SLASHES);
  exit;
}

/** Ambil user_id melalui require_auth() (signature fleksibel) */
function auth_user_id_via_require_auth($conn): ?int {
  if (!function_exists('require_auth')) return null;
  try {
    $r = require_auth($conn); // dijangka semak Authorization header sendiri
  } catch (Throwable $e) {
    return null;
  }
  if (is_int($r)) return $r;
  if (is_numeric($r)) return (int)$r;

  if (is_array($r)) {
    if (isset($r['user']['id'])) return (int)$r['user']['id'];
    if (isset($r['id'])) return (int)$r['id'];
    if (isset($r['user_id'])) return (int)$r['user_id'];
  }

  if (is_object($r)) {
    if (isset($r->user) && is_object($r->user) && isset($r->user->id)) return (int)$r->user->id;
    if (isset($r->id)) return (int)$r->id;
    if (isset($r->user_id)) return (int)$r->user_id;
  }

  // fallback globals yang mungkin di-set oleh require_auth()
  foreach (['auth_user_id','user_id','CURRENT_USER_ID'] as $k) {
    if (isset($GLOBALS[$k]) && is_numeric($GLOBALS[$k])) return (int)$GLOBALS[$k];
  }
  return null;
}

$me   = isset($_GET['me']) ? (string)$_GET['me'] : '';
$body = read_json();

$enabled = isset($body['enabled']) ? (int)$body['enabled'] : 1;
$lat     = array_key_exists('latitude',  $body) ? $body['latitude']  : null;
$lng     = array_key_exists('longitude', $body) ? $body['longitude'] : null;
$install = isset($body['install_id']) ? trim((string)$body['install_id']) : null;

// validation asas
if (!in_array($enabled, [0,1], true)) json_out(['success'=>false,'error'=>'enabled must be 0 or 1'], 400);
if ($lat !== null) {
  if (!is_numeric($lat)) json_out(['success'=>false,'error'=>'latitude must be number'], 400);
  $lat = (float)$lat;
  if ($lat < -90 || $lat > 90) json_out(['success'=>false,'error'=>'Invalid latitude'], 400);
}
if ($lng !== null) {
  if (!is_numeric($lng)) json_out(['success'=>false,'error'=>'longitude must be number'], 400);
  $lng = (float)$lng;
  if ($lng < -180 || $lng > 180) json_out(['success'=>false,'error'=>'Invalid longitude'], 400);
}

// tentukan sasaran: user (me=1) atau install_id
$userId = null;
if ($me === '1') {
  $userId = auth_user_id_via_require_auth($conn);
  if (!$userId) json_out(['success'=>false,'error'=>'Unauthorized'], 401);
} else {
  if (!$install) json_out(['success'=>false,'error'=>'install_id required'], 400);
}

try {
  if ($userId) {
    // === Upsert by user_id ===
    $stmt = $conn->prepare("SELECT id FROM user_prayer_settings WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($rowId);
    $has = $stmt->fetch();
    $stmt->close();

    if ($has) {
      if ($lat !== null && $lng !== null) {
        $stmt = $conn->prepare("UPDATE user_prayer_settings SET method='GPS', latitude=?, longitude=?, enabled=?, updated_at=NOW() WHERE user_id=?");
        $stmt->bind_param("ddii", $lat, $lng, $enabled, $userId);
      } else {
        $stmt = $conn->prepare("UPDATE user_prayer_settings SET enabled=?, updated_at=NOW() WHERE user_id=?");
        $stmt->bind_param("ii", $enabled, $userId);
      }
      $stmt->execute();
      $stmt->close();
    } else {
      if ($lat !== null && $lng !== null) {
        $stmt = $conn->prepare("INSERT INTO user_prayer_settings (user_id, method, latitude, longitude, enabled, updated_at) VALUES (?,?,?,?,?,NOW())");
        $method = 'GPS';
        $stmt->bind_param("isddi", $userId, $method, $lat, $lng, $enabled);
      } else {
        $stmt = $conn->prepare("INSERT INTO user_prayer_settings (user_id, method, enabled, updated_at) VALUES (?,?,?,NOW())");
        $method = 'GPS';
        $stmt->bind_param("isi", $userId, $method, $enabled);
      }
      $stmt->execute();
      $stmt->close();
    }
  } else {
    // === Upsert by install_id ===
    $stmt = $conn->prepare("SELECT id FROM user_prayer_settings WHERE install_id=? LIMIT 1");
    $stmt->bind_param("s", $install);
    $stmt->execute();
    $stmt->bind_result($rowId);
    $has = $stmt->fetch();
    $stmt->close();

    if ($has) {
      if ($lat !== null && $lng !== null) {
        $stmt = $conn->prepare("UPDATE user_prayer_settings SET method='GPS', latitude=?, longitude=?, enabled=?, updated_at=NOW() WHERE install_id=?");
        $stmt->bind_param("ddis", $lat, $lng, $enabled, $install);
      } else {
        $stmt = $conn->prepare("UPDATE user_prayer_settings SET enabled=?, updated_at=NOW() WHERE install_id=?");
        $stmt->bind_param("is", $enabled, $install);
      }
      $stmt->execute();
      $stmt->close();
    } else {
      if ($lat !== null && $lng !== null) {
        $stmt = $conn->prepare("INSERT INTO user_prayer_settings (install_id, method, latitude, longitude, enabled, updated_at) VALUES (?,?,?,?,?,NOW())");
        $method = 'GPS';
        $stmt->bind_param("ssddi", $install, $method, $lat, $lng, $enabled);
      } else {
        $stmt = $conn->prepare("INSERT INTO user_prayer_settings (install_id, method, enabled, updated_at) VALUES (?,?,?,NOW())");
        $method = 'GPS';
        $stmt->bind_param("ssi", $install, $method, $enabled);
      }
      $stmt->execute();
      $stmt->close();
    }
  }

  json_out(['success'=>true]);
} catch (Throwable $e) {
  json_out(['success'=>false,'error'=>'DB error','details'=>$e->getMessage()], 500);
}
