<?php
// get_prayer_settings.php
// Pulangkan setting semasa untuk user (me=1 dgn require_auth) atau ikut install_id.
// Response: { success, setting?: { enabled, latitude, longitude, updated_at, source } }

require_once __DIR__ . '/db.php'; // $conn (mysqli) + require_auth()
date_default_timezone_set('Asia/Kuala_Lumpur');
header('Content-Type: application/json; charset=utf-8');

function json_out($arr, int $code = 200) {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_SLASHES);
  exit;
}

/** Cuba dapatkan user_id daripada require_auth() (signature fleksibel) */
function auth_user_id_via_require_auth($conn): ?int {
  if (!function_exists('require_auth')) return null;
  try {
    $r = require_auth($conn); // function ini patut validate Authorization header sendiri
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

  // fallback global var yang mungkin di-set
  foreach (['auth_user_id','user_id','CURRENT_USER_ID'] as $k) {
    if (isset($GLOBALS[$k]) && is_numeric($GLOBALS[$k])) return (int)$GLOBALS[$k];
  }
  return null;
}

$me      = isset($_GET['me']) ? (string)$_GET['me'] : '';
$install = isset($_GET['install_id']) ? trim((string)$_GET['install_id']) : null;

try {
  if ($me === '1') {
    // Auth user
    $userId = auth_user_id_via_require_auth($conn);
    if (!$userId) json_out(['success' => false, 'error' => 'Unauthorized'], 401);

    // Cari ikut user_id
    $stmt = $conn->prepare("SELECT enabled, latitude, longitude, updated_at FROM user_prayer_settings WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($enabled, $lat, $lng, $updatedAt);
    if ($stmt->fetch()) {
      $stmt->close();
      json_out([
        'success' => true,
        'setting' => [
          'enabled'    => (int)$enabled,
          'latitude'   => $lat !== null ? (float)$lat : null,
          'longitude'  => $lng !== null ? (float)$lng : null,
          'updated_at' => $updatedAt,
          'source'     => 'user',
        ]
      ]);
    }
    $stmt->close();

    // (Opsyenal) fallback ikut install_id jika diberi
    if ($install) {
      $stmt = $conn->prepare("SELECT enabled, latitude, longitude, updated_at FROM user_prayer_settings WHERE install_id=? LIMIT 1");
      $stmt->bind_param("s", $install);
      $stmt->execute();
      $stmt->bind_result($enabled, $lat, $lng, $updatedAt);
      if ($stmt->fetch()) {
        $stmt->close();
        json_out([
          'success' => true,
          'setting' => [
            'enabled'    => (int)$enabled,
            'latitude'   => $lat !== null ? (float)$lat : null,
            'longitude'  => $lng !== null ? (float)$lng : null,
            'updated_at' => $updatedAt,
            'source'     => 'install',
          ]
        ]);
      }
      $stmt->close();
    }

    // Tiada rekod
    json_out(['success' => true, 'setting' => null]);

  } else {
    // Anonymous: perlu install_id
    if (!$install) json_out(['success' => false, 'error' => 'install_id required'], 400);

    $stmt = $conn->prepare("SELECT enabled, latitude, longitude, updated_at FROM user_prayer_settings WHERE install_id=? LIMIT 1");
    $stmt->bind_param("s", $install);
    $stmt->execute();
    $stmt->bind_result($enabled, $lat, $lng, $updatedAt);
    if ($stmt->fetch()) {
      $stmt->close();
      json_out([
        'success' => true,
        'setting' => [
          'enabled'    => (int)$enabled,
          'latitude'   => $lat !== null ? (float)$lat : null,
          'longitude'  => $lng !== null ? (float)$lng : null,
          'updated_at' => $updatedAt,
          'source'     => 'install',
        ]
      ]);
    }
    $stmt->close();

    json_out(['success' => true, 'setting' => null]);
  }
} catch (Throwable $e) {
  json_out(['success' => false, 'error' => 'DB error', 'details' => $e->getMessage()], 500);
}
