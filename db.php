<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
// Tunjukkan error mysqli sebagai Exception
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * db.php — FiniteApp backend helpers
 * - Sambungan MySQL (mysqli) + charset utf8mb4
 * - Auth pusat melalui `user_tokens` dengan fallback `users.token`
 * - Polisi token:
 *      Admin         => multi-device (jangan revoke token lama, JANGAN update users.token)
 *      Client/Freelancer => single-device (revoke semua token lama, update users.token kepada token baru)
 * - Util: issue_token, revoke_token(s), require_role, require_auth
 *
 * Nota:
 *   - Pastikan jadual `user_tokens` wujud (mengikut skema yang kau bagi).
 *   - Disyorkan tambah index:
 *       ALTER TABLE user_tokens
 *         ADD INDEX idx_user_revoked (user_id, revoked),
 *         ADD INDEX idx_device (device_id),
 *         ADD INDEX idx_expires (expires_at);
 */

// ==========================
// Konfigurasi Database
// ==========================
date_default_timezone_set('Asia/Kuala_Lumpur');

// Guna env jika ada, kalau tak guna default untuk Docker
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_USER', getenv('DB_USER') ?: 'finiteapp_user');
define('DB_PASS', getenv('DB_PASS') ?: 'finiteapp_pass');
define('DB_NAME', getenv('DB_NAME') ?: 'finiteapp');

// define('DB_HOST', getenv('DB_HOST') ?: '103.191.76.189');
// define('DB_USER', getenv('DB_USER') ?: 'finitemy_app');
// define('DB_PASS', getenv('DB_PASS') ?: 'Marketing123456!');
// define('DB_NAME', getenv('DB_NAME') ?: 'finitemy_app');

// Token expiry (hari). Boleh override dengan env.
define('TOKEN_EXPIRES_IN_DAYS', (int)(getenv('TOKEN_EXPIRES_IN_DAYS') ?: 30));

// Role yang single-device
const SINGLE_DEVICE_ROLES = ['client', 'freelancer'];

// ==========================
// Util am
// ==========================
if (!function_exists('getallheaders')) {
  function getallheaders(): array
  {
    $headers = [];
    foreach ($_SERVER as $name => $value) {
      if (str_starts_with($name, 'HTTP_')) {
        $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
        $headers[$key] = $value;
      }
    }
    if (isset($_SERVER['CONTENT_TYPE'])) {
      $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }
    if (isset($_SERVER['CONTENT_LENGTH'])) {
      $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    }
    return $headers;
  }
}

function json_error(int $status, string $message, array $extra = []): never
{
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => $message] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

function json_ok(array $data = []): never
{
  http_response_code(200);
  header('Content-Type: application/json');
  echo json_encode(['success' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}

// ==========================
// Sambungan DB
// ==========================
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Dapatkan sambungan mysqli (singleton)
 */
function get_db_connection(): mysqli
{
  static $conn = null;
  if ($conn instanceof mysqli) {
    return $conn;
  }
  $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
  $conn->set_charset('utf8mb4');
  // Strict mode elak data pelik
  $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
  return $conn;
}

// Kekalkan pemboleh ubah $conn untuk backward-compat endpoint lama
$conn = get_db_connection();

// ==========================
// Auth Helpers
// ==========================
/**
 * Ambil Bearer token dari header Authorization
 */
function get_bearer_token(): string
{
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  // Cuba beberapa variasi header
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
  $auth = trim($auth);
  if ($auth === '') {
    return '';
  }
  if (stripos($auth, 'Bearer ') === 0) {
    return trim(substr($auth, 7));
  }
  // Jika user hantar token mentah tanpa "Bearer "
  return $auth;
}

/**
 * Auth gabungan:
 *  1) Cuba padan token di `user_tokens` (revoked=0 & belum luput)
 *  2) Fallback ke `users.token` untuk backward-compat
 * Return: row penuh jadual `users` (assoc array) jika sah, else exception
 *
 * @throws Exception
 */
function auth_user(mysqli $conn, ?string $token = null): array
{
  $tok = $token ?? get_bearer_token();
  if ($tok === '') {
    throw new Exception('Unauthorized');
  }

  // 1) Semak user_tokens
  $sql = "
        SELECT u.*
        FROM user_tokens ut
        JOIN users u ON u.id = ut.user_id
        WHERE ut.token = ?
          AND ut.revoked = 0
          AND (ut.expires_at IS NULL OR ut.expires_at > NOW())
        LIMIT 1
    ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s', $tok);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    return $row;
  }

  // 2) Fallback: users.token
  $stmt = $conn->prepare("SELECT * FROM users WHERE token = ? LIMIT 1");
  $stmt->bind_param('s', $tok);
  $stmt->execute();
  $res = $stmt->get_result();
  if ($row = $res->fetch_assoc()) {
    return $row;
  }

  throw new Exception('Unauthorized');
}

/**
 * Require auth: jika gagal, terus 401 dan exit
 * Return row users
 */
function require_auth(mysqli $conn): array
{
  try {
    return auth_user($conn);
  } catch (Throwable $e) {
    json_error(401, 'Unauthorized');
  }
}

/**
 * Pastikan role user dibenarkan
 */
function require_role(array $user, array $allowed): void
{
  $role = strtolower((string)($user['role'] ?? ''));
  if (!in_array($role, $allowed, true)) {
    json_error(403, 'Forbidden');
  }
}

// ==========================
// Token Helpers
// ==========================
/**
 * Jana token raw (panjang 64/128 char)
 */
function generate_token(int $bytes = 48): string
{
  // 48 bytes ~ 64 base64url chars (lepas buang '=' dan ganti simbol)
  $raw = random_bytes($bytes);
  $b64 = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
  // elak terlalu pendek
  if (strlen($b64) < 64) {
    $b64 .= bin2hex(random_bytes(8));
  }
  // maximum 128 char
  return substr($b64, 0, 128);
}

/**
 * Revoke SEMUA token pengguna (set revoked=1)
 */
function revoke_user_tokens(mysqli $conn, int $user_id): void
{
  $stmt = $conn->prepare("UPDATE user_tokens SET revoked = 1 WHERE user_id = ? AND revoked = 0");
  $stmt->bind_param('i', $user_id);
  $stmt->execute();
}

/**
 * Revoke token tertentu
 */
function revoke_token(mysqli $conn, string $token): void
{
  $stmt = $conn->prepare("UPDATE user_tokens SET revoked = 1 WHERE token = ? AND revoked = 0");
  $stmt->bind_param('s', $token);
  $stmt->execute();
}

/**
 * Cipta & simpan token baharu mengikut polisi role
 *  - Admin (multi-device): JANGAN revoke token lama, JANGAN update users.token
 *  - Client/Freelancer (single-device): revoke semua token lama, UPDATE users.token kepada token baru
 *
 * Return token baharu
 */
function issue_token(
  mysqli $conn,
  int $user_id,
  string $role,
  ?string $device_id = null,
  ?string $user_agent = null,
  ?int $expires_in_days = null
): string {
  $role_lc = strtolower($role);
  $token   = generate_token();
  $expiresDays = $expires_in_days ?? TOKEN_EXPIRES_IN_DAYS;

  // Polisi single-device → revoke semua + update users.token
  if (in_array($role_lc, SINGLE_DEVICE_ROLES, true)) {
    revoke_user_tokens($conn, $user_id);

    // Update users.token untuk backward-compat app lama
    $stmt = $conn->prepare("UPDATE users SET token = ? WHERE id = ?");
    $stmt->bind_param('si', $token, $user_id);
    $stmt->execute();
  }

  // Kira expires_at
  $expires_at = null;
  if ($expiresDays > 0) {
    $stmt = $conn->prepare("SELECT DATE_ADD(NOW(), INTERVAL ? DAY) AS exp");
    $stmt->bind_param('i', $expiresDays);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $expires_at = $res['exp'] ?? null;
  }

  // Insert ke user_tokens
  $sql = "INSERT INTO user_tokens (user_id, token, device_id, user_agent, created_at, expires_at, revoked)
            VALUES (?, ?, ?, ?, NOW(), ?, 0)";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('issss', $user_id, $token, $device_id, $user_agent, $expires_at);
  $stmt->execute();

  return $token;
}

/**
 * Optional: Semak & pulangkan token aktif untuk user + device (kalau ada)
 * Berguna kalau kau nak “reuse” token sedia ada pada device sama.
 */
function get_active_token_for_device(mysqli $conn, int $user_id, ?string $device_id): ?string
{
  if (!$device_id) return null;
  $sql = "
        SELECT token
        FROM user_tokens
        WHERE user_id = ? AND device_id = ? AND revoked = 0
          AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY id DESC
        LIMIT 1
    ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('is', $user_id, $device_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  return $row['token'] ?? null;
}

// ==========================
// Contoh penggunaan ringkas (rujuk di endpoint)
// ==========================
/*
require_once __DIR__ . '/db.php';
$conn = get_db_connection();

// 1) Di login.php (lepas verify email/password):
//    - Dapatkan $user (id, role)
//    - Untuk client/freelancer → boleh enforce install_id
$token = issue_token($conn, $user['id'], $user['role'], $_POST['install_id'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

// 2) Di mana-mana endpoint:
// $user = require_auth($conn);
// require_role($user, ['admin','client','freelancer']);
// $uid = (int)$user['id'];
*/
