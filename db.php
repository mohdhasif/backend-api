<?php
// db.php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli("127.0.0.1", "root", "", "finiteapp", 3306);
  $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Database connection failed']);
  exit;
}

/**
 * Helper: ambil Authorization: Bearer <token>
 */
function get_bearer_token(): ?string
{
  // Cuba dari header standard
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

  if (stripos($auth, 'Bearer ') === 0) {
    return trim(substr($auth, 7));
  }

  // Sokong ?token=... untuk testing
  if (!empty($_GET['token'])) return $_GET['token'];
  if (!empty($_POST['token'])) return $_POST['token'];

  return null;
}

/**
 * Require auth: Semak token di user_tokens (utama).
 * Jika tak jumpa, fallback ke users.token (sementara untuk backward compat).
 * Return: array user {id, name, email, role, avatar_url}
 */
function require_auth(mysqli $conn): array
{
  $token = get_bearer_token();

  if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
  }

  // 1) Cuba cari di user_tokens (revoked=0, belum expired)
  $sql = "
    SELECT u.id, u.name, u.email, u.role, u.avatar_url
    FROM user_tokens t
    JOIN users u ON u.id = t.user_id
    WHERE t.token = ?
      AND t.revoked = 0
      AND (t.expires_at IS NULL OR t.expires_at > NOW())
    LIMIT 1
  ";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();

  if ($user) return $user;

  // 2) Fallback: semak users.token (untuk endpoint lama). Disarankan hapus bila semua endpoint sudah migrate.
  $stmt = $conn->prepare("SELECT id, name, email, role, avatar_url FROM users WHERE token = ? LIMIT 1");
  $stmt->bind_param("s", $token);
  $stmt->execute();
  $res = $stmt->get_result();
  $user = $res->fetch_assoc();
  $stmt->close();

  if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
  }

  return $user;
}

/**
 * Helper: generate token raw (64 hex chars)
 */
function generate_token(): string
{
  try {
    return bin2hex(random_bytes(32)); // 64 chars
  } catch (Throwable $e) {
    return bin2hex(openssl_random_pseudo_bytes(32));
  }
}
