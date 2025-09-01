<?php
// save_prayer_settings.php
// Upsert user_prayer_settings untuk user (me=1 dengan require_auth) atau install_id (anonymous).
// Body JSON: { enabled: 0|1, latitude?: float, longitude?: float, [install_id?: string] }

require_once __DIR__ . '/db.php'; // $conn (mysqli)
date_default_timezone_set('Asia/Kuala_Lumpur');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if (!function_exists('read_json')) {
  function read_json(): array
  {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
  }
}

// Try auth that returns ['id'=>..., 'role'=>...], or null if no/invalid token.
function try_auth_assoc(mysqli $conn): ?array
{
  if (!function_exists('require_auth')) return null;
  try {
    $r = require_auth($conn); // your function may echo+exit on failure; if not, normalize:
  } catch (Throwable $e) {
    return null;
  }
  if (is_array($r)) {
    $id = $r['user']['id'] ?? $r['id'] ?? $r['user_id'] ?? null;
    $role = $r['user']['role'] ?? $r['role'] ?? null;
    return $id ? ['id' => (int)$id, 'role' => $role] : null;
  }
  if (is_object($r)) {
    $id = $r->user->id ?? $r->id ?? $r->user_id ?? null;
    $role = $r->user->role ?? $r->role ?? null;
    return $id ? ['id' => (int)$id, 'role' => $role] : null;
  }
  return null;
}

// Get install_id from JSON/POST/GET/header; otherwise, if me=1 & authed, pick latest device
function resolve_install_id(mysqli $conn, ?array $auth): ?string
{
  $body = read_json();

  // 1) JSON
  foreach (['install_id', 'installId'] as $k) {
    if (isset($body[$k]) && $body[$k] !== '') return trim((string)$body[$k]);
  }
  // 2) POST / GET
  foreach (['install_id', 'installId'] as $k) {
    if (!empty($_POST[$k])) return trim((string)$_POST[$k]);
    if (!empty($_GET[$k]))  return trim((string)$_GET[$k]);
  }
  // 3) Headers
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  foreach (['X-Install-Id', 'Install-Id', 'x-install-id'] as $hk) {
    if (!empty($headers[$hk])) return trim((string)$headers[$hk]);
  }

  // 4) me=1 + authed → fallback to latest device from DB
  $me = isset($_GET['me']) ? (string)$_GET['me'] : '';
  if ($me === '1' && $auth && isset($auth['id'])) {
    $uid = (int)$auth['id'];
    $sql = "SELECT install_id
            FROM user_push_subscriptions
            WHERE user_id = ? AND install_id IS NOT NULL AND install_id <> ''
            ORDER BY last_seen_at DESC
            LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
      $stmt->bind_param("i", $uid);
      $stmt->execute();
      $res = $stmt->get_result();
      if ($row = $res->fetch_assoc()) {
        return (string)$row['install_id'];
      }
    }
  }

  // 5) Not found
  return null;
}

function read_json(): array
{
  $raw = file_get_contents('php://input');
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}
function json_out($arr, int $code = 200)
{
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_SLASHES);
  exit;
}

/** Ambil user_id melalui require_auth() (signature fleksibel) */
function auth_user_id_via_require_auth($conn): ?int
{
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
  foreach (['auth_user_id', 'user_id', 'CURRENT_USER_ID'] as $k) {
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

$auth = try_auth_assoc($conn); // may be null if no token
$install = resolve_install_id($conn, $auth);

// json_out(['success' => false, 'error' => $install], 400);

if (!$install) {
  json_out([
    'success' => false,
    'error'   => 'Missing install_id',
    'hint'    => 'Send install_id in JSON body (install_id), or include ?me=1 with Authorization to fallback to your latest device.'
  ]);
  http_response_code(400);
  exit;
}

// validation asas
if (!in_array($enabled, [0, 1], true)) json_out(['success' => false, 'error' => 'enabled must be 0 or 1'], 400);
if ($lat !== null) {
  if (!is_numeric($lat)) json_out(['success' => false, 'error' => 'latitude must be number'], 400);
  $lat = (float)$lat;
  if ($lat < -90 || $lat > 90) json_out(['success' => false, 'error' => 'Invalid latitude'], 400);
}
if ($lng !== null) {
  if (!is_numeric($lng)) json_out(['success' => false, 'error' => 'longitude must be number'], 400);
  $lng = (float)$lng;
  if ($lng < -180 || $lng > 180) json_out(['success' => false, 'error' => 'Invalid longitude'], 400);
}

// tentukan sasaran: user (me=1) atau install_id
$userId = null;
if ($me === '1') {
  $userId = auth_user_id_via_require_auth($conn);
  if (!$userId) json_out(['success' => false, 'error' => 'Unauthorized'], 401);
} else {
  if (!$install) json_out(['success' => false, 'error' => 'install_id required'], 400);
}


/**
 * Save prayer settings per role and device.
 *
 * @param mysqli     $conn
 * @param int|null   $userId   Logged-in user id (null = anonymous)
 * @param string|null $role    'admin' | 'client' | 'freelancer' | null
 * @param string     $install  install_id (device id)
 * @param string     $method   'GPS' | 'JAKIM' | 'ALADHAN'
 * @param mixed      $lat      null or number/string
 * @param mixed      $lng      null or number/string
 * @param int        $enabled  0/1
 * @return array{success:bool, mode?:string, error?:string, details?:string}
 */
function savePrayerSetting(mysqli $conn, ?int $userId, ?string $role, string $install, string $method, $lat, $lng, int $enabled): array
{
  // Normalize inputs
  $role   = strtolower((string)$role);
  $method = in_array($method, ['GPS', 'JAKIM', 'ALADHAN'], true) ? $method : 'GPS';
  $latStr = ($lat === null || $lat === '') ? null : (string)number_format((float)$lat, 6, '.', '');
  $lngStr = ($lng === null || $lng === '') ? null : (string)number_format((float)$lng, 6, '.', '');

  $conn->begin_transaction();
  try {
    if ($role === 'admin') {
      // ===== ADMIN: multi-device (operate strictly on (user_id, install_id)) =====
      if (!$userId) {
        throw new Exception('Admin update requires valid userId.');
      }

      // 1) Claim anonymous row (user_id IS NULL) for this install, if any
      $stmt = $conn->prepare("
                UPDATE user_prayer_settings
                   SET user_id = ?, updated_at = NOW()
                 WHERE user_id IS NULL AND install_id = ?
            ");
      $stmt->bind_param("is", $userId, $install);
      $stmt->execute();
      $stmt->close();

      // 2) Upsert the specific (user_id, install_id) row
      if ($latStr !== null && $lngStr !== null) {
        // Update everything (method, lat, lng, enabled)
        $stmt = $conn->prepare("
                    INSERT INTO user_prayer_settings
                        (user_id, install_id, method, latitude, longitude, enabled, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        method     = VALUES(method),
                        latitude   = VALUES(latitude),
                        longitude  = VALUES(longitude),
                        enabled    = VALUES(enabled),
                        updated_at = NOW()
                ");
        $stmt->bind_param("issssi", $userId, $install, $method, $latStr, $lngStr, $enabled);
      } else {
        // Only update method & enabled (preserve existing coords)
        $stmt = $conn->prepare("
                    INSERT INTO user_prayer_settings
                        (user_id, install_id, method, enabled, updated_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        method     = VALUES(method),
                        enabled    = VALUES(enabled),
                        updated_at = NOW()
                ");
        $stmt->bind_param("issi", $userId, $install, $method, $enabled);
      }
      $stmt->execute();
      $stmt->close();

      $conn->commit();
      return ['success' => true, 'mode' => 'admin'];
    }

    // ===== NON-ADMIN (client/freelancer) =====
    if (!$userId) {
      // Anonymous device-level settings (keep exactly one row per install with user_id NULL)
      // Try update existing anonymous row, else insert new
      $stmt = $conn->prepare("SELECT id FROM user_prayer_settings WHERE user_id IS NULL AND install_id = ? LIMIT 1");
      $stmt->bind_param("s", $install);
      $stmt->execute();
      $stmt->bind_result($rowId);
      $has = $stmt->fetch();
      $stmt->close();

      if ($has) {
        if ($latStr !== null && $lngStr !== null) {
          $stmt = $conn->prepare("
                        UPDATE user_prayer_settings
                           SET method = ?, latitude = ?, longitude = ?, enabled = ?, updated_at = NOW()
                         WHERE id = ?
                    ");
          $stmt->bind_param("sssii", $method, $latStr, $lngStr, $enabled, $rowId);
        } else {
          $stmt = $conn->prepare("
                        UPDATE user_prayer_settings
                           SET method = ?, enabled = ?, updated_at = NOW()
                         WHERE id = ?
                    ");
          $stmt->bind_param("sii", $method, $enabled, $rowId);
        }
        $stmt->execute();
        $stmt->close();
      } else {
        if ($latStr !== null && $lngStr !== null) {
          $stmt = $conn->prepare("
                        INSERT INTO user_prayer_settings
                            (user_id, install_id, method, latitude, longitude, enabled, updated_at)
                        VALUES (NULL, ?, ?, ?, ?, ?, NOW())
                    ");
          $stmt->bind_param("ssssi", $install, $method, $latStr, $lngStr, $enabled);
        } else {
          $stmt = $conn->prepare("
                        INSERT INTO user_prayer_settings
                            (user_id, install_id, method, enabled, updated_at)
                        VALUES (NULL, ?, ?, ?, NOW())
                    ");
          $stmt->bind_param("ssi", $install, $method, $enabled);
        }
        $stmt->execute();
        $stmt->close();
      }

      $conn->commit();
      return ['success' => true, 'mode' => 'anon'];
    }

    // Logged-in non-admin → single-device enforcement
    // 1) Does this user already have a row?
    $stmt = $conn->prepare("SELECT id, install_id FROM user_prayer_settings WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($rowId, $existingInstall);
    $hasRow = $stmt->fetch();
    $stmt->close();

    if ($hasRow) {
      if ($existingInstall !== $install) {
        // 2) Different device → delete all rows for this user and insert the new device
        $stmt = $conn->prepare("DELETE FROM user_prayer_settings WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        if ($latStr !== null && $lngStr !== null) {
          $stmt = $conn->prepare("
                        INSERT INTO user_prayer_settings
                            (user_id, install_id, method, latitude, longitude, enabled, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
          $stmt->bind_param("issssi", $userId, $install, $method, $latStr, $lngStr, $enabled);
        } else {
          $stmt = $conn->prepare("
                        INSERT INTO user_prayer_settings
                            (user_id, install_id, method, enabled, updated_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
          $stmt->bind_param("issi", $userId, $install, $method, $enabled);
        }
        $stmt->execute();
        $stmt->close();
      } else {
        // 3) Same device → UPDATE (include install_id in SET to fill nulls)
        if ($latStr !== null && $lngStr !== null) {
          $stmt = $conn->prepare("
                        UPDATE user_prayer_settings
                           SET install_id = ?, method = ?, latitude = ?, longitude = ?, enabled = ?, updated_at = NOW()
                         WHERE user_id = ? AND install_id = ?
                    ");
          $stmt->bind_param("sssisis", $install, $method, $latStr, $lngStr, $enabled, $userId, $install);
        } else {
          $stmt = $conn->prepare("
                        UPDATE user_prayer_settings
                           SET install_id = ?, method = ?, enabled = ?, updated_at = NOW()
                         WHERE user_id = ? AND install_id = ?
                    ");
          $stmt->bind_param("siiis", $install, $method, $enabled, $userId, $install);
        }
        $stmt->execute();
        $stmt->close();
      }
    } else {
      // 4) No row yet → INSERT fresh
      if ($latStr !== null && $lngStr !== null) {
        $stmt = $conn->prepare("
                    INSERT INTO user_prayer_settings
                        (user_id, install_id, method, latitude, longitude, enabled, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
        $stmt->bind_param("issssi", $userId, $install, $method, $latStr, $lngStr, $enabled);
      } else {
        $stmt = $conn->prepare("
                    INSERT INTO user_prayer_settings
                        (user_id, install_id, method, enabled, updated_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
        $stmt->bind_param("issi", $userId, $install, $method, $enabled);
      }
      $stmt->execute();
      $stmt->close();
    }

    $conn->commit();
    return ['success' => true, 'mode' => 'single-device'];
  } catch (Throwable $e) {
    $conn->rollback();
    return ['success' => false, 'error' => 'DB error', 'details' => $e->getMessage()];
  }
}

$user = require_auth($conn);
$userId = $user['id'];
try {

  $role = $role ?? null;
  if ($userId && $role === null) {
    $stmt = $conn->prepare("SELECT LOWER(role) FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($role);
    $stmt->fetch();
    $stmt->close();
  }

  // Normalisasi lat/lng -> string (supaya mudah bind NULL)
  $latStr = ($lat === null ? null : (string)number_format((float)$lat, 6, '.', ''));
  $lngStr = ($lng === null ? null : (string)number_format((float)$lng, 6, '.', ''));
  $method = 'GPS';


  // json_out(['success' => false, 'error' => 'userId ' . $userId . 'install ' . $install], 400);

  // if ($userId) {
  //   // === Upsert by user_id ===
  //   $stmt = $conn->prepare("SELECT id FROM user_prayer_settings WHERE user_id=? LIMIT 1");
  //   $stmt->bind_param("i", $userId);
  //   $stmt->execute();
  //   $stmt->bind_result($rowId);
  //   $has = $stmt->fetch();
  //   $stmt->close();

  //   if ($role !== 'admin') {
  //     // Single-device: padam SEMUA device lain kecuali current install
  //     $stmt = $conn->prepare("DELETE FROM user_prayer_settings WHERE user_id=? AND (install_id IS NULL OR install_id <> ?)");
  //     $stmt->bind_param("is", $userId, $install);
  //     $stmt->execute();
  //     $stmt->close();
  //   }

  //   if ($has) {
  //     if ($lat !== null && $lng !== null) {
  //       $stmt = $conn->prepare("UPDATE user_prayer_settings SET method='GPS', latitude=?, longitude=?, enabled=?, updated_at=NOW() WHERE user_id=?");
  //       $stmt->bind_param("ddii", $lat, $lng, $enabled, $userId);
  //     } else {
  //       $stmt = $conn->prepare("UPDATE user_prayer_settings SET enabled=?, updated_at=NOW() WHERE user_id=?");
  //       $stmt->bind_param("ii", $enabled, $userId);
  //     }
  //     $stmt->execute();
  //     $stmt->close();
  //   } else {
  //     if ($lat !== null && $lng !== null) {
  //       $stmt = $conn->prepare("INSERT INTO user_prayer_settings (user_id, method, latitude, longitude, enabled, updated_at) VALUES (?,?,?,?,?,NOW())");
  //       $method = 'GPS';
  //       $stmt->bind_param("isddi", $userId, $method, $lat, $lng, $enabled);
  //     } else {
  //       $stmt = $conn->prepare("INSERT INTO user_prayer_settings (user_id, method, enabled, updated_at) VALUES (?,?,?,NOW())");
  //       $method = 'GPS';
  //       $stmt->bind_param("isi", $userId, $method, $enabled);
  //     }
  //     $stmt->execute();
  //     $stmt->close();
  //   }
  // } else {
  //   // === Upsert by install_id ===
  //   $stmt = $conn->prepare("SELECT id FROM user_prayer_settings WHERE install_id=? LIMIT 1");
  //   $stmt->bind_param("s", $install);
  //   $stmt->execute();
  //   $stmt->bind_result($rowId);
  //   $has = $stmt->fetch();
  //   $stmt->close();

  //   if ($has) {
  //     if ($lat !== null && $lng !== null) {
  //       $stmt = $conn->prepare("UPDATE user_prayer_settings SET method='GPS', latitude=?, longitude=?, enabled=?, updated_at=NOW() WHERE install_id=?");
  //       $stmt->bind_param("ddis", $lat, $lng, $enabled, $install);
  //     } else {
  //       $stmt = $conn->prepare("UPDATE user_prayer_settings SET enabled=?, updated_at=NOW() WHERE install_id=?");
  //       $stmt->bind_param("is", $enabled, $install);
  //     }
  //     $stmt->execute();
  //     $stmt->close();
  //   } else {
  //     if ($lat !== null && $lng !== null) {
  //       $stmt = $conn->prepare("INSERT INTO user_prayer_settings (install_id, method, latitude, longitude, enabled, updated_at) VALUES (?,?,?,?,?,NOW())");
  //       $method = 'GPS';
  //       $stmt->bind_param("ssddi", $install, $method, $lat, $lng, $enabled);
  //     } else {
  //       $stmt = $conn->prepare("INSERT INTO user_prayer_settings (install_id, method, enabled, updated_at) VALUES (?,?,?,NOW())");
  //       $method = 'GPS';
  //       $stmt->bind_param("ssi", $install, $method, $enabled);
  //     }
  //     $stmt->execute();
  //     $stmt->close();
  //   }
  // }

  $res = savePrayerSetting(
    $conn,
    $userId,        // null kalau anonymous
    $role,          // 'admin' | 'client' | 'freelancer'
    $install,       // install_id
    $method,        // 'GPS'|'JAKIM'|'ALADHAN'
    $lat,
    $lng,     // null ATAU float/string
    $enabled        // 0/1
  );
  json_out($res);

  json_out(['success' => true]);
} catch (Throwable $e) {
  json_out(['success' => false, 'error' => 'DB error', 'details' => $e->getMessage()], 500);
}

// publish