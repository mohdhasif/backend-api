<?php
// api/freelancers_simple.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    require_once __DIR__ . '/db.php';

    // --- Auth by token (ikut pattern API kau yang lain) ---
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $auth);
    if (!$token) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token']);
        exit;
    }

    $conn->set_charset('utf8mb4');
    $u = $conn->prepare("SELECT id FROM users WHERE token=?");
    $u->bind_param("s", $token);
    $u->execute();
    if (!$u->get_result()->fetch_assoc()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    // --- Filters ---
    $q         = isset($_GET['q']) ? trim($_GET['q']) : '';
    $status    = isset($_GET['status']) ? trim($_GET['status']) : ''; // e.g. active/approved
    $only_act  = isset($_GET['only_active']) ? intval($_GET['only_active']) : 0; // 1 = active/approved only

    $page      = max(1, intval($_GET['page'] ?? 1));
    $per_page  = min(50, max(1, intval($_GET['per_page'] ?? 20)));
    $offset    = ($page - 1) * $per_page;

    // --- Build WHERE ---
    $where = [];
    $params = [];
    $types = "";

    if ($q !== "") {
        $where[] = "(f.name LIKE CONCAT('%', ?, '%') OR f.email LIKE CONCAT('%', ?, '%'))";
        $params[] = $q;
        $params[] = $q;
        $types .= "ss";
    }
    if ($status !== "") {
        $where[] = "f.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if ($only_act === 1) {
        $where[] = "(f.status IN ('active','approved'))";
    }

    $where_sql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

    // --- Query data + total ---
    $sql = "SELECT f.id, f.name, f.email, f.phone, f.avatar_url, f.status
            FROM freelancers f
            $where_sql
            ORDER BY f.name ASC
            LIMIT ? OFFSET ?";
    $types2  = $types . "ii";
    $params2 = $params;
    $params2[] = $per_page;
    $params2[] = $offset;

    $st = $conn->prepare($sql);
    if ($types2) $st->bind_param($types2, ...$params2);
    $st->execute();
    $res = $st->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;

    // total
    $tsql = "SELECT COUNT(*) AS c FROM freelancers f $where_sql";
    $tc = $conn->prepare($tsql);
    if ($types) $tc->bind_param($types, ...$params);
    $tc->execute();
    $tres = $tc->get_result()->fetch_assoc();
    $total = intval($tres['c'] ?? 0);

    echo json_encode([
        'success' => true,
        'data'    => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'has_more' => $offset + $per_page < $total
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $e->getMessage()]);
}
