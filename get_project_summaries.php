<?php
// get_project_summaries.php
require_once __DIR__ . '/db.php';  // guna $conn dan require_auth()

// Wajib sahkan token (akan 401 kalau tak sah)
$user = require_auth($conn);

// Input optional
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

$sql = "
SELECT
  p.id AS project_id,
  COALESCE(p.title, CONCAT('Project #', p.id)) AS project_title,
  CASE
    WHEN c.client_type = 'individual' THEN COALESCE(u.name, 'Unknown Client')
    ELSE COALESCE(c.company_name, 'Unknown Company')
  END AS client_name,
  p.due_date,
  COALESCE(ts.total_tasks, 0) AS total_tasks,
  COALESCE(ts.completed_tasks, 0) AS completed_tasks,
  COALESCE(pf.freelancer_count, 0) AS freelancer_count,
  COALESCE(pf.avatars_csv, '') AS avatars_csv
FROM projects p
LEFT JOIN clients c ON c.id = p.client_id
LEFT JOIN users u ON u.id = c.user_id
LEFT JOIN (
  SELECT t.project_id,
         COUNT(*) AS total_tasks,
         SUM(CASE WHEN t.status='completed' THEN 1 ELSE 0 END) AS completed_tasks
  FROM tasks t
  GROUP BY t.project_id
) ts ON ts.project_id = p.id
LEFT JOIN (
  SELECT p2.id AS project_id,
         GROUP_CONCAT(DISTINCT f.avatar_url) AS avatars_csv,
         COUNT(DISTINCT f.id) AS freelancer_count
  FROM projects p2
  LEFT JOIN tasks t2 ON t2.project_id = p2.id
  LEFT JOIN task_assignees ta2 ON ta2.task_id = t2.id
  LEFT JOIN freelancers f ON f.id = ta2.freelancer_id
  GROUP BY p2.id
) pf ON pf.project_id = p.id
";

$params = [];
$types  = "";
if ($projectId) {
  $sql .= " WHERE p.id = ? ";
  $params[] = $projectId;
  $types   .= "i";
}

$sql .= " ORDER BY p.due_date IS NULL, p.due_date ASC, p.id DESC";

try {
  $stmt = $conn->prepare($sql);
  if (!empty($params)) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();

  $out = [];
  while ($r = $res->fetch_assoc()) {
    $total = (int)$r['total_tasks'];
    $completed = (int)$r['completed_tasks'];
    $percent = $total > 0 ? (int)round(($completed / $total) * 100) : 0;

    $avatars = [];
    if (!empty($r['avatars_csv'])) {
      $split = array_unique(array_map('trim', explode(',', $r['avatars_csv'])));
      $split = array_values(array_filter($split, fn($v) => $v !== '' && $v !== null));
      $avatars = $split;
    }
    $top3 = array_slice($avatars, 0, 3);
    $extra = max(0, ((int)$r['freelancer_count']) - count($top3));

    $out[] = [
      "project_id"         => (int)$r["project_id"],
      "project_title"      => $r["project_title"],
      "client_name"        => $r["client_name"],
      "due_date"           => $r["due_date"],
      "total_tasks"        => $total,
      "completed_tasks"    => $completed,
      "progress_percent"   => $percent,
      "freelancer_count"   => (int)$r["freelancer_count"],
      "freelancer_avatars" => $top3,
      "extra_freelancers"  => $extra,
    ];
  }
  $stmt->close();
  echo json_encode($projectId ? ($out[0] ?? null) : $out, JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Failed to fetch project summaries']);
}
