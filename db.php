<?php
// db.php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $conn = new mysqli("localhost", "root", "", "finiteapp", 3306);
  $conn->set_charset("utf8mb4");
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Database connection failed']);
  exit;
}

/**
 * Semak token dalam header Authorization: Bearer <token>
 * Return data user jika sah. Jika tak sah -> 401 + JSON dan exit.
 */
function require_auth(mysqli $conn): array {
  $headers = function_exists('getallheaders') ? getallheaders() : [];
  $authHeader = $headers['Authorization'] ?? '';
  $token = str_replace('Bearer ', '', $authHeader);

  if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
  }

  $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE token = ? LIMIT 1");
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
