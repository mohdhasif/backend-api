<?php
// create_project.php
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/db.php'; // gunakan db.php anda

try {
  // ✅ Auth
  $user = require_auth($conn); // pastikan fungsi ini wujud dalam db.php seperti yang anda guna

  // ✅ Read JSON body
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);

  if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
  }

  // ✅ Extract & validate
  $title       = trim($data['title'] ?? '');
  $client_id   = (int)($data['client_id'] ?? 0);
  $description = isset($data['description']) && $data['description'] !== '' ? $data['description'] : null;
  $priority    = $data['priority'] ?? null; // 'low' | 'medium' | 'high' | null
  $start_at    = $data['start_at'] ?? null; // 'YYYY-MM-DD HH:MM:SS' | null
  $end_at      = $data['end_at'] ?? null;   // 'YYYY-MM-DD HH:MM:SS' | null
  $status      = $data['status'] ?? 'pending';
  $progress    = (int)($data['progress'] ?? 0);

  // Optional: support due_date (YYYY-MM-DD) jika anda mahu simpan
  $due_date    = $data['due_date'] ?? null;

  if ($title === '' || $client_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Validation failed: title & client_id required']);
    exit;
  }

  // Optional: basic datetime sanity (server-side)
  if ($start_at && $end_at && strtotime($end_at) < strtotime($start_at)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'End date/time must be after Start date/time']);
    exit;
  }

  // ✅ Prepared statement (nullable bindings)
  // Nota: Sesuaikan nama kolum ikut table anda
  // Contoh table `projects` ada kolum:
  // id, client_id, title, description, priority, start_at (DATETIME NULL), end_at (DATETIME NULL),
  // status, progress, due_date (DATE NULL), created_at
  $stmt = $conn->prepare("
    INSERT INTO projects
      (client_id, title, description, priority, start_at, end_at, status, progress, due_date, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");

  // Convert empty string to null safely
  $start_at_val = $start_at ? $start_at : null;
  $end_at_val   = $end_at ? $end_at : null;
  $due_date_val = $due_date ? $due_date : null;

$stmt->bind_param(
  'issssssis',
  $client_id,
  $title,
  $description,
  $priority,
  $start_at_val,
  $end_at_val,
  $status,
  $progress,
  $due_date_val
);

  $stmt->execute();
  $newId = $stmt->insert_id;

  echo json_encode(['success' => true, 'id' => $newId]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
