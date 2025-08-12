<?php
// projects_calendar.php
// Pulangkan start/end setiap project untuk view calendar

require_once __DIR__ . '/db.php'; // gunakan db.php yang anda sudah update

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

try {
    // Wajib auth
    $user = require_auth($conn); // -> array user; kalau tak sah akan exit dgn 401 oleh helper

    // Params
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : null; // YYYY-MM-DD
    $end_date   = isset($_GET['end_date']) ? trim($_GET['end_date'])   : null; // YYYY-MM-DD

    // Default range: -30 hingga +30 hari
    $today = new DateTime('now');
    if (!$start_date) {
        $d = clone $today;
        $d->modify('-30 days');
        $start_date = $d->format('Y-m-d');
    }
    if (!$end_date) {
        $d = clone $today;
        $d->modify('+30 days');
        $end_date = $d->format('Y-m-d');
    }

    // Gunakan 00:00:00 dan 23:59:59 untuk inclusive range
    $start_dt = $start_date . ' 00:00:00';
    $end_dt   = $end_date   . ' 23:59:59';

    // Ambil project yang overlap dengan range (start dalam range, atau end dalam range, atau meliputi keseluruhan)
    $sql = "
    SELECT 
      p.id,
      p.title,
      p.status,
      p.start_at,
      p.end_at,
      c.client_type,
      c.company_name,
      u.name AS user_name
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON c.user_id = u.id
    WHERE
      (
        (p.start_at IS NOT NULL AND p.start_at BETWEEN ? AND ?)
        OR
        (p.end_at IS NOT NULL AND p.end_at BETWEEN ? AND ?)
        OR
        (p.start_at IS NOT NULL AND p.end_at IS NOT NULL AND p.start_at <= ? AND p.end_at >= ?)
      )
    ORDER BY COALESCE(p.start_at, p.end_at) ASC, p.id DESC
  ";

    $stmt = $conn->prepare($sql);
    // bind 6 params: start, end, start, end, start, end
    $stmt->bind_param('ssssss', $start_dt, $end_dt, $start_dt, $end_dt, $start_dt, $end_dt);
    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        // Bentukkan client_display (company_name utk company; nama user utk individu)
        $client_display = null;
        if ($r['client_type'] === 'company') {
            $client_display = $r['company_name'] ?: null;
        } else {
            $client_display = $r['user_name'] ?: null;
        }

        $rows[] = [
            'id' => (int)$r['id'],
            'title' => $r['title'],
            'status' => $r['status'],
            'start_at' => $r['start_at'], // as-is (YYYY-MM-DD HH:MM:SS) – client akan parse
            'end_at' => $r['end_at'],
            'client_display' => $client_display,
        ];
    }

    echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
