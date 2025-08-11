<?php
require_once __DIR__ . '/db.php';

$user = require_auth($conn);

try {
  $q = $conn->query("
    SELECT 
      p.id AS project_id,
      p.title AS project_title,
      p.client_id,
      CASE 
        WHEN c.client_type = 'company' THEN c.company_name
        ELSE u.name
      END AS client_name
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY p.created_at DESC
  ");

  $rows = [];
  while ($row = $q->fetch_assoc()) {
    $rows[] = [
      'id' => (int)$row['project_id'],
      'title' => $row['project_title'],
      'client_id' => (int)$row['client_id'],
      'client_name' => $row['client_name'] ?? null,
    ];
  }

  echo json_encode(['success' => true, 'data' => $rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Gagal ambil senarai projek']);
}
