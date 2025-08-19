<?php
// api/freelancers_simple.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    require_once __DIR__ . '/db.php';

    // Auth (akan throw jika tak sah)
    $user = auth_user($conn); // <-- pusat
    $auth_user_id = (int)($user['id'] ?? 0);

    // --- Filters ---
    $q         = isset($_GET['q']) ? trim($_GET['q']) : '';
    $status    = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : '';
    $only_act  = isset($_GET['only_active']) ? (int)$_GET['only_active'] : 0; // 1 = approved only

    $page      = max(1, (int)($_GET['page'] ?? 1));
    $per_page  = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset    = ($page - 1) * $per_page;

    // Whitelist status ikut enum freelancers
    $allowed_status = ['pending', 'approved', 'rejected', 'inactive'];
    if ($status !== '' && !in_array($status, $allowed_status, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Invalid status. Allowed: " . implode(', ', $allowed_status)]);
        exit;
    }

    // --- Build WHERE (guna alias f = freelancers, u = users) ---
    $where  = [];
    $params = [];
    $types  = '';

    if ($q !== '') {
        // cari di users
        $where[] = "(u.name LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%') OR u.phone LIKE CONCAT('%', ?, '%'))";
        $params[] = $q;
        $params[] = $q;
        $params[] = $q;
        $types   .= 'sss';
    }

    if ($status !== '') {
        $where[]  = "f.status = ?";
        $params[] = $status;
        $types   .= 's';
    }

    if ($only_act === 1) {
        $where[] = "(f.status IN ('approved'))";
    }

    // Contoh kalau nak limit ikut organisasi / scope:
    // $where[]  = "u.organization_id = ?";
    // $params[] = $user['organization_id'];
    // $types   .= 'i';

    $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // --- Query data ---
    $sql = "
        SELECT
            f.id,
            f.user_id,
            u.name,
            u.email,
            u.phone,
            f.avatar_url,
            f.status
        FROM freelancers f
        INNER JOIN users u ON u.id = f.user_id
        $where_sql
        ORDER BY u.name ASC
        LIMIT ? OFFSET ?
    ";

    $st = $conn->prepare($sql);
    $types2  = $types . 'ii';
    $params2 = $params;
    $params2[] = $per_page;
    $params2[] = $offset;
    $st->bind_param($types2, ...$params2);
    $st->execute();
    $res = $st->get_result();

    $rows = [];
    while ($r = $res->fetch_assoc()) {
        // Optional: normalisasi field kosong ke null
        $r['avatar_url'] = $r['avatar_url'] ?: null;
        $rows[] = $r;
    }

    // --- Total ---
    $tsql = "
        SELECT COUNT(*) AS c
        FROM freelancers f
        INNER JOIN users u ON u.id = f.user_id
        $where_sql
    ";
    $tc = $conn->prepare($tsql);
    if ($types !== '') {
        $tc->bind_param($types, ...$params);
    }
    $tc->execute();
    $tres  = $tc->get_result()->fetch_assoc();
    $total = (int)($tres['c'] ?? 0);

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'has_more' => ($offset + $per_page) < $total
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'detail' => $e->getMessage()
    ]);
}
