<?php
require_once 'db.php';

try {
  $user = require_auth($conn);
  $uid = (int)$user['id'];

  $status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'all'; // all|unread|read
  $page = max(1, (int)($_GET['page'] ?? 1));
  $per = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
  $offset = ($page - 1) * $per;

  $where = "user_id = ?";
  if ($status === 'unread') $where .= " AND read_at IS NULL";
  if ($status === 'read')   $where .= " AND read_at IS NOT NULL";

  // total
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM notifications WHERE $where");
  $stmt->bind_param("i", $uid);
  $stmt->execute();
  $total = (int)$stmt->get_result()->fetch_assoc()['c'];

  // data
  $stmt = $conn->prepare("
      SELECT id, user_id, title, body, type, data_json, read_at, created_at
      FROM notifications
      WHERE $where
      ORDER BY created_at DESC
      LIMIT ? OFFSET ?
  ");
  $stmt->bind_param("iii", $uid, $per, $offset);
  $stmt->execute();
  $res = $stmt->get_result();
  $data = [];
  while ($row = $res->fetch_assoc()) {
    $row['data'] = $row['data_json'] ? json_decode($row['data_json'], true) : null;
    unset($row['data_json']);
    $data[] = $row;
  }

  echo json_encode(['success' => true, 'data' => $data, 'total' => $total, 'page' => $page, 'per_page' => $per], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal memuat notifikasi']);
}
